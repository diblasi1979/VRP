<?php

use App\Http\Controllers\RouteOptimizationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — TMS (Transportation Management System)
|--------------------------------------------------------------------------
*/

// Pedidos
Route::get('/orders',                [RouteOptimizationController::class, 'orders']);
Route::post('/orders',               [RouteOptimizationController::class, 'storeOrder']);
Route::patch('/orders/{order}/status',[RouteOptimizationController::class, 'updateOrderStatus']);

// Vehículos
Route::get('/vehicles',              [RouteOptimizationController::class, 'vehicles']);
Route::post('/vehicles',             [RouteOptimizationController::class, 'storeVehicle']);

// Rutas
Route::get('/routes',                [RouteOptimizationController::class, 'routes']);
Route::delete('/routes',             [RouteOptimizationController::class, 'clearRoutes']);

// Optimización — endpoint principal
Route::post('/optimize-routes',      [RouteOptimizationController::class, 'optimize']);
