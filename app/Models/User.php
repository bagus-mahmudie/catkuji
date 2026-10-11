<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\UserWishlist;
use App\Models\GachaEvent;

class User extends Authenticatable implements \Illuminate\Contracts\Auth\MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'user',
        'status' => 'active',
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'google_id',
        'facebook_id',
        'oauth_provider',
        'role',
        'status',
        'token_balance',
        'admin_requested_at',
        'admin_approved_at',
        'admin_approved_by',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'token_balance' => 'integer',
            'admin_requested_at' => 'datetime',
            'admin_approved_at' => 'datetime',
        ];
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function drawTransactions()
    {
        return $this->hasMany(DrawTransaction::class);
    }

    public function drawResults()
    {
        return $this->hasMany(DrawResult::class);
    }

    public function inventories()
    {
        return $this->hasMany(UserInventory::class);
    }

    public function shipmentOrders()
    {
        return $this->hasMany(ShipmentOrder::class);
    }

    public function sellbackTransactions()
    {
        return $this->hasMany(SellbackTransaction::class);
    }

    public function adminAuditLogs()
    {
        return $this->hasMany(AdminAuditLog::class, 'admin_id');
    }

    public function createdGachaEvents()
    {
        return $this->hasMany(GachaEvent::class, 'created_by');
    }

    public function updatedGachaEvents()
    {
        return $this->hasMany(GachaEvent::class, 'updated_by');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function wishlists()
    {
        return $this->hasMany(UserWishlist::class);
    }

    public function wishlistEvents()
    {
        return $this->belongsToMany(
            GachaEvent::class,
            'user_wishlists',
            'user_id',
            'gacha_event_id'
        )->withTimestamps();
    }
    public function tokenTransactions()
    {
        return $this->hasMany(TokenTransaction::class);
    }
}
