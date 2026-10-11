<?php

namespace App\Actions\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DebitWalletAction
{
    public function execute(
        User $user,
        int|string $amount,
        string $category,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?array $meta = null
    ): WalletTransaction {
        $amount = (int) $amount;

        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah coin harus lebih dari 0.');
        }

        return DB::transaction(function () use (
            $user,
            $amount,
            $category,
            $referenceType,
            $referenceId,
            $description,
            $meta
        ) {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new RuntimeException('Wallet tidak ditemukan.');
            }

            if (! $wallet->isActive()) {
                throw new RuntimeException('Wallet tidak aktif.');
            }

            $before = (int) $wallet->balance;

            if ($before < $amount) {
                throw new RuntimeException('Saldo coin tidak cukup.');
            }

            $after = $before - $amount;

            $wallet->update([
                'balance' => $after,
                'currency' => 'COIN',
            ]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'debit',
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'meta_json' => $meta,
            ]);
        });
    }
}