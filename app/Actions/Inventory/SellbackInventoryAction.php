<?php

namespace App\Actions\Inventory;

use App\Actions\Wallet\CreditWalletAction;
use App\Models\SellbackTransaction;
use App\Models\User;
use App\Models\UserInventory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SellbackInventoryAction
{
    public function __construct(
        protected CreditWalletAction $creditWalletAction
    ) {
    }

    public function execute(User $user, UserInventory $inventory): SellbackTransaction
    {
        return DB::transaction(function () use ($user, $inventory) {
            $lockedInventory = UserInventory::query()
                ->whereKey($inventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedInventory->user_id !== (int) $user->id) {
                throw new RuntimeException('Inventory tidak ditemukan untuk user ini.');
            }

            if (! $lockedInventory->isSellbackable()) {
                throw new RuntimeException('Item ini tidak bisa dijual kembali.');
            }

            if ((float) $lockedInventory->sellback_price <= 0) {
                throw new RuntimeException('Item ini tidak memiliki nilai sellback.');
            }

            $existingSellback = SellbackTransaction::query()
                ->where('inventory_id', $lockedInventory->id)
                ->exists();

            if ($existingSellback) {
                throw new RuntimeException('Item ini sudah pernah dijual kembali.');
            }

            $walletTransaction = $this->creditWalletAction->execute(
                user: $user,
                amount: $lockedInventory->sellback_price,
                category: 'sellback',
                referenceType: 'inventory',
                referenceId: $lockedInventory->id,
                description: 'Sellback item ' . $lockedInventory->prize_name_snapshot,
                meta: [
                    'inventory_id' => $lockedInventory->id,
                    'gacha_prize_id' => $lockedInventory->gacha_prize_id,
                    'draw_result_id' => $lockedInventory->draw_result_id,
                    'source_event_id' => $lockedInventory->source_event_id,
                    'prize_name' => $lockedInventory->prize_name_snapshot,
                    'prize_type' => $lockedInventory->prize_type,
                    'sellback_price' => (float) $lockedInventory->sellback_price,
                ],
            );

            $sellback = SellbackTransaction::create([
                'user_id' => $user->id,
                'inventory_id' => $lockedInventory->id,
                'wallet_transaction_id' => $walletTransaction->id,
                'amount' => $lockedInventory->sellback_price,
                'status' => 'completed',
                'meta_json' => [
                    'inventory_id' => $lockedInventory->id,
                    'gacha_prize_id' => $lockedInventory->gacha_prize_id,
                    'draw_result_id' => $lockedInventory->draw_result_id,
                    'source_event_id' => $lockedInventory->source_event_id,
                    'prize_name' => $lockedInventory->prize_name_snapshot,
                    'prize_type' => $lockedInventory->prize_type,
                ],
            ]);

            $lockedInventory->update([
                'status' => 'sellbacked',
                'sellbacked_at' => now(),
            ]);

            return $sellback;
        });
    }
}