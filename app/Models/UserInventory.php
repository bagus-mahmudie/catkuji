<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'draw_result_id',
        'token_redeem_transaction_id',
        'gacha_prize_id',
        'source_event_id',
        'prize_name_snapshot',
        'prize_image_snapshot',
        'rarity_code_snapshot',
        'prize_type',
        'sellback_price',
        'status',
        'claimed_at',
        'sellbacked_at',
        'shipping_requested_at',
        'packed_at',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'sellback_price' => 'decimal:2',
            'claimed_at' => 'datetime',
            'sellbacked_at' => 'datetime',
            'shipping_requested_at' => 'datetime',
            'packed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function drawResult()
    {
        return $this->belongsTo(DrawResult::class);
    }

    public function tokenRedeemTransaction()
    {
        return $this->belongsTo(TokenRedeemTransaction::class, 'token_redeem_transaction_id');
    }

    public function prize()
    {
        return $this->belongsTo(GachaPrize::class, 'gacha_prize_id');
    }

    public function sourceEvent()
    {
        return $this->belongsTo(GachaEvent::class, 'source_event_id');
    }

    public function sellbackTransaction()
    {
        return $this->hasOne(SellbackTransaction::class, 'inventory_id');
    }

    public function shipmentOrderItem()
    {
        return $this->hasOne(ShipmentOrderItem::class, 'inventory_id');
    }

    public function scopeStored($query)
    {
        return $query->where('status', 'stored');
    }

    public function scopeShippable($query)
    {
        return $query
            ->where('status', 'stored')
            ->where('prize_type', 'physical');
    }

    public function isSellbackable(): bool
    {
        return $this->status === 'stored';
    }

    public function isShippable(): bool
    {
        return $this->status === 'stored' && $this->prize_type === 'physical';
    }
}