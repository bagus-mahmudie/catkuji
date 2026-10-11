<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_redirects_to_home_when_register_is_modal_based(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect('/?auth=register');
    }

    public function test_new_users_can_start_registration_flow(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $response = $this->post('/register', [
            'terms' => true,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect();

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $response->assertSessionHas('email_verification_user_id');
        $this->assertGuest();
    }
}