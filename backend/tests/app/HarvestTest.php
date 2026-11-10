<?php
namespace Tests\App;
use App\Field\DraftBook;
use App\Harvest\HarvestBook;
use App\Field\FieldProblem;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
final class HarvestTest extends CIUnitTestCase
{
 use DatabaseTestTrait;use FeatureTestTrait;
 protected $namespace=null;
 private int $actor;private int $leader;private int $foreign;private int $harvest;private array $workers=[];
 protected function setUp():void
 {
  $this->resetServices();$_SESSION=[];$_COOKIE=[];parent::setUp();helper(['auth','setting']);$config=config('Settings');$config->handlers=['array'];Services::injectMock('settings',new Settings($config));
  $this->actor=$this->user('admin','admin');$this->leader=$this->user('leader','supervisor');$this->foreign=$this->user('foreign','supervisor');$this->login($this->actor);
  $client=$this->fixture('clients',['name'=>'Cliente']);$site=$this->fixture('sites',['name'=>'Pomar','client_id'=>$client]);$box=$this->fixture('units',['name'=>'Caixa','code'=>'caixa']);$kg=$this->fixture('units',['name'=>'Quilograma','code'=>'kg']);$service=$this->fixture('services',['name'=>'Laranja','payment_unit_id'=>$box,'billing_unit_id'=>$kg]);
  $book=new HarvestBook($this->db);$c=$book->createContract(['name'=>'Contrato laranja','client_id'=>$client,'site_id'=>$site,'service_id'=>$service,'payment_unit_id'=>$box,'billing_unit_id'=>$kg,'billing_rate'=>'6','payment_rate'=>'9','conversion_factor'=>'2'])['item'];$this->harvest=(int)$book->createHarvest(['name'=>'Colheita novembro','contract_id'=>(int)$c['id'],'controller_id'=>$this->leader,'starts_on'=>'2026-11-01'])['item']['id'];
  foreach(['Ana','Pedro','João','Maria','José']as$name)$this->workers[]=$this->fixture('workers',['name'=>$name]);
 }
 private function fixture(string $table,array $data):int{$this->db->table('agri_'.$table)->insert($data+['created_at'=>'2026-11-01 08:00:00','updated_at'=>'2026-11-01 08:00:00']);return(int)$this->db->insertID();}
 private function user(string $name,string $group):int{$p=auth()->getProvider();$p->save(new User(['username'=>$name,'email'=>$name.'@example.test','password'=>'Password123!','active'=>true]));$u=$p->findById($p->getInsertID());$u->addGroup($group);return(int)$u->id;}
 private function login(int $id):void{auth('session')->logout();auth('session')->login(auth()->getProvider()->findById($id));}
 private function uuid():string{return sprintf('%08x-1111-4111-8111-111111111111',random_int(1,0x7fffffff));}
 private function log(?int $score=4):array{$this->login($this->leader);$people=[];foreach($this->workers as$w)$people[]=['worker_id'=>$w,'present'=>true,'score'=>$score,'pool_key'=>'group','payment_quantity'=>null];return(new DraftBook($this->db))->create(['request_id'=>$this->uuid(),'harvest_id'=>$this->harvest,'work_date'=>'2026-11-02','attendees'=>$people,'pools'=>[['pool_key'=>'group','name'=>'Grupo A','quantity'=>'500']]],$this->leader)['item'];}
 private function validated():array{$l=$this->log();$b=new HarvestBook($this->db);$s=$b->daily($l['id'],'submit',['version'=>$l['version']])['item'];$this->login($this->actor);return$b->daily($l['id'],'validate',['version'=>$s['version']])['item'];}
 private function request(string $path,array $body,string $method='post'){$token=csrf_hash();session()->set(config('Security')->tokenName,$token);return$this->withSession()->withHeaders(['X-CSRF-TOKEN'=>$token])->withBodyFormat('json')->{$method}('/api/v1'.$path,$body);}
 public function testHarvestFlowMatchesCashAndQuantitiesWithoutFixedTeam():void
 {
  $l=$this->validated();$this->assertNull($l['team_id']);$b=new HarvestBook($this->db);$d=$b->delivery($this->harvest,['request_id'=>$this->uuid(),'delivered_on'=>'2026-11-03','reference'=>'Talão 1','billing_quantity'=>'1000','items'=>[['work_log_id'=>$l['id'],'quantity'=>'500']]])['item'];
  $receipt=['request_id'=>$this->uuid(),'kind'=>'receipt','paid_on'=>'2026-11-10','amount'=>'6000','quantity'=>'1000','delivery_id'=>(int)$d['id'],'reference'=>'Transferência cliente'];$b->movement($this->harvest,$receipt);$b->movement($this->harvest,$receipt);
  foreach($this->workers as$w)$b->movement($this->harvest,['request_id'=>$this->uuid(),'kind'=>'payment','paid_on'=>'2026-11-10','amount'=>'900','worker_id'=>$w,'work_log_id'=>$l['id'],'quantity'=>'100','reference'=>'Pagamento parcial da colheita']);
  $h=$b->detail($this->harvest);$s=$h['summary'];$this->assertSame(600000,$s['received_cents']);$this->assertSame(450000,$s['paid_cents']);$this->assertSame(150000,$s['cash_cents']);$this->assertEquals(500,$s['paid_quantity']);$this->assertEquals(1000,$s['received_quantity']);$this->assertEquals(0,$s['difference']);$this->assertCount(6,$h['movements']);$this->assertSame('active',$h['status']);
  $closed=$b->close($this->harvest,['version'=>(int)$h['version'],'ends_on'=>'2026-11-10'])['item'];$this->assertSame('closed',$closed['status']);
 }
 public function testControllerCannotSeeMoneyOrOtherHarvests():void
 {
  $l=$this->validated();$this->login($this->leader);$b=new HarvestBook($this->db);$h=$b->detail($this->harvest);$this->assertArrayNotHasKey('summary',$h);$this->assertArrayNotHasKey('payment_rate',$h);$this->assertArrayNotHasKey('workers',$h);$this->assertArrayNotHasKey('earnings',$h['activities'][0]);
  $r=$this->request('/work-logs/'.$l['id'],[],'get');$r->assertStatus(200);$this->assertStringNotContainsString('amount_cents',$r->getJSON());$this->request('/contracts',[],'get')->assertStatus(403);$this->request('/work-logs/'.$l['id'].'/close-preview',[],'get')->assertStatus(403);
  $this->login($this->foreign);$this->request('/harvests/'.$this->harvest,[],'get')->assertStatus(403);$this->request('/work-logs/'.$l['id'],[],'get')->assertStatus(403);$this->request('/work-logs/'.$l['id'].'/photos',[],'get')->assertStatus(403);
 }
 public function testMissingEvaluationBlocksDailyClosureAndValidationCannotDuplicate():void
 {
  $l=$this->log(null);$b=new HarvestBook($this->db);try{$b->daily($l['id'],'submit',['version'=>$l['version']]);$this->fail('Sem avaliação aceite');}catch(FieldProblem $e){$this->assertSame(422,$e->status);}$this->assertSame(0,$this->db->table('agri_harvest_allocations')->countAllResults());
 }
 public function testDeliveryCannotUseMoreThanAvailableOrDuplicateActivity():void
 {
  $l=$this->validated();$b=new HarvestBook($this->db);try{$b->delivery($this->harvest,['request_id'=>$this->uuid(),'delivered_on'=>'2026-11-03','reference'=>'Excesso','billing_quantity'=>'1000','items'=>[['work_log_id'=>$l['id'],'quantity'=>'501']]]);$this->fail('Excesso aceite');}catch(FieldProblem $e){$this->assertSame(422,$e->status);}$this->assertSame(0,$this->db->table('agri_harvest_deliveries')->countAllResults());
 }
 public function testCloseRequiresValidatedActivitiesAndMatchedDeliveries():void
 {
  $this->validated();$b=new HarvestBook($this->db);try{$b->close($this->harvest,['version'=>1,'ends_on'=>'2026-11-10']);$this->fail('Sem entrega aceite');}catch(FieldProblem $e){$this->assertSame(422,$e->status);}
 }
 public function testQuickWorkerRequiresPhoneAndNoTeam():void
 {
  $this->login($this->leader);$this->request('/field/workers',['name'=>'Novo colaborador','phone'=>'+351 912 345 678','is_active'=>true])->assertStatus(201);$this->request('/field/workers',['name'=>'Sem telefone','is_active'=>true])->assertStatus(422);
 }
 public function testDuplicateValidationAndExcessPaymentAreRejected():void
 {
  $l=$this->validated();$b=new HarvestBook($this->db);try{$b->daily($l['id'],'validate',['version'=>$l['version']]);$this->fail('Validação duplicada');}catch(FieldProblem $e){$this->assertSame(409,$e->status);}
  $this->assertSame(5,$this->db->table('agri_harvest_allocations')->countAllResults());
  $in=['request_id'=>$this->uuid(),'kind'=>'payment','paid_on'=>'2026-11-10','amount'=>'909','worker_id'=>$this->workers[0],'work_log_id'=>$l['id'],'quantity'=>'101','reference'=>'Excesso'];try{$b->movement($this->harvest,$in);$this->fail('Excesso aceite');}catch(FieldProblem $e){$this->assertSame(422,$e->status);}
  $this->assertSame(0,$this->db->table('agri_harvest_movements')->countAllResults());
 }
 public function testMeasuredShortfallDoesNotReduceWorkerEarnings():void
 {
  $l=$this->validated();$b=new HarvestBook($this->db);$b->delivery($this->harvest,['request_id'=>$this->uuid(),'delivered_on'=>'2026-11-03','reference'=>'Peso menor','billing_quantity'=>'850','items'=>[['work_log_id'=>$l['id'],'quantity'=>'500']]]);$s=$b->detail($this->harvest)['summary'];$this->assertEquals(-150,$s['difference']);$this->assertEquals(1.7,$s['actual_ratio']);$this->assertSame(450000,$s['payable_cents']);$this->assertSame(510000,$s['receivable_cents']);
 }

}
