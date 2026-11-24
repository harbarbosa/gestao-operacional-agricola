<?php
namespace App\Controllers;
use App\Harvest\HarvestBook;
use App\Field\FieldProblem;
use App\Reports\ReportExport;
class SimpleController extends BaseController
{
 public function statement(string $id='0'){
  try{
   HarvestBook::requireAdmin();$db=db_connect();$from=HarvestBook::date($this->request->getGet('date_from')??gmdate('Y-m-01'));$to=HarvestBook::date($this->request->getGet('date_to')??gmdate('Y-m-d'));if($from>$to)throw new FieldProblem(422,'Período inválido.');$worker=(int)$id;
   $person=$worker?$db->table('agri_workers')->where('id',$worker)->get()->getRowArray():null;if($worker&&!$person)throw new FieldProblem(404,'Trabalhador não encontrado.');
   $q=$db->table('agri_earnings e')->select('e.id,e.worker_id,e.work_log_id,e.quantity,e.unit_name,e.rate,e.amount_cents,l.work_date,l.service_name,w.name AS worker_name,p.name AS pool_name')->join('agri_work_logs l','l.id=e.work_log_id')->join('agri_workers w','w.id=e.worker_id')->join('agri_work_pools p','p.work_log_id=e.work_log_id AND p.pool_key=e.pool_key','left')->where('l.status','closed')->where('l.work_date <=',$to);if($worker)$q->where('e.worker_id',$worker);$earnings=$q->orderBy('l.work_date')->get(10001)->getResultArray();
   // Earlier contract records remain readable in the worker's history.
   $q=$db->table('agri_harvest_allocations e')->select('e.work_log_id AS id,e.worker_id,e.work_log_id,e.quantity,l.payment_unit_name AS unit_name,e.rate,e.amount_cents,l.work_date,l.service_name,w.name AS worker_name,p.name AS pool_name')->join('agri_work_logs l','l.id=e.work_log_id')->join('agri_workers w','w.id=e.worker_id')->join('agri_work_log_attendees a','a.work_log_id=e.work_log_id AND a.worker_id=e.worker_id')->join('agri_work_pools p','p.work_log_id=e.work_log_id AND p.pool_key=a.pool_key','left')->where('l.status','closed')->where('l.work_date <=',$to);if($worker)$q->where('e.worker_id',$worker);$earnings=array_merge($earnings,$q->get(10001)->getResultArray());
   $q=$db->table('agri_payment_entries p')->select('p.id,p.amount_cents,p.paid_on,p.reference,p.notes,s.worker_id,w.name AS worker_name')->join('agri_settlements s','s.id=p.settlement_id')->join('agri_workers w','w.id=s.worker_id')->where('p.paid_on <=',$to);if($worker)$q->where('s.worker_id',$worker);$payments=$q->get(10001)->getResultArray();
   $q=$db->table('agri_harvest_movements p')->select('p.id,p.amount_cents,p.paid_on,p.reference,p.notes,p.worker_id,w.name AS worker_name')->join('agri_workers w','w.id=p.worker_id')->where('p.kind','payment')->where('p.paid_on <=',$to);if($worker)$q->where('p.worker_id',$worker);$payments=array_merge($payments,$q->get(10001)->getResultArray());
   if(count($earnings)>10000||count($payments)>10000)throw new FieldProblem(422,'Reduza o período: limite de 10 000 registos.');
   $opening=0;$earned=0;$paid=0;$units=[];foreach($earnings as$e){if($e['work_date']<$from){$opening+=(int)$e['amount_cents'];continue;}$earned+=(int)$e['amount_cents'];$u=$e['unit_name'];$units[$u]=(string)\Brick\Math\BigDecimal::of($units[$u]??'0')->plus($e['quantity']??'0');}foreach($payments as$p){if($p['paid_on']<$from)$opening-=(int)$p['amount_cents'];else$paid+=(int)$p['amount_cents'];}
   $items=array_values(array_filter($earnings,fn($e)=>$e['work_date']>=$from));$payments=array_values(array_filter($payments,fn($p)=>$p['paid_on']>=$from));usort($items,fn($a,$b)=>strcmp($a['work_date'],$b['work_date']));usort($payments,fn($a,$b)=>strcmp($a['paid_on'],$b['paid_on']));
   $result=['worker'=>$person?array_intersect_key($person,array_flip(['id','name','phone','photo_version'])):null,'period'=>['from'=>$from,'to'=>$to],'items'=>$items,'payments'=>$payments,'production'=>$units,'summary'=>['opening_cents'=>$opening,'earned_cents'=>$earned,'paid_cents'=>$paid,'balance_cents'=>$opening+$earned-$paid]];
   if($this->request->getGet('format')==='pdf'){
    $date=fn($d)=>implode('/',array_reverse(explode('-',$d)));$money=fn($c)=>number_format($c/100,2,',',' ').' €';$lines=['AGROPERI - DEMONSTRATIVO DO TRABALHADOR',$person['name']??'Todos os trabalhadores','Período: '.$date($from).' a '.$date($to),'Emitido em: '.(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Lisbon')))->format('d/m/Y H:i'),'','Saldo anterior: '.$money($opening),'Produção apurada no período: '.$money($earned),'Pagamentos no período: '.$money($paid),'SALDO A PAGAR: '.$money($opening+$earned-$paid),'','PRODUÇÃO VALIDADA'];foreach($units as$u=>$v)$lines[]=$v.' '.$u;
    foreach($items as$e){$lines[]='';$lines[]=$date($e['work_date']).' | '.$e['worker_name'].' | '.$e['service_name'];$lines[]=($e['pool_name']?'Parcela do grupo '.$e['pool_name']:'Individual').' | '.$e['quantity'].' '.$e['unit_name'].' x '.($e['rate']??'—').' € = '.$money($e['amount_cents']);}
    $lines[]='';$lines[]='PAGAMENTOS REALIZADOS';foreach($payments as$p){$lines[]=$date($p['paid_on']).' | '.$p['worker_name'].' | '.$money($p['amount_cents']);$lines[]=($p['reference']??'').' '.($p['notes']??'');}
    return $this->response->setHeader('Content-Disposition','attachment; filename="agroperi-demonstrativo.pdf"')->setHeader('Cache-Control','no-store')->setContentType('application/pdf')->setBody(ReportExport::textPdf($lines));
   }
   return $this->response->setJSON($result);
  }catch(FieldProblem $e){return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]);}
 }
}
