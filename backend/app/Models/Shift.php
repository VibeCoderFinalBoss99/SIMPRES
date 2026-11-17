<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    // start_time dan end_time (TIME) dibiarkan string, contoh "07:30:00".
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'late_tolerance_min',
    ];

    protected $casts = [
        'late_tolerance_min' => 'integer',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
