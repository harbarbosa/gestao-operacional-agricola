<?php

namespace App\Controllers;

use CodeIgniter\Database\Exceptions\DatabaseException;

class CatalogController extends BaseController
{
    // Only these names may ever select tables or fields.
    private const FIELDS = [
        'sites' => ['name' => 160, 'client_id' => 'clients', 'locality' => 160, 'notes' => 2000],
        'workers' => ['name' => 160, 'email' => 254, 'phone' => 40, 'notes' => 2000, 'team_id' => 'teams'],
        'teams' => ['manager_user_id' => 'managers', 'name' => 160, 'responsible_name' => 120, 'notes' => 2000],
        'clients' => ['name' => 160, 'contact_name' => 120, 'email' => 254, 'phone' => 40, 'notes' => 2000],
        'units' => ['name' => 160, 'code' => 32],
        'services' => ['name' => 160, 'notes' => 2000, 'payment_unit_id' => 'units', 'billing_unit_id' => 'units'],
    ];

    public function index(string $resource)
    {
        if (! isset(self::FIELDS[$resource])) {
            return $this->error(404, 'Cadastro inexistente.');
        }
        $page = $this->request->getGet('page') ?? '1';
        $query = $this->request->getGet('q') ?? '';
        if (! is_scalar($page) || ! ctype_digit((string) $page) || (int) $page < 1
            || (int) $page > 100000 || ! is_string($query) || mb_strlen($query) > 160) {
            return $this->error(422, 'Filtros inválidos.');
        }
        $builder = db_connect()->table('agri_' . $resource);
        $referenceField = $resource === 'workers' ? 'team_id' : ($resource === 'sites' ? 'client_id' : null);
        if ($referenceField && ($reference = $this->request->getGet($referenceField)) !== null) {
            if (! is_string($reference) || ! ctype_digit($reference) || (int) $reference < 1) { return $this->error(422, 'Filtro de referência inválido.'); }
            $builder->where($referenceField, (int) $reference);
        }
        if (trim($query) !== '') {
            $builder->like('name', trim($query));
        }
        $total = $builder->countAllResults(false);
        $rows = $builder->orderBy('id', 'DESC')->get(25, ((int) $page - 1) * 25)->getResultArray();
        return $this->response->setJSON([
            'items' => array_map(fn ($row) => $this->normalize($row), $rows),
            'total' => $total, 'page' => (int) $page, 'pageSize' => 25,
        ]);
    }

    public function show(string $resource, string $id)
    {
        if (! isset(self::FIELDS[$resource])) {
            return $this->error(404, 'Cadastro inexistente.');
        }
        $row = db_connect()->table('agri_' . $resource)->where('id', $id)->get()->getRowArray();
        return $row ? $this->response->setJSON(['item' => $this->normalize($row)])
            : $this->error(404, 'Registo não encontrado.');
    }

    public function create(string $resource)
    {
        return $this->save($resource);
    }

    public function update(string $resource, string $id)
    {
        return $this->save($resource, (int) $id);
    }

    public function remove(string $resource, string $id)
    {
        if (! isset(self::FIELDS[$resource])) { return $this->error(404, 'Cadastro inexistente.'); }
        $input = $this->request->getJSON(true);
        if (! is_array($input) || array_keys($input) !== ['version'] || ! is_int($input['version']) || $input['version'] < 1) {
            return $this->error(422, 'Indique a versão atual do registo.');
        }
        $db = db_connect();
        $table = 'agri_' . $resource;
        $db->transBegin();
        try {
            // O bloqueio e as chaves RESTRICT protegem contra vínculos concorrentes.
            $sql = 'SELECT * FROM ' . $db->escapeIdentifiers($db->prefixTable($table)) . ' WHERE id = ?' . ($db->getPlatform() === 'MySQLi' ? ' FOR UPDATE' : '');
            $before = $db->query($sql, [(int) $id])->getRowArray();
            if (! $before || (int) $before['version'] !== $input['version']) {
                $db->transRollback();
                return $this->error($before ? 409 : 404, $before ? 'Este registo foi alterado. Recarregue a listagem.' : 'Registo não encontrado.');
            }
            $linked = false;
            foreach (self::FIELDS as $other => $fields) {
                foreach ($fields as $field => $target) {
                    if ($target === $resource && $db->table('agri_' . $other)->where($field, $id)->countAllResults() > 0) { $linked = true; }
                }
            }
            foreach ([
                'prices' => ['service_id' => 'services', 'worker_id' => 'workers', 'client_id' => 'clients', 'unit_id' => 'units'],
                'work_logs' => ['team_id' => 'teams', 'site_id' => 'sites', 'service_id' => 'services', 'client_id' => 'clients', 'payment_unit_id' => 'units', 'billing_unit_id' => 'units'],
                'work_log_attendees' => ['worker_id' => 'workers'],
                'contracts' => ['client_id'=>'clients','site_id'=>'sites','service_id'=>'services','billing_unit_id'=>'units','payment_unit_id'=>'units'],
                'harvests' => ['client_id'=>'clients','site_id'=>'sites','service_id'=>'services','billing_unit_id'=>'units','payment_unit_id'=>'units'],
                'harvest_movements' => ['worker_id'=>'workers'],
            ] as $other => $fields) {
                foreach ($fields as $field => $target) {
                    if ($target === $resource && $db->table('agri_' . $other)->where($field, $id)->countAllResults() > 0) { $linked = true; }
                }
            }
            // Ligações configuradas no próprio registo também são preservadas.
            foreach (self::FIELDS[$resource] as $field => $target) {
                if (is_string($target) && ! empty($before[$field])) { $linked = true; }
            }
            $now = gmdate('Y-m-d H:i:s');
            if ($linked) {
                $db->table($table)->where('id', $id)->where('version', $input['version'])->update([
                    'is_active' => false, 'version' => $input['version'] + 1, 'updated_at' => $now,
                ]);
                $after = $db->table($table)->where('id', $id)->get()->getRowArray();
            } else {
                $db->table($table)->where('id', $id)->where('version', $input['version'])->delete();
                $after = null;
            }
            $db->table('agri_catalog_audit')->insert([
                'resource' => $resource, 'record_id' => (int) $id, 'actor_id' => auth()->id(),
                'action' => $linked ? 'deactivate' : 'delete',
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => $now,
            ]);
            if (! $db->transStatus()) { throw new DatabaseException('Falha ao remover cadastro.'); }
            $db->transCommit();
            return $this->response->setJSON([
                'action' => $linked ? 'deactivated' : 'deleted',
                'message' => $linked ? 'Registo desativado por possuir vínculos. O histórico foi preservado.' : 'Registo excluído.',
            ]);
        } catch (DatabaseException $exception) {
            $db->transRollback();
            log_message('error', 'Falha ao remover cadastro {resource}.', ['resource' => $resource]);
            return $this->error(409, 'Não foi possível remover. Recarregue a listagem e tente novamente.');
        }
    }

    private function save(string $resource, ?int $id = null)
    {
        if (! isset(self::FIELDS[$resource])) {
            return $this->error(404, 'Cadastro inexistente.');
        }
        $db = db_connect();
        $table = 'agri_' . $resource;
        $before = $id ? $db->table($table)->where('id', $id)->get()->getRowArray() : null;
        if ($id && ! $before) {
            return $this->error(404, 'Registo não encontrado.');
        }
        $input = $this->request->getJSON(true);
        if (! is_array($input) || array_is_list($input)) {
            return $this->error(422, 'Envie um objeto com os dados do cadastro.');
        }
        $allowed = [...array_keys(self::FIELDS[$resource]), 'is_active', ...($id ? ['version'] : [])];
        if (array_diff(array_keys($input), $allowed) !== []) {
            return $this->error(422, 'O pedido contém campos não permitidos.');
        }
        $data = [];
        $errors = [];
        foreach (self::FIELDS[$resource] as $field => $rule) {
            $value = $input[$field] ?? null;
            if (is_int($rule)) {
                if ($value !== null && ! is_string($value)) {
                    $errors[$field] = 'Indique um texto válido.';
                    continue;
                }
                $value = trim($value ?? '');
                if (mb_strlen($value) > $rule || ($field === 'name' && $value === '')) {
                    $errors[$field] = $field === 'name' ? 'Nome obrigatório (até 160 caracteres).' : 'Texto demasiado longo.';
                }
                if ($field === 'email' && $value !== '' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'Email inválido.';
                }
                if ($field === 'code' && ! preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $value)) {
                    $errors[$field] = 'Use um código em minúsculas, sem espaços (até 32 caracteres).';
                }
                $data[$field] = $value === '' ? null : $value;
                continue;
            }
            if ($value === null && in_array($field, ['team_id', 'manager_user_id'], true)) {
                $data[$field] = null;
                continue;
            }
            if (! is_int($value) || $value < 1) {
                $errors[$field] = 'Selecione um registo válido.';
                continue;
            }
            $related = $rule === 'managers' ? $db->table(config('Auth')->tables['users'])->where('id', $value)->get()->getRowArray() : $db->table('agri_' . $rule)->where('id', $value)->get()->getRowArray();
            if ($related && $rule === 'managers') { $related['is_active'] = $related['active']; $manager = auth()->getProvider()->findById($value); if (!$manager || (!$manager->can('field.record') && !$manager->can('field.manage')) || $manager->isBanned()) $related = null; }
            if (! $related || (! $related['is_active'] && (int) ($before[$field] ?? 0) !== $value)) {
                $errors[$field] = 'O registo selecionado não existe ou está inativo.';
            }
            $data[$field] = $value;
        }
        if (array_key_exists('is_active', $input) && ! is_bool($input['is_active'])) {
            $errors['is_active'] = 'Indique ativo ou inativo.';
        }
        $data['is_active'] = $input['is_active'] ?? (isset($before['is_active']) ? (bool) $before['is_active'] : true);
        if ($id && (! is_int($input['version'] ?? null) || $input['version'] < 1)) {
            $errors['version'] = 'A versão do registo é obrigatória.';
        }
        if ($resource === 'units' && isset($data['code'])) {
            $duplicate = $db->table($table)->where('code', $data['code']);
            if ($id) { $duplicate->where('id !=', $id); }
            if ($duplicate->countAllResults() > 0) { $errors['code'] = 'Código já utilizado.'; }
        }
        if ($errors !== []) {
            return $this->error(422, 'Verifique os campos indicados.', $errors);
        }
        if ($id && (int) $before['version'] !== $input['version']) {
            return $this->error(409, 'Este registo foi alterado. Recarregue antes de editar.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        $data['version'] = $id ? $input['version'] + 1 : 1;
        $db->transBegin();
        try {
            if ($id) {
                $db->table($table)->where('id', $id)->where('version', $input['version'])->update($data);
                if ($db->affectedRows() !== 1) {
                    $db->transRollback();
                    return $this->error(409, 'Este registo foi alterado. Recarregue antes de editar.');
                }
            } else {
                $data['created_at'] = $now;
                $db->table($table)->insert($data);
                $id = (int) $db->insertID();
            }
            $after = $db->table($table)->where('id', $id)->get()->getRowArray();
            $db->table('agri_catalog_audit')->insert([
                'resource' => $resource, 'record_id' => $id, 'actor_id' => auth()->id(),
                'action' => $before ? 'update' : 'create',
                'before_json' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => $now,
            ]);
            if (! $db->transStatus()) { throw new DatabaseException('Falha ao guardar cadastro.'); }
            $db->transCommit();
        } catch (DatabaseException $exception) {
            $db->transRollback();
            // Não devolver SQL nem dados de ligação ao cliente.
            log_message('error', 'Falha de persistência no cadastro {resource}.', ['resource' => $resource]);
            return $this->error(409, 'Não foi possível guardar. Verifique referências e códigos duplicados e tente novamente.');
        }
        return $this->response->setStatusCode($before ? 200 : 201)->setJSON(['item' => $this->normalize($after)]);
    }

    private function normalize(array $row): array
    {
        foreach (['id', 'version', 'team_id', 'client_id', 'payment_unit_id', 'billing_unit_id', 'manager_user_id'] as $field) {
            if (isset($row[$field])) { $row[$field] = (int) $row[$field]; }
        }
        $row['is_active'] = (bool) $row['is_active'];
        return $row;
    }

    private function error(int $status, string $message, array $fields = [])
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => $message, 'fields' => $fields]);
    }
}
