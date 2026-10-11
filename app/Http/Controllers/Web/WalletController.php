<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index()
    {
        $user = auth()->user()->load('wallet');

        return Inertia::render('Wallet/Index', [
            'wallet' => $user->wallet,
            'tokenBalance' => (int) ($user->token_balance ?? 0),
            'midtransClientKey' => config('midtrans.client_key'),
        ]);
    }
}