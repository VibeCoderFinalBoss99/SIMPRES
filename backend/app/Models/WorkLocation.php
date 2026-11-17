<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkLocation extends Model
{
    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'radius_m',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'radius_m' => 'integer',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
