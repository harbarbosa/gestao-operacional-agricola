<?php
namespace App\Controllers;
use App\Field\FieldAccess;
use App\Field\FieldProblem;
class PerformanceController extends BaseController
{
    public function index(?string $worker=null)
    {
        try {
            $from=$this->request->getGet('date_from')??''; $to=$this->request->getGet('date_to')??'';
            foreach([$from,$to] as $date) if($date!==''&&(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!(($parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date))&&$parsed->format('Y-m-d')===$date)))throw new FieldProblem(422,'Data inválida.');
            if($from&&$to&&$from>$to)throw new FieldProblem(422,'Período inválido.');
            $builder=db_connect()->table('agri_work_log_attendees a')->join('agri_work_logs l','l.id=a.work_log_id')->join('agri_workers w','w.id=a.worker_id')->where('l.status','closed')->where('a.present',1)->where('a.score !=',null);
            if(!FieldAccess::manager())$builder->whereIn('l.team_id',FieldAccess::teamIds()?:[0]);
            if($from)$builder->where('l.work_date >=',$from); if($to)$builder->where('l.work_date <=',$to);
            if($worker) { $rows=$builder->select('l.id AS work_log_id,l.work_date,l.team_name,l.service_name,a.score,a.notes,w.name AS worker_name')->where('a.worker_id',(int)$worker)->orderBy('l.work_date','DESC')->orderBy('l.id','DESC')->get(200)->getResultArray(); return $this->response->setJSON(['items'=>$rows]); }
            $page=$this->request->getGet('page')??'1'; if(!is_string($page)||!ctype_digit($page)||(int)$page<1||(int)$page>100000)throw new FieldProblem(422,'Página inválida.');
            $rows=$builder->select('w.id AS worker_id,w.name AS worker_name,ROUND(AVG(a.score),2) AS average,COUNT(*) AS evaluations,MIN(a.score) AS minimum,MAX(a.score) AS maximum')->groupBy('w.id')->groupBy('w.name')->orderBy('average','DESC')->orderBy('evaluations','DESC')->orderBy('w.name')->get(25,((int)$page-1)*25)->getResultArray();
            return $this->response->setJSON(['items'=>$rows,'page'=>(int)$page,'pageSize'=>25]);
        } catch(FieldProblem $e) {return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]);}
    }
}
