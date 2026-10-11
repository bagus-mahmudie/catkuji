<?php

namespace App\Actions\Token;

use App\Models\TokenTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditTokenAction
{
    public function execute(
        User $user,
        int|string $amount,
        string $category,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?array $meta = null
    ): TokenTransaction {
        $amount = (int) $amount;

        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah token harus lebih dari 0.');
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
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = (int) ($lockedUser->token_balance ?? 0);
            $after = $before + $amount;

            $lockedUser->update([
                'token_balance' => $after,
            ]);

            return TokenTransaction::create([
                'user_id' => $lockedUser->id,
                'type' => 'credit',
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