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
final class SimpleProductionTest extends CIUnitTestCase
{
 use DatabaseTestTrait;use FeatureTestTrait;
 protected $namespace=null;
 private int $actor;private int $leader;private int $foreign;private array $workers=[];
 protected function setUp():void
 {
  $this->resetServices();$_SESSION=[];$_COOKIE=[];parent::setUp();helper(['auth','setting']);$config=config('Settings');$config->handlers=['array'];Services::injectMock('settings',new Settings($config));
  $this->actor=$this->user('admin','admin');$this->leader=$this->user('leader','supervisor');$this->foreign=$this->user('foreign','supervisor');$this->login($this->actor);
  config('Agroperi')->legacyEnabled=false;
  foreach(['Ana','Pedro','João','Maria','José']as$name)$this->workers[]=$this->fixture('workers',['name'=>$name]);
 }
 private function fixture(string $table,array $data):int{$this->db->table('agri_'.$table)->insert($data+['created_at'=>'2026-11-01 08:00:00','updated_at'=>'2026-11-01 08:00:00']);return(int)$this->db->insertID();}
 private function user(string $name,string $group):int{$p=auth()->getProvider();$p->save(new User(['username'=>$name,'email'=>$name.'@example.test','password'=>'Password123!','active'=>true]));$u=$p->findById($p->getInsertID());$u->addGroup($group);return(int)$u->id;}
 private function login(int $id):void{auth('session')->logout();auth('session')->login(auth()->getProvider()->findById($id));}
 private function uuid():string{return sprintf('%08x-1111-4111-8111-111111111111',random_int(1,0x7fffffff));}
 private function request(string $path,array $body,string $method='post'){$token=csrf_hash();session()->set(config('Security')->tokenName,$token);return$this->withSession()->withHeaders(['X-CSRF-TOKEN'=>$token])->withBodyFormat('json')->{$method}('/api/v1'.$path,$body);}
 protected function tearDown():void{config('Agroperi')->legacyEnabled=true;parent::tearDown();}
 private function simple(array $attendees=[],array $pools=[]):array{$this->login($this->leader);return(new DraftBook($this->db))->create(['simple_mode'=>true,'request_id'=>$this->uuid(),'work_date'=>'2026-11-24','activity_name'=>'Laranja','unit_name'=>'Caixa de 200 kg','attendees'=>$attendees,'pools'=>$pools],$this->leader)['item'];}
 private function complete(array $people,array $pools=[]):array{$l=$this->simple($people,$pools);$b=new HarvestBook($this->db);$l=$b->daily($l['id'],'submit',['version'=>$l['version']])['item'];$this->login($this->actor);return$b->daily($l['id'],'validate',['version'=>$l['version']])['item'];}
 public function testNoCataloguesRequiredAndIndividualPricesAreHistorical():void{
  $l=$this->complete([['worker_id'=>$this->workers[0],'present'=>true,'score'=>4,'payment_quantity'=>'5','payment_rate'=>'10'],['worker_id'=>$this->workers[1],'present'=>true,'score'=>5,'payment_quantity'=>'5','payment_rate'=>'12']]);$this->assertNull($l['harvest_id']);$this->assertNull($l['payment_unit_id']);$this->assertSame('Caixa de 200 kg',$l['payment_unit_name']);$earn=$this->db->table('agri_earnings')->orderBy('worker_id')->get()->getResultArray();$this->assertSame([5000,6000],array_map(fn($r)=>(int)$r['amount_cents'],$earn));foreach(['clients','contracts','units','services','harvests']as$t)$this->assertSame(0,$this->db->table('agri_'.$t)->countAllResults());
  try{(new DraftBook($this->db))->update($l['id'],['version'=>$l['version'],'attendees'=>[]],$this->actor);$this->fail('Alteração histórica aceite');}catch(FieldProblem $e){$this->assertSame(409,$e->status);}
 }
 public function testGroupQuantityIsDividedOnceAndEachWorkerUsesTheirOwnRate():void{
  $this->complete([['worker_id'=>$this->workers[0],'present'=>true,'score'=>4,'pool_key'=>'g','payment_rate'=>'10'],['worker_id'=>$this->workers[1],'present'=>true,'score'=>5,'pool_key'=>'g','payment_rate'=>'12']],[['pool_key'=>'g','name'=>'Grupo','quantity'=>'12']]);$earn=$this->db->table('agri_earnings')->orderBy('worker_id')->get()->getResultArray();$this->assertSame(['6.0000','6.0000'],array_column($earn,'quantity'));$this->assertSame([6000,7200],array_map(fn($r)=>(int)$r['amount_cents'],$earn));
 }
 public function testMissingPricePreventsSubmissionAndDraftsDoNotCreateMoney():void{
  $l=$this->simple([['worker_id'=>$this->workers[0],'present'=>true,'score'=>4,'payment_quantity'=>'5']]);$this->assertSame(0,$this->db->table('agri_earnings')->countAllResults());try{(new HarvestBook($this->db))->daily($l['id'],'submit',['version'=>1]);$this->fail('Sem preço aceite');}catch(FieldProblem $e){$this->assertSame(422,$e->status);}
 }
 public function testPartialPaymentAndPdfMatchTheSameWorkerBalance():void{
  $this->complete([['worker_id'=>$this->workers[0],'present'=>true,'score'=>4,'payment_quantity'=>'5','payment_rate'=>'10']]);$w=$this->workers[0];$p=new \App\Finance\ProductionBook($this->db);$items=$p->pending($w,'2026-11-01','2026-11-30');$input=['worker_id'=>$w,'date_from'=>'2026-11-01','date_to'=>'2026-11-30','request_id'=>$this->uuid(),'preview_hash'=>$p::pendingHash($w,'2026-11-01','2026-11-30',$items)];$s=$p->settle($input,$this->actor)['item'];$s=$p->pay((int)$s['id'],['request_id'=>$this->uuid(),'version'=>1,'amount'=>'20','paid_on'=>'2026-11-24'],$this->actor)['item'];$this->assertSame(2000,(int)$s['paid_cents']);$r=$this->request('/simple/workers/'.$w.'/statement?date_from=2026-11-01&date_to=2026-11-30',[],'get');$r->assertStatus(200);$data=json_decode($r->getJSON(),true);$this->assertSame(3000,$data['summary']['balance_cents']);$this->assertCount(1,$data['items']);$r=$this->request('/simple/workers/'.$w.'/statement?date_from=2026-11-01&date_to=2026-11-30&format=pdf',[],'get');$r->assertStatus(200);$this->assertStringStartsWith('%PDF-1.4',$r->response()->getBody());
  $this->assertCount(0,$p->pending($w,'2026-11-01','2026-11-30'));
 }
 public function testDisabledModulesAndFinancialPermissions():void{
  $this->request('/contracts',[],'get')->assertStatus(403);$this->request('/catalog/clients',[],'get')->assertStatus(403);$this->login($this->leader);$this->request('/simple/statement',[],'get')->assertStatus(403);$this->login($this->foreign);$l=$this->simple();$this->login($this->foreign);$this->request('/work-logs/'.$l['id'],[],'get')->assertStatus(403);
 }
 public function testOfflineReplayDoesNotDuplicateTheActivityOrItsPrices():void{
  $this->login($this->leader);$client=$this->uuid();$data=['simple_mode'=>true,'work_date'=>'2026-11-24','activity_name'=>'Uva','unit_name'=>'Caixa','attendees'=>[['worker_id'=>$this->workers[0],'present'=>true,'score'=>4,'payment_quantity'=>'5','payment_rate'=>'10']]];$input=['kind'=>'work','request_id'=>$this->uuid(),'payload'=>['client_id'=>$client,'version'=>0,'data'=>$data]];$b=new \App\Offline\OfflineBook($this->db);$one=$b->apply($input);$two=$b->apply($input);$this->assertSame($one,$two);$this->assertSame(1,$this->db->table('agri_work_logs')->countAllResults());$this->assertSame('10.0000',$one['item']['attendees'][0]['payment_rate']);
 }
}
