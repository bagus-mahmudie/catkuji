<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfilePageController extends Controller
{
    protected function sharedProfileProps(Request $request): array
    {
        $user = $request->user();

        $user->loadMissing('wallet');

        return [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ?? null,
            ],
            'tokenBalance' => (int) ($user->token_balance ?? 0),
            'catKoinBalance' => (int) ($user->wallet?->balance ?? 0),
        ];
    }

    public function language(Request $request): Response
    {
        return Inertia::render('Profile/Language', [
            ...$this->sharedProfileProps($request),
            'currentLanguage' => 'id',
        ]);
    }

    public function terms(Request $request): Response
    {
        return Inertia::render('Profile/Terms', [
            ...$this->sharedProfileProps($request),
        ]);
    }

    public function privacy(Request $request): Response
    {
        return Inertia::render('Profile/Privacy', [
            ...$this->sharedProfileProps($request),
        ]);
    }

    public function token(Request $request): Response
    {
        return Inertia::render('Profile/Token', [
            ...$this->sharedProfileProps($request),
        ]);
    }
}