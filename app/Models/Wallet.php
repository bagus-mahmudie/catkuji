<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'locked_balance',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'locked_balance' => 'integer',
        ];
    }

    public function hasSufficientBalance(int|string $amount): bool
    {
        return (int) $this->balance >= (int) $amount;
    }

    public function availableBalance(): int
    {
        return max(0, (int) $this->balance - (int) $this->locked_balance);
    }

    public function hasSufficientAvailableBalance(int|string $amount): bool
    {
        return $this->availableBalance() >= (int) $amount;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}