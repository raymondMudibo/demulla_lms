<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone_number' => '0712345678',
            'id_number' => '99887766',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('portal.dashboard', absolute: false));
    }

    public function test_new_users_can_register_without_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'No Email User',
            'email' => null,
            'phone_number' => '0799887766',
            'id_number' => '11223344',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('portal.dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'name' => 'No Email User',
            'email' => null,
            'phone_number' => '254799887766',
            'id_number' => '11223344',
        ]);
    }
}
