<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserPack extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'draw_transaction_id',
        'pack_no',
        'pack_count',
        'status',
        'opened_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function drawTransaction()
    {
        return $this->belongsTo(DrawTransaction::class);
    }

    public function items()
    {
        return $this->hasMany(UserPackItem::class);
    }
}