<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GachaEventPrize extends Model
{
    use HasFactory;

    protected $fillable = [
        'gacha_event_id',
        'gacha_prize_id',
        'display_name',
        'rarity_code',
        'draw_weight',
        'initial_stock',
        'current_stock',
        'reserved_stock',
        'sellback_price_override',
        'coin_price',
        'token_reward',
        'is_token_redeemable',
        'token_redeem_price',
        'redeem_stock_total',
        'redeem_stock_left',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'draw_weight' => 'integer',
            'initial_stock' => 'integer',
            'current_stock' => 'integer',
            'reserved_stock' => 'integer',
            'sellback_price_override' => 'decimal:2',
            'coin_price' => 'integer',
            'token_reward' => 'integer',
            'is_token_redeemable' => 'boolean',
            'token_redeem_price' => 'integer',
            'redeem_stock_total' => 'integer',
            'redeem_stock_left' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(GachaEvent::class, 'gacha_event_id');
    }

    public function prize()
    {
        return $this->belongsTo(GachaPrize::class, 'gacha_prize_id');
    }

    public function drawResults()
    {
        return $this->hasMany(DrawResult::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('current_stock', '>', 0);
    }

    public function hasStock(): bool
    {
        return $this->current_stock > 0;
    }

    public function getEffectiveSellbackPriceAttribute(): string
    {
        return $this->sellback_price_override ?? $this->prize?->sellback_price ?? '0.00';
    }

    public function results()
    {
        return $this->hasMany(DrawResult::class, 'gacha_event_prize_id');
    }
}