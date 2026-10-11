<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Storage;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => fn () => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'avatar' => $request->user()->avatar,
                        'avatar_url' => $this->resolveAvatarUrl(
                            $request->user()->avatar,
                            $request->user()->name
                        ),
                        'role' => $request->user()->role,
                        'status' => $request->user()->status,
                        'token_balance' => (int) ($request->user()->token_balance ?? 0),
                    ]
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
                'showEmailVerificationModal' => fn () => (bool) $request->session()->get('showEmailVerificationModal', false),
                'verificationEmail' => fn () => (string) $request->session()->get('verificationEmail', ''),
                'verificationContext' => fn () => (string) $request->session()->get('verificationContext', 'login'),
                'created_category_id' => fn () => $request->session()->get('created_category_id'),
            ],

            'walletBalance' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return 0;
                }

                $user->loadMissing('wallet');

                return (int) ($user->wallet?->balance ?? 0);
            },

            'tokenBalance' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return 0;
                }

                return (int) ($user->token_balance ?? 0);
            },

            'catKoinBalance' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return 0;
                }

                $user->loadMissing('wallet');

                return (int) ($user->wallet?->balance ?? 0);
            },

            'walletRupiahEquivalent' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return 0;
                }

                $user->loadMissing('wallet');

                $walletBalance = (int) ($user->wallet?->balance ?? 0);
                $coinRate = (int) config('gacha.coin_rate', 1000);

                return $walletBalance * $coinRate;
            },

            'wishlistEventIds' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return [];
                }

                return $user->wishlistEvents()
                    ->pluck('gacha_events.id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            },
        ]);
    }

    private function resolveAvatarUrl(?string $avatar, ?string $name): string
    {
        if ($avatar) {
            if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
                return $avatar;
            }

            if (str_starts_with($avatar, '/storage/')) {
                return $avatar;
            }

            if (str_starts_with($avatar, 'storage/')) {
                return asset($avatar);
            }

            return asset('storage/' . ltrim($avatar, '/'));
        }

        return 'https://ui-avatars.com/api/?name='
            . urlencode($name ?: 'User')
            . '&background=ff675c&color=ffffff';
    }
}