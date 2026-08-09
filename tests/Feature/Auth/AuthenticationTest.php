<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'phone_number' => '254712345678',
            'id_number' => '12345678',
        ]);

        // 1. Authenticate with Email
        $response = $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);
        $this->assertAuthenticated();
        $response->assertRedirect(route('portal.dashboard', absolute: false));

        auth()->logout();

        // 2. Authenticate with local Phone Number (0712345678)
        $responsePhone = $this->post('/login', [
            'login' => '0712345678',
            'password' => 'password',
        ]);
        $this->assertAuthenticated();
        $responsePhone->assertRedirect(route('portal.dashboard', absolute: false));

        auth()->logout();

        // 3. Authenticate with ID Number (12345678)
        $responseId = $this->post('/login', [
            'login' => '12345678',
            'password' => 'password',
        ]);
        $this->assertAuthenticated();
        $responseId->assertRedirect(route('portal.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
