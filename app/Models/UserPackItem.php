<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserPackItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_pack_id',
        'draw_result_id',
        'gacha_prize_id',
        'prize_name_snapshot',
        'prize_image_snapshot',
        'rarity_code',
        'sellback_price',
        'token_reward',
        'prize_type',
        'revealed_at',
    ];

    protected $casts = [
        'sellback_price' => 'decimal:2',
        'revealed_at' => 'datetime',
        'token_reward' => 'integer',
    ];

    public function pack()
    {
        return $this->belongsTo(UserPack::class, 'user_pack_id');
    }

    public function drawResult()
    {
        return $this->belongsTo(DrawResult::class);
    }

    public function prize()
    {
        return $this->belongsTo(GachaPrize::class, 'gacha_prize_id');
    }
}