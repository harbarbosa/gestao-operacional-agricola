<?php
namespace App\Field;
class WorkerSafety
{
 public function __construct(private \CodeIgniter\Database\BaseConnection $db){}
 public function summary(array $worker):array{
  $q=$this->db->table('agri_work_log_attendees a')->select('AVG(a.score) AS performance_avg,COUNT(a.score) AS evaluation_count')->join('agri_work_logs l','l.id=a.work_log_id')->where('a.worker_id',$worker['id'])->where('a.present',1)->whereIn('l.status',['closed','submitted'])->get()->getRowArray();
  $worker['is_blocked']=(bool)$worker['is_blocked'];$worker['performance_avg']=$q['performance_avg']===null?null:round((float)$q['performance_avg'],2);$worker['evaluation_count']=(int)$q['evaluation_count'];$worker['low_performance']=$worker['performance_avg']!==null&&$worker['performance_avg']<3;return $worker;
 }
 public function history(int $id):array{
  FieldAccess::requireField();$w=$this->db->table('agri_workers')->select('id,name,is_active,is_blocked,block_reason,version')->where('id',$id)->get()->getRowArray();if(!$w)throw new FieldProblem(404,'Trabalhador não encontrado.');
  $w=$this->summary($w);$w['evaluations']=$this->db->table('agri_work_log_attendees a')->select('l.work_date,l.service_name,a.score,a.notes')->join('agri_work_logs l','l.id=a.work_log_id')->where('a.worker_id',$id)->where('a.present',1)->where('a.score !=',null)->whereIn('l.status',['closed','submitted'])->orderBy('l.work_date','DESC')->orderBy('l.id','DESC')->get(20)->getResultArray();return $w;
 }
 public function block(int $id,array $in):array{
  if(!auth()->user()->can('catalog.manage'))throw new FieldProblem(403,'Sem permissão para bloquear trabalhadores.');if(!is_bool($in['blocked']??null)||!is_int($in['version']??null)||!is_string($in['reason']??null)||mb_strlen($in['reason'])>500||mb_strlen(trim($in['reason']))<3)throw new FieldProblem(422,'Indique a versão e o motivo (3 a 500 caracteres).');
  $this->db->transBegin();try{$before=$this->db->query('SELECT * FROM '.$this->db->escapeIdentifiers($this->db->prefixTable('agri_workers')).' WHERE id=?'.($this->db->getPlatform()==='MySQLi'?' FOR UPDATE':''),[$id])->getRowArray();if(!$before)throw new FieldProblem(404,'Trabalhador não encontrado.');if((int)$before['version']!==$in['version'])throw new FieldProblem(409,'Registo alterado. Reabra a ficha.');
   $this->db->table('agri_workers')->where('id',$id)->update(['is_blocked'=>$in['blocked'],'block_reason'=>trim($in['reason']),'version'=>$in['version']+1,'updated_at'=>gmdate('Y-m-d H:i:s')]);$after=$this->db->table('agri_workers')->where('id',$id)->get()->getRowArray();$this->db->table('agri_catalog_audit')->insert(['resource'=>'workers','record_id'=>$id,'actor_id'=>auth()->id(),'action'=>$in['blocked']?'block':'unblock','before_json'=>json_encode($before),'after_json'=>json_encode($after),'created_at'=>gmdate('Y-m-d H:i:s')]);if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Persistence');return ['item'=>$this->summary($after)];
  }catch(\Throwable $e){$this->db->transRollback();throw $e;}
 }
}
