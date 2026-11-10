<?php
namespace Tests\App;
use App\Field\DraftBook;
use App\Field\FieldProblem;
use App\Finance\ProductionBook;
use App\Pricing\PriceBook;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class ProductionTest extends CIUnitTestCase
{
    use DatabaseTestTrait; use FeatureTestTrait;
    protected $namespace=null;
    private int $actor; private int $team; private int $otherTeam; private int $client; private int $site; private int $unit; private int $service; private array $workers;
    protected function setUp(): void
    {
        $this->resetServices();$_SESSION=[];$_COOKIE=[];parent::setUp();helper(['auth','setting']);$settings=config('Settings');$settings->handlers=['array'];Services::injectMock('settings',new Settings($settings));
        $provider=auth()->getProvider();$provider->save(new User(['username'=>'financeadmin','email'=>'finance@example.test','password'=>'Password123!','active'=>true]));$user=$provider->findById($provider->getInsertID());$user->addGroup('admin');$this->actor=$user->id;auth('session')->login($user);
        $this->team=$this->fixture('teams',['name'=>'Equipa A']);$this->otherTeam=$this->fixture('teams',['name'=>'Equipa B']);$this->client=$this->fixture('clients',['name'=>'Cliente']);$this->site=$this->fixture('sites',['name'=>'Quinta','client_id'=>$this->client]);$this->unit=$this->fixture('units',['name'=>'Caixa','code'=>'caixa']);$this->service=$this->fixture('services',['name'=>'Colheita','payment_unit_id'=>$this->unit,'billing_unit_id'=>$this->unit]);
        $this->workers=[];foreach(['Ana','Pedro','Maria'] as $name)$this->workers[]=$this->fixture('workers',['name'=>$name,'team_id'=>$this->otherTeam]);
        (new PriceBook($this->db))->create(['service_id'=>$this->service,'kind'=>'payment','amount'=>'1.0000','valid_from'=>'2026-01-01'],$this->actor);
    }
    private function fixture(string $table,array $data): int {$this->db->table('agri_'.$table)->insert($data+['created_at'=>'2026-11-01 08:00:00','updated_at'=>'2026-11-01 08:00:00']);return(int)$this->db->insertID();}
    private function activity(bool $group=false,?int $score=4,string $request='11111111-1111-4111-8111-111111111111'): array
    {
        $rows=[];foreach(($group?$this->workers:[$this->workers[0]]) as $worker)$rows[]=['worker_id'=>$worker,'present'=>true,'time_in'=>'08:00','time_out'=>'17:00','payment_quantity'=>$group?null:'10.0100','score'=>$score,'notes'=>'Bom trabalho','pool_key'=>$group?'poolA':null];
        return (new DraftBook($this->db))->create(['request_id'=>$request,'work_date'=>'2026-11-08','team_id'=>$this->team,'client_id'=>$this->client,'site_id'=>$this->site,'service_id'=>$this->service,'service_version'=>1,'billing_quantity'=>'10.0100','attendees'=>$rows,'pools'=>$group?[['pool_key'=>'poolA','name'=>'Grupo A','quantity'=>'10.0100']]:[]],$this->actor)['item'];
    }
    private function closed(bool $group=false): array {$log=$this->activity($group);$book=new ProductionBook($this->db);$preview=$book->preview($log['id']);return $book->close($log['id'],['version'=>$log['version'],'preview_hash'=>$preview['preview_hash']],$this->actor)['item'];}
    private function request(string $path,array $data,string $method='post'){$token=csrf_hash();session()->set(config('Security')->tokenName,$token);return $this->withSession()->withHeaders(['X-CSRF-TOKEN'=>$token])->withBodyFormat('json')->{$method}('/api/v1'.$path,$data);}
    public function testClosingRequiresScoreAndDoesNotGeneratePartialEarnings(): void
    {
        $log=$this->activity(false,null);try{(new ProductionBook($this->db))->preview($log['id']);$this->fail('Fecho sem avaliação aceite');}catch(FieldProblem $e){$this->assertSame(422,$e->status);}
        $this->assertSame(0,$this->db->table('agri_earnings')->countAllResults());$this->seeInDatabase('agri_work_logs',['id'=>$log['id'],'status'=>'draft']);
    }
    public function testEqualGroupDivisionPreservesEveryCentAndCannotDuplicateClosure(): void
    {
        $log=$this->activity(true);$book=new ProductionBook($this->db);$preview=$book->preview($log['id']);$input=['version'=>1,'preview_hash'=>$preview['preview_hash']];
        $closed=$book->close($log['id'],$input,$this->actor)['item'];$this->assertSame('closed',$closed['status']);$this->assertSame([334,334,333],array_map(fn($r)=>(int)$r['amount_cents'],$closed['earnings']));$this->assertSame(1001,array_sum(array_column($closed['earnings'],'amount_cents')));
        $this->assertTrue($book->close($log['id'],$input,$this->actor)['replayed']);$this->assertSame(3,$this->db->table('agri_earnings')->countAllResults());
        $this->seeInDatabase('agri_workers',['id'=>$this->workers[0],'team_id'=>$this->team]);
        $this->request('/work-logs/'.$log['id'],['version'=>2,'notes'=>'Editar fechado','billing_quantity'=>'1','attendees'=>[]],'put')->assertStatus(409);
    }
    public function testChangedPriceRequiresNewConfirmation(): void
    {
        $log=$this->activity();$book=new ProductionBook($this->db);$preview=$book->preview($log['id']);$this->db->table('agri_prices')->update(['amount'=>'2.0000']);
        try{$book->close($log['id'],['version'=>1,'preview_hash'=>$preview['preview_hash']],$this->actor);$this->fail('Valores alterados aceites');}catch(FieldProblem $e){$this->assertSame(409,$e->status);}
        $this->assertSame(0,$this->db->table('agri_earnings')->countAllResults());
    }
    public function testFlexiblePeriodSettlementAndPartialPaymentCannotDuplicateOrOverpay(): void
    {
        $this->closed(true);$book=new ProductionBook($this->db);$worker=$this->workers[0];$items=$book->pending($worker,'2026-11-01','2026-11-15');$input=['worker_id'=>$worker,'date_from'=>'2026-11-01','date_to'=>'2026-11-15','request_id'=>'22222222-2222-4222-8222-222222222222','preview_hash'=>ProductionBook::pendingHash($worker,'2026-11-01','2026-11-15',$items)];
        $settlement=$book->settle($input,$this->actor)['item'];$this->assertSame(334,(int)$settlement['total_cents']);$this->assertTrue($book->settle($input,$this->actor)['replayed']);$this->assertSame([],$book->pending($worker,'2026-11-01','2026-11-15'));
        $pay=['request_id'=>'33333333-3333-4333-8333-333333333333','version'=>1,'amount'=>'1.00','paid_on'=>'2026-11-15','reference'=>'Transferência','notes'=>'Teste'];$paid=$book->pay($settlement['id'],$pay,$this->actor)['item'];$this->assertSame(100,(int)$paid['paid_cents']);$this->assertTrue($book->pay($settlement['id'],$pay,$this->actor)['replayed']);$this->assertCount(1,$paid['payments']);
        try{$book->pay($settlement['id'],array_replace($pay,['request_id'=>'44444444-4444-4444-8444-444444444444','version'=>2,'amount'=>'3.00']),$this->actor);$this->fail('Pagamento excessivo aceite');}catch(FieldProblem $e){$this->assertSame(422,$e->status);}
        $this->assertSame(1,$this->db->table('agri_payment_entries')->countAllResults());
        $summary=$this->withSession()->get('/api/v1/settlements/summary');$summary->assertStatus(200);$this->assertSame('901',json_decode($summary->getJSON(),true)['pending_cents']);
    }
    public function testDraftsBlockSettlementInSelectedPeriod(): void
    {
        $this->activity();$book=new ProductionBook($this->db);$worker=$this->workers[0];$input=['worker_id'=>$worker,'date_from'=>'2026-11-01','date_to'=>'2026-11-15','request_id'=>'22222222-2222-4222-8222-222222222222','preview_hash'=>ProductionBook::pendingHash($worker,'2026-11-01','2026-11-15',[])];
        try{$book->settle($input,$this->actor);$this->fail('Rascunhos ignorados');}catch(FieldProblem $e){$this->assertSame(409,$e->status);}
        $this->assertSame(0,$this->db->table('agri_settlements')->countAllResults());
    }
    public function testSupervisorCreatesWorkersWithoutFixedTeamAndCannotAccessForeignLegacyActivity(): void
    {
        $log=$this->activity();$provider=auth()->getProvider();$provider->save(new User(['username'=>'controller','email'=>'controller@example.test','password'=>'Password123!','active'=>true]));$user=$provider->findById($provider->getInsertID());$user->addGroup('supervisor');$this->db->table('agri_teams')->where('id',$this->team)->update(['manager_user_id'=>$user->id]);auth('session')->logout();auth('session')->login($user);
        $this->request('/field/workers',['name'=>'Novo trabalhador','phone'=>'+351 912 345 678'])->assertStatus(201);$this->request('/field/workers',['name'=>'Outro sem telefone'])->assertStatus(422);
        $list=$this->withSession()->get('/api/v1/work-logs');$list->assertStatus(200);$this->assertSame(1,json_decode($list->getJSON(),true)['total']);
        $this->db->table('agri_work_logs')->where('id',$log['id'])->update(['team_id'=>$this->otherTeam]);$this->withSession()->get('/api/v1/work-logs/'.$log['id'])->assertStatus(403);$this->request('/work-logs/'.$log['id'].'/close',['version'=>1,'preview_hash'=>str_repeat('0',64)])->assertStatus(403);
        $this->request('/settlements',[])->assertStatus(403);
    }
    public function testPerformanceUsesClosedActivitiesAndPreservesComments(): void
    {
        $this->closed();$result=$this->withSession()->get('/api/v1/performance');$result->assertStatus(200);$row=json_decode($result->getJSON(),true)['items'][0];$this->assertSame(4.0,(float)$row['average']);$this->assertSame(1,(int)$row['evaluations']);
        $detail=$this->withSession()->get('/api/v1/performance/'.$this->workers[0]);$detail->assertStatus(200);$this->assertSame('Bom trabalho',json_decode($detail->getJSON(),true)['items'][0]['notes']);
    }
    public function testGroupProductionIsReportedOnceWithClosedStatus(): void
    {
        $this->closed(true);$book=new \App\Reports\WorkReport($this->db);$report=$book->result($book->filters(['status'=>'closed']));
        $this->assertSame(3,$report['total']);$this->assertSame('10.0100',$report['summary']['group_production'][0]['quantity']);$this->assertSame('10.0100',$report['summary']['billing'][0]['quantity']);$this->assertSame('Grupo A',$report['items'][0]['pool_name']);
    }

}
