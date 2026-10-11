<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NpsFeedback extends Model
{
    protected $table = 'nps_feedbacks';
    protected $fillable = [
        'user_id',
        'gacha_event_id',
        'score',
        'type',
        'feedback',
        'page_url',
        'agree_terms',
    ];

    protected $casts = [
        'agree_terms' => 'boolean',
    ];
}