<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'csat_feedback_id',
        'title',
        'description',
        'note_date',
    ];

    protected function casts(): array
    {
        return [
            'note_date' => 'datetime',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function csatFeedback()
    {
        return $this->belongsTo(CsatFeedback::class, 'csat_feedback_id');
    }
}