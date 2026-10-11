<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GachaEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $coinRate = (int) config('gacha.coin_rate', 1000);

        $events = $user
            ->wishlistEvents()
            ->with('category:id,category_glow_color,category_glow_position')
            ->published()
            ->latest('sort_order')
            ->latest('id')
            ->paginate(6)
            ->through(function ($event) use ($coinRate) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'thumbnail_image' => $event->thumbnail_image,
                    'badge_label' => $event->badge_label,
                    'price_per_draw' => (int) $event->price_per_draw,
                    'price_rupiah' => (int) $event->price_per_draw * $coinRate,
                    'stock_left' => (int) $event->stock_left,
                    'stock_total' => (int) $event->stock_total,
                    'category_glow_color' => $event->category?->category_glow_color ?: '#F66455',
                    'category_glow_position' => $event->category?->category_glow_position ?: 'right',
                    'is_active' => method_exists($event, 'isActive')
                        ? $event->isActive()
                        : true,
                ];
            })
            ->withQueryString();

        return Inertia::render('Profile/Wishlist', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ?? null,
            ],
            'tokenBalance' => (int) ($user->token_balance ?? 0),
            'catKoinBalance' => (int) ($user->wallet?->balance ?? 0),
            'events' => $events,
        ]);
    }

    public function store(Request $request, GachaEvent $event)
    {
        $request->user()
            ->wishlistEvents()
            ->syncWithoutDetaching([$event->id]);

        return back(303);
    }

    public function destroy(Request $request, GachaEvent $event)
    {
        $request->user()
            ->wishlistEvents()
            ->detach($event->id);

        return back(303);
    }
}