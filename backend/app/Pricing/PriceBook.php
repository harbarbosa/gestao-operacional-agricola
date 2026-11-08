<?php

namespace App\Pricing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use Throwable;

class PriceBook
{
    public function __construct(private BaseConnection $db) {}

    public static function decimal(mixed $value, string $field): string
    {
        if (! is_string($value) || ! preg_match('/^(0|[1-9][0-9]{0,7})(\.[0-9]{1,4})?$/D', $value)) {
            throw new PriceProblem(422, 'Verifique os valores decimais.', [$field => 'Use texto decimal positivo ou zero, até 8 inteiros e 4 casas, com ponto.']);
        }
        return (string) BigDecimal::of($value)->toScale(4);
    }

    private static function date(mixed $value, string $field): string
    {
        $date = is_string($value) && preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $value)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new PriceProblem(422, 'Data inválida.', [$field => 'Use uma data válida no formato AAAA-MM-DD.']);
        }
        return $value;
    }

    private static function id(mixed $value, string $field, bool $optional = false): ?int
    {
        if ($optional && $value === null) { return null; }
        if (! is_int($value) || $value < 1) {
            throw new PriceProblem(422, 'Referência inválida.', [$field => 'Selecione um registo válido.']);
        }
        return $value;
    }

    private static function keys(array $input, array $allowed): void
    {
        if (array_is_list($input) || array_diff(array_keys($input), $allowed)) {
            throw new PriceProblem(422, 'Campos não permitidos.');
        }
    }

    private function scope(array $input): array
    {
        $kind = $input['kind'] ?? null;
        if (! in_array($kind, ['payment', 'billing'], true)) {
            throw new PriceProblem(422, 'Selecione pagamento ou cobrança.');
        }
        $worker = self::id($input['worker_id'] ?? null, 'worker_id', true);
        $client = self::id($input['client_id'] ?? null, 'client_id', true);
        if (($kind === 'payment' && $client !== null) || ($kind === 'billing' && $worker !== null)) {
            throw new PriceProblem(422, 'Pagamento aceita trabalhador; cobrança aceita cliente.');
        }
        return ['kind' => $kind, 'worker_id' => $worker, 'client_id' => $client];
    }

    private function record(string $table, int $id, bool $active = false): array
    {
        $row = $this->db->table('agri_' . $table)->where('id', $id)->get()->getRowArray();
        if (! $row || ($active && ! $row['is_active'])) {
            throw new PriceProblem(422, 'Referência inexistente ou inativa.', [$table => 'Selecione um registo disponível.']);
        }
        return $row;
    }

    // Todas as escritas de tarifas do mesmo serviço passam pelo mesmo bloqueio.
    private function lockService(int $id, bool $active = true): array
    {
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('agri_services'));
        $suffix = $this->db->getPlatform() === 'MySQLi' ? ' FOR UPDATE' : '';
        $row = $this->db->query('SELECT * FROM ' . $table . ' WHERE id = ?' . $suffix, [$id])->getRowArray();
        if (! $row || ($active && ! $row['is_active'])) { throw new PriceProblem(422, 'Serviço inexistente ou inativo.'); }
        return $row;
    }

    public function create(array $input, int $actor): array
    {
        self::keys($input, ['service_id', 'kind', 'worker_id', 'client_id', 'amount', 'valid_from', 'valid_to']);
        $serviceId = self::id($input['service_id'] ?? null, 'service_id');
        $scope = $this->scope($input);
        $amount = self::decimal($input['amount'] ?? null, 'amount');
        $from = self::date($input['valid_from'] ?? null, 'valid_from');
        $to = ($input['valid_to'] ?? null) === null ? null : self::date($input['valid_to'], 'valid_to');
        if ($to !== null && $to < $from) { throw new PriceProblem(422, 'O fim não pode anteceder o início.'); }
        return $this->transaction(function () use ($serviceId, $scope, $amount, $from, $to, $actor) {
            $service = $this->lockService($serviceId);
            if ($scope['worker_id'] !== null) { $this->record('workers', $scope['worker_id'], true); }
            if ($scope['client_id'] !== null) { $this->record('clients', $scope['client_id'], true); }
            $unit = $this->record('units', (int) $service[$scope['kind'] === 'payment' ? 'payment_unit_id' : 'billing_unit_id'], true);
            $overlap = $this->db->table('agri_prices')->where('service_id', $serviceId)
                ->where($scope)->where('canceled_at', null)->where('valid_from <=', $to ?? '9999-12-31')
                ->groupStart()->where('valid_to', null)->orWhere('valid_to >=', $from)->groupEnd()->countAllResults();
            if ($overlap > 0) { throw new PriceProblem(409, 'Já existe uma tarifa para este âmbito no período indicado. Encerre a anterior antes de criar outra.'); }
            $now = gmdate('Y-m-d H:i:s');
            $data = $scope + [
                'service_id' => $serviceId, 'unit_id' => $unit['id'], 'unit_name' => $unit['name'], 'unit_code' => $unit['code'],
                'amount' => $amount, 'currency' => 'EUR', 'valid_from' => $from, 'valid_to' => $to,
                'created_by' => $actor, 'created_at' => $now, 'updated_at' => $now, 'version' => 1,
            ];
            $this->db->table('agri_prices')->insert($data);
            $id = (int) $this->db->insertID();
            $after = $this->price($id);
            $this->audit($id, $actor, 'create', null, $after);
            return $after;
        });
    }

    public function change(int $id, array $input, int $actor, bool $cancel): array
    {
        self::keys($input, $cancel ? ['version', 'reason'] : ['version', 'valid_to']);
        $version = self::id($input['version'] ?? null, 'version');
        $date = $cancel ? null : self::date($input['valid_to'] ?? null, 'valid_to');
        $reason = $input['reason'] ?? null;
        if ($cancel && (! is_string($reason) || mb_strlen(trim($reason)) < 3 || mb_strlen($reason) > 500)) {
            throw new PriceProblem(422, 'Indique o motivo do cancelamento (3 a 500 caracteres).');
        }
        $initial = $this->price($id);
        return $this->transaction(function () use ($initial, $id, $version, $date, $reason, $actor, $cancel) {
            $this->lockService($initial['service_id'], false);
            $before = $this->price($id);
            if ($before['version'] !== $version || $before['canceled_at'] !== null) {
                throw new PriceProblem(409, 'A tarifa foi alterada ou cancelada. Recarregue a listagem.');
            }
            if (! $cancel && ($date < $before['valid_from'] || ($before['valid_to'] !== null && $date > $before['valid_to']))) {
                throw new PriceProblem(422, 'O encerramento deve estar dentro da vigência atual.');
            }
            $data = ['version' => $version + 1, 'updated_at' => gmdate('Y-m-d H:i:s')];
            if ($cancel) {
                $data['canceled_at'] = $data['updated_at'];
                $data['cancel_reason'] = trim($reason);
            } else { $data['valid_to'] = $date; }
            $this->db->table('agri_prices')->where('id', $id)->where('version', $version)->update($data);
            if ($this->db->affectedRows() !== 1) { throw new PriceProblem(409, 'A tarifa foi alterada.'); }
            $after = $this->price($id);
            $this->audit($id, $actor, $cancel ? 'cancel' : 'end', $before, $after);
            return $after;
        });
    }

    public function preview(array $input): array
    {
        self::keys($input, ['service_id', 'kind', 'worker_id', 'client_id', 'date', 'quantity']);
        $serviceId = self::id($input['service_id'] ?? null, 'service_id');
        $this->record('services', $serviceId);
        $scope = $this->scope($input);
        if ($scope['worker_id'] !== null) { $this->record('workers', $scope['worker_id']); }
        if ($scope['client_id'] !== null) { $this->record('clients', $scope['client_id']); }
        $date = self::date($input['date'] ?? null, 'date');
        $quantity = self::decimal($input['quantity'] ?? null, 'quantity');
        $rows = $this->matching($serviceId, $scope, $date);
        if ($rows === [] && ($scope['worker_id'] !== null || $scope['client_id'] !== null)) {
            $scope['worker_id'] = $scope['client_id'] = null;
            $rows = $this->matching($serviceId, $scope, $date);
        }
        if (count($rows) > 1) { throw new PriceProblem(409, 'Tarifas ambíguas. Corrija a configuração antes de calcular.'); }
        if ($rows === []) { throw new PriceProblem(404, 'Não existe tarifa aplicável nesta data.'); }
        $price = $this->normalize($rows[0]);
        return [
            'price' => $price, 'quantity' => $quantity,
            'total' => (string) BigDecimal::of($price['amount'])->multipliedBy($quantity)->toScale(2, RoundingMode::HALF_UP),
            'provisional' => true,
        ];
    }

    private function matching(int $service, array $scope, string $date): array
    {
        return $this->db->table('agri_prices')->where('service_id', $service)->where($scope)
            ->where('canceled_at', null)->where('valid_from <=', $date)
            ->groupStart()->where('valid_to', null)->orWhere('valid_to >=', $date)->groupEnd()->get()->getResultArray();
    }

    public function price(int $id): array
    {
        $row = $this->db->table('agri_prices')->where('id', $id)->get()->getRowArray();
        if (! $row) { throw new PriceProblem(404, 'Tarifa não encontrada.'); }
        return $this->normalize($row);
    }

    public function normalize(array $row): array
    {
        foreach (['id', 'service_id', 'worker_id', 'client_id', 'unit_id', 'version', 'created_by'] as $field) {
            if (isset($row[$field])) { $row[$field] = (int) $row[$field]; }
        }
        $row['amount'] = (string) BigDecimal::of((string) $row['amount'])->toScale(4);
        return $row;
    }

    private function audit(int $id, int $actor, string $action, ?array $before, array $after): void
    {
        $this->db->table('agri_price_audit')->insert([
            'price_id' => $id, 'actor_id' => $actor, 'action' => $action,
            'before_json' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_json' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
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
            if ($error instanceof PriceProblem) { throw $error; }
            log_message('error', 'Falha ao persistir configuração de tarifas.');
            throw new PriceProblem(409, 'Não foi possível guardar. Recarregue e verifique a configuração.');
        }
    }
}
