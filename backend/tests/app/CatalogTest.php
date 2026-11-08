<?php

namespace Tests\App;

use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class CatalogTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = null;

    protected function setUp(): void
    {
        $this->resetServices();
        $_SESSION = [];
        $_COOKIE = [];
        parent::setUp();
        helper(['auth', 'setting']);
        $settings = config('Settings');
        $settings->handlers = ['array'];
        Services::injectMock('settings', new Settings($settings));
    }

    private function login(string $group = 'admin'): void
    {
        $provider = auth()->getProvider();
        $user = new User([
            'username' => 'cataloguser', 'email' => 'catalog@example.test',
            'password' => 'ExampleTestPassword123!', 'active' => true,
        ]);
        $this->assertTrue($provider->save($user));
        $user = $provider->findById($provider->getInsertID());
        $user->addGroup($group);
        auth('session')->login($user);
    }

    private function write(string $resource, array $data, ?int $id = null)
    {
        $token = csrf_hash();
        session()->set(config('Security')->tokenName, $token);
        $request = $this->withSession()->withHeaders(['X-CSRF-TOKEN' => $token, 'X-Requested-With' => 'XMLHttpRequest'])
            ->withBodyFormat('json');
        return $id ? $request->put('/api/v1/catalog/' . $resource . '/' . $id, $data)
            : $request->post('/api/v1/catalog/' . $resource, $data);
    }

    private function create(string $resource, array $data): array
    {
        $result = $this->write($resource, $data);
        $result->assertStatus(201);
        return json_decode($result->getJSON(), true)['item'];
    }

    public function testAnonymousCannotReadOrCreateCatalogs(): void
    {
        $this->get('/api/v1/catalog/workers')->assertStatus(401);
        $this->write('workers', ['name' => 'Exemplo'])->assertStatus(401);
    }

    public function testWorkerCannotReadOrWriteWholeCompanyCatalog(): void
    {
        $this->login('worker');
        $this->withSession()->get('/api/v1/catalog/workers')->assertStatus(403);
        $this->write('teams', ['name' => 'Exemplo'])->assertStatus(403);
    }

    public function testSupervisorCannotReadUnscopedCatalog(): void
    {
        $this->login('supervisor');
        $this->withSession()->get('/api/v1/catalog/clients')->assertStatus(403);
        $this->write('services', ['name' => 'Exemplo'])->assertStatus(403);
    }

    public function testAdminCanCreateWorkerWithTeamAndAudit(): void
    {
        $this->login();
        $team = $this->create('teams', ['name' => 'Equipa Norte']);
        $worker = $this->create('workers', ['name' => 'Trabalhador Exemplo', 'team_id' => $team['id']]);
        $this->assertSame($team['id'], $worker['team_id']);
        $this->assertTrue($worker['is_active']);
        $this->assertSame(1, $worker['version']);
        $this->seeInDatabase('agri_catalog_audit', ['resource' => 'workers', 'record_id' => $worker['id'], 'actor_id' => auth()->id(), 'action' => 'create']);
    }

    public function testUpdateRequiresCurrentVersionAndDoesNotDuplicateAuditOnConflict(): void
    {
        $this->login();
        $client = $this->create('clients', ['name' => 'Cliente Exemplo']);
        $result = $this->write('clients', ['name' => 'Cliente Atualizado', 'version' => 1, 'is_active' => false], $client['id']);
        $result->assertStatus(200);
        $updated = json_decode($result->getJSON(), true)['item'];
        $this->assertSame(2, $updated['version']);
        $this->assertFalse($updated['is_active']);
        $this->write('clients', ['name' => 'Alteração Antiga', 'version' => 1], $client['id'])->assertStatus(409);
        $this->seeInDatabase('agri_clients', ['id' => $client['id'], 'name' => 'Cliente Atualizado']);
        $this->assertSame(2, $this->db->table('agri_catalog_audit')->countAllResults());
    }

    public function testServicesHaveIndependentPaymentAndBillingUnits(): void
    {
        $this->login();
        $hour = $this->create('units', ['name' => 'Hora', 'code' => 'hora']);
        $box = $this->create('units', ['name' => 'Caixa', 'code' => 'caixa']);
        $service = $this->create('services', ['name' => 'Colheita', 'payment_unit_id' => $hour['id'], 'billing_unit_id' => $box['id']]);
        $this->assertSame($hour['id'], $service['payment_unit_id']);
        $this->assertSame($box['id'], $service['billing_unit_id']);
    }

    public function testInvalidDataAndUnknownFieldsAreRejected(): void
    {
        $this->login();
        $this->write('workers', ['name' => '   '])->assertStatus(422);
        $this->write('clients', ['name' => 'Exemplo', 'email' => 'invalido'])->assertStatus(422);
        $this->write('teams', ['name' => 'Exemplo', 'is_active' => null])->assertStatus(422);
        $this->write('workers', ['name' => 'Exemplo', 'role' => 'admin'])->assertStatus(422);
        $this->write('workers', ['name' => 'Exemplo', 'team_id' => 999999])->assertStatus(422);
        $this->write('services', ['name' => 'Exemplo'])->assertStatus(422);
        $this->write('units', ['name' => 'Hora', 'code' => 'Codigo Invalido'])->assertStatus(422);
        $this->assertSame(0, $this->db->table('agri_catalog_audit')->countAllResults());
    }

    public function testDuplicateUnitCodeIsRejected(): void
    {
        $this->login();
        $this->create('units', ['name' => 'Hora', 'code' => 'hora']);
        $this->write('units', ['name' => 'Outra Hora', 'code' => 'hora'])->assertStatus(422);
    }

    public function testInactiveReferenceCannotBeAssignedButExistingLinkIsPreserved(): void
    {
        $this->login();
        $team = $this->create('teams', ['name' => 'Equipa']);
        $worker = $this->create('workers', ['name' => 'Exemplo', 'team_id' => $team['id']]);
        $this->write('teams', ['name' => 'Equipa', 'is_active' => false, 'version' => 1], $team['id'])->assertStatus(200);
        $this->write('workers', ['name' => 'Novo', 'team_id' => $team['id']])->assertStatus(422);
        $this->write('workers', ['name' => 'Exemplo Atualizado', 'team_id' => $team['id'], 'version' => 1], $worker['id'])->assertStatus(200);
    }

    public function testListIsPaginatedAndSearchable(): void
    {
        $this->login();
        for ($index = 0; $index < 27; $index++) {
            $this->db->table('agri_clients')->insert(['name' => 'Cliente ' . $index, 'created_at' => '2026-11-08 12:00:00', 'updated_at' => '2026-11-08 12:00:00']);
        }
        $result = $this->withSession()->get('/api/v1/catalog/clients');
        $result->assertStatus(200);
        $data = json_decode($result->getJSON(), true);
        $this->assertCount(25, $data['items']);
        $this->assertSame(27, $data['total']);
        $second = $this->withSession()->get('/api/v1/catalog/clients?page=2');
        $this->assertCount(2, json_decode($second->getJSON(), true)['items']);
        $filtered = $this->withSession()->get('/api/v1/catalog/clients?q=Cliente%2026');
        $this->assertCount(1, json_decode($filtered->getJSON(), true)['items']);
        $this->withSession()->get('/api/v1/catalog/clients?page=0')->assertStatus(422);
    }

    public function testMissingRecordAndVersionAreRejected(): void
    {
        $this->login();
        $this->withSession()->get('/api/v1/catalog/workers/99999')->assertStatus(404);
        $this->write('workers', ['name' => 'Exemplo', 'version' => 1], 99999)->assertStatus(404);
        $team = $this->create('teams', ['name' => 'Equipa']);
        $this->write('teams', ['name' => 'Equipa'], $team['id'])->assertStatus(422);
    }
}
