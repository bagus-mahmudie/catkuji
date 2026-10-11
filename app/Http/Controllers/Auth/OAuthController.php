<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['google', 'facebook']), 404);

        session([
            'oauth_redirect_to' => request()->query('redirect_to', '/'),
        ]);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['google', 'facebook']), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            return redirect('/?auth=login')
                ->with('status', 'Login dengan '.$provider.' gagal. Silakan coba lagi.');
        }

        $providerColumn = $provider.'_id';

        $user = User::where($providerColumn, $socialUser->getId())->first();

        if (! $user && $socialUser->getEmail()) {
            $user = User::where('email', Str::lower($socialUser->getEmail()))->first();
        }

        if ($user) {
            $user->forceFill([
                $providerColumn => $socialUser->getId(),
                'oauth_provider' => $provider,
                'avatar' => $user->avatar ?: $socialUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        } else {
            $email = $socialUser->getEmail();

            if (! $email) {
                return redirect('/?auth=login')
                    ->with('status', 'Akun '.$provider.' tidak memberikan email. Gunakan metode login lain.');
            }

            $user = User::create([
                'name' => $socialUser->getName()
                    ?: Str::of($email)->before('@')->replace(['.', '_', '-'], ' ')->squish()->title(),
                'email' => Str::lower($email),
                $providerColumn => $socialUser->getId(),
                'oauth_provider' => $provider,
                'avatar' => $socialUser->getAvatar(),
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'role' => 'user',
                'status' => 'active',
            ]);
        }

        if (! $user->isActive()) {
            return redirect('/?auth=login')
                ->with('status', 'Akun kamu sedang tidak aktif.');
        }

        Auth::login($user, true);

        request()->session()->regenerate();

        $redirectTo = session()->pull('oauth_redirect_to', '/');

        if (
            ! is_string($redirectTo) ||
            ! str_starts_with($redirectTo, '/') ||
            str_starts_with($redirectTo, '//') ||
            str_starts_with($redirectTo, '/\\')
        ) {
            $redirectTo = '/';
        }

        return redirect()->intended($redirectTo);
    }
}
