<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Schedule extends Model
{
    protected $fillable = [
        'user_id',
        'shift_id',
        'date',
        'is_day_off',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_day_off' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }
}
