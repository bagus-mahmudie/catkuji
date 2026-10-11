<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingController extends Controller
{
    public function admins(Request $request): Response
    {
        if ($request->user()->role !== 'super_admin') {
            abort(403, 'Akses hanya untuk super admin.');
        }

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $admins = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where(function ($query) {
                $query
                    ->whereNotNull('admin_requested_at')
                    ->orWhereIn('role', ['pending_admin', 'admin', 'super_admin']);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('admin_requested_at')
            ->latest()
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'requested_at' => optional($user->admin_requested_at)->translatedFormat('d M Y H:i'),
                'approved_at' => optional($user->admin_approved_at)->translatedFormat('d M Y H:i'),
                'avatar_url' => $this->avatarUrl($user),
            ]);

        return Inertia::render('Admin/Settings/Admins', [
            'admins' => $admins,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'roleOptions' => [
                ['label' => 'Pending Admin', 'value' => 'pending_admin'],
                ['label' => 'Admin', 'value' => 'admin'],
                ['label' => 'Super Admin', 'value' => 'super_admin'],
                ['label' => 'User', 'value' => 'user'],
            ],
            'statusOptions' => [
                ['label' => 'All Status', 'value' => 'all'],
                ['label' => 'Pending', 'value' => 'pending'],
                ['label' => 'Active', 'value' => 'active'],
                ['label' => 'Inactive', 'value' => 'inactive'],
                ['label' => 'Rejected', 'value' => 'rejected'],
            ],
        ]);
    }

    private function avatarUrl(User $user): string
    {
        if (! empty($user->avatar)) {
            if (str_starts_with($user->avatar, 'http://') || str_starts_with($user->avatar, 'https://')) {
                return $user->avatar;
            }

            return asset('storage/'.$user->avatar);
        }

        return 'https://ui-avatars.com/api/?name='
            .urlencode($user->name ?: $user->email)
            .'&background=ff675c&color=fff';
    }

    public function updateAdmin(User $user, Request $request): RedirectResponse
    {
        if ($request->user()->role !== 'super_admin') {
            abort(403, 'Akses hanya untuk super admin.');
        }

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'role' => 'Kamu tidak bisa mengubah role akun sendiri.',
            ]);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in(['pending_admin', 'admin', 'super_admin', 'user'])],
            'status' => ['required', Rule::in(['pending', 'active', 'inactive', 'rejected'])],
        ]);

        $payload = [
            'role' => $validated['role'],
            'status' => $validated['status'],
        ];

        if (in_array($validated['role'], ['admin', 'super_admin'], true) && $validated['status'] === 'active') {
            $payload['admin_approved_at'] = now();
            $payload['admin_approved_by'] = $request->user()->id;
            $payload['email_verified_at'] = $user->email_verified_at ?? now();
        }

        if ($validated['role'] === 'pending_admin') {
            $payload['status'] = 'pending';
        }

        if ($validated['status'] === 'rejected') {
            $payload['role'] = 'pending_admin';
        }

        $user->update($payload);

        return back()->with('success', 'Data admin berhasil diperbarui.');
    }
}