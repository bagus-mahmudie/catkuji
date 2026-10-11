<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GachaCategory;
use App\Models\GachaEvent;
use App\Models\GachaPrize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:140',
                Rule::unique('gacha_categories', 'slug'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'card_style' => ['required', Rule::in(['standard', 'promo'])],
            'promo_left_bg' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'category_glow_color' => [
                'nullable',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'category_glow_position' => [
                'required',
                Rule::in(['left', 'right']),
            ],
            'promo_starts_at' => [
                'nullable',
                'date',
                'required_with:promo_ends_at',
            ],
            'promo_ends_at' => [
                'nullable',
                'date',
                'required_with:promo_starts_at',
                'after:promo_starts_at',
            ],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $slug = filled($validated['slug'] ?? null)
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $originalSlug = $slug;
        $counter = 2;

        while (GachaCategory::query()->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $promoLeftBgPath = null;

        if ($request->hasFile('promo_left_bg')) {
            $promoLeftBgPath = $request
                ->file('promo_left_bg')
                ->store('categories/promo-left-bg', 'public');
        }

        $category = GachaCategory::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'card_style' => $validated['card_style'],
            'promo_left_bg' => $promoLeftBgPath,
            'category_glow_color' => strtoupper($validated['category_glow_color'] ?? '#F66455'),
            'category_glow_position' => $validated['category_glow_position'] ?? 'right',
            'promo_starts_at' => $validated['card_style'] === 'promo'
                ? ($validated['promo_starts_at'] ?? null)
                : null,
            'promo_ends_at' => $validated['card_style'] === 'promo'
                ? ($validated['promo_ends_at'] ?? null)
                : null,
            'sort_order' => GachaCategory::query()->active()->max('sort_order') + 1,
        ]);

        if (! empty($validated['sort_order'])) {
            $this->moveCategoryToOrder($category, (int) $validated['sort_order']);
        }

        return back()
            ->with('success', 'Kategori baru berhasil ditambahkan.')
            ->with('created_category_id', $category->id);
    }

    public function update(Request $request, GachaCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:140',
                Rule::unique('gacha_categories', 'slug')->ignore($category->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'card_style' => ['required', Rule::in(['standard', 'promo'])],
            'promo_left_bg' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'remove_promo_left_bg' => ['nullable', 'boolean'],
            'category_glow_color' => [
                'nullable',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'category_glow_position' => [
                'required',
                Rule::in(['left', 'right']),
            ],
            'promo_starts_at' => [
                'nullable',
                'date',
                'required_with:promo_ends_at',
            ],
            'promo_ends_at' => [
                'nullable',
                'date',
                'required_with:promo_starts_at',
                'after:promo_starts_at',
            ],
            
            'sort_order' => ['required', 'integer', 'min:1'],
        ]);

        $slug = filled($validated['slug'] ?? null)
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $originalSlug = $slug;
        $counter = 2;

        while (
            GachaCategory::query()
                ->where('slug', $slug)
                ->whereKeyNot($category->id)
                ->exists()
        ) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $promoLeftBgPath = $category->promo_left_bg;

        if ($request->boolean('remove_promo_left_bg')) {
            if ($promoLeftBgPath) {
                Storage::disk('public')->delete($promoLeftBgPath);
            }

            $promoLeftBgPath = null;
        }

        if ($request->hasFile('promo_left_bg')) {
            if ($promoLeftBgPath) {
                Storage::disk('public')->delete($promoLeftBgPath);
            }

            $promoLeftBgPath = $request
                ->file('promo_left_bg')
                ->store('categories/promo-left-bg', 'public');
        }

        $category->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'card_style' => $validated['card_style'],
            'promo_left_bg' => $promoLeftBgPath,
            'category_glow_color' => strtoupper($validated['category_glow_color'] ?? '#F66455'),
            'category_glow_position' => $validated['category_glow_position'] ?? 'right',
            'promo_starts_at' => $validated['card_style'] === 'promo'
                ? ($validated['promo_starts_at'] ?? null)
                : null,
            'promo_ends_at' => $validated['card_style'] === 'promo'
                ? ($validated['promo_ends_at'] ?? null)
                : null,
        ]);

        $this->moveCategoryToOrder($category->fresh(), (int) $validated['sort_order']);

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(GachaCategory $category): RedirectResponse
    {
        DB::transaction(function () use ($category) {
            $oldOrder = (int) $category->sort_order;

            $category->update([
                'is_archived' => true,
                'archived_at' => now(),
                'sort_order' => 999999,
            ]);

            GachaCategory::query()
                ->active()
                ->where('sort_order', '>', $oldOrder)
                ->decrement('sort_order');
        });

        return back()->with('success', 'Category berhasil diarsipkan.');
    }

    private function moveCategoryToOrder(GachaCategory $category, int $targetOrder): void
    {
        $targetOrder = max(1, $targetOrder);

        $maxOrder = (int) GachaCategory::query()
            ->active()
            ->whereKeyNot($category->id)
            ->max('sort_order');

        $targetOrder = min($targetOrder, $maxOrder + 1);

        $currentOrder = (int) ($category->sort_order ?: $targetOrder);

        if ($currentOrder === $targetOrder) {
            $category->update([
                'sort_order' => $targetOrder,
            ]);

            return;
        }

        if ($currentOrder < $targetOrder) {
            GachaCategory::query()
                ->active()
                ->whereKeyNot($category->id)
                ->whereBetween('sort_order', [$currentOrder + 1, $targetOrder])
                ->decrement('sort_order');
        } else {
            GachaCategory::query()
                ->active()
                ->whereKeyNot($category->id)
                ->whereBetween('sort_order', [$targetOrder, $currentOrder - 1])
                ->increment('sort_order');
        }

        $category->update([
            'sort_order' => $targetOrder,
        ]);
    }
}