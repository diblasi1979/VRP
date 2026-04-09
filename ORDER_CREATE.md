# ORDER_CREATE — Documentación del servicio de pedidos

Referencia completa de los endpoints REST para crear y consultar pedidos en el sistema VRP TMS.

---

## Endpoints disponibles

| Método | URL | Descripción |
|--------|-----|-------------|
| `POST` | `/api/orders` | Crea un nuevo pedido |
| `GET` | `/api/orders` | Lista todos los pedidos (con filtro opcional por estado) |

Base URL del servidor de desarrollo: `http://127.0.0.1:8000`

---

## POST `/api/orders` — Crear pedido

### Headers

| Header | Valor |
|--------|-------|
| `Content-Type` | `application/json` |
| `Accept` | `application/json` |

### Body (JSON)

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `address` | `string` (max 255) | ✅ Sí | Dirección legible del punto de entrega |
| `lat` | `number` (-90 a 90) | ✅ Sí | Latitud geográfica |
| `lng` | `number` (-180 a 180) | ✅ Sí | Longitud geográfica |
| `weight` | `number` (> 0) | ✅ Sí | Peso del pedido en **kg** |
| `time_window_start` | `string` (HH:MM) | ❌ No | Inicio de la ventana de entrega (ej. `"09:00"`) |
| `time_window_end` | `string` (HH:MM) | ❌ No | Fin de la ventana de entrega (ej. `"13:00"`) |
| `notes` | `string` | ❌ No | Observaciones para el repartidor |

> **Valores por defecto** al omitir los campos opcionales:
> - `time_window_start`: `"08:00"`
> - `time_window_end`: `"18:00"`
> - `notes`: `null`
> - `status`: `"pending"` (asignado automáticamente, no se envía)

### Ejemplo de request

```http
POST http://127.0.0.1:8000/api/orders
Content-Type: application/json

{
  "address": "Av. Corrientes 1234, CABA, Argentina",
  "lat": -34.6037,
  "lng": -58.3816,
  "weight": 12.5,
  "time_window_start": "09:00",
  "time_window_end": "14:00",
  "notes": "Dejar en portería, piso 3"
}
```

### Respuesta exitosa — `201 Created`

```json
{
  "success": true,
  "data": {
    "id": 21,
    "address": "Av. Corrientes 1234, CABA, Argentina",
    "lat": "-34.6037000",
    "lng": "-58.3816000",
    "weight": "12.50",
    "time_window_start": "09:00",
    "time_window_end": "14:00",
    "status": "pending",
    "notes": "Dejar en portería, piso 3",
    "created_at": "2026-04-09T15:00:00.000000Z",
    "updated_at": "2026-04-09T15:00:00.000000Z"
  }
}
```

### Respuesta de error de validación — `422 Unprocessable Content`

```json
{
  "message": "The lat field is required.",
  "errors": {
    "lat": ["The lat field is required."]
  }
}
```

---

## GET `/api/orders` — Listar pedidos

### Query params (opcionales)

| Parámetro | Valores posibles | Descripción |
|-----------|------------------|-------------|
| `status` | `pending` \| `assigned` \| `delivered` | Filtra pedidos por estado |

### Ejemplos

```http
GET http://127.0.0.1:8000/api/orders
GET http://127.0.0.1:8000/api/orders?status=pending
GET http://127.0.0.1:8000/api/orders?status=assigned
```

### Respuesta exitosa — `200 OK`

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "address": "San Martín 456, San Nicolás, CABA",
      "lat": "-34.6074000",
      "lng": "-58.3735000",
      "weight": "18.50",
      "time_window_start": "08:00",
      "time_window_end": "12:00",
      "status": "pending",
      "notes": null,
      "created_at": "2026-04-09T12:00:00.000000Z",
      "updated_at": "2026-04-09T12:00:00.000000Z"
    }
  ]
}
```

---

## Estados del pedido

| Estado | Descripción |
|--------|-------------|
| `pending` | Pedido creado, aún no asignado a ninguna ruta |
| `assigned` | Asignado a una ruta durante la optimización (`POST /api/optimize-routes`) |
| `delivered` | Marcado como entregado (`PATCH /api/orders/{id}/status`) |

### Cambiar estado manualmente

```http
PATCH http://127.0.0.1:8000/api/orders/{id}/status
Content-Type: application/json

{
  "status": "delivered"
}
```

---

## Integración con el optimizador de rutas

Solo los pedidos con `status = "pending"` son tomados en cuenta al ejecutar `POST /api/optimize-routes`. Una vez optimizados, su estado cambia automáticamente a `"assigned"`.

El flujo completo es:

```
POST /api/orders        → status: pending
POST /api/optimize-routes → status: assigned (automático)
PATCH /api/orders/{id}/status → status: delivered (manual)
```

---

## Prueba rápida con curl

```bash
curl -s -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "address": "Av. Santa Fe 3200, Palermo, CABA",
    "lat": -34.5954,
    "lng": -58.4105,
    "weight": 8.0,
    "time_window_start": "10:00",
    "time_window_end": "16:00"
  }'
```
