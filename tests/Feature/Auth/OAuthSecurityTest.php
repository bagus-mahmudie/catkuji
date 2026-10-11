<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Tests\TestCase;

class OAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_rejects_invalid_oauth_state(): void
    {
        $provider = \Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        $provider->shouldNotReceive('stateless');
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
        $this->get('/auth/google/callback?state=invalid')->assertRedirect('/?auth=login');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
