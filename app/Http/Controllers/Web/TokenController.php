<?php

namespace App\Http\Controllers\Web;

use App\Actions\Token\RedeemTokenPrizeAction;
use App\Http\Controllers\Controller;
use App\Models\GachaEventPrize;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class TokenController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $redeemItems = GachaEventPrize::query()
        ->with(['event', 'prize'])
        ->where('is_token_redeemable', true)
        ->where('token_redeem_price', '>', 0)
        ->where('redeem_stock_left', '>', 0)
        ->whereHas('event', function ($query) {
            $query->where('status', 'published');
        })
        ->whereHas('prize', function ($query) {
            $query->where('status', 'active');
        })
        ->orderBy('sort_order')
        ->orderBy('id')
        ->paginate(12)
        ->withQueryString()
        ->through(fn (GachaEventPrize $item) => $this->mapRedeemItem($item));

        return Inertia::render('Token/Index', [
            'tokenBalance' => (int) ($user->token_balance ?? 0),
            'redeemItems' => $redeemItems,
        ]);
    }

    public function show(Request $request, string $prizeSlug)
    {
        $user = $request->user();

        $gachaEventPrize = GachaEventPrize::query()
            ->with(['event', 'prize'])
            ->where('is_token_redeemable', true)
            ->where('token_redeem_price', '>', 0)
            ->whereHas('event', function ($query) {
                $query->where('status', 'published');
            })
            ->whereHas('prize', function ($query) use ($prizeSlug) {
                $query->where('slug', $prizeSlug)
                    ->where('status', 'active');
            })
            ->firstOrFail();

        return Inertia::render('Token/Show', [
            'tokenBalance' => (int) ($user->token_balance ?? 0),
            'item' => $this->mapRedeemItem($gachaEventPrize),
        ]);
    }

    public function redeem(
        Request $request,
        string $prizeSlug,
        RedeemTokenPrizeAction $redeemTokenPrizeAction
    ) {
        try {
            $gachaEventPrize = GachaEventPrize::query()
                ->with(['event', 'prize'])
                ->where('is_token_redeemable', true)
                ->where('token_redeem_price', '>', 0)
                ->whereHas('event', function ($query) {
                    $query->where('status', 'published');
                })
                ->whereHas('prize', function ($query) use ($prizeSlug) {
                    $query->where('slug', $prizeSlug)
                        ->where('status', 'active');
                })
                ->firstOrFail();

            $result = $redeemTokenPrizeAction->execute(
                $request->user(),
                $gachaEventPrize
            );

            return response()->json([
                'message' => 'Redeem token berhasil. Item sudah masuk ke inventory.',
                'data' => [
                    ...$result,
                    'redirect_url' => route('inventory.index'),
                ],
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    protected function mapRedeemItem(GachaEventPrize $item): array
    {
        $prize = $item->prize;
        $event = $item->event;

        return [
            'id' => $item->id,

            /*
             * Slug URL diambil dari tabel gacha_prizes.slug.
             */
            'slug' => $prize?->slug,

            'source_type' => 'gacha_event_prize',
            'gacha_event_id' => $item->gacha_event_id,
            'gacha_event_prize_id' => $item->id,
            'gacha_prize_id' => $item->gacha_prize_id,

            'event_name' => $event?->name,
            'event_slug' => $event?->slug,

            'name' => $item->display_name ?: ($prize?->name ?? '-'),
            'description' => $prize?->description,
            'image' => $prize?->image,
            'rarity_code' => $item->rarity_code,

            'token_price' => (int) $item->token_redeem_price,
            'stock_left' => (int) $item->redeem_stock_left,
            'stock_total' => (int) $item->redeem_stock_total,

            'token_reward' => (int) $item->token_reward,
            'coin_price' => (int) $item->coin_price,
            'sort_order' => (int) $item->sort_order,

            'prize_type' => $prize?->prize_type ?? 'physical',
            'market_price' => (int) ($prize?->market_price ?? 0),
            'sellback_price' => (int) ($item->sellback_price_override ?? $prize?->sellback_price ?? 0),
        ];
    }
}