<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VrpSeeder extends Seeder
{
    /**
     * Crea vehículos y pedidos de prueba situados en Buenos Aires, Argentina.
     * Depósito central: Parque Industrial Lobos / Av. Roca y Constitución.
     */
    public function run(): void
    {
        // ---------------------------------------------------------------
        // Vehículos (depósito en Parque Patricios, CABA)
        // ---------------------------------------------------------------
        Vehicle::truncate();

        Vehicle::insert([
            [
                'name'          => 'Furgón BA-01',
                'start_address' => 'Av. Caseros 2900, Parque Patricios, CABA',
                'capacity'      => 800.00,
                'max_route_distance_km' => 120.00,
                'start_lat'     => -34.6345,
                'start_lng'     => -58.4012,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'name'          => 'Furgón BA-02',
                'start_address' => 'Av. Caseros 2900, Parque Patricios, CABA',
                'capacity'      => 600.00,
                'max_route_distance_km' => 90.00,
                'start_lat'     => -34.6345,
                'start_lng'     => -58.4012,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'name'          => 'Camioneta BA-03',
                'start_address' => 'Av. Caseros 2900, Parque Patricios, CABA',
                'capacity'      => 400.00,
                'max_route_distance_km' => 70.00,
                'start_lat'     => -34.6345,
                'start_lng'     => -58.4012,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        // ---------------------------------------------------------------
        // Pedidos (20 domicilios reales en CABA y GBA)
        // ---------------------------------------------------------------
        Order::truncate();

        $orders = [
            [
                'address'           => 'Av. Corrientes 1234, San Nicolás, CABA',
                'lat'               => -34.6037,
                'lng'               => -58.3816,
                'weight'            => 42.0,
                'time_window_start' => '08:00',
                'time_window_end'   => '11:00',
            ],
            [
                'address'           => 'Av. Santa Fe 3500, Palermo, CABA',
                'lat'               => -34.5875,
                'lng'               => -58.4120,
                'weight'            => 31.5,
                'time_window_start' => '09:00',
                'time_window_end'   => '12:00',
            ],
            [
                'address'           => 'Av. Rivadavia 6800, Caballito, CABA',
                'lat'               => -34.6201,
                'lng'               => -58.4463,
                'weight'            => 55.0,
                'time_window_start' => '08:00',
                'time_window_end'   => '13:00',
            ],
            [
                'address'           => 'Av. Cabildo 2100, Belgrano, CABA',
                'lat'               => -34.5625,
                'lng'               => -58.4558,
                'weight'            => 28.0,
                'time_window_start' => '10:00',
                'time_window_end'   => '13:00',
            ],
            [
                'address'           => 'Av. Callao 1200, Recoleta, CABA',
                'lat'               => -34.5952,
                'lng'               => -58.3928,
                'weight'            => 37.0,
                'time_window_start' => '09:00',
                'time_window_end'   => '12:00',
            ],
            [
                'address'           => 'Carlos Calvo 3500, Boedo, CABA',
                'lat'               => -34.6289,
                'lng'               => -58.4167,
                'weight'            => 64.0,
                'time_window_start' => '08:00',
                'time_window_end'   => '11:00',
            ],
            [
                'address'           => 'Av. Juan B. Justo 4500, Villa Crespo, CABA',
                'lat'               => -34.5988,
                'lng'               => -58.4402,
                'weight'            => 48.0,
                'time_window_start' => '11:00',
                'time_window_end'   => '14:00',
            ],
            [
                'address'           => 'Av. Triunvirato 4200, Villa Urquiza, CABA',
                'lat'               => -34.5704,
                'lng'               => -58.4820,
                'weight'            => 73.0,
                'time_window_start' => '09:00',
                'time_window_end'   => '13:00',
            ],
            [
                'address'           => 'Av. de los Constituyentes 3800, Saavedra, CABA',
                'lat'               => -34.5494,
                'lng'               => -58.4876,
                'weight'            => 22.0,
                'time_window_start' => '08:00',
                'time_window_end'   => '12:00',
            ],
            [
                'address'           => 'Av. Directorio 2100, Flores, CABA',
                'lat'               => -34.6408,
                'lng'               => -58.4629,
                'weight'            => 91.0,
                'time_window_start' => '10:00',
                'time_window_end'   => '15:00',
            ],
            [
                'address'           => 'Av. Eva Perón 3500, Mataderos, CABA',
                'lat'               => -34.6611,
                'lng'               => -58.5017,
                'weight'            => 110.0,
                'time_window_start' => '08:00',
                'time_window_end'   => '12:00',
            ],
            [
                'address'           => 'Av. Hipólito Yrigoyen 8500, Lanús, GBA Sur',
                'lat'               => -34.7080,
                'lng'               => -58.3920,
                'weight'            => 85.0,
                'time_window_start' => '09:00',
                'time_window_end'   => '14:00',
            ],
            [
                'address'           => 'Av. Mitre 2300, Avellaneda, GBA Sur',
                'lat'               => -34.6660,
                'lng'               => -58.3700,
                'weight'            => 67.0,
                'time_window_start' => '10:00',
                'time_window_end'   => '14:00',
            ],
            [
                'address'           => 'Av. Rivadavia 15200, Ramos Mejía, GBA Oeste',
                'lat'               => -34.6430,
                'lng'               => -58.5630,
                'weight'            => 53.0,
                'time_window_start' => '08:00',
                'time_window_end'   => '13:00',
            ],
            [
                'address'           => 'Av. San Martín 2800, Morón, GBA Oeste',
                'lat'               => -34.6489,
                'lng'               => -58.6197,
                'weight'            => 120.0,
                'time_window_start' => '09:00',
                'time_window_end'   => '15:00',
            ],
            [
                'address'           => 'Av. Maipú 1900, Vicente López, GBA Norte',
                'lat'               => -34.5257,
                'lng'               => -58.4742,
                'weight'            => 39.0,
                'time_window_start' => '10:00',
                'time_window_end'   => '13:00',
            ],
            [
                'address'           => 'Av. del Libertador 3000, San Isidro, GBA Norte',
                'lat'               => -34.4720,
                'lng'               => -58.5139,
                'weight'            => 44.0,
                'time_window_start' => '09:00',
                'time_window_end'   => '14:00',
            ],
            [
                'address'           => 'Av. Presidente Perón 4500, Quilmes, GBA Sur',
                'lat'               => -34.7224,
                'lng'               => -58.2531,
                'weight'            => 76.0,
                'time_window_start' => '08:00',
                'time_window_end'   => '12:00',
            ],
            [
                'address'           => 'Av. Meeks 1200, Lomas de Zamora, GBA Sur',
                'lat'               => -34.7601,
                'lng'               => -58.4015,
                'weight'            => 59.0,
                'time_window_start' => '11:00',
                'time_window_end'   => '16:00',
            ],
            [
                'address'           => 'Av. Márquez 3100, San Miguel, GBA Norte',
                'lat'               => -34.5416,
                'lng'               => -58.7138,
                'weight'            => 33.0,
                'time_window_start' => '09:00',
                'time_window_end'   => '14:00',
            ],
        ];

        foreach ($orders as $order) {
            Order::create(array_merge($order, ['status' => 'pending']));
        }

        $this->command->info('✔ VrpSeeder: 3 vehículos y 20 pedidos creados en Buenos Aires.');
    }
}
