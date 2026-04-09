<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente para la API de OpenRouteService.
 *
 * Documentación oficial: https://openrouteservice.org/dev/#/api-docs
 *
 * Obtén tu API key gratuita en: https://openrouteservice.org/dev/#/signup
 * y configura ORS_API_KEY en el archivo .env
 */
class OpenRouteServiceClient
{
    private const BASE_URL    = 'https://api.openrouteservice.org';
    private const MATRIX_PATH = '/v2/matrix/driving-car';
    private const OPT_PATH    = '/optimization';

    private Client $http;
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.ors.key');

        if (empty($this->apiKey)) {
            throw new RuntimeException(
                'ORS_API_KEY no configurada. Añádela en .env: ORS_API_KEY=tu_clave'
            );
        }

        $this->http = new Client([
            'base_uri' => self::BASE_URL,
            'timeout'  => 30,
            'headers'  => [
                'Authorization' => $this->apiKey,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // Matriz de distancias/duraciones
    // -----------------------------------------------------------------------

    /**
     * Llama a /v2/matrix/driving-car para obtener duraciones entre N puntos.
     *
     * @param  array  $locations  Array de [lng, lat] (formato ORS)
     * @return array  Respuesta decodificada de la API
     */
    public function getMatrix(array $locations): array
    {
        $body = [
            'locations' => $locations,
            'metrics'   => ['distance', 'duration'],
            'units'     => 'km',
        ];

        try {
            $response = $this->http->post(self::MATRIX_PATH, [
                'json' => $body,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            $this->handleApiError($e, 'matrix');
        }
    }

    // -----------------------------------------------------------------------
    // Optimización VRP (Vrp)
    // -----------------------------------------------------------------------

    /**
     * Llama a /optimization con un payload VRP completo.
     *
     * Formato esperado del payload:
     * {
     *   "jobs": [
     *     {
     *       "id": 1,
     *       "location": [lng, lat],
     *       "amount": [weight_kg],
     *       "time_windows": [[unix_start, unix_end]]
     *     }, ...
     *   ],
     *   "vehicles": [
     *     {
     *       "id": 1,
     *       "profile": "driving-car",
     *       "start": [lng, lat],
     *       "end":   [lng, lat],
     *       "capacity": [max_kg]
     *     }, ...
     *   ]
     * }
     *
     * @param  array  $payload  Payload VRP completo (jobs + vehicles)
     * @return array  Respuesta decodificada de la API
     */
    public function optimize(array $payload): array
    {
        try {
            $response = $this->http->post(self::OPT_PATH, [
                'json' => $payload,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            $this->handleApiError($e, 'optimization');
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function handleApiError(ClientException $e, string $endpoint): never
    {
        $statusCode = $e->getResponse()->getStatusCode();
        $body       = $e->getResponse()->getBody()->getContents();

        Log::error("ORS /{$endpoint} error {$statusCode}", ['body' => $body]);

        $message = match ($statusCode) {
            401 => 'ORS: API key inválida o sin permisos.',
            403 => 'ORS: Acceso denegado. Revisa los límites de tu plan.',
            429 => 'ORS: Límite de peticiones alcanzado. Espera un momento.',
            default => "ORS: Error {$statusCode} al llamar /{$endpoint}. Detalles: {$body}",
        };

        throw new RuntimeException($message);
    }
}
