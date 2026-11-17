<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'schedule_id',
        'date',
        'check_in_at',
        'check_in_lat',
        'check_in_lng',
        'check_in_accuracy_m',
        'check_in_distance_m',
        'check_out_at',
        'check_out_lat',
        'check_out_lng',
        'check_out_accuracy_m',
        'check_out_distance_m',
        'status',
        'late_minutes',
        'source',
        'client_uuid',
        'device_info',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'check_in_at' => 'datetime',
        'check_in_lat' => 'decimal:7',
        'check_in_lng' => 'decimal:7',
        'check_in_accuracy_m' => 'decimal:1',
        'check_in_distance_m' => 'decimal:1',
        'check_out_at' => 'datetime',
        'check_out_lat' => 'decimal:7',
        'check_out_lng' => 'decimal:7',
        'check_out_accuracy_m' => 'decimal:1',
        'check_out_distance_m' => 'decimal:1',
        'late_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }
}
