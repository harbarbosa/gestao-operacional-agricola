<?php

namespace Tests\App;

use CodeIgniter\Settings\Settings;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class AuthTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = '\\CodeIgniter\\Shield';

    protected function setUp(): void
    {
        parent::setUp();
        helper(['auth', 'setting']);
        $settings = config('Settings');
        $settings->handlers = ['array'];
        Services::injectMock('settings', new Settings($settings));
        service('cache')->clean();
    }

    private function createUser(string $role = 'worker', bool $active = true): User
    {
        $provider = auth()->getProvider();
        $user = new User([
            'username' => 'exampleuser', 'email' => 'example@example.test',
            'password' => 'ExampleTestPassword123!', 'active' => $active,
        ]);
        $this->assertTrue($provider->save($user));
        $user = $provider->findById($provider->getInsertID());
        $user->addGroup($role);
        return $user;
    }

    private function credentials(string $password = 'ExampleTestPassword123!')
    {
        $token = csrf_hash();
        return $this->withSession([config('Security')->tokenName => $token])
            ->withHeaders(['X-CSRF-TOKEN' => $token, 'X-Requested-With' => 'XMLHttpRequest'])
            ->withBodyFormat('json')->post('/api/v1/auth/login', [
                'email' => 'example@example.test', 'password' => $password,
            ]);
    }

    public function testAnonymousCannotReadUserOrUsers(): void
    {
        $this->get('/api/v1/auth/me')->assertStatus(401);
        $this->get('/api/v1/users')->assertStatus(401);
    }

    public function testLoginReturnsCurrentUserWithoutSecrets(): void
    {
        $this->createUser();
        $result = $this->credentials();
        $result->assertStatus(200);
        $result->assertJSONFragment(['username' => 'exampleuser', 'groups' => ['worker']]);
        $this->assertStringNotContainsString('ExampleTestPassword', $result->getJSON());
        $this->assertStringNotContainsString('secret', $result->getJSON());
    }

    public function testIncorrectPasswordIsRejected(): void
    {
        $this->createUser();
        $this->credentials('IncorrectPassword123!')->assertStatus(401);
        $this->assertFalse(auth('session')->loggedIn());
    }

    public function testInactiveAccountCannotLogin(): void
    {
        $this->createUser('worker', false);
        $this->credentials()->assertStatus(401);
        $this->assertFalse(auth('session')->loggedIn());
    }

    public function testLoginWithoutCsrfIsRejected(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->withBodyFormat('json')->post('/api/v1/auth/login', [
                'email' => 'example@example.test', 'password' => 'ExampleTestPassword123!',
            ])->assertStatus(403);
    }

    public function testWorkerCannotReadUsers(): void
    {
        $user = $this->createUser();
        auth('session')->login($user);
        $this->withSession()->get('/api/v1/users')->assertStatus(403);
    }

    public function testSupervisorCannotReadUsers(): void
    {
        $user = $this->createUser('supervisor');
        auth('session')->login($user);
        $this->withSession()->get('/api/v1/users')->assertStatus(403);
    }

    public function testAdminCanReadUsers(): void
    {
        $user = $this->createUser('admin');
        auth('session')->login($user);
        $this->withSession()->get('/api/v1/users')->assertStatus(200);
    }

    public function testDeactivatedSessionIsRejected(): void
    {
        $user = $this->createUser('worker', false);
        auth('session')->login($user);
        $this->withSession()->get('/api/v1/auth/me')->assertStatus(401);
    }

    public function testLogoutInvalidatesAuthentication(): void
    {
        auth('session')->login($this->createUser());
        $token = csrf_hash();
        session()->set(config('Security')->tokenName, $token);
        $this->withSession()->withHeaders(['X-CSRF-TOKEN' => $token])
            ->withBodyFormat('json')->post('/api/v1/auth/logout', [])->assertStatus(200);
        $this->assertFalse(auth('session')->loggedIn());
    }

    public function testNoPublicRegistrationRoute(): void
    {
        $this->get('/register')->assertStatus(404);
        $this->assertFalse(config('Auth')->allowRegistration);
    }

    public function testLoginRateLimitCannotBeAvoidedByNewSession(): void
    {
        $key = 'auth-login-' . hash('sha256', service('request')->getIPAddress());
        for ($i = 0; $i < 10; $i++) {
            service('throttler')->check($key, 10, 60);
        }
        $this->credentials('incorrect')->assertStatus(429);
    }
}
