<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $appends = [
        'is_available',
    ];

    protected $fillable = [
        'name',
        'start_address',
        'capacity',
        'start_lat',
        'start_lng',
        'is_active',
    ];

    protected $casts = [
        'capacity'  => 'float',
        'start_lat' => 'float',
        'start_lng' => 'float',
        'is_active' => 'boolean',
    ];

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    public function activeRoutes(): HasMany
    {
        return $this->hasMany(Route::class)->whereIn('status', ['pending', 'optimized', 'in_progress']);
    }

    public function getIsAvailableAttribute(): bool
    {
        if (array_key_exists('active_routes_count', $this->attributes)) {
            return (int) $this->attributes['active_routes_count'] === 0;
        }

        return !$this->activeRoutes()->exists();
    }
}
