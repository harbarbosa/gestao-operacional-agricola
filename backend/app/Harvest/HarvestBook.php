<?php
namespace App\Harvest;
use App\Field\FieldAccess;
use App\Field\FieldProblem;
use App\Field\DraftBook;
use App\Finance\ProductionBook;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
class HarvestBook
{
    public function __construct(private \CodeIgniter\Database\BaseConnection $db) {}
    public static function admin(): bool { return auth()->user()->can('finance.manage'); }
    public static function requireAdmin(): void { if(!self::admin())throw new FieldProblem(403,'Acesso reservado à administração.'); }
    public static function decimal($v,bool $zero=true): string
    {
        if(!is_string($v)||!preg_match('/^\d{1,8}(\.\d{1,4})?$/D',$v)||(!$zero&&BigDecimal::of($v)->isLessThanOrEqualTo(0)))throw new FieldProblem(422,'Quantidade ou valor inválido.');return (string)BigDecimal::of($v)->toScale(4);
    }
    public static function date($v): string { $d=is_string($v)?\DateTimeImmutable::createFromFormat('!Y-m-d',$v):false;if(!$d||$d->format('Y-m-d')!==$v)throw new FieldProblem(422,'Data inválida.');return $v; }
    private function text($v,int $max=160): string { if(!is_string($v)||mb_strlen(trim($v))<1||mb_strlen($v)>$max)throw new FieldProblem(422,'Preencha os campos obrigatórios.');return trim($v); }
    private function record(string $table,int $id): array { $r=$this->db->table('agri_'.$table)->where('id',$id)->get()->getRowArray();if(!$r)throw new FieldProblem(404,'Registo não encontrado.');return $r; }
    public function access(int $id): array { FieldAccess::requireField();$h=$this->record('harvests',$id);return $h; }
    private function lock(int $id): array { $r=$this->db->query('SELECT * FROM '.$this->db->escapeIdentifiers($this->db->prefixTable('agri_harvests')).' WHERE id=?'.($this->db->getPlatform()==='MySQLi'?' FOR UPDATE':''),[$id])->getRowArray();if(!$r)throw new FieldProblem(404,'Colheita não encontrada.');return $r; }
    private function tx(callable $fn): array { $this->db->transBegin();try{$r=$fn();if(!$this->db->transStatus()||!$this->db->transCommit())throw new \RuntimeException('Persistence');return $r;}catch(\Throwable $e){$this->db->transRollback();if($e instanceof FieldProblem)throw $e;log_message('error','Falha colheita: {error}',['error'=>$e->getMessage()]);throw new FieldProblem(409,'Não foi possível confirmar. Recarregue antes de repetir.');} }
    private function request(array $in): string { $v=$in['request_id']??null;if(!is_string($v)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$v))throw new FieldProblem(422,'Identificador inválido.');return $v; }
    private function replay(string $table,string $request,string $hash): ?array { $r=$this->db->table($table)->where('request_id',$request)->get()->getRowArray();if($r&&!hash_equals($r['request_hash'],$hash))throw new FieldProblem(409,'Identificador utilizado com outros dados.');return $r; }
    public function listing(string $type): array
    {
        if($type==='contracts')self::requireAdmin();elseif(!self::admin()&&!auth()->user()->can('reports.view'))FieldAccess::requireField();
        $q=$this->db->table('agri_'.$type.' h')->select('h.*,c.name AS client_name,s.name AS service_name,p.name AS site_name')->join('agri_clients c','c.id=h.client_id')->join('agri_services s','s.id=h.service_id')->join('agri_sites p','p.id=h.site_id');

        $items=$q->orderBy('h.id','DESC')->get()->getResultArray();
        if(!self::admin())foreach($items as &$r)unset($r['billing_rate'],$r['payment_rate'],$r['conversion_factor']);return ['items'=>$items];
    }
    public function startContract(array $in):array
    {
        self::requireAdmin();return $this->tx(function()use($in){
            $client=$this->record('clients',(int)($in['client_id']??0));$now=gmdate('Y-m-d H:i:s');
            $data=$in;
            if(empty($data['site_id'])){$this->db->table('agri_sites')->insert(['client_id'=>$client['id'],'name'=>$this->text($in['local_name']??$client['name']),'created_at'=>$now,'updated_at'=>$now]);$data['site_id']=(int)$this->db->insertID();}
            if(empty($data['service_id'])){$this->db->table('agri_services')->insert(['name'=>$this->text($in['name']??null),'billing_unit_id'=>(int)($in['billing_unit_id']??0),'payment_unit_id'=>(int)($in['payment_unit_id']??0),'created_at'=>$now,'updated_at'=>$now]);$data['service_id']=(int)$this->db->insertID();}
            $contract=$this->createContract($data)['item'];$contractId=(int)$contract['id'];unset($contract['id']);$contract+=['contract_id'=>$contractId,'controller_id'=>0,'starts_on'=>self::date($in['starts_on']??null),'status'=>'active'];
            $this->db->table('agri_harvests')->insert($contract);return ['item'=>$this->detail((int)$this->db->insertID())];
        });
    }
    public function createContract(array $in): array
    {
        self::requireAdmin();$data=['name'=>$this->text($in['name']??null),'notes'=>is_string($in['notes']??null)?mb_substr($in['notes'],0,2000):null];
        foreach(['client_id'=>'clients','site_id'=>'sites','service_id'=>'services','billing_unit_id'=>'units','payment_unit_id'=>'units']as$key=>$table){if(!is_int($in[$key]??null))throw new FieldProblem(422,'Selecione os cadastros.');$r=$this->record($table,$in[$key]);if(!$r['is_active'])throw new FieldProblem(422,'Registo inativo.');$data[$key]=$in[$key];}
        if((int)$this->record('sites',$data['site_id'])['client_id']!==$data['client_id'])throw new FieldProblem(422,'O local não pertence ao cliente.');
        foreach(['billing_rate','payment_rate']as$key)$data[$key]=self::decimal($in[$key]??null);
        $data['conversion_factor']=empty($in['conversion_factor'])?null:self::decimal($in['conversion_factor'],false);
        $this->db->table('agri_contracts')->insert($data+['created_by'=>auth()->id(),'created_at'=>gmdate('Y-m-d H:i:s')]);return ['item'=>$this->record('contracts',(int)$this->db->insertID())];
    }
    public function createHarvest(array $in): array
    {
        self::requireAdmin();$contract=$this->record('contracts',(int)($in['contract_id']??0));$user=auth()->getProvider()->findById((int)($in['controller_id']??0));if(!$user||!$user->active||$user->isBanned()||(!$user->can('field.record')&&!$user->can('field.manage')))throw new FieldProblem(422,'Selecione um controlador ativo.');
        unset($contract['id'],$contract['version'],$contract['created_by'],$contract['created_at']);$contract['name']=$this->text($in['name']??null);$contract+=['contract_id'=>(int)$in['contract_id'],'controller_id'=>(int)$in['controller_id'],'starts_on'=>self::date($in['starts_on']??null),'created_by'=>auth()->id(),'created_at'=>gmdate('Y-m-d H:i:s')];
        $this->db->table('agri_harvests')->insert($contract);return ['item'=>$this->detail((int)$this->db->insertID())];
    }
    public function activityInput(array $in): array
    {
        $h=$this->access((int)$in['harvest_id']);if($h['status']!=='active')throw new FieldProblem(409,'A colheita está encerrada.');if(($in['work_date']??'')<$h['starts_on'])throw new FieldProblem(422,'A atividade antecede a colheita.');
        $s=$this->record('services',(int)$h['service_id']);return array_replace($in,['team_id'=>null,'controller_id'=>(int)auth()->id(),'client_id'=>(int)$h['client_id'],'site_id'=>(int)$h['site_id'],'service_id'=>(int)$h['service_id'],'service_version'=>(int)$s['version']]);
    }
    public static function operational(array $log): array { unset($log['earnings'],$log['closure_hash']);return $log; }
    public function daily(int $id,string $action,array $in): array
    {
        return $this->tx(function()use($id,$action,$in){$raw=$this->record('work_logs',$id);$h=$this->lock((int)$raw['harvest_id']);FieldAccess::checkLog($raw);$this->access((int)$h['id']);$log=(new DraftBook($this->db))->get($id);
            if($h['status']!=='active')throw new FieldProblem(409,'Colheita encerrada.');
            if(!is_int($in['version']??null)||$in['version']!==$log['version'])throw new FieldProblem(409,'Atividade alterada. Reabra o registo.');
            if($action==='return'){self::requireAdmin();if($log['status']!=='submitted')throw new FieldProblem(409,'Atividade não está em validação.');$status='draft';}
            elseif($action==='submit'){if($log['status']!=='draft')throw new FieldProblem(409,'Atividade já encerrada.');$this->allocations($log,$h,false);$status='submitted';}
            else {self::requireAdmin();if($log['status']!=='submitted')throw new FieldProblem(409,'Encerre o dia antes de validar.');$plans=$this->allocations($log,$h,true);foreach($plans as$p)$this->db->table('agri_harvest_allocations')->insert(['work_log_id'=>$id]+$p);$status='closed';}
            $now=gmdate('Y-m-d H:i:s');$this->db->table('agri_work_logs')->where('id',$id)->where('version',$in['version'])->update(['status'=>$status,'version'=>$in['version']+1,'updated_by'=>auth()->id(),'updated_at'=>$now,'closed_at'=>$now,'closed_by'=>auth()->id(),'validated_by'=>$action==='validate'?auth()->id():null]);if($this->db->affectedRows()!==1)throw new FieldProblem(409,'Atividade alterada.');
            $after=(new DraftBook($this->db))->get($id);$this->db->table('agri_work_log_audit')->insert(['work_log_id'=>$id,'actor_id'=>auth()->id(),'action'=>$action,'before_json'=>json_encode($log),'after_json'=>json_encode($after),'created_at'=>$now]);return ['item'=>self::operational($after)];});
    }
    private function allocations(array $log,array $h,bool $money): array
    {
        $people=array_values(array_filter($log['attendees'],fn($p)=>$p['present']));if(!$people)throw new FieldProblem(422,'Adicione trabalhadores presentes.');$result=[];
        foreach($people as$p){$w=$this->db->query('SELECT * FROM '.$this->db->escapeIdentifiers($this->db->prefixTable('agri_workers')).' WHERE id=?'.($this->db->getPlatform()==='MySQLi'?' FOR UPDATE':''),[(int)$p['worker_id']])->getRowArray();if($w['is_blocked'])throw new FieldProblem(422,'Trabalhador bloqueado: '.$w['name'].'. Remova-o da atividade.');if(!$p['score'])throw new FieldProblem(422,'Avalie obrigatoriamente todos os trabalhadores presentes.');if(!$p['pool_key']){if($p['payment_quantity']===null)throw new FieldProblem(422,'Complete a produção de cada trabalhador.');$result[]=['worker_id'=>$p['worker_id'],'quantity'=>$p['payment_quantity'],'amount_cents'=>$money?ProductionBook::cents((string)BigDecimal::of($p['payment_quantity'])->multipliedBy($h['payment_rate'])):0];}}
        foreach($log['pools']as$pool){$members=array_values(array_filter($people,fn($p)=>$p['pool_key']===$pool['pool_key']));if(count($members)<2)throw new FieldProblem(422,'Cada grupo precisa de pelo menos dois trabalhadores presentes.');$workers=array_column($members,'worker_id');$cents=$money?ProductionBook::cents((string)BigDecimal::of($pool['quantity'])->multipliedBy($h['payment_rate'])):0;$amounts=ProductionBook::divide($cents,$workers);$units=ProductionBook::divide(BigDecimal::of((string)$pool['quantity'])->toScale(4)->getUnscaledValue()->toInt(),$workers);foreach($workers as$w)$result[]=['worker_id'=>$w,'quantity'=>(string)BigDecimal::ofUnscaledValue($units[$w],4),'amount_cents'=>$amounts[$w]];}
        return $result;
    }
    public function delivery(int $id,array $in): array
    {
        self::requireAdmin();$request=$this->request($in);$hash=hash('sha256',json_encode([$id,$in,auth()->id()]));return $this->tx(function()use($id,$in,$request,$hash){$h=$this->lock($id);if($old=$this->replay('agri_harvest_deliveries',$request,$hash))return ['item'=>$old];if($h['status']!=='active')throw new FieldProblem(409,'Colheita encerrada.');
            $date=self::date($in['delivered_on']??null);if($date<$h['starts_on'])throw new FieldProblem(422,'Data anterior à colheita.');$billing=self::decimal($in['billing_quantity']??null,false);$rows=$in['items']??null;if(!is_array($rows)||!array_is_list($rows)||!count($rows)||count($rows)>200)throw new FieldProblem(422,'Associe a produção entregue.');$total=BigDecimal::zero();$seen=[];
            foreach($rows as&$r){$log=$this->record('work_logs',(int)($r['work_log_id']??0));if((int)$log['harvest_id']!==$id||$log['status']!=='closed'||$log['work_date']>$date||isset($seen[$log['id']]))throw new FieldProblem(422,'Selecione atividades validadas desta colheita e anteriores à entrega.');$seen[$log['id']]=true;$r=['work_log_id'=>(int)$log['id'],'quantity'=>self::decimal($r['quantity']??null,false)];$produced=$this->db->table('agri_harvest_allocations')->selectSum('quantity','total')->where('work_log_id',$log['id'])->get()->getRowArray()['total']??'0';$used=$this->db->table('agri_delivery_items')->selectSum('quantity','total')->where('work_log_id',$log['id'])->get()->getRowArray()['total']??'0';if(BigDecimal::of($used)->plus($r['quantity'])->isGreaterThan($produced))throw new FieldProblem(422,'Entrega superior à produção ainda disponível da atividade.');$total=$total->plus($r['quantity']);}unset($r);
            $data=['harvest_id'=>$id,'delivered_on'=>$date,'payment_quantity'=>(string)$total,'billing_quantity'=>$billing,'reference'=>$this->text($in['reference']??'Entrega'),'created_by'=>auth()->id(),'created_at'=>gmdate('Y-m-d H:i:s'),'request_id'=>$request,'request_hash'=>$hash];$this->db->table('agri_harvest_deliveries')->insert($data);$delivery=(int)$this->db->insertID();foreach($rows as$r)$this->db->table('agri_delivery_items')->insert(['delivery_id'=>$delivery]+$r);return ['item'=>$this->record('harvest_deliveries',$delivery)];});
    }
    public function movement(int $id,array $in): array
    {
        self::requireAdmin();$request=$this->request($in);$hash=hash('sha256',json_encode([$id,$in,auth()->id()]));return $this->tx(function()use($id,$in,$request,$hash){$h=$this->lock($id);if($old=$this->replay('agri_harvest_movements',$request,$hash))return ['item'=>$old];$kind=$in['kind']??'';if(!in_array($kind,['receipt','payment','expense'],true))throw new FieldProblem(422,'Movimento inválido.');$amount=self::decimal($in['amount']??null,false);if(BigDecimal::of($amount)->toScale(2,RoundingMode::DOWN)->compareTo($amount)!==0)throw new FieldProblem(422,'Use duas casas decimais no valor.');$cents=ProductionBook::cents($amount);$date=self::date($in['paid_on']??null);if($date<$h['starts_on'])throw new FieldProblem(422,'Data anterior à colheita.');
            $worker=null;$log=null;$delivery=null;$quantity=null;
            if($kind==='payment'){$worker=(int)($in['worker_id']??0);$this->record('workers',$worker);$log=empty($in['work_log_id'])?null:(int)$in['work_log_id'];$quantity=empty($in['quantity'])?null:self::decimal($in['quantity'],false);if(($log===null)!==($quantity===null))throw new FieldProblem(422,'Indique atividade e quantidade juntas, ou registe um adiantamento.');
                if($log){$a=$this->db->table('agri_harvest_allocations a')->join('agri_work_logs l','l.id=a.work_log_id')->where('a.work_log_id',$log)->where('a.worker_id',$worker)->where('l.harvest_id',$id)->get()->getRowArray();if(!$a)throw new FieldProblem(422,'Produção não pertence ao trabalhador nesta colheita.');$used=$this->db->table('agri_harvest_movements')->selectSum('quantity','total')->where('work_log_id',$log)->where('worker_id',$worker)->where('kind','payment')->get()->getRowArray()['total']??'0';if(BigDecimal::of($used)->plus($quantity)->isGreaterThan($a['quantity']))throw new FieldProblem(422,'Quantidade superior ao saldo da atividade.');$earned=$this->db->table('agri_harvest_allocations a')->selectSum('a.amount_cents','total')->join('agri_work_logs l','l.id=a.work_log_id')->where('l.harvest_id',$id)->where('a.worker_id',$worker)->get()->getRowArray()['total']??0;$paid=$this->db->table('agri_harvest_movements')->selectSum('amount_cents','total')->where('harvest_id',$id)->where('worker_id',$worker)->where('kind','payment')->get()->getRowArray()['total']??0;if((int)$paid+$cents>(int)$earned)throw new FieldProblem(422,'O pagamento excede o saldo após os adiantamentos.');$expected=ProductionBook::cents((string)BigDecimal::of($quantity)->multipliedBy($h['payment_rate']));if(abs($expected-$cents)>1)throw new FieldProblem(422,'O valor não corresponde à quantidade e tarifa.');}
            }
            if($kind==='receipt'){$delivery=empty($in['delivery_id'])?null:(int)$in['delivery_id'];$quantity=empty($in['quantity'])?null:self::decimal($in['quantity'],false);if(($delivery===null)!==($quantity===null))throw new FieldProblem(422,'Indique entrega e quantidade juntas, ou registe um adiantamento.');if($delivery){$d=$this->record('harvest_deliveries',$delivery);if((int)$d['harvest_id']!==$id)throw new FieldProblem(422,'Entrega de outra colheita.');$used=$this->db->table('agri_harvest_movements')->selectSum('quantity','total')->where('delivery_id',$delivery)->where('kind','receipt')->get()->getRowArray()['total']??'0';if(BigDecimal::of($used)->plus($quantity)->isGreaterThan($d['billing_quantity']))throw new FieldProblem(422,'Quantidade superior ao saldo da entrega.');if(ProductionBook::cents((string)BigDecimal::of($quantity)->multipliedBy($h['billing_rate']))!==$cents)throw new FieldProblem(422,'O valor não corresponde à quantidade e tarifa.');}}
            $data=['harvest_id'=>$id,'kind'=>$kind,'worker_id'=>$worker,'delivery_id'=>$delivery,'work_log_id'=>$log,'quantity'=>$quantity,'amount_cents'=>$cents,'paid_on'=>$date,'reference'=>$this->text($in['reference']??'Movimento'),'created_by'=>auth()->id(),'created_at'=>gmdate('Y-m-d H:i:s'),'request_id'=>$request,'request_hash'=>$hash];$this->db->table('agri_harvest_movements')->insert($data);return ['item'=>$this->record('harvest_movements',(int)$this->db->insertID())];});
    }
    public function detail(int $id): array
    {
        $h=$this->access($id);foreach(['client'=>'clients','site'=>'sites','service'=>'services','payment_unit'=>'units','billing_unit'=>'units']as$key=>$table)$h[$key.'_name']=$this->record($table,(int)$h[$key.'_id'])['name'];
        $logs=$this->db->table('agri_work_logs')->where('harvest_id',$id)->orderBy('work_date')->orderBy('id')->get()->getResultArray();if(!self::admin()&&!FieldAccess::manager())$logs=array_values(array_filter($logs,fn($l)=>(int)$l['controller_id']===(int)auth()->id()));$h['activities']=array_map(fn($l)=>self::operational((new DraftBook($this->db))->get((int)$l['id'])),$logs);
        if(!self::admin()){unset($h['billing_rate'],$h['payment_rate'],$h['conversion_factor']);return $h;}
        $h['deliveries']=$this->db->table('agri_harvest_deliveries')->where('harvest_id',$id)->orderBy('id')->get()->getResultArray();
        $h['movements']=$this->db->table('agri_harvest_movements m')->select('m.*,w.name AS worker_name')->join('agri_workers w','w.id=m.worker_id','left')->where('m.harvest_id',$id)->orderBy('m.paid_on')->orderBy('m.id')->get()->getResultArray();
        $h['workers']=$this->db->table('agri_harvest_allocations a')->select('a.*,w.name AS worker_name,l.work_date')->join('agri_workers w','w.id=a.worker_id')->join('agri_work_logs l','l.id=a.work_log_id')->where('l.harvest_id',$id)->orderBy('l.work_date')->get()->getResultArray();
        $s=['produced'=>'0','delivered'=>'0','weighed'=>'0','paid_quantity'=>'0','received_quantity'=>'0','payable_cents'=>0,'receivable_cents'=>0,'paid_cents'=>0,'received_cents'=>0,'expense_cents'=>0];
        foreach($h['workers']as$a){$s['produced']=(string)BigDecimal::of($s['produced'])->plus($a['quantity']);$s['payable_cents']+=(int)$a['amount_cents'];}
        foreach($h['deliveries']as$d){$s['delivered']=(string)BigDecimal::of($s['delivered'])->plus($d['payment_quantity']);$s['weighed']=(string)BigDecimal::of($s['weighed'])->plus($d['billing_quantity']);$s['receivable_cents']+=ProductionBook::cents((string)BigDecimal::of($d['billing_quantity'])->multipliedBy($h['billing_rate']));}
        foreach($h['movements']as$m){$key=['receipt'=>'received_cents','payment'=>'paid_cents','expense'=>'expense_cents'][$m['kind']];$s[$key]+=(int)$m['amount_cents'];if($m['quantity']!==null&&$m['kind']!=='expense'){$q=$m['kind']==='receipt'?'received_quantity':'paid_quantity';$s[$q]=(string)BigDecimal::of($s[$q])->plus($m['quantity']);}}
        $s['cash_cents']=$s['received_cents']-$s['paid_cents']-$s['expense_cents'];$s['margin_cents']=$s['receivable_cents']-$s['payable_cents']-$s['expense_cents'];$s['pending_payment_cents']=$s['payable_cents']-$s['paid_cents'];$s['pending_receipt_cents']=$s['receivable_cents']-$s['received_cents'];
        $s['expected']=$h['conversion_factor']?(string)BigDecimal::of($s['delivered'])->multipliedBy($h['conversion_factor']):null;$s['difference']=$s['expected']!==null?(string)BigDecimal::of($s['weighed'])->minus($s['expected']):null;$s['actual_ratio']=BigDecimal::of($s['delivered'])->isGreaterThan(0)?(string)BigDecimal::of($s['weighed'])->dividedBy($s['delivered'],4,RoundingMode::HALF_UP):null;$h['summary']=$s;return $h;
    }
    public function close(int $id,array $in): array
    {
        self::requireAdmin();return $this->tx(function()use($id,$in){$h=$this->lock($id);if(!is_int($in['version']??null)||(int)$h['version']!==$in['version']||$h['status']!=='active')throw new FieldProblem(409,'Colheita alterada ou encerrada.');$end=self::date($in['ends_on']??null);if($end<$h['starts_on'])throw new FieldProblem(422,'Data final inválida.');$logs=$this->db->table('agri_work_logs')->where('harvest_id',$id)->get()->getResultArray();$has=false;foreach($logs as$l){if($l['status']==='canceled')continue;$has=true;if($l['status']!=='closed'||$l['work_date']>$end)throw new FieldProblem(422,'Valide todas as atividades e confira a data final.');}if(!$has)throw new FieldProblem(422,'Sem atividades validadas.');$s=$this->detail($id)['summary'];if(BigDecimal::of($s['produced'])->compareTo($s['delivered'])!==0)throw new FieldProblem(422,'Associe toda a produção às entregas antes do fechamento.');$this->db->table('agri_harvests')->where('id',$id)->update(['status'=>'closed','ends_on'=>$end,'version'=>$in['version']+1]);return ['item'=>$this->detail($id)];});
    }
}
