<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CsatFeedback extends Model
{
    protected $table = 'csat_feedbacks';

    protected $fillable = [
        'user_id',
        'shipment_order_id',
        'rating',
        'review',
        'agree_terms',
    ];

    protected $casts = [
        'agree_terms' => 'boolean',
    ];

    public function notes()
    {
        return $this->hasMany(AdminNote::class, 'csat_feedback_id');
    }
}