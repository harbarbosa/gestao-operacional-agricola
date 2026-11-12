<?php
namespace App\Controllers;
use App\Offline\OfflineBook;
use App\Field\FieldProblem;
class OfflineController extends BaseController
{
 private function run(callable $fn){try{return $this->response->setJSON($fn(new OfflineBook(db_connect())));}catch(FieldProblem $e){return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]);}}
 public function snapshot(){return $this->run(fn($b)=>$b->snapshot());}
 public function apply(){return $this->run(fn($b)=>$b->apply($this->request->getJSON(true)??[]));}
}
