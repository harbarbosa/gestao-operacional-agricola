<?php
namespace App\Controllers;
use App\Harvest\HarvestBook;
use App\Field\FieldProblem;
use App\Field\FieldAccess;
class HarvestsController extends BaseController
{
 private function run(callable $fn){try{return $this->response->setJSON($fn(new HarvestBook(db_connect())));}catch(FieldProblem $e){return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]);}}
 public function startContract(){return $this->run(fn($b)=>$b->startContract($this->request->getJSON(true)??[]));}
 public function workerHistory(string $id){return $this->run(fn()=>['item'=>(new \App\Field\WorkerSafety(db_connect()))->history((int)$id)]);}
 public function workerBlock(string $id){return $this->run(fn()=>(new \App\Field\WorkerSafety(db_connect()))->block((int)$id,$this->request->getJSON(true)??[]));}
 public function contracts(){return $this->run(fn($b)=>$b->listing('contracts'));}
 public function createContract(){return $this->run(fn($b)=>$b->createContract($this->request->getJSON(true)??[]));}
 public function index(){return $this->run(fn($b)=>$b->listing('harvests'));}
 public function create(){return $this->run(fn($b)=>$b->createHarvest($this->request->getJSON(true)??[]));}
 public function show(string $id){return $this->run(fn($b)=>['item'=>$b->detail((int)$id)]);}
 public function delivery(string $id){return $this->run(fn($b)=>$b->delivery((int)$id,$this->request->getJSON(true)??[]));}
 public function movement(string $id){return $this->run(fn($b)=>$b->movement((int)$id,$this->request->getJSON(true)??[]));}
 public function close(string $id){return $this->run(fn($b)=>$b->close((int)$id,$this->request->getJSON(true)??[]));}
 public function daily(string $id,string $action){return $this->run(fn($b)=>$b->daily((int)$id,$action,$this->request->getJSON(true)??[]));}
 private function log(int $id): array { FieldAccess::requireField();$log=db_connect()->table('agri_work_logs')->where('id',$id)->get()->getRowArray();if(!$log)throw new FieldProblem(404,'Atividade não encontrada.');FieldAccess::checkLog($log);return $log; }
 public function photos(string $id){return $this->run(function()use($id){$this->log((int)$id);return ['items'=>db_connect()->table('agri_production_photos')->select('id,worker_id,pool_key,description,created_at')->where('work_log_id',(int)$id)->orderBy('id','DESC')->get()->getResultArray()];});}
 public function upload(string $id)
 {
  return $this->run(function()use($id){if($this->request->getPost('request_id'))return (new \App\Offline\OfflineBook(db_connect()))->photo((int)$id,$this->request->getFile('photo'),$this->request->getPost());$log=$this->log((int)$id);if($log['status']!=='draft')throw new FieldProblem(409,'Adicione fotos antes de encerrar a atividade.');$f=$this->request->getFile('photo');if(!$f||!$f->isValid()||$f->getSize()>5*1024*1024||!in_array($f->getMimeType(),['image/jpeg','image/png','image/webp'],true)||!getimagesize($f->getTempName()))throw new FieldProblem(422,'Envie uma fotografia JPG, PNG ou WebP até 5 MB.');
   $worker=$this->request->getPost('worker_id');$pool=$this->request->getPost('pool_key');if($worker&&$pool)throw new FieldProblem(422,'Selecione trabalhador ou grupo.');if($worker&&!db_connect()->table('agri_work_log_attendees')->where('work_log_id',(int)$id)->where('worker_id',(int)$worker)->countAllResults())throw new FieldProblem(422,'Trabalhador não participa nesta atividade.');if($pool&&!db_connect()->table('agri_work_pools')->where('work_log_id',(int)$id)->where('pool_key',$pool)->countAllResults())throw new FieldProblem(422,'Grupo inexistente.');
   $description=$this->request->getPost('description')??'';if(!is_string($description)||mb_strlen($description)>160)throw new FieldProblem(422,'Descrição inválida.');$dir=WRITEPATH.'production-photos';if(!is_dir($dir))mkdir($dir,0700,true);$name=bin2hex(random_bytes(24));$mime=$f->getMimeType();$f->move($dir,$name);try{db_connect()->table('agri_production_photos')->insert(['work_log_id'=>(int)$id,'worker_id'=>$worker?(int)$worker:null,'pool_key'=>$pool?:null,'filename'=>$name,'mime'=>$mime,'description'=>$description,'created_by'=>auth()->id(),'created_at'=>gmdate('Y-m-d H:i:s')]);}catch(\Throwable $e){unlink($dir.'/'.$name);throw $e;}return ['id'=>(int)db_connect()->insertID()];});
 }
 public function photo(string $id)
 {
  try{$p=db_connect()->table('agri_production_photos')->where('id',(int)$id)->get()->getRowArray();if(!$p)throw new FieldProblem(404,'Foto não encontrada.');$this->log((int)$p['work_log_id']);$path=WRITEPATH.'production-photos/'.$p['filename'];if(!is_file($path))throw new FieldProblem(404,'Foto indisponível.');return $this->response->setHeader('Cache-Control','private, no-store')->setHeader('X-Content-Type-Options','nosniff')->setContentType($p['mime'])->setBody(file_get_contents($path));}catch(FieldProblem $e){return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]);}
 }
}
