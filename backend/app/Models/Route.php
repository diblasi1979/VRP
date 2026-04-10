<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Route extends Model
{
    use HasFactory;

    protected $appends = [
        'total_distance_km',
    ];

    protected $fillable = [
        'vehicle_id',
        'total_distance',
        'total_duration',
        'status',
        'optimized_at',
        'completed_at',
    ];

    protected $casts = [
        'optimized_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('stop_sequence');
    }

    protected function totalDistanceKm(): Attribute
    {
        return Attribute::make(
            get: fn (): ?float => ($meters = $this->resolveDistanceMeters()) !== null
                ? round($meters / 1000, 1)
                : null,
        );
    }

    private function resolveDistanceMeters(): ?float
    {
        if ($this->total_distance !== null) {
            return (float) $this->total_distance;
        }

        if (!$this->relationLoaded('vehicle') || !$this->relationLoaded('stops')) {
            return null;
        }

        $vehicle = $this->vehicle;
        if (!$vehicle || $vehicle->start_lat === null || $vehicle->start_lng === null) {
            return null;
        }

        $points = [[(float) $vehicle->start_lat, (float) $vehicle->start_lng]];

        foreach ($this->stops as $stop) {
            if (!$stop->relationLoaded('order') || !$stop->order) {
                return null;
            }

            $points[] = [(float) $stop->order->lat, (float) $stop->order->lng];
        }

        $points[] = [(float) $vehicle->start_lat, (float) $vehicle->start_lng];

        $meters = 0.0;
        for ($idx = 0; $idx < count($points) - 1; $idx++) {
            [$fromLat, $fromLng] = $points[$idx];
            [$toLat, $toLng] = $points[$idx + 1];
            $meters += $this->haversineMeters($fromLat, $fromLng, $toLat, $toLng);
        }

        return $meters > 0 ? $meters : null;
    }

    private function haversineMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371000;

        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }
}
