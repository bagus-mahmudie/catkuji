<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenRedeemTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'redeem_no',
        'user_id',
        'gacha_event_prize_id',
        'gacha_event_id',
        'gacha_prize_id',
        'token_cost',
        'status',
        'prize_name_snapshot',
        'prize_image_snapshot',
        'prize_type',
        'sellback_price_snapshot',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'token_cost' => 'integer',
            'sellback_price_snapshot' => 'decimal:2',
            'meta_json' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function eventPrize()
    {
        return $this->belongsTo(GachaEventPrize::class, 'gacha_event_prize_id');
    }

    public function event()
    {
        return $this->belongsTo(GachaEvent::class, 'gacha_event_id');
    }

    public function prize()
    {
        return $this->belongsTo(GachaPrize::class, 'gacha_prize_id');
    }

    public function inventory()
    {
        return $this->hasOne(UserInventory::class, 'token_redeem_transaction_id');
    }
}