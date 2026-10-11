<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    public function create(Request $request): RedirectResponse
    {
        $target = $this->resolvePreviousPage($request, '/');

        return redirect()->to($target.(str_contains($target, '?') ? '&' : '?').'auth=register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $phoneDigits = preg_replace('/\D+/', '', (string) $request->input('phone', ''));
        $phoneDigits = (string) preg_replace('/^(62|0)+/', '', $phoneDigits);

        $request->merge([
            'phone' => $phoneDigits !== '' ? '+62'.$phoneDigits : null,
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+62[0-9]{8,15}$/', Rule::unique('users', 'phone')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ], [
            'phone.regex' => 'Nomor HP harus menggunakan format nomor Indonesia yang valid.',
            'terms.accepted' => 'Kamu harus menyetujui syarat dan ketentuan terlebih dahulu.',
        ]);

        $generatedName = (string) Str::of($validated['email'])
            ->before('@')
            ->replace(['.', '_', '-'], ' ')
            ->squish()
            ->title();

        $user = User::create([
            'name' => $generatedName !== '' ? $generatedName : 'User '.Str::upper(Str::random(5)),
            'email' => Str::lower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        // event(new Registered($user));

        EmailOtpVerificationController::sendOtp($user);

        return back()
            ->with('status', 'Kami sudah mengirimkan kode verifikasi ke email kamu.')
            ->with('showEmailVerificationModal', true)
            ->with('verificationEmail', $user->email)
            ->with('verificationContext', 'register');
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

        if (! str_starts_with($redirectTo, '/')) {
            return $fallback;
        }

        if (str_starts_with($redirectTo, '//') || str_starts_with($redirectTo, '/\\')) {
            return $fallback;
        }

        if (str_starts_with($redirectTo, '/login') || str_starts_with($redirectTo, '/register')) {
            return $fallback;
        }

        return $redirectTo;
    }
}