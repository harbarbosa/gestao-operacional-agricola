<?php
namespace App\Field;
use App\Offline\OfflineBook;
class WorkerPortraits
{
 public function __construct(private \CodeIgniter\Database\BaseConnection $db){}
 public static function access():void{if(!auth()->user()->can('catalog.manage'))FieldAccess::requireField();}
 public function save(int $worker,$file,array $input):array{
  self::access();if(!$file||!$file->isValid()||$file->getSize()>1024*1024||!in_array($file->getMimeType(),['image/jpeg','image/png','image/webp'],true)||!@getimagesize($file->getTempName()))throw new FieldProblem(422,'Envie uma fotografia válida até 1 MB.');
  $version=$input['version']??null;if(!is_string($version)||!ctype_digit($version))throw new FieldProblem(422,'Versão da fotografia inválida.');$target=null;
  try{return (new OfflineBook($this->db))->receipt($input['request_id']??'','portrait',['worker'=>$worker,'version'=>(int)$version,'hash'=>hash_file('sha256',$file->getTempName())],function()use($worker,$file,$version,&$target){
   $row=$this->db->query('SELECT * FROM '.$this->db->escapeIdentifiers($this->db->prefixTable('agri_workers')).' WHERE id=?'.($this->db->getPlatform()==='MySQLi'?' FOR UPDATE':''),[$worker])->getRowArray();if(!$row)throw new FieldProblem(404,'Trabalhador não encontrado.');if((int)$row['photo_version']!==(int)$version)throw new FieldProblem(409,'A fotografia mudou. Reabra o cadastro antes de substituir.');if((int)$version>0&&!auth()->user()->can('catalog.manage'))throw new FieldProblem(403,'A substituição da fotografia é reservada à administração.');
   $dir=WRITEPATH.'worker-portraits';if(!is_dir($dir))mkdir($dir,0700,true);$name=bin2hex(random_bytes(24));$mime=$file->getMimeType();$file->move($dir,$name);$target=$dir.'/'.$name;
   $values=['filename'=>$name,'mime'=>$mime,'updated_by'=>auth()->id(),'updated_at'=>gmdate('Y-m-d H:i:s')];$old=$this->db->table('agri_worker_portraits')->where('worker_id',$worker)->get()->getRowArray();if($old)$this->db->table('agri_worker_portraits')->where('worker_id',$worker)->update($values);else$this->db->table('agri_worker_portraits')->insert(['worker_id'=>$worker]+$values);
   $this->db->table('agri_workers')->where('id',$worker)->update(['photo_version'=>(int)$version+1]);$this->db->table('agri_catalog_audit')->insert(['resource'=>'workers','record_id'=>$worker,'actor_id'=>auth()->id(),'action'=>'photo','before_json'=>null,'after_json'=>json_encode(['photo_version'=>(int)$version+1]),'created_at'=>gmdate('Y-m-d H:i:s')]);return ['photo_version'=>(int)$version+1];
  });}catch(\Throwable $e){if($target&&is_file($target))unlink($target);throw $e;}
 }
 public function get(int $id):array{self::access();$p=$this->db->table('agri_worker_portraits')->where('worker_id',$id)->get()->getRowArray();if(!$p)throw new FieldProblem(404,'Sem fotografia.');return $p;}
}
