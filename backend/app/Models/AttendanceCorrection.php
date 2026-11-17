<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    // corrected_check_in dan corrected_check_out (TIME) dibiarkan string.
    protected $fillable = [
        'user_id',
        'attendance_id',
        'date',
        'corrected_check_in',
        'corrected_check_out',
        'reason',
        'attachment_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    // Admin yang memutuskan koreksi.
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
