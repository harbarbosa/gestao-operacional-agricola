<?php
namespace App\Finance;
use App\Field\DraftBook;
use App\Field\FieldAccess;
use App\Field\FieldProblem;
use App\Pricing\PriceBook;
use App\Pricing\PriceProblem;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;

class ProductionBook
{
    public function __construct(private \CodeIgniter\Database\BaseConnection $db) {}
    private function lock(string $table,int $id,string $column='id'): ?array
    {
        return $this->db->query('SELECT * FROM '.$this->db->escapeIdentifiers($this->db->prefixTable($table)).' WHERE '.$column.' = ?'.($this->db->getPlatform()==='MySQLi'?' FOR UPDATE':''),[$id])->getRowArray();
    }
    private function transaction(callable $operation)
    {
        $this->db->transBegin();
        try { $result=$operation(); if(!$this->db->transStatus() || !$this->db->transCommit()) throw new \RuntimeException('Persistence failure'); return $result; }
        catch(\Throwable $e) { $this->db->transRollback(); if($e instanceof FieldProblem) throw $e; if($e instanceof PriceProblem) throw new FieldProblem($e->status,$e->getMessage()); log_message('error','Falha no fecho de produção: {type}',['type'=>get_class($e)]); throw new FieldProblem(409,'Não foi possível confirmar a operação. Recarregue antes de repetir.'); }
    }
    public static function cents(string $amount): int
    {
        $value=BigDecimal::of($amount)->toScale(2,RoundingMode::HALF_UP)->getUnscaledValue();
        if($value->isLessThan(0) || $value->isGreaterThan('9000000000000000')) throw new FieldProblem(422,'Valor fora do limite permitido.');
        return $value->toInt();
    }
    public static function divide(int $cents,array $workers): array
    {
        sort($workers,SORT_NUMERIC); if(count($workers)<2 || count(array_unique($workers))!==count($workers)) throw new FieldProblem(422,'Um grupo precisa de pelo menos dois trabalhadores diferentes.');
        $base=intdiv($cents,count($workers)); $remaining=$cents%count($workers); $result=[];
        foreach($workers as $index=>$worker) $result[$worker]=$base+($index<$remaining?1:0);
        return $result;
    }
    private static function version(array $input): int
    {
        if(array_diff(array_keys($input),['version','preview_hash']) || !is_string($input['preview_hash']??null) || !preg_match('/^[a-f0-9]{64}$/D',$input['preview_hash']) || !is_int($input['version']??null) || $input['version']<1) throw new FieldProblem(422,'Indique a versão atual.'); return $input['version'];
    }
    private function plan(array $log): array
    {
        $present=array_values(array_filter($log['attendees'],fn($row)=>$row['present']));
        if(!$present) throw new FieldProblem(422,'Adicione pelo menos um trabalhador presente.');
        foreach($present as $row) if(!$row['score'] || !$row['time_in'] || !$row['time_out']) throw new FieldProblem(422,'Complete entrada, saída e avaliação de todos os trabalhadores presentes.');
        $plans=[]; $prices=new PriceBook($this->db);
        foreach($present as $row) if(!$row['pool_key']) {
            if($row['payment_quantity']===null) throw new FieldProblem(422,'Indique a produção individual ou associe o trabalhador a um grupo.');
            $quote=$prices->preview(['service_id'=>$log['service_id'],'kind'=>'payment','worker_id'=>$row['worker_id'],'date'=>$log['work_date'],'quantity'=>$row['payment_quantity']]);
            if($quote['price']['unit_id']!==$log['payment_unit_id']) throw new FieldProblem(409,'A unidade da tarifa difere da unidade desta atividade.');
            $plans[]=['worker_id'=>$row['worker_id'],'worker_name'=>$row['worker_name'],'pool_key'=>null,'pool_name'=>null,'quantity'=>$row['payment_quantity'],'unit_name'=>$log['payment_unit_name'],'price_id'=>$quote['price']['id'],'rate'=>$quote['price']['amount'],'amount_cents'=>self::cents($quote['total'])];
        }
        foreach($log['pools'] as $pool) {
            $members=array_values(array_filter($present,fn($row)=>$row['pool_key']===$pool['pool_key']));
            $quote=$prices->preview(['service_id'=>$log['service_id'],'kind'=>'payment','date'=>$log['work_date'],'quantity'=>(string)$pool['quantity']]);
            if($quote['price']['unit_id']!==$log['payment_unit_id']) throw new FieldProblem(409,'A unidade da tarifa do grupo difere da atividade.');
            $split=self::divide(self::cents($quote['total']),array_column($members,'worker_id'));
            foreach($members as $row) $plans[]=['worker_id'=>$row['worker_id'],'worker_name'=>$row['worker_name'],'pool_key'=>$pool['pool_key'],'pool_name'=>$pool['name'],'quantity'=>null,'unit_name'=>$log['payment_unit_name'],'price_id'=>$quote['price']['id'],'rate'=>$quote['price']['amount'],'amount_cents'=>$split[$row['worker_id']]];
        }
        usort($plans,fn($a,$b)=>$a['worker_id']<=>$b['worker_id']);
        return $plans;
    }
    public function preview(int $id): array
    {
        $log=(new DraftBook($this->db))->get($id); FieldAccess::checkLog($log); if(!\App\Harvest\HarvestBook::admin()) throw new FieldProblem(403,'Fecho financeiro reservado à administração.');
        if($log['status']!=='draft') throw new FieldProblem(409,'A atividade já foi fechada ou cancelada.');
        $plans=$this->plan($log); return ['item'=>$log,'allocations'=>$plans,'preview_hash'=>hash('sha256',json_encode([$log['version'],$plans]))];
    }
    public function close(int $id,array $input,int $actor): array
    {
        $version=self::version($input);
        return $this->transaction(function()use($id,$version,$actor,$input){
            $row=$this->lock('agri_work_logs',$id); if(!$row) throw new FieldProblem(404,'Atividade não encontrada.'); FieldAccess::checkLog($row); if(!\App\Harvest\HarvestBook::admin() || !empty($row['harvest_id'])) throw new FieldProblem(403,'Utilize a validação da colheita.');
            if($row['status']==='closed' && (int)$row['version']===$version+1 && (int)$row['closed_by']===$actor && hash_equals($row['closure_hash']??'', $input['preview_hash'])) return ['item'=>(new DraftBook($this->db))->get($id),'replayed'=>true];
            if($row['status']!=='draft' || (int)$row['version']!==$version) throw new FieldProblem(409,'Atividade alterada, fechada ou cancelada.');
            $log=(new DraftBook($this->db))->get($id); $plans=$this->plan($log); if(!hash_equals(hash('sha256',json_encode([$log['version'],$plans])),$input['preview_hash'])) throw new FieldProblem(409,'Os valores mudaram. Reveja o fecho antes de confirmar.'); foreach($plans as $plan) $this->lock('agri_workers',(int)$plan['worker_id']); $now=gmdate('Y-m-d H:i:s');
            foreach($plans as $plan) { unset($plan['worker_name'],$plan['pool_name']); $this->db->table('agri_earnings')->insert(['work_log_id'=>$id,'created_at'=>$now]+$plan); }
            $this->db->table('agri_work_logs')->where('id',$id)->update(['closure_hash'=>$input['preview_hash'],'status'=>'closed','closed_at'=>$now,'closed_by'=>$actor,'updated_by'=>$actor,'updated_at'=>$now,'version'=>$version+1]);
            $after=(new DraftBook($this->db))->get($id);
            $this->db->table('agri_work_log_audit')->insert(['work_log_id'=>$id,'actor_id'=>$actor,'action'=>'close','before_json'=>json_encode($log),'after_json'=>json_encode($after),'created_at'=>$now]);
            return ['item'=>$after,'replayed'=>false];
        });
    }
    private static function date($date): string
    {
        $parsed=is_string($date)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)?\DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if(!$parsed || $parsed->format('Y-m-d')!==$date) throw new FieldProblem(422,'Data inválida.'); return $date;
    }
    private static function request(array $input): string
    {
        $request=$input['request_id']??null;
        if(!is_string($request)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$request)) throw new FieldProblem(422,'Identificador de operação inválido.'); return $request;
    }
    public function pending(int $worker,string $from,string $to): array
    {
        self::date($from); self::date($to); if($from>$to) throw new FieldProblem(422,'Período inválido.');
        return $this->db->table('agri_earnings e')->select('e.*, l.work_date, l.service_name, l.team_name, l.client_name, p.name AS pool_name')
            ->join('agri_work_logs l','l.id=e.work_log_id')->join('agri_settlement_items i','i.earning_id=e.id','left')->join('agri_work_pools p','p.work_log_id=e.work_log_id AND p.pool_key=e.pool_key','left')
            ->where('e.worker_id',$worker)->where('l.work_date >=',$from)->where('l.work_date <=',$to)->where('i.earning_id',null)->orderBy('l.work_date')->orderBy('e.id')->get()->getResultArray();
    }
    public static function pendingHash(int $worker,string $from,string $to,array $items): string { return hash('sha256',json_encode([$worker,$from,$to,array_map(fn($item)=>[(int)$item['id'],(string)$item['amount_cents']],$items)])); }
    public function settle(array $input,int $actor): array
    {
        if(array_diff(array_keys($input),['worker_id','date_from','date_to','request_id','preview_hash'])) throw new FieldProblem(422,'Campos não permitidos.');
        $worker=$input['worker_id']??null; if(!is_int($worker)||$worker<1) throw new FieldProblem(422,'Selecione um trabalhador.');
        $from=self::date($input['date_from']??null); $to=self::date($input['date_to']??null); if($from>$to) throw new FieldProblem(422,'Período inválido.');
        $request=self::request($input); $preview=$input['preview_hash']??''; if(!is_string($preview)) throw new FieldProblem(422,'Confirme a prévia da produção.'); $hash=hash('sha256',json_encode([$worker,$from,$to,$actor,$preview]));
        return $this->transaction(function()use($worker,$from,$to,$actor,$request,$hash,$preview){
            if(!$this->lock('agri_workers',$worker)) throw new FieldProblem(404,'Trabalhador inexistente.');
            $existing=$this->db->table('agri_settlements')->where('request_id',$request)->get()->getRowArray();
            if($existing) { if(!hash_equals($existing['request_hash'],$hash)) throw new FieldProblem(409,'Identificador já utilizado.'); return ['item'=>$this->settlement((int)$existing['id']),'replayed'=>true]; }
            $drafts=$this->db->table('agri_work_logs l')->join('agri_work_log_attendees a','a.work_log_id=l.id')->where('a.worker_id',$worker)->where('a.present',1)->where('l.work_date >=',$from)->where('l.work_date <=',$to)->where('l.status','draft')->countAllResults();
            if($drafts) throw new FieldProblem(409,'Existem atividades por fechar para este trabalhador no período.');
            $items=$this->pending($worker,$from,$to); if(!hash_equals(self::pendingHash($worker,$from,$to,$items),$preview)) throw new FieldProblem(409,'A produção mudou. Consulte novamente antes de fechar.'); if(!$items) throw new FieldProblem(422,'Sem produção pendente de fecho neste período.');
            $total=BigInteger::zero(); foreach($items as $item) $total=$total->plus($item['amount_cents']);
            if($total->isGreaterThan('9000000000000000')) throw new FieldProblem(422,'Total fora do limite permitido.');
            $this->db->table('agri_settlements')->insert(['worker_id'=>$worker,'date_from'=>$from,'date_to'=>$to,'total_cents'=>$total->toInt(),'paid_cents'=>0,'version'=>1,'created_by'=>$actor,'created_at'=>gmdate('Y-m-d H:i:s'),'request_id'=>$request,'request_hash'=>$hash]);
            $id=(int)$this->db->insertID(); foreach($items as $item) $this->db->table('agri_settlement_items')->insert(['settlement_id'=>$id,'earning_id'=>$item['id']]);
            return ['item'=>$this->settlement($id),'replayed'=>false];
        });
    }
    public function settlement(int $id): array
    {
        $item=$this->db->table('agri_settlements s')->select('s.*, w.name AS worker_name')->join('agri_workers w','w.id=s.worker_id')->where('s.id',$id)->get()->getRowArray();
        if(!$item) throw new FieldProblem(404,'Fecho não encontrado.'); unset($item['request_hash']);
        $item['items']=$this->db->table('agri_settlement_items i')->select('e.*,l.work_date,l.service_name,l.client_name,p.name AS pool_name')->join('agri_earnings e','e.id=i.earning_id')->join('agri_work_logs l','l.id=e.work_log_id')->join('agri_work_pools p','p.work_log_id=e.work_log_id AND p.pool_key=e.pool_key','left')->where('i.settlement_id',$id)->get()->getResultArray();
        $item['payments']=$this->db->table('agri_payment_entries')->select('id,amount_cents,paid_on,reference,notes,created_by,created_at')->where('settlement_id',$id)->orderBy('id')->get()->getResultArray(); return $item;
    }
    public function pay(int $id,array $input,int $actor): array
    {
        if(array_diff(array_keys($input),['request_id','version','amount','paid_on','reference','notes'])) throw new FieldProblem(422,'Campos não permitidos.');
        $request=self::request($input); $date=self::date($input['paid_on']??null);
        try { $decimal=PriceBook::decimal($input['amount']??null,'amount'); try { BigDecimal::of($decimal)->toScale(2,RoundingMode::UNNECESSARY); } catch(\Throwable $e) { throw new FieldProblem(422,'O pagamento deve ter no máximo duas casas decimais.'); } $amount=self::cents($decimal); } catch(PriceProblem $e) { throw new FieldProblem(422,'Valor inválido.'); }
        if($amount<1 || !is_int($input['version']??null)) throw new FieldProblem(422,'Indique um valor positivo e a versão atual.');
        foreach(['reference'=>160,'notes'=>500] as $key=>$max) if(isset($input[$key]) && (!is_string($input[$key])||mb_strlen($input[$key])>$max)) throw new FieldProblem(422,'Referência ou observação inválida.');
        $hash=hash('sha256',json_encode([$id,$amount,$date,$input['reference']??null,$input['notes']??null,$actor]));
        return $this->transaction(function()use($id,$input,$request,$date,$amount,$actor,$hash){
            $row=$this->lock('agri_settlements',$id); if(!$row) throw new FieldProblem(404,'Fecho não encontrado.');
            $old=$this->db->table('agri_payment_entries')->where('request_id',$request)->get()->getRowArray();
            if($old) { if(!hash_equals($old['request_hash'],$hash)) throw new FieldProblem(409,'Identificador já utilizado.'); return ['item'=>$this->settlement($id),'replayed'=>true]; }
            if((int)$row['version']!==$input['version']) throw new FieldProblem(409,'Fecho alterado. Reabra o registo.');
            if($amount>(int)$row['total_cents']-(int)$row['paid_cents']) throw new FieldProblem(422,'Pagamento superior ao saldo pendente.');
            $this->db->table('agri_payment_entries')->insert(['settlement_id'=>$id,'amount_cents'=>$amount,'paid_on'=>$date,'reference'=>$input['reference']??null,'notes'=>$input['notes']??null,'created_by'=>$actor,'created_at'=>gmdate('Y-m-d H:i:s'),'request_id'=>$request,'request_hash'=>$hash]);
            $this->db->table('agri_settlements')->where('id',$id)->update(['paid_cents'=>(int)$row['paid_cents']+$amount,'version'=>(int)$row['version']+1]);
            return ['item'=>$this->settlement($id),'replayed'=>false];
        });
    }
}
