<?php
namespace Tests\App;

use App\Reports\WorkReport;
use App\Reports\ReportExport;
use App\Users\UserProblem;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class ReportsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $namespace = null;
    private array $filters;
    protected function setUp(): void
    {
        $this->resetServices(); $_SESSION = []; $_COOKIE = []; parent::setUp(); helper(['auth', 'setting']);
        $settings = config('Settings'); $settings->handlers = ['array']; Services::injectMock('settings', new Settings($settings));
        $provider = auth()->getProvider(); $provider->save(new User(['username' => 'reportadmin', 'email' => 'report@example.test', 'password' => 'Password123!', 'active' => true]));
        $user = $provider->findById($provider->getInsertID()); $user->addGroup('admin'); auth('session')->login($user);
        $unit = $this->fixture('units', ['name' => 'Caixa', 'code' => 'caixa']);
        $team = $this->fixture('teams', ['name' => 'Equipa']); $client = $this->fixture('clients', ['name' => 'Cliente']);
        $site = $this->fixture('sites', ['name' => 'Quinta', 'client_id' => $client]);
        $service = $this->fixture('services', ['name' => 'Colheita', 'payment_unit_id' => $unit, 'billing_unit_id' => $unit]);
        $worker1 = $this->fixture('workers', ['name' => 'Ana', 'team_id' => $team]); $worker2 = $this->fixture('workers', ['name' => 'Pedro', 'team_id' => $team]);
        $log = $this->fixture('work_logs', ['request_id' => 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'creation_hash' => str_repeat('a', 64), 'work_date' => '2026-07-10', 'team_id' => $team, 'client_id' => $client, 'site_id' => $site, 'service_id' => $service, 'team_name' => 'Equipa', 'client_name' => 'Cliente', 'site_name' => 'Quinta', 'service_name' => 'Colheita', 'status' => 'draft', 'payment_unit_id' => $unit, 'billing_unit_id' => $unit, 'payment_unit_name' => 'Caixa', 'billing_unit_name' => 'Caixa', 'payment_unit_code' => 'caixa', 'billing_unit_code' => 'caixa', 'billing_quantity' => '100.0000', 'created_by' => $user->id, 'updated_by' => $user->id, 'notes' => '=HYPERLINK("bad")']);
        foreach ([[$worker1, 'Ana', '40.0000'], [$worker2, 'Pedro', '60.0000']] as [$id, $name, $quantity]) $this->db->table('agri_work_log_attendees')->insert(['work_log_id' => $log, 'worker_id' => $id, 'worker_name' => $name, 'present' => 1, 'time_in' => '08:00', 'time_out' => '17:00', 'payment_quantity' => $quantity, 'score' => 5]);
        $this->filters = ['worker_id' => (string) $worker1, 'client_id' => (string) $client, 'service_id' => (string) $service];
    }
    private function fixture(string $table, array $data): int { $this->db->table('agri_' . $table)->insert($data + ['created_at' => '2026-07-10 08:00:00', 'updated_at' => '2026-07-10 17:00:00']); return (int) $this->db->insertID(); }
    public function testSummariesDoNotMultiplyClientProductionByParticipants(): void
    {
        $book = new WorkReport($this->db); $data = $book->result($book->filters([]));
        $this->assertSame(2, $data['total']); $this->assertSame(1, $data['summary']['work_logs']); $this->assertSame(2, $data['summary']['workers']);
        $this->assertSame('100.0000', $data['summary']['billing'][0]['quantity']); $this->assertSame('100.0000', $data['summary']['production'][0]['quantity']);
        $filtered = $book->result($book->filters($this->filters));
        $this->assertSame(1, $filtered['total']); $this->assertSame('40.0000', $filtered['summary']['production'][0]['quantity']); $this->assertSame('100.0000', $filtered['summary']['billing'][0]['quantity']);
    }
    public function testDateStatusAndReferenceFilters(): void
    {
        $book = new WorkReport($this->db);
        foreach ([['date_from' => '2026-07-11'], ['date_to' => '2026-07-09'], ['status' => 'canceled'], ['client_id' => '99999']] as $filter) $this->assertSame(0, $book->result($book->filters($filter))['total']);
        foreach ([['date_from' => '2026-02-31'], ['date_from' => '2026-07-11', 'date_to' => '2026-07-01'], ['worker_id' => ['1']], ['status' => 'validated']] as $filter) {
            try { $book->filters($filter); $this->fail('Filtro inválido aceite'); } catch (UserProblem $error) { $this->assertSame(422, $error->status); }
        }
    }
    public function testExportsIncludeAllRowsAndNeutralizeSpreadsheetFormulas(): void
    {
        $book = new WorkReport($this->db); $data = $book->result($book->filters([]), 1, true);
        $csv = ReportExport::csv($data); $this->assertStringContainsString("'=HYPERLINK", $csv); $this->assertStringContainsString('10/07/2026', $csv); $this->assertStringContainsString('09:00', $csv);
        $pdf = ReportExport::pdf($data); $this->assertStringStartsWith('%PDF-1.4', $pdf); $this->assertStringContainsString('%%EOF', $pdf);
        $file = tempnam(sys_get_temp_dir(), 'test-report-'); file_put_contents($file, ReportExport::xlsx($data));
        $zip = new \ZipArchive(); $this->assertTrue($zip->open($file)); $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Ana', $xml); $this->assertStringContainsString('Pedro', $xml); $this->assertStringNotContainsString('<f>', $xml); $this->assertStringContainsString('<v>40.0000</v>', $xml); $zip->close(); unlink($file);
    }
    public function testReportsRequireExplicitPermission(): void
    {
        $this->withSession()->get('/api/v1/reports/work-logs')->assertStatus(200);
        $this->withSession()->get('/api/v1/reports/work-logs/export?format=csv')->assertStatus(200);
        $user = auth()->user(); $user->syncGroups('worker'); auth('session')->logout(); auth('session')->login($user);
        $this->withSession()->get('/api/v1/reports/work-logs')->assertStatus(403); $this->withSession()->get('/api/v1/reports/work-logs/export?format=pdf')->assertStatus(403);
        auth('session')->logout(); $this->get('/api/v1/reports/work-logs')->assertStatus(401);
    }
    public function testReportOnlyPermissionCanLoadFilterOptionsWithoutCatalogWriteAccess(): void
    {
        $user = auth()->user(); $user->syncGroups('worker'); $user->addPermission('reports.view'); auth('session')->logout(); auth('session')->login($user);
        $result = $this->withSession()->get('/api/v1/reports/options/workers?q=Ana'); $result->assertStatus(200);
        $item = json_decode($result->getJSON(), true)['items'][0];
        $this->assertSame('Ana', $item['name']); $this->assertArrayNotHasKey('phone', $item);
        $this->withSession()->get('/api/v1/catalog/workers')->assertStatus(403);
    }

}
