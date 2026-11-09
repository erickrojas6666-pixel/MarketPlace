# API de OficiosYa

Base: `http://127.0.0.1:8000/api` · Formato: JSON · Autenticación: token **Bearer** (Laravel Sanctum).

Los errores siempre son JSON y nunca incluyen rutas, clases ni consultas SQL
(con `APP_DEBUG=false`). Un recurso ajeno responde **404**, igual que uno inexistente.

## Los 6 servicios propios

| # | Servicio | Endpoints | Acceso |
|---|----------|-----------|--------|
| 1 | Autenticación | `POST /auth/login` · `GET /auth/me` · `POST /auth/logout` | login público; resto con token |
| 2 | Catálogo | `GET /servicios` · `GET /servicios/{id}` | público |
| 3 | Búsqueda por cercanía | `GET /busqueda?lat=&lng=&radio_km=` | público |
| 4 | Pedidos | `GET /pedidos` · `POST /pedidos` · `GET /pedidos/{id}` · `PATCH /pedidos/{id}/estado` | token |
| 5 | Pagos (retención y liberación) | `GET /pagos` · `POST /pagos` · `GET /pagos/{id}` · `POST /pagos/{id}/liberar` | token |
| 6 | Reseñas | `GET /resenas?servicio_id=` · `POST /resenas` | lectura pública; crear con token |

## 1. Autenticación

```
POST /api/auth/login
{ "email": "cliente@correo.com", "password": "********", "device_name": "mi-celular" }
```
Respuesta `200`:
```json
{ "token": "oficiosya_1|abc...", "token_type": "Bearer",
  "expires_at": "2026-11-15T12:00:00-06:00",
  "usuario": { "id": 6, "nombre": "Erick", "email": "...", "roles": ["cliente"] } }
```
Usa el token en cada petición: `Authorization: Bearer <token>`.

- Máx. 5 intentos fallidos por correo+IP por minuto (`429` con `Retry-After`) y 10 peticiones/min por IP.
- Mismo mensaje (`401`) exista o no el correo.
- Máx. 5 tokens activos por usuario; vigencia `SANCTUM_EXPIRATION` minutos (7 días por defecto).
- `POST /auth/logout` revoca solo el token usado.

## 2. Catálogo
`GET /servicios?categoria_id=&q=&per_page=` — solo servicios activos de prestadores verificados.
Incluye `niveles` (paquetes), `calificacion_promedio` y `resenas_count`. Del prestador solo sale el nombre.

## 3. Búsqueda por cercanía
`GET /busqueda?lat=19.04&lng=-98.20&radio_km=10` — `lat` y `lng` obligatorios; `radio_km` 1–50 (10 por defecto).
Solo prestadores que atienden ese punto (dentro del radio pedido y de su propia cobertura), del más
cercano al más lejano, con `distancia_km`. La ubicación no se guarda.

## 4. Pedidos
- `POST /pedidos` `{ "servicio_id": 1, "direccion_servicio": "Calle 5", "fecha_deseada": "2026-11-20" }` → `201`.
- `GET /pedidos?estado=aceptado` — los tuyos (como cliente o como prestador).
- `PATCH /pedidos/{id}/estado` `{ "estado": "aceptado" }` — sigue la máquina de estados (RF-24);
  si no corresponde: `422` con el motivo. Cada pedido trae `acciones_permitidas` para tu usuario.

## 5. Pagos (escrow)
1. Con el pedido en `aceptado`, el cliente hace `POST /pagos` `{ "pedido_id": 3 }` → pago **retenido** (`201`).
2. Cuando el pedido está `confirmado`, el cliente (o un admin) hace `POST /pagos/{id}/liberar`
   → el prestador recibe el monto menos la comisión (`MARKETPLACE_COMISION`, 12 % por defecto).
3. `estado` del pago: `retenido` · `liberado` · `reembolsado`. Los `movimientos` son el libro contable.

La pasarela es **simulada** (`metodo: "simulado"`); Mercado Pago/Stripe sustituirá solo la referencia.
Con `MARKETPLACE_ESCROW=true` el pedido pasa solo a `pagado` al retener el pago; con `false` (por defecto)
el flujo del pedido no cambia.

## 6. Reseñas
- `POST /resenas` `{ "pedido_id": 1, "calificacion": 5, "comentario": "..." }` — solo el cliente, solo pedido `confirmado`, una por pedido.
- `GET /resenas?servicio_id=1` — públicas; solo el primer nombre del autor.

## Reintentos seguros (`Idempotency-Key`)
`POST /pedidos` y `POST /pagos` aceptan la cabecera `Idempotency-Key: <uuid>`. Repetir la misma petición
no duplica el pedido ni el pago: responde `200` con el mismo resultado (la primera vez `201`).

## Códigos de respuesta
`200/201` éxito · `401` sin token o credenciales incorrectas · `404` no existe o no es tuyo ·
`422` validación o regla de negocio (`{"message","errors"}`) · `429` demasiadas peticiones.

## Flujo completo de prueba
cliente: `POST /pedidos` → prestador: `PATCH estado=aceptado` → cliente: `POST /pagos` →
prestador: `PATCH en_progreso` → `PATCH completado` → cliente: `PATCH confirmado` →
cliente: `POST /pagos/{id}/liberar` → cliente: `POST /resenas`.
