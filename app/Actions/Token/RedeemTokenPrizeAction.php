<?php

namespace App\Actions\Token;

use App\Models\GachaEventPrize;
use App\Models\TokenRedeemTransaction;
use App\Models\TokenTransaction;
use App\Models\User;
use App\Models\UserInventory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RedeemTokenPrizeAction
{
    public function execute(User $user, GachaEventPrize $eventPrize): array
    {
        return DB::transaction(function () use ($user, $eventPrize) {
            $eventPrize = GachaEventPrize::query()
                ->with(['event', 'prize'])
                ->whereKey($eventPrize->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $eventPrize->is_token_redeemable) {
                throw new RuntimeException('Item ini tidak tersedia untuk redeem token.');
            }

            if ((int) $eventPrize->token_redeem_price <= 0) {
                throw new RuntimeException('Harga redeem token tidak valid.');
            }

            if ((int) $eventPrize->redeem_stock_left <= 0) {
                throw new RuntimeException('Stok redeem sudah habis.');
            }

            if ($eventPrize->event?->status !== 'published') {
                throw new RuntimeException('Event item redeem tidak aktif.');
            }

            $tokenCost = (int) $eventPrize->token_redeem_price;
            $balanceBefore = (int) ($lockedUser->token_balance ?? 0);

            if ($balanceBefore < $tokenCost) {
                throw new RuntimeException('Saldo token tidak cukup.');
            }

            $balanceAfter = $balanceBefore - $tokenCost;

            $prize = $eventPrize->prize;
            $event = $eventPrize->event;

            $prizeName = $eventPrize->display_name ?: ($prize?->name ?? '-');
            $prizeImage = $prize?->image;
            $prizeType = $prize?->prize_type ?? 'physical';
            $sellbackPrice = $eventPrize->sellback_price_override ?? ($prize?->sellback_price ?? 0);

            $redeemTransaction = TokenRedeemTransaction::create([
                'redeem_no' => $this->generateRedeemNo(),
                'user_id' => $lockedUser->id,
                'gacha_event_prize_id' => $eventPrize->id,
                'gacha_event_id' => $eventPrize->gacha_event_id,
                'gacha_prize_id' => $eventPrize->gacha_prize_id,
                'token_cost' => $tokenCost,
                'status' => 'success',
                'prize_name_snapshot' => $prizeName,
                'prize_image_snapshot' => $prizeImage,
                'prize_type' => $prizeType,
                'sellback_price_snapshot' => $sellbackPrice,
                'meta_json' => [
                    'event_name' => $event?->name,
                    'event_slug' => $event?->slug,
                    'rarity_code' => $eventPrize->rarity_code,
                    'redeem_stock_before' => (int) $eventPrize->redeem_stock_left,
                    'token_balance_before' => $balanceBefore,
                    'token_balance_after' => $balanceAfter,
                ],
            ]);

            $lockedUser->update([
                'token_balance' => $balanceAfter,
            ]);

            TokenTransaction::create([
                'user_id' => $lockedUser->id,
                'type' => 'debit',
                'category' => 'redeem_item',
                'amount' => $tokenCost,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => TokenRedeemTransaction::class,
                'reference_id' => $redeemTransaction->id,
                'description' => 'Redeem token item: ' . $prizeName,
                'meta_json' => [
                    'redeem_no' => $redeemTransaction->redeem_no,
                    'gacha_event_prize_id' => $eventPrize->id,
                    'gacha_prize_id' => $eventPrize->gacha_prize_id,
                    'prize_name' => $prizeName,
                    'token_cost' => $tokenCost,
                ],
            ]);

            $eventPrize->decrement('redeem_stock_left');

            $inventory = UserInventory::create([
                'user_id' => $lockedUser->id,
                'draw_result_id' => null,
                'token_redeem_transaction_id' => $redeemTransaction->id,
                'gacha_prize_id' => $eventPrize->gacha_prize_id,
                'source_event_id' => $eventPrize->gacha_event_id,
                'prize_name_snapshot' => $prizeName,
                'prize_image_snapshot' => $prizeImage,
                'rarity_code_snapshot' => $eventPrize->rarity_code
                    ?? $eventPrize->prize?->rarity_code
                    ?? null,
                'prize_type' => $prizeType,
                'sellback_price' => $sellbackPrice,
                'status' => 'stored',
            ]);

            return [
                'redeem_transaction' => [
                    'id' => $redeemTransaction->id,
                    'redeem_no' => $redeemTransaction->redeem_no,
                    'token_cost' => $tokenCost,
                    'status' => $redeemTransaction->status,
                ],
                'inventory' => [
                    'id' => $inventory->id,
                    'name' => $inventory->prize_name_snapshot,
                    'status' => $inventory->status,
                ],
                'token' => [
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ],
                'stock' => [
                    'redeem_stock_left' => max(0, (int) $eventPrize->redeem_stock_left - 1),
                ],
            ];
        });
    }

    protected function generateRedeemNo(): string
    {
        return 'RDM-' . now()->format('YmdHis') . '-' . strtoupper(str()->random(6));
    }
}