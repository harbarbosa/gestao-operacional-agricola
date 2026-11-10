<?php
namespace App\Controllers;
use App\Finance\ProductionBook;
use App\Field\FieldAccess;
use App\Field\FieldProblem;
class ProductionController extends BaseController
{
    private function run(callable $operation)
    {
        try { return $this->response->setJSON($operation(new ProductionBook(db_connect()))); }
        catch(FieldProblem $e) { return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]); }
    }
    public function preview(string $id) { return $this->run(function($book)use($id){FieldAccess::requireField();return $book->preview((int)$id);}); }
    public function close(string $id) { return $this->run(function($book)use($id){FieldAccess::requireField();return $book->close((int)$id,$this->request->getJSON(true)??[],(int)auth()->id());}); }
    public function pending()
    {
        return $this->run(function($book){ $worker=$this->request->getGet('worker_id'); if(!is_string($worker)||!ctype_digit($worker)||(int)$worker<1) throw new FieldProblem(422,'Selecione um trabalhador.'); $from=(string)$this->request->getGet('date_from'); $to=(string)$this->request->getGet('date_to'); $items=$book->pending((int)$worker,$from,$to); return ['items'=>$items,'preview_hash'=>ProductionBook::pendingHash((int)$worker,$from,$to,$items)]; });
    }
    public function summary()
    {
        $pending=db_connect()->table('agri_earnings e')->select('COALESCE(SUM(e.amount_cents),0) AS total',false)->join('agri_settlement_items i','i.earning_id=e.id','left')->where('i.earning_id',null)->get()->getRowArray();
        $closed=db_connect()->table('agri_settlements')->select('COALESCE(SUM(total_cents-paid_cents),0) AS pending, COALESCE(SUM(paid_cents),0) AS paid',false)->get()->getRowArray();
        return $this->response->setJSON(['pending_cents'=>(string)\Brick\Math\BigInteger::of((string)$pending['total'])->plus((string)$closed['pending']),'paid_cents'=>(string)$closed['paid']]);
    }
    public function index()
    {
        $page=$this->request->getGet('page')??'1'; $worker=$this->request->getGet('worker_id');
        if(!is_string($page)||!ctype_digit($page)||(int)$page<1||(int)$page>100000||($worker!==null&&(!is_string($worker)||!ctype_digit($worker)||(int)$worker<1))) return $this->response->setStatusCode(422)->setJSON(['error'=>'Filtros inválidos.']);
        $builder=db_connect()->table('agri_settlements s')->select('s.id,s.worker_id,s.date_from,s.date_to,s.total_cents,s.paid_cents,s.version,s.created_at,w.name AS worker_name')->join('agri_workers w','w.id=s.worker_id');
        if($worker!==null)$builder->where('s.worker_id',(int)$worker); $total=$builder->countAllResults(false); return $this->response->setJSON(['items'=>$builder->orderBy('s.id','DESC')->get(25,((int)$page-1)*25)->getResultArray(),'total'=>$total,'page'=>(int)$page]);
    }
    public function show(string $id) { return $this->run(fn($book)=>['item'=>$book->settlement((int)$id)]); }
    public function settle() { return $this->run(fn($book)=>$book->settle($this->request->getJSON(true)??[],(int)auth()->id())); }
    public function pay(string $id) { return $this->run(fn($book)=>$book->pay((int)$id,$this->request->getJSON(true)??[],(int)auth()->id())); }
}
