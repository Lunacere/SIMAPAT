<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function registrationData(array $extra = []): array
    {
        return array_merge(['name' => 'Anggota Baru', 'username' => 'anggota_baru', 'email' => 'baru@example.com',
            'password' => 'password-aman-123', 'password_confirmation' => 'password-aman-123'], $extra);
    }

    public function test_registration_creates_a_basic_user_with_a_hashed_password_and_logs_them_in(): void
    {
        $this->get('/login')->assertOk()->assertSee('Daftar sekarang');
        $this->get('/register')->assertOk()->assertSee('User basic');
        $this->post('/register', $this->registrationData())->assertRedirect('/dashboard');
        $user = User::where('username', 'anggota_baru')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertNull($user->client_id);
        $this->assertTrue(Hash::check('password-aman-123', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->get('/users')->assertForbidden();
        $this->get('/negotiations')->assertForbidden();
        $this->get('/register')->assertRedirect('/dashboard');
    }

    public function test_registration_rejects_privileged_role_and_client_assignment(): void
    {
        $this->post('/register', $this->registrationData(['role' => 'admin', 'client_id' => 1, 'email_verified_at' => now()]))
            ->assertSessionHasErrors(['role', 'client_id', 'email_verified_at']);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_registration_rejects_duplicate_accounts_and_unconfirmed_or_short_passwords(): void
    {
        User::factory()->create(['username' => 'anggota_baru', 'email' => 'baru@example.com']);
        $this->post('/register', $this->registrationData(['password' => 'short', 'password_confirmation' => 'other']))
            ->assertSessionHasErrors(['username', 'email', 'password']);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/register', [])->assertSessionHasErrors('username');
        }
        $this->post('/register', $this->registrationData())->assertTooManyRequests();
        $this->assertDatabaseCount('users', 0);
    }
}
