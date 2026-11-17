<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
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
