<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GachaPrize extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'slug',
        'description',
        'image',
        'category',
        'prize_type',
        'rarity_code',
        'market_price',
        'sellback_price',
        'weight_grams',
        'length_cm',
        'width_cm',
        'height_cm',
        'metadata_json',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'market_price' => 'decimal:2',
            'sellback_price' => 'decimal:2',
            'weight_grams' => 'integer',
            'length_cm' => 'integer',
            'width_cm' => 'integer',
            'height_cm' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function eventPrizes()
    {
        return $this->hasMany(GachaEventPrize::class, 'gacha_prize_id');
    }

    public function drawResults()
    {
        return $this->hasMany(DrawResult::class);
    }

    public function inventories()
    {
        return $this->hasMany(UserInventory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}