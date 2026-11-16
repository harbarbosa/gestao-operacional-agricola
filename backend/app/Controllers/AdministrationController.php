<?php
namespace App\Controllers;
use App\Finance\AdministrationBook;
use App\Finance\ContractPayments;
use App\Harvest\HarvestBook;
use App\Field\FieldProblem;
class AdministrationController extends BaseController
{
 private function run(callable $fn){try{return $this->response->setHeader('Cache-Control','no-store')->setJSON($fn());}catch(FieldProblem $e){return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]);}}
 public function portfolio(){return $this->run(fn()=>(new AdministrationBook(db_connect()))->portfolio($this->request->getGet()));}
 public function update(string $id){return $this->run(fn()=>(new HarvestBook(db_connect()))->updateContract((int)$id,$this->request->getJSON(true)??[]));}
 public function deactivate(string $id){return $this->run(fn()=>(new HarvestBook(db_connect()))->updateContract((int)$id,$this->request->getJSON(true)??[],true));}
 public function pending(){return $this->run(fn()=>(new ContractPayments(db_connect()))->pending((int)$this->request->getGet('harvest_id'),(int)$this->request->getGet('worker_id'),(string)$this->request->getGet('date_from'),(string)$this->request->getGet('date_to')));}
 public function settle(){return $this->run(fn()=>(new ContractPayments(db_connect()))->create($this->request->getJSON(true)??[]));}
 public function settlement(string $id){return $this->run(fn()=>['item'=>(new ContractPayments(db_connect()))->detail((int)$id)]);}
 public function pay(string $id){return $this->run(fn()=>(new ContractPayments(db_connect()))->pay((int)$id,$this->request->getJSON(true)??[]));}
 public function expense(string $id){return $this->run(fn()=>(new ContractPayments(db_connect()))->expense((int)$id,$this->request->getJSON(true)??[]));}
 public function export(){try{
  $q=$this->request->getGet();$r=(new AdministrationBook(db_connect()))->portfolio($q);$contractReport=($q['type']??'')==='contracts';$rows=$contractReport?array_values(array_filter($r['contracts'],fn($c)=>(empty($q['status'])||$c['status']===$q['status'])&&(!$c['ends_on']||$c['ends_on']>=$r['period']['from']))):$r['movements'];if(count($rows)>10000)throw new FieldProblem(422,'Reduza os filtros para exportar até 10 000 linhas.');$f=fopen('php://temp','r+');fwrite($f,"\xEF\xBB\xBF");$safe=fn($v)=>preg_match('/^[=+@\-\t\r]/u',(string)$v)?"'".$v:$v;$money=fn($v)=>((int)$v<0?'-':'').intdiv(abs((int)$v),100).','.str_pad((string)(abs((int)$v)%100),2,'0',STR_PAD_LEFT);
  if($contractReport){fputcsv($f,['Contrato','Cliente','Local','Início','Estado','Receita apurada (€)','Remuneração apurada (€)','Despesas reconhecidas (€)','Recebido (€)','Pago (€)','Saldo a receber (€)','Saldo a pagar (€)','Resultado apurado (€)','Saldo de caixa (€)'],';');foreach($rows as$c){$s=$c['summary'];fputcsv($f,[$safe($c['name']),$safe($c['client_name']),$safe($c['site_name']),date('d/m/Y',strtotime($c['starts_on'])),(['active'=>'Em curso','closed'=>'Encerrado','inactive'=>'Inativo'][$c['status']]??$c['status']),$money($s['receivable_cents']),$money($s['payable_cents']),$money($s['expense_cents']),$money($s['received_cents']),$money($s['paid_cents']),$money($s['pending_receipt_cents']),$money($s['pending_payment_cents']),$money($s['margin_cents']),$money($s['cash_cents'])],';');}}
  else{fputcsv($f,['Data','Contrato','Tipo','Descrição','Trabalhador','Entrada (€)','Saída (€)'],';');foreach($rows as$m){$amount=$money($m['amount_cents']);fputcsv($f,[date('d/m/Y',strtotime($m['paid_on'])),$safe($m['contract_name']),(['receipt'=>'Recebimento','payment'=>'Pagamento','expense'=>'Despesa'][$m['kind']]??$m['kind']),$safe($m['reference']??''),$safe($m['worker_name']??''),$m['kind']==='receipt'?$amount:'',$m['kind']!=='receipt'?$amount:''],';');}}
  rewind($f);$csv=stream_get_contents($f);fclose($f);return $this->response->setHeader('Cache-Control','no-store')->setHeader('Content-Disposition','attachment; filename="agroperi-relatorio.csv"')->setContentType('text/csv','UTF-8')->setBody($csv);
 }catch(FieldProblem $e){return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]);}}
}
