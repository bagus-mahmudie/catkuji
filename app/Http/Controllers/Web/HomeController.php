<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\GachaCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index(Request $request)
    {

        $selectedCategory = $request->string('category')->toString();
        $search = trim($request->string('search')->toString());

        $banners = Banner::query()
            ->active()
            ->orderBy('sort_order')
            ->latest('id')
            ->get()
            ->map(fn (Banner $banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'image' => $this->bannerImageUrl($banner->image),
                'url' => $banner->url,
                'alt_text' => $banner->alt_text,
                'description' => $banner->description,
            ])
            ->values();

        $categoryModels = GachaCategory::query()->visible()->orderBy('sort_order')->get();
        $allCategories = $categoryModels->map(fn ($category) => $category->only([
            'id', 'name', 'slug', 'card_style', 'promo_left_bg', 'category_glow_color',
            'category_glow_position', 'promo_starts_at', 'promo_ends_at',
        ]));
        $categories = $selectedCategory === '' ? $categoryModels
            : $categoryModels->filter(fn ($category) => (string) $category->id === $selectedCategory)->values();

        // Eager-load per display style: four per standard category, all promo events.
        // Laravel applies the limit per parent with a grouped relation query.
        foreach ($categories->groupBy(fn ($category) => $category->card_style === 'promo' ? 'promo' : 'standard') as $style => $group) {
            $group->load(['events' => function ($query) use ($search, $style) {
                $query->published()
                    ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orderBy('sort_order')->orderBy('id');
                if ($style !== 'promo') {
                    $query->limit(4);
                }
            }]);
        }

        if ($search !== '') {
            $categories = $categories->filter(fn ($category) => $category->events->isNotEmpty())->values();
        }

        $hasAnyEventResults = $categories->contains(function ($category) {
            return $category->events->isNotEmpty();
        });

        $coinRate = (int) config('gacha.coin_rate', 1000);

        $categories = $categories->map(function ($category) use ($coinRate) {
            $category->card_style = $category->card_style ?: 'standard';

            $category->promo_left_bg_url = $category->promo_left_bg
                ? Storage::disk('public')->url($category->promo_left_bg)
                : null;

            $category->category_glow_color = $category->category_glow_color ?: '#F66455';
            $category->category_glow_position = $category->category_glow_position ?: 'right';
            $category->promo_starts_at = optional($category->promo_starts_at)->toIso8601String();
            $category->promo_ends_at = optional($category->promo_ends_at)->toIso8601String();

            $category->setRelation('events', $category->events->map(function ($event) use ($coinRate) {
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
            }));

            return $category;
        });

        return Inertia::render('Home', [
            'banners' => $banners,
            'categories' => $categories,
            'allCategories' => $allCategories,
            'filters' => [
                'category' => $selectedCategory,
                'search' => $search,
            ],
            'hasAnyEventResults' => $hasAnyEventResults,

        ]);
    }

    private function bannerImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::url($path);
    }
}
