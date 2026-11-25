<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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

    /**
     * Jadwal tetap pegawai untuk satu tanggal: shift tetap pada Senin-Sabtu, libur pada Minggu.
     * Null jika pegawai tidak punya shift tetap (misalnya pegawai shift yang diatur lewat roster).
     * Tidak disimpan ke database.
     */
    public static function fromDefault(User $user, Carbon $date): ?self
    {
        if (! $user->default_shift_id) {
            return null;
        }

        $dayOff = $date->isSunday();
        $schedule = new self([
            'user_id' => $user->id,
            'shift_id' => $dayOff ? null : $user->default_shift_id,
            'date' => $date->toDateString(),
            'is_day_off' => $dayOff,
        ]);

        return $schedule->setRelation('shift', $dayOff ? null : $user->defaultShift);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }
}
