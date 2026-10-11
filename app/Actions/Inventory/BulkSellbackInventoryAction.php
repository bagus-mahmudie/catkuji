<?php

namespace App\Actions\Inventory;

use App\Actions\Wallet\CreditWalletAction;
use App\Models\SellbackTransaction;
use App\Models\User;
use App\Models\UserInventory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BulkSellbackInventoryAction
{
    public function __construct(
        protected CreditWalletAction $creditWalletAction
    ) {
    }

    public function execute(User $user, array $items): array
    {
        return DB::transaction(function () use ($user, $items) {
            $lockedInventories = collect();
            $summaryItems = collect();

            foreach ($items as $item) {
                $gachaPrizeId = (int) ($item['gacha_prize_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);

                if ($gachaPrizeId <= 0 || $quantity <= 0) {
                    continue;
                }

                $inventories = UserInventory::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'stored')
                    ->where('gacha_prize_id', $gachaPrizeId)
                    ->where('sellback_price', '>', 0)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->limit($quantity)
                    ->get();

                if ($inventories->count() < $quantity) {
                    throw new RuntimeException('Sebagian item sudah berubah, jumlah item tidak mencukupi, atau item tidak memiliki nilai sellback.');
                }

                $lockedInventories = $lockedInventories->merge($inventories);

                $first = $inventories->first();

                $summaryItems->push([
                    'gacha_prize_id' => $gachaPrizeId,
                    'name' => $first->prize_name_snapshot,
                    'image' => $first->prize_image_snapshot,
                    'prize_type' => $first->prize_type,
                    'sellback_price' => (int) $first->sellback_price,
                    'quantity' => $quantity,
                    'subtotal' => (int) $first->sellback_price * $quantity,
                ]);
            }

            if ($lockedInventories->isEmpty()) {
                throw new RuntimeException('Tidak ada item yang dipilih untuk dijual.');
            }

            $totalAmount = (int) $lockedInventories->sum(fn ($inventory) => (int) $inventory->sellback_price);
            $totalItems = (int) $lockedInventories->count();

            if ($totalAmount <= 0) {
                throw new RuntimeException('Total sellback harus lebih dari 0 coin.');
            }

            $walletTransaction = $this->creditWalletAction->execute(
                user: $user,
                amount: $totalAmount,
                category: 'sellback',
                referenceType: 'inventory_bulk',
                referenceId: null,
                description: 'Bulk sellback inventory',
                meta: [
                    'total_items' => $totalItems,
                    'items' => $summaryItems->values()->all(),
                ],
            );

            foreach ($lockedInventories as $inventory) {
                SellbackTransaction::create([
                    'user_id' => $user->id,
                    'inventory_id' => $inventory->id,
                    'wallet_transaction_id' => $walletTransaction->id,
                    'amount' => (int) $inventory->sellback_price,
                    'status' => 'completed',
                    'meta_json' => [
                        'inventory_id' => $inventory->id,
                        'gacha_prize_id' => $inventory->gacha_prize_id,
                        'draw_result_id' => $inventory->draw_result_id,
                        'source_event_id' => $inventory->source_event_id,
                        'prize_name' => $inventory->prize_name_snapshot,
                        'prize_type' => $inventory->prize_type,
                    ],
                ]);

                $inventory->update([
                    'status' => 'sellbacked',
                    'sellbacked_at' => now(),
                ]);
            }

            return [
                'total_amount' => $totalAmount,
                'total_items' => $totalItems,
                'items' => $summaryItems->values()->all(),
            ];
        });
    }
}