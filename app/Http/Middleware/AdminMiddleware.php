<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Middleware ini dipakai untuk semua route /admin.
     * Kalau belum login, arahkan ke /admin/login.
     * Kalau sudah login tapi bukan admin, tampilkan 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            abort(403, 'Kamu tidak memiliki akses ke halaman admin.');
        }

        if ($user->status !== 'active') {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->with('error', 'Akun admin kamu sedang tidak aktif.');
        }

        return $next($request);
    }
}