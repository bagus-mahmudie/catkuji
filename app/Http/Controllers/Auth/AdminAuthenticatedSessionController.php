<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class AdminAuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        if (! $user || ! in_array($user->role, ['admin', 'super_admin'], true)) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun ini tidak memiliki akses admin.',
            ]);
        }

        if (
            ! $user ||
            ! in_array($user->role, ['admin', 'super_admin'], true) ||
            $user->status !== 'active'
        ) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun ini belum memiliki akses admin aktif.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Email admin belum diverifikasi.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function register(Request $request): RedirectResponse
    {
        $phoneDigits = preg_replace('/\D+/', '', (string) $request->input('phone', ''));
        $phoneDigits = (string) preg_replace('/^(62|0)+/', '', $phoneDigits);

        $request->merge([
            'phone' => $phoneDigits !== '' ? '+62'.$phoneDigits : null,
        ]);

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+62[0-9]{8,15}$/',
                Rule::unique('users', 'phone'),
            ],
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

        User::create([
            'name' => $generatedName !== '' ? $generatedName : 'Admin '.Str::upper(Str::random(5)),
            'email' => Str::lower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'pending_admin',
            'status' => 'pending',
            'admin_requested_at' => now(),
        ]);

        return redirect()
        ->route('admin.login')
        ->with('status', 'Pendaftaran admin berhasil dikirim. Tunggu persetujuan dari admin.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}