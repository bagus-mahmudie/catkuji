<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellbackTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'inventory_id',
        'wallet_transaction_id',
        'amount',
        'status',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta_json' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inventory()
    {
        return $this->belongsTo(UserInventory::class, 'inventory_id');
    }

    public function walletTransaction()
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}