<?php

namespace Tests\App;

use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class PricingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $namespace = null;
    private int $serviceId;
    private int $workerId;
    private int $clientId;
    private int $hourId;
    private int $boxId;

    protected function setUp(): void
    {
        $this->resetServices(); $_SESSION = []; $_COOKIE = [];
        parent::setUp();
        helper(['auth', 'setting']);
        $settings = config('Settings'); $settings->handlers = ['array'];
        Services::injectMock('settings', new Settings($settings));
        if (getenv('EXPECTED_TEST_DB')) { $this->assertSame(getenv('EXPECTED_TEST_DB'), $this->db->getPlatform()); }
        $this->hourId = $this->fixture('units', ['name' => 'Hora', 'code' => 'hora']);
        $this->boxId = $this->fixture('units', ['name' => 'Caixa', 'code' => 'caixa']);
        $this->serviceId = $this->fixture('services', ['name' => 'Colheita', 'payment_unit_id' => $this->hourId, 'billing_unit_id' => $this->boxId]);
        $this->workerId = $this->fixture('workers', ['name' => 'Pessoa Exemplo']);
        $this->clientId = $this->fixture('clients', ['name' => 'Cliente Exemplo']);
    }

    private function fixture(string $table, array $data): int
    {
        $this->db->table('agri_' . $table)->insert($data + ['created_at' => '2026-11-01 00:00:00', 'updated_at' => '2026-11-01 00:00:00']);
        return (int) $this->db->insertID();
    }

    private function login(string $role = 'admin'): void
    {
        $provider = auth()->getProvider();
        $user = new User(['username' => 'priceuser', 'email' => 'prices@example.test', 'password' => 'ExampleTestPassword123!', 'active' => true]);
        $this->assertTrue($provider->save($user));
        $user = $provider->findById($provider->getInsertID()); $user->addGroup($role); auth('session')->login($user);
    }

    private function write(string $path, array $data)
    {
        $token = csrf_hash(); session()->set(config('Security')->tokenName, $token);
        return $this->withSession()->withHeaders(['X-CSRF-TOKEN' => $token, 'X-Requested-With' => 'XMLHttpRequest'])
            ->withBodyFormat('json')->post('/api/v1/prices' . $path, $data);
    }

    private function rate(array $changes = []): array
    {
        $result = $this->write('', array_replace([
            'service_id' => $this->serviceId, 'kind' => 'payment', 'amount' => '10.0000',
            'valid_from' => '2026-11-01', 'valid_to' => '2026-11-30',
        ], $changes));
        $result->assertStatus(201);
        return json_decode($result->getJSON(), true)['item'];
    }

    private function simulate(array $changes = [])
    {
        return $this->write('/preview', array_replace([
            'service_id' => $this->serviceId, 'kind' => 'payment', 'worker_id' => $this->workerId,
            'date' => '2026-11-08', 'quantity' => '2',
        ], $changes));
    }

    public function testAnonymousAndWorkerCannotReadOrConfigurePrices(): void
    {
        $this->get('/api/v1/prices?service_id=' . $this->serviceId)->assertStatus(401);
        $this->write('', [])->assertStatus(401);
        $this->login('worker');
        $this->withSession()->get('/api/v1/prices?service_id=' . $this->serviceId)->assertStatus(403);
        $this->simulate()->assertStatus(403);
    }

    public function testSupervisorCannotReadOrConfigureFinancialPrices(): void
    {
        $this->login('supervisor');
        $this->withSession()->get('/api/v1/prices?service_id=' . $this->serviceId)->assertStatus(403);
        $this->write('', [])->assertStatus(403);
    }

    public function testWorkerSpecificPriceWinsAndDefaultIsFallback(): void
    {
        $this->login();
        $default = $this->rate(['amount' => '10.1250']);
        $specific = $this->rate(['worker_id' => $this->workerId, 'amount' => '12.5000']);
        $result = $this->simulate(); $result->assertStatus(200);
        $data = json_decode($result->getJSON(), true);
        $this->assertSame($specific['id'], $data['price']['id']);
        $this->assertSame('25.00', $data['total']);
        $other = $this->fixture('workers', ['name' => 'Outra Pessoa']);
        $result = $this->simulate(['worker_id' => $other]); $result->assertStatus(200);
        $this->assertSame($default['id'], json_decode($result->getJSON(), true)['price']['id']);
        $this->assertSame('20.25', json_decode($result->getJSON(), true)['total']);
    }

    public function testBillingAndPaymentHaveDifferentUnitsAndClientSpecificPrice(): void
    {
        $this->login();
        $payment = $this->rate();
        $this->rate(['kind' => 'billing', 'amount' => '2.5000']);
        $specific = $this->rate(['kind' => 'billing', 'client_id' => $this->clientId, 'amount' => '3.2500']);
        $result = $this->simulate(['kind' => 'billing', 'worker_id' => null, 'client_id' => $this->clientId]);
        $result->assertStatus(200); $data = json_decode($result->getJSON(), true);
        $this->assertSame('6.50', $data['total']);
        $this->assertSame($this->boxId, $data['price']['unit_id']);
        $this->assertSame($this->hourId, $payment['unit_id']);
        $this->assertSame($specific['id'], $data['price']['id']);
    }

    public function testOverlappingInclusivePeriodsRejectedButAdjacentAllowed(): void
    {
        $this->login(); $this->rate();
        $data = ['service_id' => $this->serviceId, 'kind' => 'payment', 'amount' => '11', 'valid_from' => '2026-11-30', 'valid_to' => '2026-12-31'];
        $this->write('', $data)->assertStatus(409);
        $this->write('', array_replace($data, ['valid_from' => '2026-12-01']))->assertStatus(201);
        $this->assertSame(2, $this->db->table('agri_prices')->countAllResults());
        $this->assertSame(2, $this->db->table('agri_price_audit')->countAllResults());
    }

    public function testCloseOpenPeriodThenCreateNextWithoutRewritingOldAmount(): void
    {
        $this->login(); $price = $this->rate(['valid_to' => null]);
        $this->write('/' . $price['id'] . '/end', ['version' => 1, 'valid_to' => '2026-11-30'])->assertStatus(200);
        $this->rate(['amount' => '12.5', 'valid_from' => '2026-12-01', 'valid_to' => null]);
        $november = $this->simulate(); $november->assertStatus(200);
        $this->assertSame('20.00', json_decode($november->getJSON(), true)['total']);
        $december = $this->simulate(['date' => '2026-12-01']); $december->assertStatus(200);
        $this->assertSame('25.00', json_decode($december->getJSON(), true)['total']);
        $this->write('/' . $price['id'] . '/end', ['version' => 1, 'valid_to' => '2026-11-20'])->assertStatus(409);
        $this->assertSame(3, $this->db->table('agri_price_audit')->countAllResults());
    }

    public function testNoPriceIsAnErrorInsteadOfImplicitZero(): void
    {
        $this->login(); $this->simulate()->assertStatus(404);
        $this->rate(); $this->simulate(['date' => '2026-12-01'])->assertStatus(404);
    }

    public function testExplicitZeroSpecificPriceDoesNotFallBack(): void
    {
        $this->login(); $this->rate(); $this->rate(['worker_id' => $this->workerId, 'amount' => '0']);
        $result = $this->simulate(); $result->assertStatus(200);
        $this->assertSame('0.00', json_decode($result->getJSON(), true)['total']);
    }

    public function testExactDecimalRoundingAndLargeValues(): void
    {
        $this->login(); $price = $this->rate(['amount' => '0.1250']);
        $result = $this->simulate(['quantity' => '1']); $result->assertStatus(200);
        $this->assertSame('0.13', json_decode($result->getJSON(), true)['total']);
        $this->write('/' . $price['id'] . '/cancel', ['version' => 1, 'reason' => 'Corrigir exemplo'])->assertStatus(200);
        $this->rate(['amount' => '99999999.9999']);
        $large = $this->simulate(['quantity' => '99999999.9999']); $large->assertStatus(200);
        $this->assertSame('9999999999980000.00', json_decode($large->getJSON(), true)['total']);
    }

    public function testCancelPreservesHistoryAndStopsSelection(): void
    {
        $this->login(); $price = $this->rate();
        $this->write('/' . $price['id'] . '/cancel', ['version' => 1, 'reason' => ''])->assertStatus(422);
        $this->write('/' . $price['id'] . '/cancel', ['version' => 1, 'reason' => 'Preço cadastrado incorretamente'])->assertStatus(200);
        $this->simulate()->assertStatus(404);
        $this->seeInDatabase('agri_prices', ['id' => $price['id'], 'cancel_reason' => 'Preço cadastrado incorretamente']);
        $this->seeInDatabase('agri_price_audit', ['price_id' => $price['id'], 'action' => 'cancel', 'actor_id' => auth()->id()]);
        $this->rate();
    }

    public function testInvalidDecimalsDatesScopeAndUnknownFields(): void
    {
        $this->login();
        $base = ['service_id' => $this->serviceId, 'kind' => 'payment', 'amount' => '10', 'valid_from' => '2026-11-01'];
        foreach ([['amount' => 1.25], ['amount' => '-1'], ['amount' => '1e3'], ['amount' => '0.00001'], ['valid_from' => '2026-02-30'], ['valid_to' => '2026-10-31'], ['client_id' => $this->clientId], ['worker_id' => 999999], ['amount' => '10', 'currency' => 'USD']] as $change) {
            $this->write('', array_replace($base, $change))->assertStatus(422);
        }
        $this->assertSame(0, $this->db->table('agri_prices')->countAllResults());
    }

    public function testUnitSnapshotSurvivesServiceAndUnitEdits(): void
    {
        $this->login(); $this->rate();
        $this->db->table('agri_services')->where('id', $this->serviceId)->update(['payment_unit_id' => $this->boxId]);
        $this->db->table('agri_units')->where('id', $this->hourId)->update(['name' => 'Hora Renomeada']);
        $result = $this->simulate(); $result->assertStatus(200); $data = json_decode($result->getJSON(), true);
        $this->assertSame('Hora', $data['price']['unit_name']);
        $this->assertSame($this->hourId, $data['price']['unit_id']);
    }

    public function testListIncludesHistoricalAndSpecificRows(): void
    {
        $this->login(); $this->rate(); $this->rate(['worker_id' => $this->workerId]);
        $result = $this->withSession()->get('/api/v1/prices?service_id=' . $this->serviceId);
        $result->assertStatus(200); $data = json_decode($result->getJSON(), true);
        $this->assertSame(2, $data['total']);
        $this->assertSame('Pessoa Exemplo', $data['items'][0]['worker_name']);
        $this->assertSame('10.0000', $data['items'][0]['amount']);
    }

    public function testStaleUnitExpectationIsRejectedWithoutCreatingPrice(): void
    {
        $this->login();
        $this->db->table('agri_services')->where('id', $this->serviceId)->update(['payment_unit_id' => $this->boxId]);
        $this->write('', ['service_id' => $this->serviceId, 'unit_id' => $this->hourId, 'kind' => 'payment', 'amount' => '10', 'valid_from' => '2026-11-01'])->assertStatus(409);
        $this->assertSame(0, $this->db->table('agri_prices')->countAllResults());
        $this->assertSame(0, $this->db->table('agri_price_audit')->countAllResults());
    }

    public function testInactiveServicePreventsNewRatesButAllowsClosingHistory(): void
    {
        $this->login(); $price = $this->rate();
        $this->db->table('agri_services')->where('id', $this->serviceId)->update(['is_active' => 0]);
        $this->write('', ['service_id' => $this->serviceId, 'kind' => 'billing', 'amount' => '10', 'valid_from' => '2026-11-01'])->assertStatus(422);
        $this->write('/' . $price['id'] . '/end', ['version' => 1, 'valid_to' => '2026-11-15'])->assertStatus(200);
    }
}
