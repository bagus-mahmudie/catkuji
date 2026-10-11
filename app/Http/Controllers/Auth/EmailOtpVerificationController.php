<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerifyEmailOtpNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmailOtpVerificationController extends Controller
{
    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'context' => ['nullable', 'in:login,register'],
        ], [
            'code.required' => 'Kode verifikasi wajib diisi.',
            'code.digits' => 'Kode verifikasi harus 6 digit.',
        ]);

        $context = $validated['context'] ?? 'login';

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return back()
                ->withErrors(['email' => 'Email tidak ditemukan.'])
                ->with('showEmailVerificationModal', true)
                ->with('verificationEmail', $validated['email'])
                ->with('verificationContext', $context);
        }

        if (! $user->isActive() || $user->hasVerifiedEmail()
            || (int) $request->session()->get('email_verification_user_id') !== $user->id
            || ($request->user() && $request->user()->id !== $user->id)) {
            return back()->withErrors(['code' => 'Permintaan verifikasi tidak valid. Silakan login kembali.']);
        }

        $key = 'email-otp:'.$user->id;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Terlalu banyak percobaan. Coba lagi dalam 10 menit.']);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 600);

        $verified = DB::transaction(function () use ($user, $validated) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $lockedUser->isActive() || $lockedUser->hasVerifiedEmail()) {
                return false;
            }
            $verification = EmailVerificationCode::query()
                ->where('user_id', $lockedUser->id)
                ->where('email', $lockedUser->email)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')->lockForUpdate()->first();

            if (! $verification || ! Hash::check($validated['code'], $verification->code)) {
                return false;
            }
            $verification->update(['used_at' => now()]);
            $lockedUser->markEmailAsVerified();
            return true;
        });

        if (! $verified) {
            return back()
                ->withErrors(['code' => 'Kode verifikasi tidak valid atau sudah kadaluarsa.'])
                ->with('showEmailVerificationModal', true)
                ->with('verificationEmail', $validated['email'])
                ->with('verificationContext', $context);
        }

        $request->session()->forget('email_verification_user_id');
        \Illuminate\Support\Facades\RateLimiter::clear($key);
        $user->refresh();
        event(new \Illuminate\Auth\Events\Verified($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Email berhasil diverifikasi.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'context' => ['nullable', 'in:login,register'],
        ]);

        $context = $validated['context'] ?? 'login';

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return back()
                ->withErrors(['email' => 'Email tidak ditemukan.'])
                ->with('showEmailVerificationModal', true)
                ->with('verificationEmail', $validated['email'])
                ->with('verificationContext', $context);
        }

        if ($user->hasVerifiedEmail()) {
            return back()
                ->with('status', 'Email sudah diverifikasi. Silakan login.');
        }

        if (! $user->isActive()
            || (int) $request->session()->get('email_verification_user_id') !== $user->id
            || ($request->user() && $request->user()->id !== $user->id)) {
            return back()->withErrors(['email' => 'Permintaan verifikasi tidak valid. Silakan login kembali.']);
        }

        $this->sendOtp($user);

        return back()
            ->with('status', 'Kode verifikasi baru berhasil dikirim ke email kamu.')
            ->with('showEmailVerificationModal', true)
            ->with('verificationEmail', $user->email)
            ->with('verificationContext', $context);
    }

    public function notice(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }
        $request->session()->put('email_verification_user_id', $user->id);
        return redirect()->route('home')
            ->with('showEmailVerificationModal', true)
            ->with('verificationEmail', $user->email)
            ->with('verificationContext', 'login');
    }

    public static function sendOtp(User $user): void
    {
        EmailVerificationCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $plainCode = (string) random_int(100000, 999999);

        EmailVerificationCode::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code' => Hash::make($plainCode),
            'expires_at' => now()->addMinutes(10),
        ]);

        request()->session()->put('email_verification_user_id', $user->id);

        try {
            $user->notify(new VerifyEmailOtpNotification($plainCode));
        } catch (\Throwable $e) {
            \Log::error('Gagal mengirim OTP email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}