<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Vehicle;
use App\Services\RouteOptimizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class RouteOptimizationController extends Controller
{
    public function __construct(
        private readonly RouteOptimizerService $optimizer
    ) {}

    // -----------------------------------------------------------------------
    // POST /api/optimize-routes
    // -----------------------------------------------------------------------

    /**
     * Lanza la optimización VRP y devuelve las rutas generadas.
     */
    public function optimize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
        ]);

        try {
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);
            $routes = $this->optimizer->optimize($vehicle->id);

            return response()->json([
                'success' => true,
                'message' => count($routes) . ' ruta(s) optimizadas correctamente para ' . $vehicle->name . '.',
                'data'    => $routes,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al optimizar rutas. Revisa los logs.',
            ], 500);
        }
    }

    // -----------------------------------------------------------------------
    // GET /api/routes
    // -----------------------------------------------------------------------

    /**
     * Devuelve todas las rutas con vehículo y paradas (con pedido incluido).
     */
    public function routes(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['active', 'pending', 'optimized', 'in_progress', 'completed'])],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $query = Route::with(['vehicle', 'stops.order']);

        match ($request->input('status', 'active')) {
            'active' => $query->whereIn('status', ['pending', 'optimized', 'in_progress']),
            'pending', 'optimized', 'in_progress', 'completed' => $query->where('status', $request->status),
            default => null,
        };

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        if ($request->filled('date_from')) {
            $column = $request->input('status') === 'completed' ? 'completed_at' : 'optimized_at';
            $query->whereDate($column, '>=', $request->date('date_from')->toDateString());
        }

        if ($request->filled('date_to')) {
            $column = $request->input('status') === 'completed' ? 'completed_at' : 'optimized_at';
            $query->whereDate($column, '<=', $request->date('date_to')->toDateString());
        }

        $routes = $query
            ->orderByDesc('completed_at')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $routes]);
    }

    // -----------------------------------------------------------------------
    // GET /api/orders
    // -----------------------------------------------------------------------

    /**
     * Devuelve los pedidos, filtrable por status.
     */
    public function orders(Request $request): JsonResponse
    {
        $query = Order::query();

        if ($request->filled('status')) {
            $request->validate([
                'status' => Rule::in(['pending', 'assigned', 'delivered']),
            ]);
            $query->where('status', $request->status);
        }

        return response()->json(['success' => true, 'data' => $query->orderBy('id')->get()]);
    }

    // -----------------------------------------------------------------------
    // POST /api/orders
    // -----------------------------------------------------------------------

    /**
     * Crea un nuevo pedido.
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address'           => 'required|string|max:255',
            'lat'               => 'required|numeric|between:-90,90',
            'lng'               => 'required|numeric|between:-180,180',
            'weight'            => 'required|numeric|min:0.01',
            'time_window_start' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'time_window_end'   => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'notes'             => 'nullable|string',
        ]);

        $order = Order::create($data);

        return response()->json(['success' => true, 'data' => $order], 201);
    }

    // -----------------------------------------------------------------------
    // GET /api/vehicles
    // -----------------------------------------------------------------------

    public function vehicles(): JsonResponse
    {
        $vehicles = Vehicle::withCount('activeRoutes')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $vehicles]);
    }

    // -----------------------------------------------------------------------
    // POST /api/vehicles
    // -----------------------------------------------------------------------

    public function storeVehicle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'start_address' => 'nullable|string|max:255',
            'capacity'      => 'required|numeric|min:1',
            'start_lat'     => 'required|numeric|between:-90,90',
            'start_lng'     => 'required|numeric|between:-180,180',
        ]);

        $vehicle = Vehicle::create($data);

        return response()->json(['success' => true, 'data' => $vehicle], 201);
    }

    // -----------------------------------------------------------------------
    // PATCH /api/orders/{order}/status
    // -----------------------------------------------------------------------

    public function updateOrderStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'assigned', 'delivered'])],
        ]);

        DB::transaction(function () use ($order, $data) {
            $order->update($data);
            $this->syncRouteStatusForOrder($order->fresh());
        });

        return response()->json(['success' => true, 'data' => $order->fresh()]);
    }

    // -----------------------------------------------------------------------
    // DELETE /api/routes
    // -----------------------------------------------------------------------

    /**
     * Limpia todas las rutas y vuelve a poner los pedidos en 'pending'.
     * Útil para re-optimizar desde cero.
     */
    public function clearRoutes(): JsonResponse
    {
        Route::whereIn('status', ['pending', 'optimized', 'in_progress'])->delete();
        Order::where('status', 'assigned')->update(['status' => 'pending']);

        return response()->json(['success' => true, 'message' => 'Rutas activas eliminadas. El historial de rutas completadas se conserva.']);
    }

    private function syncRouteStatusForOrder(Order $order): void
    {
        $routeStop = RouteStop::with(['route.stops.order'])->where('order_id', $order->id)->first();

        if (!$routeStop || !$routeStop->route) {
            return;
        }

        $route = $routeStop->route;
        $orders = $route->stops
            ->pluck('order')
            ->filter();

        if ($orders->isEmpty()) {
            return;
        }

        $allDelivered = $orders->every(fn (Order $stopOrder) => $stopOrder->status === 'delivered');
        $anyDelivered = $orders->contains(fn (Order $stopOrder) => $stopOrder->status === 'delivered');

        if ($allDelivered) {
            $route->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return;
        }

        $route->update([
            'status' => $anyDelivered ? 'in_progress' : 'optimized',
            'completed_at' => null,
        ]);
    }
}
