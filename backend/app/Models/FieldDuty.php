<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldDuty extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'location_name',
        'description',
        'latitude',
        'longitude',
        'photo_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Admin yang memutuskan tugas lapangan.
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
