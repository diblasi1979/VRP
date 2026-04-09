<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Orquesta la optimización de rutas VRP completa:
 *  1. Carga pedidos pendientes y vehículos activos
 *  2. Construye el payload para OpenRouteService /optimization
 *  3. Parsea la respuesta y persiste rutas + paradas en DB
 *  4. Marca los pedidos como 'assigned'
 */
class RouteOptimizerService
{
    public function __construct(
        private readonly OpenRouteServiceClient $orsClient
    ) {}

    // -----------------------------------------------------------------------
    // Punto de entrada principal
    // -----------------------------------------------------------------------

    /**
     * Ejecuta la optimización completa.
     *
     * @return array  Rutas creadas con sus paradas (con relaciones cargadas)
     */
    public function optimize(): array
    {
        $orders   = Order::where('status', 'pending')->get();
        $vehicles = Vehicle::where('is_active', true)->get();

        if ($orders->isEmpty()) {
            throw new RuntimeException('No hay pedidos pendientes para optimizar.');
        }

        if ($vehicles->isEmpty()) {
            throw new RuntimeException('No hay vehículos activos disponibles.');
        }

        // Construir el payload VRP
        $payload = $this->buildPayload($orders, $vehicles);

        Log::info('VRP payload enviado a ORS', ['jobs' => count($payload['jobs']), 'vehicles' => count($payload['vehicles'])]);

        // Llamar a ORS
        $response = $this->orsClient->optimize($payload);

        Log::info('VRP respuesta recibida de ORS', ['routes' => count($response['routes'] ?? [])]);

        // Persistir y retornar
        return $this->persistRoutes($response, $orders, $vehicles);
    }

    // -----------------------------------------------------------------------
    // Construcción del payload ORS
    // -----------------------------------------------------------------------

    /**
     * Convierte los modelos Eloquent al formato JSON que exige ORS /optimization.
     */
    private function buildPayload(Collection $orders, Collection $vehicles): array
    {
        $jobs = $orders->map(function (Order $order) {
            [$twStart, $twEnd] = $order->getTimeWindowTimestamps();

            return [
                'id'           => $order->id,
                'location'     => [$order->lng, $order->lat], // ORS usa [lng, lat]
                'amount'       => [(int) round($order->weight)],
                'time_windows' => [[$twStart, $twEnd]],
                'description'  => $order->address,
            ];
        })->values()->toArray();

        $vehiclesPayload = $vehicles->map(function (Vehicle $vehicle) {
            return [
                'id'       => $vehicle->id,
                'profile'  => 'driving-car',
                'start'    => [$vehicle->start_lng, $vehicle->start_lat],
                'end'      => [$vehicle->start_lng, $vehicle->start_lat],
                'capacity' => [(int) round($vehicle->capacity)],
            ];
        })->values()->toArray();

        return [
            'jobs'     => $jobs,
            'vehicles' => $vehiclesPayload,
        ];
    }

    // -----------------------------------------------------------------------
    // Persistencia de resultados
    // -----------------------------------------------------------------------

    /**
     * Transacción DB: elimina rutas anteriores, crea nuevas rutas y paradas,
     * y actualiza el estado de cada pedido a 'assigned'.
     */
    private function persistRoutes(array $orsResponse, Collection $orders, Collection $vehicles): array
    {
        if (empty($orsResponse['routes'])) {
            throw new RuntimeException(
                'ORS no devolvió rutas. Puede que algunos pedidos sean inviables dentro de las ventanas horarias.'
            );
        }

        // Índices para búsqueda rápida
        $ordersById   = $orders->keyBy('id');
        $vehiclesById = $vehicles->keyBy('id');
        $createdRoutes = [];

        DB::transaction(function () use ($orsResponse, $ordersById, $vehiclesById, &$createdRoutes) {
            // Limpiar asignaciones previas (re-optimizar)
            $assignedOrderIds = $ordersById->keys()->toArray();
            RouteStop::whereIn('order_id', $assignedOrderIds)->delete();
            Route::whereIn('vehicle_id', $vehiclesById->keys()->toArray())
                 ->where('status', 'optimized')
                 ->delete();

            foreach ($orsResponse['routes'] as $orsRoute) {
                $vehicleId = $orsRoute['vehicle'];
                $vehicle   = $vehiclesById[$vehicleId] ?? null;

                if (!$vehicle) {
                    Log::warning("VRP: vehículo {$vehicleId} no encontrado en DB, saltando ruta.");
                    continue;
                }

                // Crear la ruta
                $route = Route::create([
                    'vehicle_id'     => $vehicle->id,
                    'total_distance' => $orsRoute['summary']['distance'] ?? null,  // metros
                    'total_duration' => $orsRoute['summary']['duration'] ?? null,  // segundos
                    'status'         => 'optimized',
                    'optimized_at'   => now(),
                ]);

                // Crear paradas (solo steps tipo 'job', no start/end)
                $sequence = 1;
                foreach ($orsRoute['steps'] as $step) {
                    if ($step['type'] !== 'job') {
                        continue;
                    }

                    $orderId = $step['id'];
                    $order   = $ordersById[$orderId] ?? null;

                    if (!$order) {
                        Log::warning("VRP: pedido {$orderId} referenciado por ORS no encontrado en DB.");
                        continue;
                    }

                    // Convertir timestamp de llegada a HH:MM
                    $arrivalTime = $this->secondsToHHMM($step['arrival'] ?? null);

                    RouteStop::create([
                        'route_id'          => $route->id,
                        'order_id'          => $order->id,
                        'stop_sequence'     => $sequence++,
                        'estimated_arrival' => $arrivalTime,
                    ]);

                    // Marcar pedido como asignado
                    $order->update(['status' => 'assigned']);
                }

                $createdRoutes[] = $route->load(['vehicle', 'stops.order']);
            }
        });

        return $createdRoutes;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Convierte segundos Unix a 'HH:MM'.
     * ORS devuelve la hora de llegada como segundos desde las 00:00 del día.
     */
    private function secondsToHHMM(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        // ORS arrival puede ser timestamp o segundos desde medianoche
        // Si es mayor que 86400, se trata como timestamp Unix
        if ($seconds > 86400) {
            $dt = \Carbon\Carbon::createFromTimestamp($seconds);
            return $dt->format('H:i');
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return sprintf('%02d:%02d', $h, $m);
    }
}
