<?php
namespace Tests\App;

use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class UsersTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    protected $namespace = null;
    private int $actor;
    protected function setUp(): void
    {
        $this->resetServices(); $_SESSION = []; $_COOKIE = []; parent::setUp(); helper(['auth', 'setting']);
        $settings = config('Settings'); $settings->handlers = ['array']; Services::injectMock('settings', new Settings($settings));
        $user = $this->fixture('admin', 'manager'); $this->actor = $user->id; auth('session')->login($user);
    }
    private function fixture(string $role, string $name): User
    {
        $provider = auth()->getProvider();
        $provider->save(new User(['username' => $name, 'email' => $name . '@example.test', 'password' => 'ExamplePassword123!', 'active' => true]));
        $user = $provider->findById($provider->getInsertID()); $user->addGroup($role); return $user;
    }
    private function write(string $path, array $data, string $method = 'post')
    {
        $token = csrf_hash(); session()->set(config('Security')->tokenName, $token);
        return $this->withSession()->withHeaders(['X-CSRF-TOKEN' => $token, 'X-Requested-With' => 'XMLHttpRequest'])->withBodyFormat('json')->{$method}(($path === '/auth/password' ? '/api/v1' : '/api/v1/users') . $path, $data);
    }
    private function data(array $changes = []): array { return array_replace(['username' => 'newuser', 'email' => 'new@example.test', 'password' => 'SafePassword123!', 'role' => 'worker', 'active' => true, 'permissions' => []], $changes); }
    public function testCreateAccountAndAssignOnlyExplicitAccess(): void
    {
        $result = $this->write('', $this->data(['permissions' => ['field.manage']])); $result->assertStatus(201);
        $user = json_decode($result->getJSON(), true)['user'];
        $this->assertContains('field.manage', $user['permissions']); $this->assertNotContains('users.manage', $user['permissions']);
        $this->assertStringNotContainsString('SafePassword123', $result->getJSON()); $this->assertStringNotContainsString('hash', $result->getJSON());
        $this->seeInDatabase('agri_user_audit', ['user_id' => $user['id'], 'action' => 'create']);
        auth('session')->logout();
        $this->assertTrue(auth('session')->attempt(['email' => 'new@example.test', 'password' => 'SafePassword123!'])->isOK());
    }
    public function testInvalidOrDuplicateDataDoNotCreateAccount(): void
    {
        foreach ([['role' => 'root'], ['permissions' => ['root.*']], ['active' => 'true'], ['password' => 'short'], ['email' => 'not-email'], ['email' => 'manager@example.test']] as $change) $this->write('', $this->data($change))->assertStatus(422);
        $this->assertSame(1, $this->db->table(config('Auth')->tables['users'])->countAllResults());
    }
    public function testWorkerCannotListCreateUpdateOrDeactivateUsers(): void
    {
        auth('session')->logout(); auth('session')->login($this->fixture('worker', 'ordinary'));
        $this->withSession()->get('/api/v1/users')->assertStatus(403);
        $this->write('', $this->data())->assertStatus(403);
        $this->write('/' . $this->actor, $this->data(['version' => 1]), 'put')->assertStatus(403);
        $this->write('/' . $this->actor, ['version' => 1], 'delete')->assertStatus(403);
    }
    public function testSelfDeactivationAndLastAdministratorAreProtected(): void
    {
        $this->write('/' . $this->actor, ['version' => 1], 'delete')->assertStatus(409);
        $other = $this->fixture('worker', 'delegated'); $other->addPermission('users.manage', 'users.view'); auth('session')->logout(); auth('session')->login($other);
        $this->write('/' . $this->actor, ['version' => 1], 'delete')->assertStatus(409);
        $this->seeInDatabase(config('Auth')->tables['users'], ['id' => $this->actor, 'active' => 1]);
    }
    public function testEditPermissionsAndPasswordInvalidatesExistingSession(): void
    {
        $target = $this->fixture('worker', 'target');
        $data = $this->data(['username' => 'target', 'email' => 'target@example.test', 'password' => 'NewPassword123!', 'permissions' => ['reports.view'], 'version' => 1]);
        $result = $this->write('/' . $target->id, $data, 'put'); $result->assertStatus(200);
        $user = json_decode($result->getJSON(), true)['user']; $this->assertSame(2, $user['version']); $this->assertContains('reports.view', $user['permissions']);
        $this->write('/' . $target->id, $data, 'put')->assertStatus(409);
        $audit = $this->db->table('agri_user_audit')->get()->getRowArray(); $this->assertStringNotContainsString('NewPassword123', $audit['after_json']);
        auth('session')->logout(); auth('session')->login(auth()->getProvider()->findById($target->id)); session()->set('agri_user_security_version', 1);
        $this->withSession()->get('/api/v1/auth/me')->assertStatus(401);
    }
    public function testDeactivatePreservesIdentityAndHistory(): void
    {
        $target = $this->fixture('worker', 'target');
        $this->write('/' . $target->id, ['version' => 1], 'delete')->assertStatus(200);
        $this->seeInDatabase(config('Auth')->tables['users'], ['id' => $target->id, 'active' => 0]);
        $this->seeInDatabase('agri_user_audit', ['user_id' => $target->id, 'action' => 'deactivate']);
        $this->assertNotNull(auth()->getProvider()->findById($target->id)->getEmailIdentity());
    }
    public function testReadOnlyViewerCannotChangeAccountsAndFiltersWork(): void
    {
        $this->fixture('worker', 'target');
        $result = $this->withSession()->get('/api/v1/users?q=target&role=worker&active=1'); $result->assertStatus(200);
        $this->assertSame(1, json_decode($result->getJSON(), true)['total']);
        $this->withSession()->get('/api/v1/users?active=bad')->assertStatus(422);
        $viewer = $this->fixture('worker', 'viewer'); $viewer->addPermission('users.view'); auth('session')->logout(); auth('session')->login($viewer);
        $this->withSession()->get('/api/v1/users')->assertStatus(200); $this->withSession()->get('/api/v1/users/metadata')->assertStatus(200);
        $this->write('', $this->data())->assertStatus(403);
    }
    public function testOwnPasswordRequiresTheCurrentPassword():void
    {
        $this->write('/auth/password',['current_password'=>'WrongPassword!','new_password'=>'NewPassword123!','confirmation'=>'NewPassword123!'])->assertStatus(422);
        $hash=$this->db->table('auth_identities')->where('user_id',$this->actor)->where('type','email_password')->get()->getRowArray()['secret2'];$this->assertTrue(service('passwords')->verify('ExamplePassword123!',$hash));
    }
    public function testOwnPasswordCannotChangeAnotherAccountOrAcceptWeakConfirmation():void
    {
        $this->write('/auth/password',['current_password'=>'ExamplePassword123!','new_password'=>'short','confirmation'=>'different','user_id'=>999])->assertStatus(422);
        $this->assertSame(0,$this->db->table('agri_user_audit')->where('action','password')->countAllResults());
    }
    public function testPasswordChangePreservesCurrentSessionAndInvalidatesOtherVersions():void
    {
        $this->write('/auth/password',['current_password'=>'ExamplePassword123!','new_password'=>'NewPassword123!','confirmation'=>'NewPassword123!'])->assertStatus(200);
        $hash=$this->db->table('auth_identities')->where('user_id',$this->actor)->where('type','email_password')->get()->getRowArray()['secret2'];$this->assertTrue(service('passwords')->verify('NewPassword123!',$hash));$this->assertFalse(service('passwords')->verify('ExamplePassword123!',$hash));
        $this->withSession()->get('/api/v1/auth/me')->assertStatus(200);$audit=$this->db->table('agri_user_audit')->where('action','password')->get()->getRowArray();$this->assertSame(1,(int)$audit['password_changed']);$this->assertStringNotContainsString('NewPassword123!',json_encode($audit));session()->set('agri_user_security_version',1);$this->withSession()->get('/api/v1/auth/me')->assertStatus(401);
    }
    public function testAnonymousCannotChangePassword():void
    {
        auth('session')->logout();$this->write('/auth/password',['current_password'=>'ExamplePassword123!','new_password'=>'NewPassword123!','confirmation'=>'NewPassword123!'])->assertStatus(401);
    }

}
