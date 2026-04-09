<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'lat',
        'lng',
        'weight',
        'time_window_start',
        'time_window_end',
        'status',
        'notes',
    ];

    protected $casts = [
        'lat'    => 'float',
        'lng'    => 'float',
        'weight' => 'float',
    ];

    public function routeStop(): HasOne
    {
        return $this->hasOne(RouteStop::class);
    }

    /**
     * Devuelve el time_window como timestamps Unix del día actual.
     * El servicio ORS requiere segundos desde epoch.
     */
    public function getTimeWindowTimestamps(): array
    {
        $today = now()->startOfDay()->timestamp;
        [$startH, $startM] = explode(':', $this->time_window_start);
        [$endH,   $endM]   = explode(':', $this->time_window_end);

        return [
            $today + ($startH * 3600) + ($startM * 60),
            $today + ($endH   * 3600) + ($endM   * 60),
        ];
    }
}
