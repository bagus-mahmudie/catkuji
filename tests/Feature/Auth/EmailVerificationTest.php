<?php

namespace Tests\Feature\Auth;

use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_account_cannot_login_with_arbitrary_otp(): void
    {
        $user = User::factory()->create();
        $this->post('/verify-email-code', ['email' => $user->email, 'code' => '123456'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_valid_otp_requires_matching_session_and_is_consumed_once(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->code($user);
        $payload = ['email' => $user->email, 'code' => '123456'];
        $this->post('/verify-email-code', $payload)->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->withSession(['email_verification_user_id' => $user->id])
            ->post('/verify-email-code', $payload)->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNotNull($code->fresh()->used_at);
        $this->post('/logout');
        $this->withSession(['email_verification_user_id' => $user->id])
            ->post('/verify-email-code', $payload)->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_expired_used_and_wrong_codes_are_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->code($user);
        $this->withSession(['email_verification_user_id' => $user->id]);
        $payload = ['email' => $user->email, 'code' => '123456'];
        $this->post('/verify-email-code', [...$payload, 'code' => '654321'])->assertSessionHasErrors('code');
        $code->update(['expires_at' => now()->subMinute()]);
        $this->post('/verify-email-code', $payload)->assertSessionHasErrors('code');
        $code->update(['expires_at' => now()->addMinutes(10), 'used_at' => now()]);
        $this->post('/verify-email-code', $payload)->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_unverified_session_is_blocked_and_can_reverify_own_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/wallet')->assertRedirect(route('verification.notice'));
        $this->get('/verify-email')->assertSessionHas('email_verification_user_id', $user->id);
        $this->post('/verify-email-code/resend', ['email' => $user->email])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);
        $code = EmailVerificationCode::where('user_id', $user->id)->latest('id')->first();
        $code->update(['code' => Hash::make('123456')]);
        $this->post('/verify-email-code', ['email' => $user->email, 'code' => '123456'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_inactive_accounts_are_rejected_on_login_and_existing_session(): void
    {
        $user = User::factory()->create(['status' => 'inactive', 'role' => 'super_admin']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/admin/settings/admins')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_otp_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/verify-email-code', ['email' => 'nobody@example.com', 'code' => '123456']);
        }
        $this->post('/verify-email-code', ['email' => 'nobody@example.com', 'code' => '123456'])->assertStatus(429);
    }

    public function test_otp_cannot_switch_an_authenticated_account(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->unverified()->create();
        $code = $this->code($other);
        $this->actingAs($owner)->withSession(['email_verification_user_id' => $other->id])
            ->post('/verify-email-code', ['email' => $other->email, 'code' => '123456'])
            ->assertSessionHasErrors('code');
        $this->assertAuthenticatedAs($owner);
        $this->assertNull($code->fresh()->used_at);
    }

    public function test_inactive_account_cannot_use_a_valid_otp(): void
    {
        $user = User::factory()->unverified()->create(['status' => 'inactive']);
        $code = $this->code($user);
        $this->withSession(['email_verification_user_id' => $user->id])
            ->post('/verify-email-code', ['email' => $user->email, 'code' => '123456'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertNull($code->fresh()->used_at);
    }

    private function code(User $user): EmailVerificationCode
    {
        return EmailVerificationCode::create([
            'user_id' => $user->id, 'email' => $user->email,
            'code' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10),
        ]);
    }
}
