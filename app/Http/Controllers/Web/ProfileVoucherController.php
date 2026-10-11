<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileVoucherController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Voucher', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ?? null,
            ],
            'tokenBalance' => (int) ($user->token_balance ?? 0),
            'catKoinBalance' => (int) ($user->wallet?->balance ?? 0),
        ]);
    }
}