<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GachaEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GachaEventController extends Controller
{
    public function show(Request $request, GachaEvent $event)
    {
        $event->load([
            'category:id,name,slug',
            'activeRules:id,gacha_event_id,rule_type,config_json,version,is_active',
        ]);

        $user = $request->user();
        $wallet = $user?->wallet;

        $walletBalance = (int) ($wallet?->balance ?? 0);
        $tokenBalance = (int) ($user?->token_balance ?? 0);
        $coinRate = (int) config('gacha.coin_rate', 1000);
        $catKoinBalance = $walletBalance;

        $eventStockTotal = (int) ($event->stock_total ?? 0);
        $eventStockLeft = (int) ($event->stock_left ?? 0);

        $prizes = $event->eventPrizes()
            ->with('prize:id,name,slug,description,image,category,prize_type,rarity_code,market_price,sellback_price,metadata_json')
            ->orderByRaw("
                CASE
                    WHEN rarity_code = 'UR' THEN 1
                    WHEN rarity_code = 'SSR' THEN 2
                    WHEN rarity_code = 'SR' THEN 3
                    WHEN rarity_code = 'R' THEN 4
                    WHEN rarity_code = 'C' THEN 5
                    ELSE 6
                END
            ")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString()
            ->through(function ($eventPrize) {
                $prize = $eventPrize->prize;
                $metadata = is_array($prize?->metadata_json)
                    ? $prize->metadata_json
                    : [];

                return [
                    'id' => $eventPrize->id,
                    'gacha_prize_id' => $eventPrize->gacha_prize_id,
                    'name' => $eventPrize->display_name ?: ($prize?->name ?? '-'),
                    'slug' => $prize?->slug,
                    'description' => $prize?->description,
                    'image' => $prize?->image,
                    'rarity_code' => filled($eventPrize->rarity_code)
                        ? $eventPrize->rarity_code
                        : 'C',
                    'sellback_price' => (int) (
                        $eventPrize->sellback_price_override
                        ?: $prize?->sellback_price
                        ?: 0
                    ),
                    'coin_price' => (int) ($eventPrize->coin_price ?? 0),
                    'token_reward' => (int) ($eventPrize->token_reward ?? ($metadata['ticket_reward'] ?? 0)),
                    'market_price' => (float) ($prize?->market_price ?? 0),
                    'stock_total' => (int) $eventPrize->initial_stock,
                    'stock_left' => (int) $eventPrize->current_stock,
                    'is_featured' => (bool) $eventPrize->is_featured,
                    'prize_type' => $prize?->prize_type,
                    'category' => $prize?->category,
                    'sort_order' => (int) $eventPrize->sort_order,
                ];
            });

        return Inertia::render('Events/Show', [
            'event' => [
                'id' => $event->id,
                'slug' => $event->slug,
                'name' => $event->name,
                'description' => $event->description,
                'banner_image' => $event->banner_image,
                'thumbnail_image' => $event->thumbnail_image,
                'badge_label' => $event->badge_label,
                'opened_text' => $event->opened_text ?: 'Dibuka 4 Hari Ini',
                'price_per_draw' => (int) $event->price_per_draw,
                'price_coin' => (int) $event->price_per_draw,
                'price_rupiah' => (int) $event->price_per_draw * $coinRate,
                'price_token' => (int) ($event->price_token ?? 0),
                'draw_mode' => $event->draw_mode,
                'status' => $event->status,
                'start_at' => optional($event->start_at)?->toISOString(),
                'end_at' => optional($event->end_at)?->toISOString(),
                'is_active' => $event->isActive(),
                'category' => $event->category ? [
                    'id' => $event->category->id,
                    'name' => $event->category->name,
                    'slug' => $event->category->slug,
                ] : null,
                'stock_total' => $eventStockTotal,
                'stock_left' => $eventStockLeft,
                'sellback_enabled' => (bool) $event->sellback_enabled,
                'shipping_enabled' => (bool) $event->shipping_enabled,
            ],
            'prizes' => $prizes,
            'rules' => $event->activeRules->map(fn ($rule) => [
                'id' => $rule->id,
                'rule_type' => $rule->rule_type,
                'config_json' => $rule->config_json,
                'version' => $rule->version,
            ])->values(),
            'tokenBalance' => $tokenBalance,
            'catKoinBalance' => $catKoinBalance,
            'walletBalance' => $walletBalance,
        ]);
    }
}