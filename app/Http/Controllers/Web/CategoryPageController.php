<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GachaCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryPageController extends Controller
{
    public function show(Request $request, GachaCategory $category)
    {
        if ($category->is_archived || ($category->card_style === 'promo' && $category->promo_ends_at?->isPast())) {
            abort(404);
        }
        $type = $request->string('type')->toString();

        $eventsQuery = $category->events()
            ->published()
            ->when($type !== '' && $type !== 'semua', function ($query) use ($type) {
                $query->where('event_type', $type);
            })
            ->latest('sort_order')
            ->latest('id');

        $events = $eventsQuery->get();

        $availableTypes = $category->events()
            ->published()
            ->whereNotNull('event_type')
            ->distinct()
            ->pluck('event_type')
            ->filter()
            ->values()
            ->map(fn ($item) => [
                'value' => $item,
                'label' => match ($item) {
                    'smartphone' => 'Smartphone',
                    'console' => 'Console',
                    'laptop-pc' => 'Laptop/PC',
                    'lainnya' => 'Lainnya',
                    default => ucfirst(str_replace('-', ' ', $item)),
                },
            ]);

        $coinRate = (int) config('gacha.coin_rate', 1000);

        $events = $events->map(function ($event) use ($coinRate) {
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
                'is_active' => $event->isActive(),
            ];
        });

        return Inertia::render('Category/Show', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description ?? null,
                'category_glow_color' => $category->category_glow_color ?: '#F66455',
                'category_glow_position' => $category->category_glow_position ?: 'right',
            ],
            'events' => $events,
            'filters' => [
                'type' => $type,
            ],
            'availableTypes' => $availableTypes,
        ]);
    }
}
