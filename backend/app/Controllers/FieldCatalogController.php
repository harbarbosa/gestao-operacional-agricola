<?php
namespace App\Controllers;
use App\Field\FieldAccess;
use App\Field\FieldProblem;
class FieldCatalogController extends CatalogController
{
    public function createWorker()
    {
        try { FieldAccess::requireField(); $input=$this->request->getJSON(true); if(!is_array($input)||!is_int($input['team_id']??null)) throw new FieldProblem(422,'Selecione a equipa.'); FieldAccess::check($input['team_id']); if(isset($input['is_active'])&&$input['is_active']!==true) throw new FieldProblem(422,'O novo trabalhador deve estar ativo.'); return parent::create('workers'); }
        catch(FieldProblem $e) { return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]); }
    }
    public function options(string $resource,?string $id=null)
    {
        try {
            if($resource==='managers') { if(!auth()->user()->can('catalog.manage')) throw new FieldProblem(403,'Sem permissão.'); }
            else { FieldAccess::requireField(); if(!in_array($resource,['workers','teams','clients','sites','services','units'],true)) throw new FieldProblem(404,'Opção inexistente.'); }
            $db=db_connect();
            if($resource==='managers') {
                $query=$this->request->getGet('q')??''; if(!is_string($query)||mb_strlen($query)>160) throw new FieldProblem(422,'Pesquisa inválida.');
                $provider=auth()->getProvider(); if($id)$users=[$provider->findById((int)$id)]; else $users=$provider->like('username',$query)->where('active',1)->orderBy('username')->findAll(200);
                $items=[]; foreach($users as $user) if($user && $user->active && !$user->isBanned() && ($user->can('field.record')||$user->can('field.manage'))) $items[]=['id'=>(int)$user->id,'name'=>$user->username,'version'=>1,'is_active'=>true];
                return $this->response->setJSON($id ? ['item'=>$items[0]??null] : ['items'=>$items,'total'=>count($items),'page'=>1,'pageSize'=>200]);
            }
            $builder=$db->table('agri_'.$resource);
            if($resource==='teams'&&!FieldAccess::manager())$builder->whereIn('id',FieldAccess::teamIds()?:[0]);
            if($id) { $item=$builder->where('id',(int)$id)->get()->getRowArray(); if(!$item) throw new FieldProblem(404,'Opção não encontrada.'); $item['id']=(int)$item['id'];$item['version']=(int)$item['version'];$item['is_active']=(bool)$item['is_active']; return $this->response->setJSON(['item'=>$item]); }
            $query=$this->request->getGet('q')??''; if(!is_string($query)||mb_strlen($query)>160) throw new FieldProblem(422,'Pesquisa inválida.'); if($query!=='')$builder->like('name',$query);
            if($resource==='sites' && ($client=$this->request->getGet('client_id'))!==null) { if(!is_string($client)||!ctype_digit($client)||(int)$client<1) throw new FieldProblem(422,'Cliente inválido.'); $builder->where('client_id',(int)$client); }
            $total=$builder->countAllResults(false); $items=$builder->orderBy('name')->get(25)->getResultArray();
            foreach($items as &$item) { $item['id']=(int)$item['id'];$item['version']=(int)$item['version'];$item['is_active']=(bool)$item['is_active'];
                if($resource==='workers') { $quality=$db->table('agri_work_log_attendees a')->select('AVG(a.score) AS performance_avg, COUNT(*) AS evaluation_count')->join('agri_work_logs l','l.id=a.work_log_id')->where('a.worker_id',$item['id'])->where('a.present',1)->where('l.status','closed')->get()->getRowArray();$item+=$quality; }
            }
            return $this->response->setJSON(['items'=>$items,'total'=>$total,'page'=>1,'pageSize'=>25]);
        } catch(FieldProblem $e) { return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]); }
    }
}
