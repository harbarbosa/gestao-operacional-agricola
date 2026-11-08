<?php
namespace Tests\App;

use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class FieldTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $namespace = null;
    private int $clientId; private int $siteId; private int $teamId; private int $serviceId; private int $workerId; private int $hourId; private int $boxId;

    protected function setUp(): void
    {
        $this->resetServices(); $_SESSION = []; $_COOKIE = []; parent::setUp();
        helper(['auth', 'setting']); $settings = config('Settings'); $settings->handlers = ['array']; Services::injectMock('settings', new Settings($settings));
        if (getenv('EXPECTED_TEST_DB')) { $this->assertSame(getenv('EXPECTED_TEST_DB'), $this->db->getPlatform()); }
        $this->clientId = $this->fixture('clients', ['name' => 'Cliente Exemplo']);
        $this->siteId = $this->fixture('sites', ['name' => 'Pomar Norte', 'client_id' => $this->clientId]);
        $this->teamId = $this->fixture('teams', ['name' => 'Equipa Norte', 'responsible_name' => 'Responsável Exemplo']);
        $this->hourId = $this->fixture('units', ['name' => 'Hora', 'code' => 'hora']);
        $this->boxId = $this->fixture('units', ['name' => 'Caixa', 'code' => 'caixa']);
        $this->serviceId = $this->fixture('services', ['name' => 'Colheita', 'payment_unit_id' => $this->hourId, 'billing_unit_id' => $this->boxId]);
        $this->workerId = $this->fixture('workers', ['name' => 'Pessoa Exemplo', 'team_id' => $this->teamId]);
    }
    private function fixture(string $table, array $data): int
    {
        $this->db->table('agri_' . $table)->insert($data + ['created_at' => '2026-11-01 00:00:00', 'updated_at' => '2026-11-01 00:00:00']); return (int) $this->db->insertID();
    }
    private function login(string $role = 'admin'): void
    {
        $provider = auth()->getProvider(); $user = new User(['username' => 'fielduser', 'email' => 'field@example.test', 'password' => 'ExampleTestPassword123!', 'active' => true]);
        $this->assertTrue($provider->save($user)); $user = $provider->findById($provider->getInsertID()); $user->addGroup($role); auth('session')->login($user);
    }
    private function body(array $changes = []): array
    {
        return array_replace([
            'request_id' => '11111111-1111-4111-8111-111111111111', 'work_date' => '2026-11-08', 'team_id' => $this->teamId, 'client_id' => $this->clientId, 'site_id' => $this->siteId, 'service_id' => $this->serviceId, 'service_version' => 1,
            'notes' => 'Exemplo', 'billing_quantity' => '10.5000',
            'attendees' => [['worker_id' => $this->workerId, 'present' => true, 'time_in' => '08:00', 'time_out' => '17:00', 'exit_next_day' => false, 'payment_quantity' => '3.2500', 'score' => 4, 'notes' => 'Observação']],
        ], $changes);
    }
    private function write(string $path, array $body, bool $put = false)
    {
        $token = csrf_hash(); session()->set(config('Security')->tokenName, $token);
        $request = $this->withSession()->withHeaders(['X-CSRF-TOKEN' => $token, 'X-Requested-With' => 'XMLHttpRequest'])->withBodyFormat('json');
        return $put ? $request->put('/api/v1/work-logs' . $path, $body) : $request->post('/api/v1/work-logs' . $path, $body);
    }
    private function create(array $changes = []): array
    {
        $result = $this->write('', $this->body($changes)); $result->assertStatus(201); return json_decode($result->getJSON(), true)['item'];
    }
    private function edit(array $item, array $changes = []): array
    {
        $rows = array_map(static function ($row) { unset($row['worker_name']); return $row; }, $item['attendees']);
        return array_replace(['version' => $item['version'], 'notes' => $item['notes'], 'billing_quantity' => $item['billing_quantity'], 'attendees' => $rows], $changes);
    }

    public function testAnonymousCannotReadOrCreateDrafts(): void
    {
        $this->get('/api/v1/work-logs')->assertStatus(401); $this->write('', $this->body())->assertStatus(401);
    }
    public function testSupervisorHasNoGlobalDraftAccessYet(): void
    {
        $this->login('supervisor'); $this->withSession()->get('/api/v1/work-logs')->assertStatus(403); $this->write('', $this->body())->assertStatus(403);
    }
    public function testWorkerCannotWriteOrReadCompanyDrafts(): void
    {
        $this->login('worker'); $this->withSession()->get('/api/v1/work-logs')->assertStatus(403); $this->write('', $this->body())->assertStatus(403);
    }
    public function testCreatePreservesHeaderUnitsPresenceAndAudit(): void
    {
        $this->login(); $item = $this->create();
        $this->assertSame('draft', $item['status']); $this->assertSame('Pomar Norte', $item['site_name']); $this->assertSame('Cliente Exemplo', $item['client_name']);
        $this->assertSame($this->hourId, $item['payment_unit_id']); $this->assertSame($this->boxId, $item['billing_unit_id']);
        $this->assertSame('10.5000', $item['billing_quantity']); $this->assertSame('3.2500', $item['attendees'][0]['payment_quantity']); $this->assertTrue($item['attendees'][0]['present']);
        $this->assertSame('Pessoa Exemplo', $item['attendees'][0]['worker_name']); $this->assertArrayNotHasKey('creation_hash', $item);
        $this->seeInDatabase('agri_work_log_audit', ['work_log_id' => $item['id'], 'action' => 'create', 'actor_id' => auth()->id()]);
        $this->assertSame(0, $this->db->table('agri_prices')->countAllResults());
    }
    public function testIdenticalCreationRetryDoesNotDuplicateDiaryOrAudit(): void
    {
        $this->login(); $item = $this->create(); $again = $this->write('', $this->body()); $again->assertStatus(200);
        $data = json_decode($again->getJSON(), true); $this->assertTrue($data['replayed']); $this->assertSame($item['id'], $data['item']['id']);
        $this->assertSame(1, $this->db->table('agri_work_logs')->countAllResults()); $this->assertSame(1, $this->db->table('agri_work_log_audit')->countAllResults());
        $this->write('', $this->body(['notes' => 'Outros dados']))->assertStatus(409);
    }
    public function testDraftCanBeIncompleteWithoutFinancialEffect(): void
    {
        $this->login(); $item = $this->create(['billing_quantity' => null, 'attendees' => [['worker_id' => $this->workerId, 'time_in' => '08:00']]]);
        $this->assertNull($item['attendees'][0]['time_out']); $this->assertNull($item['attendees'][0]['payment_quantity']); $this->assertNull($item['billing_quantity']);
    }
    public function testVersionConflictPreservesCurrentContentAndAudit(): void
    {
        $this->login(); $item = $this->create();
        $result = $this->write('/' . $item['id'], $this->edit($item, ['notes' => 'Atualizado', 'billing_quantity' => '12.0001']), true); $result->assertStatus(200);
        $updated = json_decode($result->getJSON(), true)['item']; $this->assertSame(2, $updated['version']);
        $this->write('/' . $item['id'], $this->edit($item, ['notes' => 'Antigo']), true)->assertStatus(409);
        $this->seeInDatabase('agri_work_logs', ['id' => $item['id'], 'notes' => 'Atualizado']); $this->assertSame(2, $this->db->table('agri_work_log_audit')->countAllResults());
        $audit = $this->db->table('agri_work_log_audit')->where('action', 'update')->get()->getRowArray();
        $this->assertSame('Exemplo', json_decode($audit['before_json'], true)['notes']);
    }
    public function testHeaderAndStatusCannotBeChangedThroughDraftUpdate(): void
    {
        $this->login(); $item = $this->create();
        foreach (['team_id' => $this->teamId, 'work_date' => '2026-11-09', 'status' => 'closed', 'amount' => '100.00'] as $key => $value) { $this->write('/' . $item['id'], $this->edit($item, [$key => $value]), true)->assertStatus(422); }
    }
    public function testCancelKeepsParticipantsAndRejectsLaterEdits(): void
    {
        $this->login(); $item = $this->create();
        $this->write('/' . $item['id'] . '/cancel', ['version' => 1, 'reason' => ''])->assertStatus(422);
        $this->write('/' . $item['id'] . '/cancel', ['version' => 1, 'reason' => 'Corrigir contexto'])->assertStatus(200);
        $this->write('/' . $item['id'], $this->edit($item), true)->assertStatus(409);
        $response = $this->withSession()->get('/api/v1/work-logs/' . $item['id']); $response->assertStatus(200); $current = json_decode($response->getJSON(), true)['item'];
        $this->assertSame('canceled', $current['status']); $this->assertCount(1, $current['attendees']);
        $retry = $this->write('', $this->body()); $retry->assertStatus(200); $this->assertSame('canceled', json_decode($retry->getJSON(), true)['item']['status']);
    }
    public function testInvalidParticipantsHoursAndQuantitiesAreRejected(): void
    {
        $this->login();
        $valid = $this->body()['attendees'][0];
        foreach ([['score' => 6], ['time_in' => '25:00'], ['time_in' => null, 'time_out' => '17:00'], ['time_in' => '22:00', 'time_out' => '06:00'], ['present' => false], ['present' => null], ['exit_next_day' => null], ['payment_quantity' => 1.25], ['payment_quantity' => '-1']] as $change) {
            $this->write('', $this->body(['attendees' => [array_replace($valid, $change)]]))->assertStatus(422);
        }
        $this->write('', $this->body(['attendees' => [$valid, $valid]]))->assertStatus(422);
        $this->write('', $this->body(['attendees' => array_fill(0, 101, $valid)]))->assertStatus(422);
        $this->assertSame(0, $this->db->table('agri_work_logs')->countAllResults());
    }
    public function testOvernightAndAbsenceAreExplicit(): void
    {
        $this->login(); $valid = $this->body()['attendees'][0];
        $item = $this->create(['attendees' => [array_replace($valid, ['time_in' => '22:00', 'time_out' => '06:00', 'exit_next_day' => true])]]);
        $this->assertTrue($item['attendees'][0]['exit_next_day']);
        $absence = ['worker_id' => $this->workerId, 'present' => false, 'notes' => 'Ausência registada'];
        $result = $this->write('/' . $item['id'], $this->edit($item, ['attendees' => [$absence]]), true); $result->assertStatus(200);
        $this->assertFalse(json_decode($result->getJSON(), true)['item']['attendees'][0]['present']);
    }
    public function testClientMismatchStaleServiceAndInvalidDateDoNotCreateDraft(): void
    {
        $this->login();
        $other = $this->fixture('clients', ['name' => 'Outro']);
        $this->write('', $this->body(['client_id' => $other]))->assertStatus(409);
        $this->write('', $this->body(['service_version' => 2]))->assertStatus(409);
        $this->write('', $this->body(['work_date' => '2026-02-30']))->assertStatus(422);
        $this->write('', $this->body(['request_id' => 'invalido']))->assertStatus(422);
        $this->assertSame(0, $this->db->table('agri_work_log_audit')->countAllResults());
    }
    public function testWrongTeamParticipantRejectedWithoutPartialReplacement(): void
    {
        $this->login(); $item = $this->create();
        $otherTeam = $this->fixture('teams', ['name' => 'Outra Equipa']); $other = $this->fixture('workers', ['name' => 'Outra Pessoa', 'team_id' => $otherTeam]);
        $this->write('/' . $item['id'], $this->edit($item, ['attendees' => [['worker_id' => $other]]]), true)->assertStatus(422);
        $this->seeInDatabase('agri_work_log_attendees', ['work_log_id' => $item['id'], 'worker_id' => $this->workerId]);
        $this->assertSame(1, $this->db->table('agri_work_log_audit')->countAllResults());
    }
    public function testExistingParticipantAndUnitSnapshotsSurviveCatalogChanges(): void
    {
        $this->login(); $item = $this->create();
        $other = $this->fixture('teams', ['name' => 'Nova Equipa']);
        $this->db->table('agri_workers')->where('id', $this->workerId)->update(['name' => 'Nome Alterado', 'team_id' => $other, 'is_active' => 0]);
        $this->db->table('agri_services')->where('id', $this->serviceId)->update(['payment_unit_id' => $this->boxId, 'version' => 2]);
        $result = $this->write('/' . $item['id'], $this->edit($item, ['notes' => 'Conferido']), true); $result->assertStatus(200);
        $current = json_decode($result->getJSON(), true)['item'];
        $this->assertSame('Pessoa Exemplo', $current['attendees'][0]['worker_name']); $this->assertSame('Hora', $current['payment_unit_name']);
    }
    public function testSiteAndTeamCatalogFiltersAndReferenceValidation(): void
    {
        $this->login();
        $result = $this->withSession()->get('/api/v1/catalog/sites?client_id=' . $this->clientId); $result->assertStatus(200); $data = json_decode($result->getJSON(), true);
        $this->assertSame(1, $data['total']); $this->assertSame($this->clientId, $data['items'][0]['client_id']);
        $workers = $this->withSession()->get('/api/v1/catalog/workers?team_id=' . $this->teamId); $this->assertCount(1, json_decode($workers->getJSON(), true)['items']);
        $this->withSession()->get('/api/v1/catalog/sites?client_id=abc')->assertStatus(422);
        $token = csrf_hash(); session()->set(config('Security')->tokenName, $token);
        $this->withSession()->withHeaders(['X-CSRF-TOKEN' => $token])->withBodyFormat('json')->post('/api/v1/catalog/sites', ['name' => 'Local Inválido', 'client_id' => 999999])->assertStatus(422);
    }
    public function testDiaryListDateFilterHidesCreationHash(): void
    {
        $this->login(); $this->create();
        $result = $this->withSession()->get('/api/v1/work-logs?date=2026-11-08'); $result->assertStatus(200); $data = json_decode($result->getJSON(), true);
        $this->assertSame(1, $data['total']); $this->assertArrayNotHasKey('creation_hash', $data['items'][0]);
        $empty = $this->withSession()->get('/api/v1/work-logs?date=2026-11-09'); $this->assertSame(0, json_decode($empty->getJSON(), true)['total']);
        $this->withSession()->get('/api/v1/work-logs?date=2026-02-30')->assertStatus(422);
        $this->withSession()->get('/api/v1/work-logs/999999')->assertStatus(404);
    }
}
