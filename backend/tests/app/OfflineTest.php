<?php
namespace Tests\App;
use App\Offline\OfflineBook;
use App\Harvest\HarvestBook;
use App\Field\FieldProblem;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
final class OfflineTest extends CIUnitTestCase
{
 use DatabaseTestTrait;use FeatureTestTrait;
 protected $namespace=null;
 private int $admin;private int $leader;private int $other;private int $contract;private int $worker;private array $files=[];
 protected function setUp():void{
  $this->resetServices();$_SESSION=[];$_COOKIE=[];parent::setUp();helper(['auth','setting']);$c=config('Settings');$c->handlers=['array'];Services::injectMock('settings',new Settings($c));$this->admin=$this->user('adminoffline','admin');$this->leader=$this->user('leaderoffline','supervisor');$this->other=$this->user('otheroffline','supervisor');$this->login($this->admin);
  $client=$this->fixture('clients',['name'=>'Cliente']);$box=$this->fixture('units',['name'=>'Caixa','code'=>'caixa']);$kg=$this->fixture('units',['name'=>'Quilograma','code'=>'kg']);$this->worker=$this->fixture('workers',['name'=>'Ana','phone'=>'+351 912 345 678']);$this->contract=(int)(new HarvestBook($this->db))->startContract(['name'=>'Laranja','client_id'=>$client,'local_name'=>'Pomar','payment_unit_id'=>$box,'billing_unit_id'=>$kg,'payment_rate'=>'20','billing_rate'=>'100','starts_on'=>'2026-11-01'])['item']['id'];$this->login($this->leader);
 }
 protected function tearDown():void{foreach($this->files as$f)if(is_file($f))unlink($f);parent::tearDown();}
 private function fixture(string $t,array $d):int{$this->db->table('agri_'.$t)->insert($d+['created_at'=>'2026-11-01 08:00:00','updated_at'=>'2026-11-01 08:00:00']);return(int)$this->db->insertID();}
 private function user(string $name,string $role):int{$p=auth()->getProvider();$p->save(new User(['username'=>$name,'email'=>$name.'@example.test','password'=>'Password123!','active'=>true]));$u=$p->findById($p->getInsertID());$u->addGroup($role);return(int)$u->id;}
 private function login(int $id):void{auth('session')->logout();auth('session')->login(auth()->getProvider()->findById($id));}
 private function uuid():string{return sprintf('%08x-1111-4111-8111-111111111111',random_int(1,0x7fffffff));}
 private function input(?string $client=null):array{return ['kind'=>'work','request_id'=>$this->uuid(),'payload'=>['client_id'=>$client??$this->uuid(),'version'=>0,'data'=>['harvest_id'=>$this->contract,'work_date'=>'2026-11-12','attendees'=>[['worker_id'=>$this->worker,'present'=>true,'payment_quantity'=>'10','score'=>4]],'pools'=>[],'notes'=>'Produção offline','billing_quantity'=>null]]];}
 public function testReplayedWorkerAndWorkNeverDuplicateAndChangedPayloadIsRejected():void{
  $b=new OfflineBook($this->db);$w=['kind'=>'worker','request_id'=>$this->uuid(),'payload'=>['name'=>'Novo trabalhador','phone'=>'+351 912 345 678']];$first=$b->apply($w);$this->assertSame($first,$b->apply($w));$this->assertSame(1,$this->db->table('agri_workers')->where('name','Novo trabalhador')->countAllResults());$in=$this->input();$log=$b->apply($in);$this->assertSame($log,$b->apply($in));$this->assertSame(1,$this->db->table('agri_work_logs')->countAllResults());$in['payload']['data']['notes']='Alterado';try{$b->apply($in);$this->fail('UUID reutilizado com outros dados');}catch(FieldProblem $e){$this->assertSame(409,$e->status);}
 }
 public function testUpdateAndSubmitAreVersionedAndIdempotentWithoutHours():void{
  $b=new OfflineBook($this->db);$in=$this->input();$a=$b->apply($in)['item'];$in['request_id']=$this->uuid();$in['payload']['version']=$a['version'];$in['payload']['data']['attendees'][0]['payment_quantity']='12';$updated=$b->apply($in)['item'];$this->assertSame(2,$updated['version']);$this->assertSame($updated,$b->apply($in)['item']);$submit=['kind'=>'submit','request_id'=>$this->uuid(),'payload'=>['id'=>$a['id'],'version'=>2]];$r=$b->apply($submit);$this->assertSame('submitted',$r['item']['status']);$this->assertSame($r,$b->apply($submit));$this->assertNull($r['item']['attendees'][0]['time_in']);
 }
 public function testStaleVersionPreservesServerAndCreatesNoReceipt():void{
  $b=new OfflineBook($this->db);$in=$this->input();$b->apply($in);$in['request_id']=$this->uuid();$in['payload']['version']=1;$b->apply($in);$in['request_id']=$this->uuid();$in['payload']['data']['notes']='Não sobrescrever';try{$b->apply($in);$this->fail('Versão antiga aceite');}catch(FieldProblem $e){$this->assertSame(409,$e->status);}$this->assertSame(0,$this->db->table('agri_offline_receipts')->where('request_id',$in['request_id'])->countAllResults());$this->seeInDatabase('agri_work_logs',['notes'=>'Produção offline','version'=>2]);
 }
 public function testCurrentBlockAndRevokedPermissionAreRecheckedAtSync():void{
  $this->db->table('agri_workers')->where('id',$this->worker)->update(['is_blocked'=>1,'block_reason'=>'Bloqueado depois da preparação']);$b=new OfflineBook($this->db);try{$b->apply($this->input());$this->fail('Bloqueio ignorado');}catch(FieldProblem $e){$this->assertSame(422,$e->status);}$this->assertSame(0,$this->db->table('agri_work_logs')->countAllResults());$u=auth()->getProvider()->findById($this->leader);$u->removeGroup('supervisor');$u->addGroup('worker');$this->login($this->leader);try{$b->snapshot();$this->fail('Permissão removida ignorada');}catch(FieldProblem $e){$this->assertSame(403,$e->status);}
 }
 public function testSnapshotHasOwnWorkSafetyHistoryAndNoFinancialValues():void{
  $b=new OfflineBook($this->db);$b->apply($this->input());$this->login($this->other);$b->apply($this->input());$this->login($this->leader);$s=$b->snapshot();$this->assertSame($this->leader,$s['owner']);$this->assertCount(1,$s['logs']);$json=json_encode($s);foreach(['payment_rate','billing_rate','amount_cents','earnings','secret']as$key)$this->assertStringNotContainsString('"'.$key.'"',$json);$this->assertArrayHasKey('is_blocked',$s['workers'][0]);$this->assertArrayHasKey('evaluations',$s['workers'][0]);
 }
 public function testOtherAccountCannotReuseAnotherWorkAndMissingEvaluationPreventsSubmit():void{
  $b=new OfflineBook($this->db);$in=$this->input();$in['payload']['data']['attendees'][0]['score']=null;$log=$b->apply($in)['item'];try{$b->apply(['kind'=>'submit','request_id'=>$this->uuid(),'payload'=>['id'=>$log['id'],'version'=>1]]);$this->fail('Sem avaliação aceite');}catch(FieldProblem $e){$this->assertSame(422,$e->status);}$this->login($this->other);$in['request_id']=$this->uuid();$in['payload']['version']=1;try{$b->apply($in);$this->fail('Trabalho de outro utilizador');}catch(FieldProblem $e){$this->assertSame(403,$e->status);}
 }
 public function testPhotoRetryIsIdempotentAndMayReplayAfterDailyClosure():void{
  $b=new OfflineBook($this->db);$log=$b->apply($this->input())['item'];$tmp=tempnam(sys_get_temp_dir(),'agri-photo-');file_put_contents($tmp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));$this->files[]=$tmp;$file=$this->createMock(UploadedFile::class);$file->method('isValid')->willReturn(true);$file->method('getSize')->willReturn(filesize($tmp));$file->method('getMimeType')->willReturn('image/png');$file->method('getTempName')->willReturn($tmp);$file->expects($this->once())->method('move')->willReturnCallback(function($dir,$name)use($tmp){$this->files[]=$dir.'/'.$name;return copy($tmp,$dir.'/'.$name);});$in=['request_id'=>$this->uuid(),'worker_id'=>(string)$this->worker,'description'=>'Caixas'];$first=$b->photo($log['id'],$file,$in);$b->apply(['kind'=>'submit','request_id'=>$this->uuid(),'payload'=>['id'=>$log['id'],'version'=>1]]);$this->assertSame($first,$b->photo($log['id'],$file,$in));$this->assertSame(1,$this->db->table('agri_production_photos')->countAllResults());
 }
 public function testOptionalPhoneAndPortraitReplay():void{
  $b=new OfflineBook($this->db);$w=$b->apply(['kind'=>'worker','request_id'=>$this->uuid(),'payload'=>['name'=>'Sem telefone']])['item'];$this->assertEmpty($w['phone']);
  $tmp=tempnam(sys_get_temp_dir(),'portrait-');file_put_contents($tmp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));$this->files[]=$tmp;$file=$this->createMock(UploadedFile::class);$file->method('isValid')->willReturn(true);$file->method('getSize')->willReturn(filesize($tmp));$file->method('getMimeType')->willReturn('image/png');$file->method('getTempName')->willReturn($tmp);$file->expects($this->once())->method('move')->willReturnCallback(function($dir,$name)use($tmp){$this->files[]=$dir.'/'.$name;return copy($tmp,$dir.'/'.$name);});
  $p=new \App\Field\WorkerPortraits($this->db);$in=['request_id'=>$this->uuid(),'version'=>'0'];$a=$p->save($w['id'],$file,$in);$this->assertSame(['photo_version'=>1],$a);$this->assertSame($a,$p->save($w['id'],$file,$in));$this->assertSame(1,$this->db->table('agri_worker_portraits')->countAllResults());
  try{$p->save($w['id'],$file,['request_id'=>$this->uuid(),'version'=>'1']);$this->fail('Controller replaced photo');}catch(FieldProblem $e){$this->assertSame(403,$e->status);}
 }
}
