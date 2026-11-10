<?php
namespace App\Controllers;

use App\Users\UserProblem;
use CodeIgniter\Shield\Entities\User;

class UsersController extends BaseController
{
    public function metadata()
    {
        $config = config('AuthGroups');
        return $this->response->setJSON(['groups' => $config->groups, 'permissions' => $config->permissions, 'matrix' => $config->matrix]);
    }

    public function index()
    {
        $page = $this->request->getGet('page') ?? '1';
        $query = $this->request->getGet('q') ?? '';
        $role = $this->request->getGet('role') ?? '';
        $active = $this->request->getGet('active') ?? '';
        if (! is_string($page) || ! ctype_digit($page) || (int) $page < 1 || (int) $page > 100000
            || ! is_string($query) || mb_strlen($query) > 160
            || ! is_string($role) || ($role !== '' && ! isset(config('AuthGroups')->groups[$role]))
            || ! in_array($active, ['', '1', '0'], true)) {
            return $this->error(422, 'Verifique os filtros.');
        }
        $tables = config('Auth')->tables;
        $db = db_connect();
        $builder = $db->table($tables['users'] . ' u')->select('u.id')
            ->join($tables['identities'] . ' i', "i.user_id = u.id AND i.type = 'email_password'", 'left')->where('u.deleted_at', null);
        if ($query !== '') $builder->groupStart()->like('u.username', trim($query))->orLike('i.secret', trim($query))->groupEnd();
        if ($active !== '') $builder->where('u.active', (int) $active);
        if ($role !== '') $builder->whereIn('u.id', $db->table($tables['groups_users'])->select('user_id')->where('group', $role));
        $total = $builder->countAllResults(false);
        $rows = $builder->orderBy('u.id', 'DESC')->get(25, ((int) $page - 1) * 25)->getResultArray();
        $users = [];
        foreach ($rows as $row) {
            $user = auth()->getProvider()->findById($row['id']);
            if ($user) $users[] = $this->serializeUser($user);
        }
        return $this->response->setJSON(['users' => $users, 'total' => $total, 'page' => (int) $page, 'pageSize' => 25]);
    }

    public function show(string $id)
    {
        $user = auth()->getProvider()->findById((int) $id);
        return $user ? $this->response->setJSON(['user' => $this->serializeUser($user)]) : $this->error(404, 'Utilizador não encontrado.');
    }
    public function create() { return $this->save(); }
    public function update(string $id) { return $this->save((int) $id); }
    public function deactivate(string $id) { return $this->save((int) $id, true); }

    private function save(?int $id = null, bool $deactivate = false)
    {
        $db = db_connect();
        $input = $this->request->getJSON(true);
        if (! is_array($input) || array_is_list($input)) return $this->error(422, 'Envie os dados do utilizador.');
        $allowed = $deactivate ? ['version'] : ['username', 'email', 'password', 'role', 'active', 'permissions', ...($id ? ['version'] : [])];
        if (array_diff(array_keys($input), $allowed)) return $this->error(422, 'O pedido contém campos não permitidos.');
        $db->transBegin();
        try {
            $lock = $db->escapeIdentifiers($db->prefixTable('agri_user_versions'));
            $db->query('SELECT * FROM ' . $lock . ' WHERE user_id = 0' . ($db->getPlatform() === 'MySQLi' ? ' FOR UPDATE' : ''))->getRowArray();
            $provider = auth()->getProvider();
            $user = $id ? $provider->findById($id) : null;
            if ($id && ! $user) throw new UserProblem(404, 'Utilizador não encontrado.');
            $before = $user ? $this->serializeUser($user) : null;
            if ($id && (! is_int($input['version'] ?? null) || $input['version'] !== $before['version'])) throw new UserProblem(409, 'Este utilizador foi alterado. Reabra o registo na listagem atualizada.');
            if ($deactivate) $input += ['username' => $before['username'], 'email' => $before['email'], 'role' => $before['role'], 'permissions' => $before['extra_permissions'], 'active' => false];
            $errors = [];
            $name = is_string($input['username'] ?? null) ? trim($input['username']) : '';
            $email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
            $password = $input['password'] ?? '';
            $role = $input['role'] ?? '';
            $active = $input['active'] ?? true;
            $permissions = $input['permissions'] ?? [];
            if ($name === '' || mb_strlen($name) > 30) $errors['username'] = 'Indique um nome com até 30 caracteres.';
            if (strlen($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Indique um email válido.';
            if (! is_string($password) || strlen($password) > 72 || ((! $id || $password !== '') && mb_strlen($password) < 8)) $errors['password'] = 'A palavra-passe deve ter pelo menos 8 caracteres e no máximo 72 bytes.';
            if (! is_string($role) || ! isset(config('AuthGroups')->groups[$role])) $errors['role'] = 'Selecione um perfil válido.';
            if (! is_bool($active)) $errors['active'] = 'Indique se a conta está ativa.';
            if (! is_array($permissions) || ! array_is_list($permissions)) $errors['permissions'] = 'Selecione permissões válidas.';
            else foreach ($permissions as $permission) if (! is_string($permission) || ! isset(config('AuthGroups')->permissions[$permission])) $errors['permissions'] = 'Selecione permissões válidas.';
            if ($errors) throw new UserProblem(422, 'Verifique os campos indicados.', $errors);
            $permissions = array_values(array_unique($permissions)); sort($permissions);
            if (in_array('users.manage', $permissions, true) && ! in_array('users.view', $permissions, true)) { $permissions[] = 'users.view'; sort($permissions); }
            $existingName = $provider->findByCredentials(['username' => $name]);
            $existingEmail = $provider->findByCredentials(['email' => $email]);
            if ($existingName && (int) $existingName->id !== $id) $errors['username'] = 'Nome já utilizado.';
            if ($existingEmail && (int) $existingEmail->id !== $id) $errors['email'] = 'Email já utilizado.';
            if ($errors) throw new UserProblem(422, 'Verifique os campos indicados.', $errors);
            $beforePermissions = $before['extra_permissions'] ?? []; sort($beforePermissions);
            $accessChanged = $before && ($before['role'] !== $role || $before['active'] !== $active || $beforePermissions !== $permissions);
            if ($id === (int) auth()->id() && $accessChanged) throw new UserProblem(409, 'Não pode desativar ou alterar o perfil e as permissões da conta em uso. Utilize outro administrador.');
            if ($before && in_array('admin', $before['groups'], true) && $before['active'] && (! $active || $role !== 'admin')) {
                $tables = config('Auth')->tables;
                $admins = $db->table($tables['users'] . ' u')->select('u.id')->distinct()->join($tables['groups_users'] . ' g', 'g.user_id = u.id')
                    ->where('u.active', 1)->where('u.deleted_at', null)->where('g.group', 'admin')->get()->getResultArray();
                $count = 0;
                foreach ($admins as $admin) { if (! $provider->findById($admin['id'])->isBanned()) $count++; }
                if ($count <= 1) throw new UserProblem(409, 'É necessário manter pelo menos um administrador ativo.');
            }
            $securityChanged = $before && ($accessChanged || $password !== '' || $email !== $before['email']);
            $user ??= new User();
            $user->username = $name; $user->email = $email; $user->active = $active;
            if ($password !== '') $user->password = $password;
            if (! $provider->save($user)) throw new UserProblem(422, 'Não foi possível guardar os dados.');
            $id ??= (int) $provider->getInsertID();
            $user = $provider->findById($id);
            $user->syncGroups($role); $user->syncPermissions(...$permissions);
            $state = $db->table('agri_user_versions')->where('user_id', $id)->get()->getRowArray();
            $newState = ['version' => $before ? $before['version'] + 1 : 1, 'security_version' => (int) ($state['security_version'] ?? 1) + ($securityChanged ? 1 : 0)];
            if ($state) $db->table('agri_user_versions')->where('user_id', $id)->update($newState);
            else $db->table('agri_user_versions')->insert(['user_id' => $id] + $newState);
            $after = $this->serializeUser($user);
            $db->table('agri_user_audit')->insert([
                'user_id' => $id, 'actor_id' => auth()->id(), 'action' => ! $before ? 'create' : ($deactivate ? 'deactivate' : 'update'),
                'before_json' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null, 'after_json' => json_encode($after, JSON_THROW_ON_ERROR),
                'password_changed' => $password !== '', 'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            if (! $db->transStatus()) throw new \RuntimeException('Persistence failure');
            $db->transCommit();
            return $this->response->setStatusCode($before ? 200 : 201)->setJSON(['user' => $after, 'reauthenticate' => $id === (int) auth()->id() && $securityChanged]);
        } catch (UserProblem $error) {
            $db->transRollback(); return $this->error($error->status, $error->getMessage(), $error->fields);
        } catch (\Throwable $error) {
            $db->transRollback(); log_message('error', 'Falha na gestão de utilizadores: {type}', ['type' => get_class($error)]);
            return $this->error(409, 'Não foi possível guardar. Recarregue e verifique se o nome e email já estão em uso.');
        }
    }

    private function serializeUser(User $user): array
    {
        $groups = $user->getGroups();
        $state = db_connect()->table('agri_user_versions')->where('user_id', $user->id)->get()->getRowArray();
        return [
            'id' => (int) $user->id, 'username' => $user->username, 'email' => $user->email,
            'active' => (bool) $user->active && ! $user->isBanned(), 'groups' => $groups,
            'role' => in_array('admin', $groups, true) ? 'admin' : ($groups[0] ?? 'worker'),
            'extra_permissions' => $user->getPermissions() ?? [],
            'permissions' => array_values(array_filter(array_keys(config('AuthGroups')->permissions), static fn ($permission) => $user->can($permission))),
            'version' => (int) ($state['version'] ?? 1),
        ];
    }
    private function error(int $status, string $message, array $fields = []) { return $this->response->setStatusCode($status)->setJSON(['error' => $message, 'fields' => $fields]); }
}
