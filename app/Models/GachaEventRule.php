<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GachaEventRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'gacha_event_id',
        'rule_type',
        'config_json',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function event()
    {
        return $this->belongsTo(GachaEvent::class, 'gacha_event_id');
    }
}