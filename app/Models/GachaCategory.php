<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GachaCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'card_style',
        'promo_left_bg',
        'category_glow_color',
        'category_glow_position',
        'sort_order',
        'promo_starts_at',
        'promo_ends_at',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'promo_starts_at' => 'datetime',
        'promo_ends_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    public function events()
    {
        return $this->hasMany(GachaEvent::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeVisible($query)
    {
        return $query->active()->where(function ($query) {
            $query->whereNull('card_style')->orWhere('card_style', '!=', 'promo')
                ->orWhereNull('promo_ends_at')->orWhere('promo_ends_at', '>=', now());
        });
    }

    public static function archiveExpiredPromos(): void
    {
        static::query()
            ->active()
            ->where('card_style', 'promo')
            ->whereNotNull('promo_ends_at')
            ->where('promo_ends_at', '<', now())
            ->update([
                'is_archived' => true,
                'archived_at' => now(),
                'sort_order' => 999999,
            ]);
    }
}
