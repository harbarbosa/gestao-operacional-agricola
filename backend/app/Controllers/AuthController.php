<?php

namespace App\Controllers;

class AuthController extends BaseController
{
    public function csrf()
    {
        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON([
            'token' => csrf_hash(),
        ]);
    }

    public function login()
    {
        // Limite partilhado por IP: não depende de cookies fornecidos pelo cliente.
        $key = 'auth-login-' . hash('sha256', $this->request->getIPAddress());
        if (! service('throttler')->check($key, 10, 60)) {
            return $this->response->setStatusCode(429)->setHeader('Retry-After', '60')
                ->setJSON(['error' => 'Demasiadas tentativas. Aguarde um minuto.']);
        }
        $data = $this->request->getJSON(true) ?? [];
        if (! is_array($data) || ! is_string($data['email'] ?? null)
            || ! is_string($data['password'] ?? null)
            || ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)
            || strlen($data['email']) > 254 || strlen($data['password']) > 1024) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Indique email e palavra-passe válidos.']);
        }
        if (auth('session')->loggedIn()) {
            return $this->response->setStatusCode(409)->setJSON(['error' => 'Termine a sessão atual antes de trocar de conta.']);
        }
        $result = auth('session')->attempt([
            'email' => trim($data['email']), 'password' => $data['password'],
        ]);
        if (! $result->isOK()) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Credenciais inválidas ou conta indisponível.']);
        }
        $user = auth('session')->user();
        if (! auth('session')->loggedIn() || ! $user || ! $user->active || $user->isBanned()) {
            auth('session')->logout();
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Credenciais inválidas ou conta indisponível.']);
        }
        session()->regenerate(true);
        $state = db_connect()->table('agri_user_versions')->where('user_id', $user->id)->get()->getRowArray();
        session()->set('agri_user_security_version', (int) ($state['security_version'] ?? 1));
        return $this->me();
    }

    public function me()
    {
        $user = auth('session')->user();
        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON(['user' => [
            'id' => (int) $user->id,
            'username' => $user->username,
            'groups' => $user->getGroups(),
            'permissions' => array_values(array_filter(
                array_keys(config('AuthGroups')->permissions),
                static fn ($permission) => $user->can($permission),
            )),
        ]]);
    }

    public function password()
    {
        $input=$this->request->getJSON(true);$id=(int)auth()->id();
        if(!service('throttler')->check('password-change-'.$id,5,60))return $this->response->setStatusCode(429)->setJSON(['error'=>'Aguarde um minuto antes de tentar novamente.']);
        if(!is_array($input)||array_diff(array_keys($input),['current_password','new_password','confirmation'])||!is_string($input['current_password']??null)||strlen($input['current_password'])>1024||!is_string($input['new_password']??null)||mb_strlen($input['new_password'])<8||strlen($input['new_password'])>72||$input['new_password']!==($input['confirmation']??null))return $this->response->setStatusCode(422)->setJSON(['error'=>'Confirme a nova palavra-passe, com pelo menos 8 caracteres e no máximo 72 bytes.']);
        $db=db_connect();$db->transBegin();
        try{
            $db->query('SELECT id FROM '.$db->escapeIdentifiers($db->prefixTable('users')).' WHERE id=?'.($db->getPlatform()==='MySQLi'?' FOR UPDATE':''),[$id])->getRowArray();
            $identity=$db->table(config('Auth')->tables['identities'])->where('user_id',$id)->where('type','email_password')->get()->getRowArray();
            if(!$identity||!service('passwords')->verify($input['current_password'],$identity['secret2'])){$db->transRollback();return $this->response->setStatusCode(422)->setJSON(['error'=>'A palavra-passe atual está incorreta.']);}
            $provider=auth()->getProvider();$user=$provider->findById($id);$user->password=$input['new_password'];if(!$provider->save($user))throw new \RuntimeException('Password save');
            $state=$db->table('agri_user_versions')->where('user_id',$id)->get()->getRowArray();$security=(int)($state['security_version']??1)+1;$values=['version'=>(int)($state['version']??1)+1,'security_version'=>$security];if($state)$db->table('agri_user_versions')->where('user_id',$id)->update($values);else$db->table('agri_user_versions')->insert(['user_id'=>$id]+$values);
            $db->table(config('Auth')->tables['remember_tokens'])->where('user_id',$id)->delete();
            $db->table('agri_user_audit')->insert(['user_id'=>$id,'actor_id'=>$id,'action'=>'password','before_json'=>null,'after_json'=>'{"password_changed":true}','password_changed'=>1,'created_at'=>gmdate('Y-m-d H:i:s')]);
            if(!$db->transStatus()||!$db->transCommit())throw new \RuntimeException('Persistence');session()->regenerate(true);session()->set('agri_user_security_version',$security);return $this->response->setJSON(['status'=>'ok']);
        }catch(\Throwable $e){$db->transRollback();log_message('error','Falha ao alterar palavra-passe: {type}',['type'=>get_class($e)]);return $this->response->setStatusCode(409)->setJSON(['error'=>'Não foi possível confirmar a alteração.']);}
    }

    public function logout()
    {
        auth('session')->logout();
        session()->regenerate(true);
        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON(['status' => 'ok']);
    }
}
