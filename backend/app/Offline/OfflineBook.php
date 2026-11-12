<?php
namespace App\Offline;
use App\Field\FieldAccess;
use App\Field\FieldProblem;
use App\Field\DraftBook;
use App\Harvest\HarvestBook;
class OfflineBook
{
 public function __construct(private \CodeIgniter\Database\BaseConnection $db){}
 private function uuid($v):string{if(!is_string($v)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$v))throw new FieldProblem(422,'Identificador offline inválido.');return $v;}
 public function receipt(string $request,string $kind,array $payload,callable $operation):array
 {
  FieldAccess::requireField();$request=$this->uuid($request);$actor=(int)auth()->id();$hash=hash('sha256',json_encode([$kind,$payload],JSON_THROW_ON_ERROR));$this->db->transBegin();
  try{
   $user=$this->db->query('SELECT id FROM '.$this->db->escapeIdentifiers($this->db->prefixTable('users')).' WHERE id=?'.($this->db->getPlatform()==='MySQLi'?' FOR UPDATE':''),[$actor])->getRowArray();if(!$user)throw new FieldProblem(401,'Inicie sessão novamente.');
   $old=$this->db->table('agri_offline_receipts')->where('actor_id',$actor)->where('request_id',$request)->get()->getRowArray();
   if($old){if(!hash_equals($old['request_hash'],$hash))throw new FieldProblem(409,'Identificador já enviado com outros dados.');$result=json_decode($old['response_json'],true,512,JSON_THROW_ON_ERROR);}
   else{$result=$operation();$this->db->table('agri_offline_receipts')->insert(['actor_id'=>$actor,'request_id'=>$request,'request_hash'=>$hash,'response_json'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>gmdate('Y-m-d H:i:s')]);}
   if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Persistence');return $result;
  }catch(\Throwable $e){$this->db->transRollback();if($e instanceof FieldProblem)throw $e;log_message('error','Falha sincronização: {type}',['type'=>get_class($e)]);throw new FieldProblem(409,'Não foi possível confirmar o envio. Os dados locais foram mantidos.');}
 }
 public function apply(array $in):array
 {
  $kind=$in['kind']??null;$payload=$in['payload']??null;if(!in_array($kind,['worker','work','submit'],true)||!is_array($payload)||array_is_list($payload))throw new FieldProblem(422,'Operação offline inválida.');
  return $this->receipt($in['request_id']??'', $kind,$payload,function()use($kind,$payload){
   if($kind==='worker'){
    $name=$payload['name']??null;$phone=$payload['phone']??null;if(!is_string($name)||trim($name)===''||mb_strlen($name)>160||!is_string($phone)||mb_strlen($phone)>40||strlen(preg_replace('/\D/','',$phone))<9||array_diff(array_keys($payload),['name','phone']))throw new FieldProblem(422,'Preencha nome completo e telefone.');
    $now=gmdate('Y-m-d H:i:s');$row=['name'=>trim($name),'phone'=>$phone,'is_active'=>1,'created_at'=>$now,'updated_at'=>$now];$this->db->table('agri_workers')->insert($row);$id=(int)$this->db->insertID();$this->db->table('agri_catalog_audit')->insert(['resource'=>'workers','record_id'=>$id,'actor_id'=>auth()->id(),'action'=>'create','before_json'=>null,'after_json'=>json_encode($row),'created_at'=>$now]);return ['item'=>['id'=>$id,'name'=>trim($name),'phone'=>$phone,'version'=>1,'is_active'=>true,'is_blocked'=>false,'evaluation_count'=>0,'performance_avg'=>null,'low_performance'=>false]];
   }
   if($kind==='submit'){
    if(!is_int($payload['id']??null)||!is_int($payload['version']??null))throw new FieldProblem(422,'Versão inválida.');return(new HarvestBook($this->db))->daily($payload['id'],'submit',['version'=>$payload['version']]);
   }
   $client=$this->uuid($payload['client_id']??null);$version=$payload['version']??null;$data=$payload['data']??null;if(!is_int($version)||$version<0||!is_array($data)||array_is_list($data))throw new FieldProblem(422,'Trabalho offline inválido.');
   $log=$this->db->table('agri_work_logs')->where('request_id',$client)->get()->getRowArray();$book=new DraftBook($this->db);
   if(!$log){if($version!==0)throw new FieldProblem(409,'O trabalho original não está disponível.');$data['request_id']=$client;$result=$book->create($data,(int)auth()->id());}
   else{
    FieldAccess::checkLog($log);if(($data['work_date']??null)!==$log['work_date']||(int)($data['harvest_id']??0)!==(int)$log['harvest_id'])throw new FieldProblem(409,'Contrato ou data diferem do trabalho original. Reveja a versão do servidor.');if((int)$log['created_by']!==(int)auth()->id())throw new FieldProblem(403,'Trabalho de outro utilizador.');
    $expected=$version===0?1:$version;if((int)$log['version']!==$expected||$log['status']!=='draft')throw new FieldProblem(409,'O trabalho foi alterado ou encerrado no servidor. Consulte o histórico antes de resolver.');
    $changes=array_intersect_key($data,array_flip(['notes','billing_quantity','attendees','pools']));$changes['version']=$expected;$result=$book->update((int)$log['id'],$changes,(int)auth()->id());
   }
   return ['item'=>HarvestBook::operational($result['item'])];
  });
 }
 public function snapshot():array
 {
  FieldAccess::requireField();$workers=$this->db->table('agri_workers')->select('id,name,phone,is_active,is_blocked,block_reason,version')->orderBy('name')->get(10001)->getResultArray();if(count($workers)>10000)throw new FieldProblem(422,'A preparação offline suporta até 10 000 trabalhadores.');
  $stats=$this->db->table('agri_work_log_attendees a')->select('a.worker_id,AVG(a.score) AS average,COUNT(a.score) AS total')->join('agri_work_logs l','l.id=a.work_log_id')->where('a.present',1)->whereIn('l.status',['submitted','closed'])->groupBy('a.worker_id')->get()->getResultArray();$stats=array_column($stats,null,'worker_id');
  $a=$this->db->escapeIdentifiers($this->db->prefixTable('agri_work_log_attendees'));$l=$this->db->escapeIdentifiers($this->db->prefixTable('agri_work_logs'));
  $history=$this->db->query("SELECT * FROM (SELECT a.worker_id,l.work_date,l.service_name,a.score,a.notes,ROW_NUMBER() OVER(PARTITION BY a.worker_id ORDER BY l.work_date DESC,l.id DESC) AS rn FROM $a a JOIN $l l ON l.id=a.work_log_id WHERE a.present=1 AND a.score IS NOT NULL AND l.status IN ('submitted','closed')) recent WHERE rn<=20")->getResultArray();$hist=[];foreach($history as$r){unset($r['rn']);$hist[$r['worker_id']][]=$r;}
  foreach($workers as&$w){$id=(int)$w['id'];$s=$stats[$id]??[];$w['id']=$id;$w['version']=(int)$w['version'];$w['is_active']=(bool)$w['is_active'];$w['is_blocked']=(bool)$w['is_blocked'];$w['evaluation_count']=(int)($s['total']??0);$w['performance_avg']=isset($s['average'])?round((float)$s['average'],2):null;$w['low_performance']=$w['performance_avg']!==null&&$w['performance_avg']<3;$w['evaluations']=$hist[$id]??[];}unset($w);
  $contracts=$this->db->table('agri_harvests h')->select('h.id,h.name,h.status,h.starts_on,h.client_id,h.site_id,h.service_id,h.payment_unit_id,h.billing_unit_id,p.name AS payment_unit_name,b.name AS billing_unit_name,c.name AS client_name,s.name AS site_name,v.name AS service_name')->join('agri_units p','p.id=h.payment_unit_id')->join('agri_units b','b.id=h.billing_unit_id')->join('agri_clients c','c.id=h.client_id')->join('agri_sites s','s.id=h.site_id')->join('agri_services v','v.id=h.service_id')->get(10001)->getResultArray();if(count($contracts)>10000)throw new FieldProblem(422,'Demasiados contratos para preparar offline.');
  $logs=$this->db->table('agri_work_logs')->where('controller_id',auth()->id())->groupStart()->where('status','draft')->orWhere('work_date >=',gmdate('Y-m-d',time()-60*86400))->groupEnd()->orderBy('work_date','DESC')->get(1001)->getResultArray();if(count($logs)>1000)throw new FieldProblem(422,'Demasiados trabalhos recentes para preparar offline.');$book=new DraftBook($this->db);$logs=array_map(fn($r)=>HarvestBook::operational($book->get((int)$r['id'])),$logs);
  return ['owner'=>(int)auth()->id(),'prepared_at'=>gmdate('c'),'contracts'=>$contracts,'workers'=>$workers,'logs'=>$logs];
 }
 public function photo(int $id,$file,array $in):array
 {
  if(!$file||!$file->isValid()||$file->getSize()>5*1024*1024||!in_array($file->getMimeType(),['image/jpeg','image/png','image/webp'],true)||!@getimagesize($file->getTempName()))throw new FieldProblem(422,'Envie JPG, PNG ou WebP até 5 MB.');
  $payload=['log'=>$id,'worker'=>$in['worker_id']??null,'pool'=>$in['pool_key']??null,'description'=>$in['description']??'','sha256'=>hash_file('sha256',$file->getTempName())];$target=null;
  try{return $this->receipt($in['request_id']??'','photo',$payload,function()use($id,$file,$payload,&$target){
   $log=$this->db->query('SELECT * FROM '.$this->db->escapeIdentifiers($this->db->prefixTable('agri_work_logs')).' WHERE id=?'.($this->db->getPlatform()==='MySQLi'?' FOR UPDATE':''),[$id])->getRowArray();if(!$log)throw new FieldProblem(404,'Trabalho indisponível.');FieldAccess::checkLog($log);if($log['status']!=='draft')throw new FieldProblem(409,'O trabalho já foi encerrado.');
   $worker=$payload['worker'];$pool=$payload['pool'];if($worker&&$pool)throw new FieldProblem(422,'Selecione trabalhador ou grupo.');if($worker&&!$this->db->table('agri_work_log_attendees')->where('work_log_id',$id)->where('worker_id',(int)$worker)->countAllResults())throw new FieldProblem(422,'Trabalhador não pertence ao trabalho.');if($pool&&!$this->db->table('agri_work_pools')->where('work_log_id',$id)->where('pool_key',$pool)->countAllResults())throw new FieldProblem(422,'Grupo não pertence ao trabalho.');if(!is_string($payload['description'])||mb_strlen($payload['description'])>160)throw new FieldProblem(422,'Descrição inválida.');
   $dir=WRITEPATH.'production-photos';if(!is_dir($dir))mkdir($dir,0700,true);$name=bin2hex(random_bytes(24));$mime=$file->getMimeType();$file->move($dir,$name);$target=$dir.'/'.$name;$this->db->table('agri_production_photos')->insert(['work_log_id'=>$id,'worker_id'=>$worker?(int)$worker:null,'pool_key'=>$pool?:null,'filename'=>$name,'mime'=>$mime,'description'=>$payload['description'],'created_by'=>auth()->id(),'created_at'=>gmdate('Y-m-d H:i:s')]);return ['id'=>(int)$this->db->insertID()];
  });}catch(\Throwable $e){if($target&&is_file($target))unlink($target);throw $e;}
 }
}
