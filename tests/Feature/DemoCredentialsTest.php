<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DemoCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_credentials_match_the_roles_and_other_accounts_are_untouched(): void
    {
        Notification::fake();
        $realUser = User::factory()->create(['username' => 'akun_asli', 'password' => 'password-asli-123']);
        $hash = $realUser->password;
        $this->seed(DemoAccountSeeder::class);
        $this->seed(DemoAccountSeeder::class);
        foreach (['admin' => ['admin123', 'admin'], 'operator' => ['operator123', 'operator'], 'peserta' => ['user123', 'user'], 'klien' => ['klien123', 'client']] as $username => [$password, $role]) {
            $account = User::where('username', $username)->firstOrFail();
            $this->assertSame($role, $account->role);
            $this->assertTrue(Hash::check($password, $account->password));
            $this->post('/login', ['username' => $username, 'password' => $password])->assertRedirect();
            $this->assertAuthenticatedAs($account);
            $this->post('/logout')->assertRedirect('/login');
        }
        $this->assertSame($hash, $realUser->refresh()->password);
        $this->assertNotNull(User::where('username', 'klien')->firstOrFail()->client_id);
        $this->assertDatabaseCount('users', 5);
    }

    public function test_demo_seeding_refuses_to_overwrite_a_real_account_using_a_reserved_username(): void
    {
        $user = User::factory()->create(['username' => 'admin', 'email' => 'asli@example.com']);
        $hash = $user->password;
        try {
            $this->seed(DemoAccountSeeder::class);
            $this->fail('Akun non-dummy harus ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('bukan akun dummy', $exception->getMessage());
        }
        $this->assertSame($hash, $user->refresh()->password);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('clients', 0);
    }
}
