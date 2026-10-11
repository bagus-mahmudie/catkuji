<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): RedirectResponse
    {
        $target = $this->resolvePreviousPage($request, '/');

        return redirect()->to($target.(str_contains($target, '?') ? '&' : '?').'auth=login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        if ($user && ! $user->hasVerifiedEmail()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            EmailOtpVerificationController::sendOtp($user);

            return back()
                ->with('status', 'Email kamu belum diverifikasi. Kami sudah kirim kode verifikasi baru.')
                ->with('showEmailVerificationModal', true)
                ->with('verificationEmail', $user->email)
                ->with('verificationContext', 'login');
        }

        $request->session()->regenerate();

        return redirect()->intended(
            $this->resolveRedirectTo(
                $request->input('redirect_to'),
                '/',
            ),
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function resolvePreviousPage(Request $request, string $fallback = '/'): string
    {
        $redirectTo = trim((string) $request->query('redirect_to', ''));

        if ($redirectTo !== '') {
            return $this->resolveRedirectTo($redirectTo, $fallback);
        }

        $previous = url()->previous();

        if (! $previous) {
            return $fallback;
        }

        $appUrl = rtrim(config('app.url'), '/');

        if ($appUrl !== '' && str_starts_with($previous, $appUrl)) {
            $previous = substr($previous, strlen($appUrl));
        }

        if (! str_starts_with($previous, '/')) {
            return $fallback;
        }

        return $this->resolveRedirectTo($previous, $fallback);
    }

    private function resolveRedirectTo(?string $redirectTo, string $fallback): string
    {
        $redirectTo = trim((string) $redirectTo);

        if ($redirectTo === '') {
            return $fallback;
        }

        if (! Str::startsWith($redirectTo, '/')) {
            return $fallback;
        }

        if (Str::startsWith($redirectTo, ['//', '/\\'])) {
            return $fallback;
        }

        if (Str::startsWith($redirectTo, ['/login', '/register'])) {
            return $fallback;
        }

        return $redirectTo;
    }
}