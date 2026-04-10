# 🚚 VRP TMS — Sistema de Optimización de Rutas

Sistema de gestión de transporte (**TMS**) con optimización de rutas VRP (*Vehicle Routing Problem*) usando **Laravel 13**, **Vue 3 + Vite** y la API de **OpenRouteService**.

---

## 📋 Tabla de Contenidos

- [Descripción General](#descripción-general)
- [Arquitectura](#arquitectura)
- [Tecnologías](#tecnologías)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Requisitos Previos](#requisitos-previos)
- [Instalación y Configuración](#instalación-y-configuración)
- [Variables de Entorno](#variables-de-entorno)
- [Base de Datos](#base-de-datos)
- [API REST](#api-rest)
- [Modelos de Datos](#modelos-de-datos)
- [Servicios Backend](#servicios-backend)
- [Frontend — Componentes Vue](#frontend--componentes-vue)
- [Flujo de Optimización](#flujo-de-optimización)
- [Ejemplo de Payload ORS](#ejemplo-de-payload-ors)
- [Datos de Prueba](#datos-de-prueba)
- [Roadmap](#roadmap)

---

## Descripción General

VRP TMS permite generar rutas optimizadas a partir de un vehículo libre seleccionado, teniendo en cuenta:

- **Capacidad de carga** de cada vehículo (kg)
- **Límite máximo de kilómetros** por ruta para cada vehículo
- **Ventanas horarias** de entrega por pedido (ej. 09:00–12:00)
- **Ubicación geográfica** de cada pedido (lat/lng)
- **Depósito central** como punto de inicio y fin de cada ruta

El resultado es una ruta optimizada para el vehículo elegido, respetando su límite de carga y las restricciones operativas de los pedidos compatibles.

---

## Arquitectura

```
┌───────────────────────────────────────────────────────────┐
│                        FRONTEND                           │
│   Vue 3 + Vite  ·  Leaflet (OSM)  ·  Axios               │
│   Dashboard → MapView / RouteList / OrderList             │
└──────────────────────────┬────────────────────────────────┘
                           │ HTTP / JSON  (proxy :5173 → :8000)
┌──────────────────────────▼────────────────────────────────┐
│                        BACKEND                            │
│   Laravel 13 (PHP 8.4)                                    │
│                                                           │
│   RouteOptimizationController                             │
│         │                                                 │
│         ├── RouteOptimizerService   ← orquestador VRP     │
│         │         │                                       │
│         │         └── OpenRouteServiceClient  → ORS API   │
│         │                                                 │
│         └── Eloquent ORM → SQLite                         │
└───────────────────────────────────────────────────────────┘
```

---

## Tecnologías

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | Laravel | 13.x |
| Lenguaje | PHP | 8.4 |
| Frontend | Vue 3 + Composition API | 3.4 |
| Build tool | Vite | 5.x |
| Mapas | Leaflet + OpenStreetMap | 1.9 |
| HTTP client backend | Guzzle | 7.x |
| HTTP client frontend | Axios | 1.7 |
| Base de datos | SQLite (configurable a MySQL) | — |
| API de optimización | OpenRouteService | v2 |
| Router VRP | ORS /optimization (basado en VROOM) | — |

---

## Estructura del Proyecto

```
VRP/
├── backend/                        # API Laravel
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   └── RouteOptimizationController.php   # 8 endpoints REST
│   │   ├── Models/
│   │   │   ├── Order.php           # Pedido con lat/lng/peso/ventana horaria
│   │   │   ├── Vehicle.php         # Vehículo con capacidad y origen
│   │   │   ├── Route.php           # Ruta asignada a un vehículo
│   │   │   └── RouteStop.php       # Parada individual dentro de una ruta
│   │   └── Services/
│   │       ├── OpenRouteServiceClient.php   # Cliente HTTP para ORS
│   │       └── RouteOptimizerService.php    # Orquestador VRP completo
│   ├── database/
│   │   ├── migrations/             # 4 migraciones (orders, vehicles, routes, route_stops)
│   │   └── seeders/
│   │       └── VrpSeeder.php       # 3 vehículos + 20 pedidos en Buenos Aires
│   ├── routes/
│   │   └── api.php                 # Definición de endpoints
│   ├── config/
│   │   ├── cors.php                # CORS habilitado para localhost:5173
│   │   └── services.php            # Configuración ORS API key
│   └── .env                        # Variables de entorno (no versionado)
│
└── frontend/                       # SPA Vue 3
    ├── src/
    │   ├── views/
    │   │   └── Dashboard.vue       # Vista principal con mapa y paneles
    │   ├── components/
    │   │   ├── MapView.vue          # Mapa Leaflet con marcadores y polilíneas
    │   │   ├── RouteList.vue        # Lista de rutas optimizadas con paradas
    │   │   └── OrderList.vue        # Lista de pedidos filtrable por estado
    │   ├── api/
    │   │   └── vrp.js              # Cliente axios para todos los endpoints
    │   ├── router/
    │   │   └── index.js            # Vue Router
    │   └── assets/
    │       └── main.css            # Estilos globales (custom properties)
    ├── index.html
    ├── vite.config.js              # Proxy /api → :8000
    └── package.json
```

---

## Requisitos Previos

- **PHP** 8.3 o superior
- **Node.js** 18 o superior + **npm**
- **Composer** (instalado globalmente o como `composer.bat`)
- **SQLite** (incluido en PHP por defecto) o **MySQL 8**
- Cuenta gratuita en [OpenRouteService](https://openrouteservice.org/dev/#/signup) para obtener la API key

---

## Instalación y Configuración

### 1. Clonar el repositorio

```bash
git clone https://github.com/diblasi1979/VRP.git
cd VRP
```

### 2. Instalar dependencias del backend

```bash
cd backend
composer install
```

### 3. Configurar variables de entorno

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` y completar `ORS_API_KEY` (ver [Variables de Entorno](#variables-de-entorno)).

### 4. Ejecutar migraciones y seeders

```bash
php artisan migrate
php artisan db:seed
```

### 5. Instalar dependencias del frontend

```bash
cd ../frontend
npm install
```

### 6. Levantar los servidores (2 terminales)

```bash
# Terminal 1 — Backend
cd backend
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 — Frontend
cd frontend
npm run dev
```

Abrir en el navegador: **http://localhost:5173**

---

## Docker

También puedes levantar todo el proyecto con Docker Compose en modo desarrollo.

### Requisitos

- Docker Desktop
- Puerto `5173` libre para el frontend
- Puerto `8000` libre para el backend

### Primer arranque

```bash
docker compose up --build
```

Servicios disponibles:

- Frontend: **http://localhost:5173**
- Backend API: **http://localhost:8000/api**

Qué hace el contenedor backend al iniciar:

- crea `backend/.env` si no existe
- crea `backend/database/database.sqlite` si no existe
- ejecuta `composer install`
- genera `APP_KEY` si falta
- corre migraciones
- ejecuta seeders solo en el primer arranque de una base nueva

### OpenRouteService API key

Si quieres usar la optimización real contra ORS, define `ORS_API_KEY` en `backend/.env`.

Ejemplo:

```env
ORS_API_KEY=tu_api_key
```

### Reiniciar desde cero

Para reinstalar dependencias de contenedor y volver a arrancar:

```bash
docker compose down -v
docker compose up --build
```

Si además quieres regenerar los datos demo de SQLite, elimina `backend/database/database.sqlite` o ejecuta:

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

---

## Variables de Entorno

Archivo: `backend/.env`

| Variable | Descripción | Ejemplo |
|---|---|---|
| `APP_KEY` | Clave de cifrado de Laravel | `base64:...` (generada con `key:generate`) |
| `APP_URL` | URL del servidor backend | `http://localhost:8000` |
| `DB_CONNECTION` | Motor de base de datos | `sqlite` ó `mysql` |
| `DB_DATABASE` | Ruta a SQLite o nombre de la DB | `database/database.sqlite` |
| `ORS_API_KEY` | API key de OpenRouteService | `5b3ce3...` |

> ⚠️ El archivo `.env` **no se versiona** por seguridad. Usar `.env.example` como plantilla.

---

## Base de Datos

### Diagrama de tablas

```
orders
├── id
├── address        (texto descriptivo)
├── lat / lng      (coordenadas decimales)
├── weight         (kg, decimal)
├── time_window_start / time_window_end   (HH:MM)
├── status         (pending | assigned | delivered)
└── notes

vehicles
├── id
├── name
├── capacity       (kg máximos)
├── max_route_distance_km   (km máximos por ruta)
├── start_lat / start_lng   (depósito/origen)
└── is_active

routes
├── id
├── vehicle_id     → vehicles.id
├── total_distance (metros)
├── total_duration (segundos)
├── status         (pending | optimized | in_progress | completed)
├── optimized_at
└── completed_at   (fecha de cierre para historial)

route_stops
├── id
├── route_id       → routes.id
├── order_id       → orders.id
├── stop_sequence  (orden de visita)
└── estimated_arrival  (HH:MM)
```

---

## API REST

Base URL: `http://localhost:8000/api`

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/orders` | Lista todos los pedidos. Acepta `?status=pending\|assigned\|delivered` |
| `POST` | `/orders` | Crea un nuevo pedido |
| `PATCH` | `/orders/{id}/status` | Actualiza el estado de u
.n pedido |
| `GET` | `/vehicles` | Lista todos los vehículos e informa si están libres mediante `is_available` |
| `POST` | `/vehicles` | Crea un nuevo vehículo con capacidad y límite máximo de km por ruta |
| `GET` | `/routes` | Lista rutas con vehículo y paradas. Acepta `?status=active|pending|optimized|in_progress|completed`, `?vehicle_id=` y `?date_from=&date_to=` |
| `DELETE` | `/routes` | Elimina solo rutas activas y restablece pedidos `assigned` a `pending` |
| `POST` | `/optimize-routes` | **Endpoint principal**: ejecuta la optimización VRP para un `vehicle_id` libre y puede devolver `unassigned_orders` |

### Ejemplo — POST /api/optimize-routes

```json
{
  "vehicle_id": 1
}
```

### Ejemplo — POST /api/orders

```json
{
  "address": "Av. Corrientes 1234, CABA",
  "lat": -34.6037,
  "lng": -58.3816,
  "weight": 42.5,
  "time_window_start": "09:00",
  "time_window_end": "12:00",
  "notes": "Timbre piso 3"
}
```

### Ejemplo — Respuesta de /api/optimize-routes

```json
{
  "success": true,
  "message": "1 ruta(s) optimizadas correctamente para Furgón BA-01.",
  "data": {
    "routes": [
      {
        "id": 1,
        "vehicle_id": 1,
        "total_distance": 45230,
        "total_distance_km": 45.2,
        "total_duration": 5400,
        "status": "optimized",
        "vehicle": { "id": 1, "name": "Furgón BA-01", "capacity": 800, "max_route_distance_km": 120 },
        "stops": [
          {
            "stop_sequence": 1,
            "estimated_arrival": "09:35",
            "order": {
              "address": "Av. Corrientes 1234, San Nicolás, CABA",
              "weight": 42,
              "time_window_start": "08:00",
              "time_window_end": "11:00"
            }
          }
        ]
      }
    ],
    "unassigned_orders": []
  }
}
```

Si el vehículo seleccionado no puede cubrir todos los pedidos, la respuesta sigue siendo exitosa y `unassigned_orders` contendrá los pedidos que quedaron fuera de la optimización.

---

## Modelos de Datos

### Order

- `getTimeWindowTimestamps()` — Convierte `HH:MM` a timestamps Unix del día actual, requeridos por ORS.

### Route

- Relación `stops()` — Devuelve las paradas ordenadas por `stop_sequence`.
- Cuando todas sus paradas quedan en `delivered`, la ruta se marca como `completed` y se conserva como historial.

### RouteStop

- Une `Route` con `Order`, almacena la hora estimada de llegada.

---

## Servicios Backend

### `OpenRouteServiceClient`

Envuelve la API de ORS con manejo de errores HTTP:

- `getMatrix(array $locations)` — Llama a `/v2/matrix/driving-car` para obtener tiempos y distancias entre N puntos.
- `optimize(array $payload)` — Llama a `/optimization` con el payload VRP completo.

Errores mapeados: 401 (key inválida), 403 (límites de plan), 429 (rate limit).

### `RouteOptimizerService`

Orquestador principal:

1. Recibe el `vehicle_id` seleccionado desde la UI
2. Valida que el vehículo esté `is_active = true` y sin rutas activas
3. Filtra pedidos `pending` cuyo peso no supere la capacidad del vehículo
4. Construye el payload VRP (jobs + vehículo) en formato ORS, incluyendo `max_distance`
5. Llama a `OpenRouteServiceClient::optimize()`
6. Persiste la ruta y sus paradas en una transacción DB
7. Devuelve `unassigned_orders` cuando ORS no puede incluir algunos pedidos sin tratarlo automáticamente como error duro
8. Marca los pedidos involucrados como `assigned`
9. Cuando todas las paradas de una ruta se informan como entregadas, la ruta pasa a `completed` y queda disponible para consultas históricas

---

## Frontend — Componentes Vue

### `Dashboard.vue`

Vista principal. Gestiona estado global (ordenes, vehículos, rutas activas e historial), acciones (generar ruta, limpiar, recargar) y muestra alertas con auto-cierre a los 6 segundos.

Antes de optimizar, obliga a seleccionar un vehículo libre y muestra su disponibilidad en la lista. Además permite marcar una parada como entregada desde la propia lista de rutas activas. Cuando todas las paradas de una ruta quedan entregadas, la ruta pasa automáticamente al historial.

Al dar de alta un vehículo, también se define su límite máximo de kilómetros por ruta y ese valor se muestra en la selección del dashboard.

Si la optimización deja pedidos fuera por restricciones, el dashboard los muestra en una sección específica de pedidos no asignados.

### `History.vue`

Pantalla dedicada a consultas históricas:

- Filtro por vehículo
- Filtro por rango de fechas
- Resumen de rutas, kilómetros y paradas completadas
- Lista rutas completadas y abre un modal con el detalle histórico de paradas

### `MapView.vue`

Mapa interactivo basado en **Leaflet + OpenStreetMap**:

- Marcador 🏭 para el depósito
- Marcadores numerados por ID de pedido, coloreados según estado (`pending` = naranja, `assigned` = azul, `delivered` = verde)
- Polilíneas coloreadas por vehículo (hasta 8 colores)
- Auto-zoom al conjunto de rutas al optimizar
- Popups con información completa de cada punto

### `RouteList.vue`

Lista de rutas generadas:
- Cabecera con nombre del vehículo, distancia total, duración y número de paradas
- Paradas ordenadas con secuencia, dirección, hora estimada, peso y ventana horaria
- Se reutiliza tanto para rutas activas como para rutas completadas guardadas en historial

### `OrderList.vue`

Lista filtrable de pedidos por estado (`Todos / Pendientes / Asignados / Entregados`) con scroll interno.

---

## Flujo de Optimización

```
Usuario selecciona un vehículo libre en el dashboard
  │
  ▼
POST /api/optimize-routes { vehicle_id }
        │
        ▼
RouteOptimizerService::optimize(vehicle_id)
        │
  ├─ 1. Valida que el vehículo siga libre
    ├─ 2. Carga pedidos (status = 'pending') compatibles con su capacidad
  ├─ 3. Construye payload VRP:
      │       jobs:     [{ id, location:[lng,lat], amount:[kg], time_windows:[[ts_start,ts_end]] }]
    │       vehicles: [{ id, profile:'driving-car', start:[lng,lat], end:[lng,lat], capacity:[kg], max_distance:[m] }]
        │
        ├─ 4. POST https://api.openrouteservice.org/optimization
        │
        ├─ 5. Parsea respuesta ORS:
  │       routes[].steps (type='job') → secuencia de entregas del vehículo seleccionado
        │
        └─ 6. DB Transaction:
    - Crea Route para el vehículo elegido con sus paradas
                - Actualiza orders.status → 'assigned'
```

---

## Ejemplo de Payload ORS

```json
{
  "jobs": [
    {
      "id": 1,
      "location": [-58.3816, -34.6037],
      "amount": [42],
      "time_windows": [[1744182000, 1744193400]],
      "description": "Av. Corrientes 1234, San Nicolás, CABA"
    }
  ],
  "vehicles": [
    {
      "id": 1,
      "profile": "driving-car",
      "start": [-58.4012, -34.6345],
      "end":   [-58.4012, -34.6345],
      "capacity": [800]
    }
  ]
}
```

> 📌 ORS usa el formato **[longitud, latitud]** (GeoJSON), no el convencional [lat, lng].

---

## Datos de Prueba

El seeder `VrpSeeder` carga automáticamente:

**3 vehículos** — Depósito: Parque Patricios, CABA (`-34.6345, -58.4012`)

| Vehículo | Capacidad |
|---|---|
| Furgón BA-01 | 800 kg · 120 km |
| Furgón BA-02 | 600 kg · 90 km |
| Camioneta BA-03 | 400 kg · 70 km |

**20 pedidos** distribuidos por Buenos Aires:

| Zona | Barrios/Localidades |
|---|---|
| CABA | San Nicolás, Palermo, Caballito, Belgrano, Recoleta, Boedo, Villa Crespo, Villa Urquiza, Saavedra, Flores, Mataderos |
| GBA Sur | Lanús, Avellaneda, Quilmes, Lomas de Zamora |
| GBA Oeste | Ramos Mejía, Morón |
| GBA Norte | Vicente López, San Isidro, San Miguel |

---

## Roadmap

- [ ] Autenticación con Laravel Sanctum
- [ ] Módulo de alta de pedidos desde el frontend
- [ ] Seguimiento en tiempo real (WebSockets / polling)
- [ ] Exportación de rutas a PDF
- [ ] Soporte para múltiples depósitos
- [ ] Cálculo de costos por ruta (combustible, tiempo)
- [ ] Integración con Google Maps como alternativa a ORS
- [ ] Tests unitarios y de integración

---

## Licencia

MIT © 2026
