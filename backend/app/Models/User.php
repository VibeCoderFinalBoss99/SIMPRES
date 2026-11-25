<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'work_location_id',
        'nip',
        'name',
        'email',
        'password',
        'phone',
        'position',
        'unit',
        'role',
        'work_type',
        'default_shift_id',
        'photo_path',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** Jenis kerja untuk ditampilkan. */
    public const WORK_TYPES = [
        'non_shift' => 'Non-shift',
        'shift' => 'Shift',
        'petugas_lapangan' => 'Petugas lapangan',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /** Shift tetap: dipakai otomatis pada tanggal yang tidak diatur di roster. */
    public function defaultShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'default_shift_id');
    }

    /**
     * Jadwal satu tanggal: jadwal di roster jika ada, kalau tidak jadwal tetap pegawai.
     * Jadwal tetap tidak tersimpan di database ($schedule->exists bernilai false).
     */
    public function scheduleOn(string $date): ?Schedule
    {
        return $this->schedules()->with('shift')->whereDate('date', $date)->first()
            ?? Schedule::fromDefault($this, Carbon::parse($date));
    }

    /**
     * Jadwal setiap tanggal dalam rentang, dengan kunci tanggal (Y-m-d).
     * Tanggal tanpa jadwal roster dan tanpa shift tetap tidak ikut.
     *
     * @return Collection<string, Schedule>
     */
    public function schedulesBetween(Carbon $start, Carbon $end): Collection
    {
        $rows = $this->schedules()->with('shift')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($s) => $s->date->toDateString());

        $result = collect();
        for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            $schedule = $rows[$day->toDateString()] ?? Schedule::fromDefault($this, $day->copy());
            if ($schedule) {
                $result[$day->toDateString()] = $schedule;
            }
        }

        return $result;
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function fieldDuties(): HasMany
    {
        return $this->hasMany(FieldDuty::class);
    }

    // Bukan notifications(), karena nama itu sudah dipakai trait Notifiable.
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }
}
