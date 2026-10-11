<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use App\Models\User;

class GachaEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'badge_label',
        'opened_text',
        'stock_left',
        'stock_total',
        'category_id',
        'code',
        'slug',
        'name',
        'description',
        'banner_image',
        'thumbnail_image',
        'price_per_draw',
        'price_per_multi_draw',
        'price_coin',
        'price_token',
        'draw_mode',
        'event_type',
        'start_at',
        'end_at',
        'status',
        'sellback_enabled',
        'shipping_enabled',
        'max_draw_per_user_per_day',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price_per_draw' => 'integer',
            'price_per_multi_draw' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'sellback_enabled' => 'boolean',
            'shipping_enabled' => 'boolean',
        ];
    }
    public function getPriceRupiahAttribute(): int
    {
        $coinRate = (int) config('gacha.coin_rate', 1000);

        return (int) $this->price_per_draw * $coinRate;
    }
    public function category()
    {
        return $this->belongsTo(GachaCategory::class, 'category_id');
    }

    public function rules()
    {
        return $this->hasMany(GachaEventRule::class);
    }

    public function activeRules()
    {
        return $this->hasMany(GachaEventRule::class)->where('is_active', true);
    }

    public function eventPrizes()
    {
        return $this->hasMany(GachaEventPrize::class);
    }

    public function availableEventPrizes()
    {
        return $this->hasMany(GachaEventPrize::class)->where('current_stock', '>', 0);
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
        return $this->hasMany(UserInventory::class, 'source_event_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeActiveNow($query)
    {
        $now = now();

        return $query
            ->where('status', 'published')
            ->where('start_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')
                  ->orWhere('end_at', '>=', $now);
            });
    }

    public function isActive(): bool
    {
        $now = now();

        if ($this->status !== 'published') {
            return false;
        }

        if ($this->start_at && $this->start_at->gt($now)) {
            return false;
        }

        if ($this->end_at && $this->end_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function wishlistedByUsers()
    {
        return $this->belongsToMany(
            User::class,
            'user_wishlists',
            'gacha_event_id',
            'user_id'
        )->withTimestamps();
    }
}