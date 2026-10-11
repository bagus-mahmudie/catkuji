<?php

namespace App\Actions\Gacha;

use App\Actions\Token\CreditTokenAction;
use App\Actions\Wallet\DebitWalletAction;
use App\Models\GachaEvent;
use App\Models\DrawTransaction;
use App\Models\DrawResult;
use App\Models\UserPack;
use App\Models\UserPackItem;
use App\Services\Gacha\PrizeSelectorService;
use Illuminate\Support\Facades\DB;

class ExecuteDrawAction
{
    public function __construct(
        protected CreditTokenAction $creditTokenAction,
        protected DebitWalletAction $debitWalletAction
    ) {}

    public function execute($user, $event, int $drawCount, string $idempotencyKey)
    {
        return DB::transaction(function () use ($user, $event, $drawCount, $idempotencyKey) {
        $event = GachaEvent::query()
            ->whereKey($event->id)
            ->lockForUpdate()
            ->firstOrFail();

        $existingTransaction = $this->findExistingTransactionByIdempotencyKey(
            $user,
            $event,
            $idempotencyKey
        );

        if ($existingTransaction) {
            return $this->buildExistingDrawResponse($existingTransaction);
        }

        if (! $event->isActive()) {
            throw new \Exception('Event tidak aktif');
        }

        if ($event->draw_mode === 'single' && $drawCount > 1) {
            throw new \Exception('Event ini hanya mendukung single draw.');
        }

        if ($event->draw_mode === 'multi' && $drawCount < 2) {
            throw new \Exception('Event ini hanya mendukung multi draw.');
        }

        if ((int) $event->stock_left < $drawCount) {
            throw new \Exception('Stok event tidak cukup');
        }

        if ($event->max_draw_per_user_per_day) {
            $todayDrawCount = DrawTransaction::query()
                ->where('user_id', $user->id)
                ->where('gacha_event_id', $event->id)
                ->where('status', 'success')
                ->whereDate('created_at', now()->toDateString())
                ->sum('draw_count');

            if ($todayDrawCount + $drawCount > (int) $event->max_draw_per_user_per_day) {
                throw new \Exception('Batas draw harian untuk event ini sudah tercapai.');
            }
        }

        $totalCoin = (int) $event->price_per_draw * $drawCount;

            $drawTransaction = DrawTransaction::create([
                'draw_no' => uniqid('DRAW-'),
                'user_id' => $user->id,
                'gacha_event_id' => $event->id,
                'payment_source' => 'wallet',
                'draw_count' => $drawCount,
                'total_cost' => $totalCoin,
                'status' => 'success',
                'request_idempotency_key' => $idempotencyKey,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $walletTransaction = $this->debitWalletAction->execute(
                user: $user,
                amount: $totalCoin,
                category: 'draw',
                referenceType: DrawTransaction::class,
                referenceId: $drawTransaction->id,
                description: 'Draw event: ' . $event->name,
                meta: [
                    'unit' => 'COIN',
                    'event_id' => $event->id,
                    'event_name' => $event->name,
                    'draw_count' => $drawCount,
                    'price_per_draw' => (int) $event->price_per_draw,
                    'total_coin' => $totalCoin,
                ],
            );

            $userPack = UserPack::create([
                'user_id' => $user->id,
                'draw_transaction_id' => $drawTransaction->id,
                'pack_no' => uniqid('PACK-'),
                'pack_count' => $drawCount,
                'status' => 'unopened',
            ]);

            $selector = new PrizeSelectorService();

            $eventPrizes = $event->eventPrizes()
                ->with('prize')
                ->where('current_stock', '>', 0)
                ->lockForUpdate()
                ->get();

            $resultsPayload = [];
            $totalTokenReward = 0;

            for ($i = 0; $i < $drawCount; $i++) {
                $selected = $selector->pick($eventPrizes);

                if ((int) $selected->current_stock <= 0) {
                    throw new \Exception('Stock hadiah habis saat draw');
                }

                $selected->current_stock = max(0, (int) $selected->current_stock - 1);
                $selected->save();

                $event->stock_left = max(0, (int) $event->stock_left - 1);
                $event->save();

                $prize = $selected->prize;
                $tokenReward = (int) ($selected->token_reward ?? 0);
                $prizeName = $selected->display_name ?: ($prize?->name ?? '-');

                $sellbackPrice = (int) (
                    $selected->sellback_price_override
                    ?? $selected->prize?->sellback_price
                    ?? 0
                );

                $drawResult = DrawResult::create([
                    'draw_transaction_id' => $drawTransaction->id,
                    'user_id' => $user->id,
                    'gacha_event_id' => $event->id,
                    'gacha_event_prize_id' => $selected->id,
                    'gacha_prize_id' => $selected->gacha_prize_id,
                    'rarity_code' => $selected->rarity_code,
                    'result_position' => $i + 1,
                    'prize_name_snapshot' => $prizeName,
                    'prize_image_snapshot' => $prize?->image,
                    'market_price_snapshot' => $prize?->market_price ?? 0,
                    'sellback_price_snapshot' => $sellbackPrice,
                    'token_reward_snapshot' => $tokenReward,
                    'probability_snapshot_json' => [
                        'draw_weight' => (int) $selected->draw_weight,
                        'token_reward' => $tokenReward,
                    ],
                ]);

                if ($tokenReward > 0) {
                    $totalTokenReward += $tokenReward;

                    $this->creditTokenAction->execute(
                        user: $user,
                        amount: $tokenReward,
                        category: 'draw_reward',
                        referenceType: DrawResult::class,
                        referenceId: $drawResult->id,
                        description: 'Token reward dari draw prize: ' . $prizeName,
                        meta: [
                            'event_id' => $event->id,
                            'event_name' => $event->name,
                            'draw_transaction_id' => $drawTransaction->id,
                            'draw_result_id' => $drawResult->id,
                            'gacha_event_prize_id' => $selected->id,
                            'gacha_prize_id' => $selected->gacha_prize_id,
                            'prize_name' => $prizeName,
                            'token_reward' => $tokenReward,
                        ],
                    );
                }

                $packItem = UserPackItem::create([
                    'user_pack_id' => $userPack->id,
                    'draw_result_id' => $drawResult->id,
                    'gacha_prize_id' => $selected->gacha_prize_id,
                    'prize_name_snapshot' => $prizeName,
                    'prize_image_snapshot' => $prize?->image,
                    'rarity_code' => $selected->rarity_code,
                    'sellback_price' => $sellbackPrice,
                    'token_reward' => $tokenReward,
                    'prize_type' => $prize?->prize_type ?? 'physical',
                ]);

                $resultsPayload[] = [
                    'draw_result_id' => $drawResult->id,
                    'pack_item_id' => $packItem->id,
                    'gacha_event_prize_id' => $selected->id,
                    'gacha_prize_id' => $selected->gacha_prize_id,
                    'position' => $i + 1,
                    'name' => $drawResult->prize_name_snapshot,
                    'image' => $drawResult->prize_image_snapshot,
                    'rarity_code' => $drawResult->rarity_code,
                    'sellback_price' => (int) $packItem->sellback_price,
                    'market_price' => (float) ($drawResult->market_price_snapshot ?? 0),
                    'token_reward' => $tokenReward,
                    'prize_type' => $packItem->prize_type,
                ];
            }

            $freshUser = $user->fresh();

            return [
                'transaction' => [
                    'id' => $drawTransaction->id,
                    'draw_no' => $drawTransaction->draw_no,
                    'draw_count' => (int) $drawTransaction->draw_count,
                    'total_cost' => (float) $drawTransaction->total_cost,
                    'event_name' => $event->name,
                ],
                'wallet' => [
                    'balance_before' => (int) $walletTransaction->balance_before,
                    'balance_after' => (int) $walletTransaction->balance_after,
                ],
                'token' => [
                    'reward_total' => $totalTokenReward,
                    'balance_after' => (int) ($freshUser->token_balance ?? 0),
                ],
                'pack' => [
                    'id' => $userPack->id,
                    'pack_no' => $userPack->pack_no,
                    'pack_count' => (int) $userPack->pack_count,
                    'status' => $userPack->status,
                ],
                'results' => $resultsPayload,
            ];
        });
    }

    protected function findExistingTransactionByIdempotencyKey($user, $event, string $idempotencyKey)
    {
        return DrawTransaction::query()
            ->where('user_id', $user->id)
            ->where('gacha_event_id', $event->id)
            ->where('request_idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }

    protected function buildExistingDrawResponse($drawTransaction): array
    {
        $drawTransaction->load([
            'user.wallet',
            'event',
            'results.inventory',
            'pack',
            'pack.items',
        ]);

        $totalTokenReward = $drawTransaction->results
            ->sum(fn ($result) => (int) ($result->token_reward_snapshot ?? 0));

        return [
            'transaction' => [
                'id' => $drawTransaction->id,
                'draw_no' => $drawTransaction->draw_no,
                'draw_count' => (int) $drawTransaction->draw_count,
                'total_cost' => (float) $drawTransaction->total_cost,
                'event_name' => optional($drawTransaction->event)->name,
                'is_duplicate' => true,
            ],
            'wallet' => [
                'balance_before' => null,
                'balance_after' => (int) optional($drawTransaction->user?->wallet)->balance,
            ],
            'token' => [
                'reward_total' => $totalTokenReward,
                'balance_after' => (int) ($drawTransaction->user?->token_balance ?? 0),
            ],
            'pack' => $drawTransaction->pack ? [
                'id' => $drawTransaction->pack->id,
                'pack_no' => $drawTransaction->pack->pack_no,
                'pack_count' => (int) $drawTransaction->pack->pack_count,
                'status' => $drawTransaction->pack->status,
            ] : null,
            'results' => $drawTransaction->results->map(function ($result) {
                return [
                    'draw_result_id' => $result->id,
                    'inventory_id' => optional($result->inventory)->id,
                    'gacha_event_prize_id' => $result->gacha_event_prize_id,
                    'gacha_prize_id' => $result->gacha_prize_id,
                    'position' => (int) $result->result_position,
                    'name' => $result->prize_name_snapshot,
                    'image' => $result->prize_image_snapshot,
                    'rarity_code' => $result->rarity_code,
                    'sellback_price' => (float) ($result->sellback_price_snapshot ?? 0),
                    'market_price' => (float) ($result->market_price_snapshot ?? 0),
                    'token_reward' => (int) ($result->token_reward_snapshot ?? 0),
                    'prize_type' => optional($result->inventory)->prize_type,
                    'inventory_status' => optional($result->inventory)->status,
                ];
            })->values()->all(),
        ];
    }
}