<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'draw_transaction_id',
        'user_id',
        'gacha_event_id',
        'gacha_event_prize_id',
        'gacha_prize_id',
        'rarity_code',
        'result_position',
        'prize_name_snapshot',
        'prize_image_snapshot',
        'market_price_snapshot',
        'sellback_price_snapshot',
        'token_reward_snapshot',
        'random_seed_snapshot',
        'probability_snapshot_json',
    ];

    protected function casts(): array
    {
        return [
            'result_position' => 'integer',
            'market_price_snapshot' => 'decimal:2',
            'sellback_price_snapshot' => 'decimal:2',
            'token_reward_snapshot' => 'integer',
            'probability_snapshot_json' => 'array',
        ];
    }

    public function drawTransaction()
    {
        return $this->belongsTo(DrawTransaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(GachaEvent::class, 'gacha_event_id');
    }

    public function eventPrize()
    {
        return $this->belongsTo(GachaEventPrize::class, 'gacha_event_prize_id');
    }

    public function prize()
    {
        return $this->belongsTo(GachaPrize::class, 'gacha_prize_id');
    }

    public function packItem()
    {
        return $this->hasOne(UserPackItem::class);
    }

    public function inventory()
    {
        return $this->hasOne(UserInventory::class, 'draw_result_id');
    }
}