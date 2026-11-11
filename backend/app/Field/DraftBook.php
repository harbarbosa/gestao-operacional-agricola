<?php

namespace App\Field;

use App\Pricing\PriceBook;
use App\Pricing\PriceProblem;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use Throwable;

class DraftBook
{
    public function __construct(private BaseConnection $db) {}

    private static function keys(array $data, array $keys): void
    {
        if (array_is_list($data) || array_diff(array_keys($data), $keys)) { throw new FieldProblem(422, 'Campos não permitidos.'); }
    }
    private static function id(mixed $value): int
    {
        if (! is_int($value) || $value < 1) { throw new FieldProblem(422, 'Referência ou versão inválida.'); }
        return $value;
    }
    private static function text(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') { return null; }
        if (! is_string($value) || mb_strlen($value) > $max) { throw new FieldProblem(422, 'Texto inválido ou demasiado longo.'); }
        return trim($value) === '' ? null : trim($value);
    }
    private static function quantity(mixed $value): ?string
    {
        if ($value === null) { return null; }
        try { return PriceBook::decimal($value, 'quantity'); }
        catch (PriceProblem $error) { throw new FieldProblem(422, 'Quantidade inválida: use texto decimal, até 8 inteiros e 4 casas.'); }
    }
    private static function date(mixed $value): string
    {
        $date = is_string($value) && preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (! $date || $date->format('Y-m-d') !== $value) { throw new FieldProblem(422, 'Data de trabalho inválida.'); }
        return $value;
    }
    private function attendees(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > 100) { throw new FieldProblem(422, 'Indique até 100 participantes.'); }
        $rows = []; $seen = [];
        foreach ($value as $row) {
            if (! is_array($row)) { throw new FieldProblem(422, 'Participante inválido.'); }
            self::keys($row, ['worker_id', 'present', 'time_in', 'time_out', 'exit_next_day', 'payment_quantity', 'score', 'notes', 'pool_key']);
            $id = self::id($row['worker_id'] ?? null);
            if (isset($seen[$id])) { throw new FieldProblem(422, 'O mesmo trabalhador não pode aparecer duas vezes no diário.'); }
            $seen[$id] = true;
            $present = array_key_exists('present', $row) ? $row['present'] : true; $nextDay = array_key_exists('exit_next_day', $row) ? $row['exit_next_day'] : false;
            if (! is_bool($present) || ! is_bool($nextDay)) { throw new FieldProblem(422, 'Presença e saída no dia seguinte devem ser booleanos.'); }
            $in = $row['time_in'] ?? null; $out = $row['time_out'] ?? null;
            foreach ([$in, $out] as $time) {
                if ($time !== null && (! is_string($time) || ! preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time))) { throw new FieldProblem(422, 'Use horários válidos no formato HH:MM.'); }
            }
            $quantity = self::quantity($row['payment_quantity'] ?? null);
            $score = $row['score'] ?? null;
            if ($score !== null && (! is_int($score) || $score < 1 || $score > 5)) { throw new FieldProblem(422, 'A avaliação deve estar entre 1 e 5.'); }
            if (! $present && ($in !== null || $out !== null || $quantity !== null || $score !== null || $nextDay)) { throw new FieldProblem(422, 'Ausência não pode ter horário, quantidade ou avaliação.'); }
            if (($out !== null && $in === null) || ($nextDay && ($in === null || $out === null))) { throw new FieldProblem(422, 'Complete a entrada antes de registar saída ou turno noturno.'); }
            if ($in !== null && $out !== null && ((! $nextDay && $out <= $in) || ($nextDay && $out > $in))) { throw new FieldProblem(422, 'Reveja os horários e a indicação de saída no dia seguinte (máximo 24 horas).'); }
            $pool = self::text($row['pool_key'] ?? null,32); if ($pool !== null && (!preg_match('/^[a-zA-Z0-9_-]+$/D',$pool) || !$present || $quantity !== null)) throw new FieldProblem(422,'No trabalho em grupo, registe a produção no grupo, sem quantidade individual.');
            $rows[] = ['pool_key'=>$pool, 'worker_id' => $id, 'present' => $present, 'time_in' => $in, 'time_out' => $out, 'exit_next_day' => $nextDay, 'payment_quantity' => $quantity, 'score' => $score, 'notes' => self::text($row['notes'] ?? null, 500)];
        }
        usort($rows, static fn ($a, $b) => $a['worker_id'] <=> $b['worker_id']);
        return $rows;
    }
    private function contents(array $data): array
    {
        if(!is_array($data['pools']??[]) || !array_is_list($data['pools']??[]) || count($data['pools']??[])>50) throw new FieldProblem(422,'Indique até 50 grupos.');
        $pools=[]; foreach(($data['pools'] ?? []) as $pool) { if (!is_array($pool)) throw new FieldProblem(422,'Grupo inválido.'); self::keys($pool,['pool_key','name','quantity']); $key=self::text($pool['pool_key'] ?? null,32); $name=self::text($pool['name'] ?? null,100); $quantity=self::quantity($pool['quantity'] ?? null); if (!$key || !preg_match('/^[a-zA-Z0-9_-]+$/D',$key) || !$name || $quantity===null || isset($pools[$key])) throw new FieldProblem(422,'Complete nome e produção dos grupos, sem duplicar identificadores.'); $pools[$key]=['pool_key'=>$key,'name'=>$name,'quantity'=>$quantity]; }
        $attendees=$this->attendees($data['attendees'] ?? null); foreach($attendees as $row) if ($row['pool_key']!==null && !isset($pools[$row['pool_key']])) throw new FieldProblem(422,'Grupo do participante inexistente.');
        return ['pools'=>array_values($pools), 'notes' => self::text($data['notes'] ?? null, 2000), 'billing_quantity' => self::quantity($data['billing_quantity'] ?? null), 'attendees' => $attendees];
    }
    private function record(string $table, int $id, bool $active = true): array
    {
        $row = $this->db->table('agri_' . $table)->where('id', $id)->get()->getRowArray();
        if (! $row || ($active && ! $row['is_active'])) { throw new FieldProblem(422, 'Selecione um registo existente e ativo: ' . $table . '.'); }
        return $row;
    }
    private function lock(string $table, int $id): ?array
    {
        $name = $this->db->escapeIdentifiers($this->db->prefixTable($table));
        return $this->db->query('SELECT * FROM ' . $name . ' WHERE id = ?' . ($this->db->getPlatform() === 'MySQLi' ? ' FOR UPDATE' : ''), [$id])->getRowArray();
    }
    private function replay(string $request, string $hash, int $actor): ?array
    {
        $row = $this->db->table('agri_work_logs')->where('request_id', $request)->get()->getRowArray();
        if (! $row) { return null; }
        if ((int) $row['created_by'] !== $actor || ! hash_equals($row['creation_hash'], $hash)) { throw new FieldProblem(409, 'Este identificador de envio já foi usado com outros dados. Consulte o diário antes de repetir.'); }
        return ['item' => $this->get((int) $row['id']), 'replayed' => true];
    }
    public function create(array $input, int $actor): array
    {
        if (!empty($input['harvest_id'])) $input=(new \App\Harvest\HarvestBook($this->db))->activityInput($input);
        self::keys($input, ['harvest_id', 'controller_id', 'request_id', 'work_date', 'team_id', 'site_id', 'client_id', 'service_id', 'service_version', 'notes', 'billing_quantity', 'attendees', 'pools']);
        $request = $input['request_id'] ?? null;
        if (! is_string($request) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $request)) { throw new FieldProblem(422, 'Identificador UUID de envio inválido.'); }
        $data = $this->contents($input) + [
            'work_date' => self::date($input['work_date'] ?? null), 'team_id' => !empty($input['harvest_id']) ? null : self::id($input['team_id'] ?? null),
            'site_id' => self::id($input['site_id'] ?? null), 'client_id' => self::id($input['client_id'] ?? null), 'service_id' => self::id($input['service_id'] ?? null), 'service_version' => self::id($input['service_version'] ?? null),
        ];
        if (!empty($input['harvest_id'])) $data += ['harvest_id'=>(int)$input['harvest_id'],'controller_id'=>(int)$input['controller_id']];
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        if ($existing = $this->replay($request, $hash, $actor)) { return $existing; }
        return $this->transaction(function () use ($data, $request, $hash, $actor) {
            $service = $this->lock('agri_services', $data['service_id']);
            if ($existing = $this->replay($request, $hash, $actor)) { return $existing; }
            if (! $service || ! $service['is_active']) { throw new FieldProblem(422, 'Serviço inexistente ou inativo.'); }
            if ((int) $service['version'] !== $data['service_version']) { throw new FieldProblem(409, 'O serviço foi alterado. Confirme as unidades e as quantidades antes de voltar a enviar.'); }
            $team = $data['team_id'] ? $this->record('teams', $data['team_id']) : ['name'=>'Atividade diária','responsible_name'=>auth()->getProvider()->findById($data['controller_id'])->username]; $site = $this->record('sites', $data['site_id']);
            if ((int) $site['client_id'] !== $data['client_id']) { throw new FieldProblem(409, 'O local não pertence ao cliente indicado. Reveja a seleção.'); }
            $client = $this->record('clients', (int) $site['client_id']);
            $rows = $this->validateWorkers($data['attendees'], $data['team_id']);
            $header = $data; unset($header['attendees'], $header['service_version'], $header['pools']);
            foreach (['payment', 'billing'] as $kind) {
                $unitId=!empty($data['harvest_id']) ? $this->db->table('agri_harvests')->where('id',$data['harvest_id'])->get()->getRowArray()[$kind.'_unit_id'] : $service[$kind.'_unit_id'];
                $unit = $this->record('units', (int)$unitId);
                $header[$kind . '_unit_id'] = (int) $unit['id']; $header[$kind . '_unit_name'] = $unit['name']; $header[$kind . '_unit_code'] = $unit['code'];
            }
            $now = gmdate('Y-m-d H:i:s');
            $header += ['request_id' => $request, 'creation_hash' => $hash, 'client_id' => $client['id'], 'team_name' => $team['name'], 'responsible_name' => $team['responsible_name'], 'site_name' => $site['name'], 'client_name' => $client['name'], 'service_name' => $service['name'], 'status' => 'draft', 'version' => 1, 'created_by' => $actor, 'updated_by' => $actor, 'created_at' => $now, 'updated_at' => $now];
            $this->db->table('agri_work_logs')->insert($header); $id = (int) $this->db->insertID();
            $this->replaceAttendees($id, $rows); $this->replacePools($id,$data['pools']);
            $after = $this->get($id); $this->audit($id, $actor, 'create', null, $after);
            return ['item' => $after, 'replayed' => false];
        });
    }
    private function validateWorkers(array $rows, ?int $team, array $old = []): array
    {
        $previous = array_column($old, null, 'worker_id');
        foreach ($rows as &$row) {
            $worker=$this->lock('agri_workers',$row['worker_id']); if(!$worker || (!isset($previous[$row['worker_id']]) && !$worker['is_active'])) throw new FieldProblem(422,'Trabalhador inexistente ou inativo.');
            if ($team !== null && ! isset($previous[$row['worker_id']]) && (int) $worker['team_id'] !== $team) { $this->db->table('agri_workers')->where('id',$worker['id'])->set('version','version + 1',false)->update(['team_id'=>$team,'updated_at'=>gmdate('Y-m-d H:i:s')]); $after=$this->db->table('agri_workers')->where('id',$worker['id'])->get()->getRowArray(); $this->db->table('agri_catalog_audit')->insert(['resource'=>'workers','record_id'=>$worker['id'],'actor_id'=>auth()->id(),'action'=>'associate','before_json'=>json_encode($worker),'after_json'=>json_encode($after),'created_at'=>gmdate('Y-m-d H:i:s')]); }
            if($worker['is_blocked'] && $row['present']) throw new FieldProblem(422,'Trabalhador bloqueado: '.$worker['name'].'. '.$worker['block_reason']);
            $row['worker_name'] = $previous[$row['worker_id']]['worker_name'] ?? $worker['name'];
        }
        return $rows;
    }
    private function replacePools(int $id,array $pools): void
    {
        $this->db->table('agri_work_pools')->where('work_log_id',$id)->delete();
        foreach($pools as $pool) $this->db->table('agri_work_pools')->insert(['work_log_id'=>$id]+$pool);
    }
    private function replaceAttendees(int $id, array $rows): void
    {
        $this->db->table('agri_work_log_attendees')->where('work_log_id', $id)->delete();
        foreach ($rows as $row) { $this->db->table('agri_work_log_attendees')->insert($row + ['work_log_id' => $id]); }
    }
    public function update(int $id, array $input, int $actor, bool $cancel = false): array
    {
        self::keys($input, $cancel ? ['version', 'reason'] : ['version', 'notes', 'billing_quantity', 'attendees', 'pools']);
        $version = self::id($input['version'] ?? null);
        $content = $cancel ? ['cancel_reason' => self::text($input['reason'] ?? null, 500)] : $this->contents($input);
        if ($cancel && mb_strlen($content['cancel_reason'] ?? '') < 3) { throw new FieldProblem(422, 'Indique o motivo do cancelamento (3 a 500 caracteres).'); }
        return $this->transaction(function () use ($id, $input, $content, $version, $actor, $cancel) {
            if (! $this->lock('agri_work_logs', $id)) { throw new FieldProblem(404, 'Diário não encontrado.'); }
            $before = $this->get($id);
            if (!empty($before['harvest_id'])) { $h=(new \App\Harvest\HarvestBook($this->db))->access((int)$before['harvest_id']); if($h['status']!=='active')throw new FieldProblem(409,'Colheita encerrada.'); }
            if ($before['version'] !== $version || $before['status'] !== 'draft') { throw new FieldProblem(409, 'O diário foi alterado ou cancelado. Recarregue antes de editar.'); }
            $data = $content;
            if ($cancel) { $data['status'] = 'canceled'; }
            else { unset($data['attendees'], $data['pools']); $rows = $this->validateWorkers($content['attendees'], $before['team_id'], $before['attendees']); }
            $data += ['version' => $version + 1, 'updated_by' => $actor, 'updated_at' => gmdate('Y-m-d H:i:s')];
            $this->db->table('agri_work_logs')->where('id', $id)->where('version', $version)->update($data);
            if ($this->db->affectedRows() !== 1) { throw new FieldProblem(409, 'O diário foi alterado.'); }
            if (! $cancel) { $this->replaceAttendees($id, $rows); $this->replacePools($id,$content['pools']); }
            $after = $this->get($id); $this->audit($id, $actor, $cancel ? 'cancel' : 'update', $before, $after);
            return ['item' => $after];
        });
    }
    public function get(int $id): array
    {
        $row = $this->db->table('agri_work_logs')->where('id', $id)->get()->getRowArray();
        if (! $row) { throw new FieldProblem(404, 'Diário não encontrado.'); }
        unset($row['creation_hash']);
        $row = $this->normalize($row);
        $rows = $this->db->table('agri_work_log_attendees')->where('work_log_id', $id)->orderBy('worker_id')->get()->getResultArray();
        foreach ($rows as &$attendee) {
            $attendee['worker_id'] = (int) $attendee['worker_id']; unset($attendee['work_log_id']);
            $attendee['present'] = (bool) $attendee['present']; $attendee['exit_next_day'] = (bool) $attendee['exit_next_day'];
            $attendee['score'] = $attendee['score'] === null ? null : (int) $attendee['score'];
            $attendee['payment_quantity'] = $attendee['payment_quantity'] === null ? null : PriceBook::decimal((string) $attendee['payment_quantity'], 'quantity');
        }
        $row['pools'] = $this->db->table('agri_work_pools')->where('work_log_id',$id)->orderBy('pool_key')->get()->getResultArray();
        $row['earnings'] = $this->db->table('agri_earnings')->where('work_log_id',$id)->orderBy('worker_id')->get()->getResultArray();
        $row['attendees'] = $rows;
        return $row;
    }
    public function normalize(array $row): array
    {
        unset($row['creation_hash']);
        foreach (['id', 'team_id', 'site_id', 'client_id', 'service_id', 'payment_unit_id', 'billing_unit_id', 'version', 'created_by', 'updated_by'] as $key) { $row[$key] = $row[$key]===null ? null : (int) $row[$key]; }
        $row['billing_quantity'] = $row['billing_quantity'] === null ? null : PriceBook::decimal((string) $row['billing_quantity'], 'quantity');
        return $row;
    }
    private function audit(int $id, int $actor, string $action, ?array $before, array $after): void
    {
        $this->db->table('agri_work_log_audit')->insert(['work_log_id' => $id, 'actor_id' => $actor, 'action' => $action, 'before_json' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null, 'after_json' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => gmdate('Y-m-d H:i:s')]);
    }
    private function transaction(callable $operation): array
    {
        $this->db->transBegin();
        try {
            $result = $operation();
            if (! $this->db->transStatus() || ! $this->db->transCommit()) { throw new \RuntimeException('Falha na transação.'); }
            return $result;
        } catch (Throwable $error) {
            $this->db->transRollback();
            if ($error instanceof FieldProblem) { throw $error; }
            log_message('error', 'Falha ao guardar diário de campo.');
            throw new FieldProblem(409, 'Não foi possível confirmar o registo. Recarregue o diário antes de repetir.');
        }
    }
}
