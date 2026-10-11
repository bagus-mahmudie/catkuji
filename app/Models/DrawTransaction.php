<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'draw_no',
        'user_id',
        'gacha_event_id',
        'payment_source',
        'total_cost',
        'draw_count',
        'status',
        'request_idempotency_key',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'total_cost' => 'decimal:2',
            'draw_count' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(GachaEvent::class, 'gacha_event_id');
    }

    public function pack()
    {
        return $this->hasOne(UserPack::class);
    }

    public function results()
    {
        return $this->hasMany(DrawResult::class, 'draw_transaction_id');
    }
}