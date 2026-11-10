# Web services de terceros — OficiosYa

| # | Servicio | Para qué | Dónde está |
|---|----------|----------|------------|
| 1 | **Nominatim (OpenStreetMap)** | Geocodificación: convertir una dirección o colonia en coordenadas | `UbicacionController` (proxy con caché y límite), `BuscadorUbicacion.vue` |
| 2 | **OpenStreetMap + Leaflet** | Mapa de la zona de búsqueda y de cobertura del prestador | `MapaUbicacion.vue` (Servicios y Perfil del prestador) |
| 3 | **Resend** | Correos transaccionales (cambios de estado del pedido) | `ResendCorreo`, `CorreoChannel`, `EstadoPedidoActualizado::toCorreo()` |
| 4 | **Mercado Pago (Checkout Pro)** | Cobro con tarjeta, OXXO o SPEI; el dinero queda retenido (escrow) | `MercadoPagoService`, `IniciarPagoMercadoPago`, `ConfirmarPagoMercadoPago` |

Además, las notificaciones push usan los servicios de mensajería del navegador (FCM, Mozilla, Apple, Windows).

Principios comunes: cada servicio está detrás de un *adapter*; las llaves viven solo en `.env`;
sin llave, la función se desactiva sin romper la app; los errores nunca se muestran con detalle al usuario.

---

## 2. Mapa (Leaflet + OpenStreetMap)
No requiere llaves. Tras `npm install` y `npm run build` funciona.
- Atribución a OpenStreetMap visible en el mapa (obligatoria por su licencia).
- Política de uso de teselas: https://operations.osmfoundation.org/policies/tiles/ (uso moderado, apto para este proyecto).
- Privacidad: al ver el mapa, el navegador pide teselas a OpenStreetMap, que puede inferir la zona general.
  Las coordenadas **no** se envían a nuestro servidor por esto, y los prestadores nunca exponen su ubicación exacta.

## 3. Correos (Resend)
1. Crea cuenta en https://resend.com y genera una **API key**.
2. En `.env`:
   ```
   RESEND_API_KEY=re_xxxxxxxx
   RESEND_FROM="OficiosYa <onboarding@resend.dev>"
   ```
3. `php artisan config:clear` y prueba: `php artisan correo:probar tu-correo@ejemplo.com`.

Con el remitente de pruebas `onboarding@resend.dev` Resend **solo entrega al correo con el que te registraste**.
Para enviar a cualquier usuario hay que verificar un dominio propio y usar un remitente de ese dominio.

## 4. Pagos (Mercado Pago)
Por defecto `MARKETPLACE_PASARELA=simulada` (sin dinero real). Para probar con Mercado Pago:

1. Entra a https://www.mercadopago.com.mx/developers con tu cuenta de Mercado Pago → **Tus integraciones** →
   **Crear aplicación** (tipo Checkout Pro). Mercado Pago crea automáticamente una cuenta de prueba *vendedor*.
2. En esa aplicación abre **Credenciales de prueba** y copia el **Access Token**. Empieza con `APP_USR-`
   (no con `TEST-`). Ese es tu `MP_ACCESS_TOKEN`.
3. En **Cuentas de prueba** crea una cuenta de prueba **comprador** (guarda su usuario y contraseña; si pide un código
   de 6 dígitos, está en la misma sección).
4. En `.env`:
   ```
   MARKETPLACE_PASARELA=mercadopago
   MP_ACCESS_TOKEN=APP_USR-xxxxxxxx
   MP_SANDBOX=false
   APP_URL=http://127.0.0.1:8000
   ```
   `MP_SANDBOX` debe quedar en `false`: las pruebas de Checkout Pro usan el `init_point` normal con cuentas de prueba;
   `sandbox_init_point` suele provocar el error "Oh, no, algo anduvo mal".
5. `php artisan config:clear`.
6. **Para pagar**, abre una **ventana de incógnito**, inicia sesión en Mercado Pago con la cuenta de prueba
   *comprador*, y desde esa misma ventana entra a la app y pulsa **Pagar**. Usa las tarjetas de prueba de la
   documentación de Mercado Pago; si pide documento, elige "OTRO" y escribe 9 dígitos.

Si el token del paso 2 no funciona (error 401 al crear el pago), usa la alternativa que describe Mercado Pago para
Checkout Pro: inicia sesión (incógnito) con la cuenta de prueba *vendedor*, crea una aplicación desde esa cuenta y usa
el Access Token de sus **Credenciales de producción**.

Flujo: el cliente pulsa **Pagar** → se crea el pago en estado *pendiente* → se le redirige a Mercado Pago →
al regresar (`/pagos/retorno`) o al llegar la notificación (`POST /api/webhooks/mercadopago`) el servidor
**consulta el pago a la API de Mercado Pago**; si está aprobado y el monto coincide, queda *retenido*.
Se libera al prestador cuando el cliente confirma el trabajo (`POST /api/pagos/{id}/liberar`).

Notificaciones (webhook): Mercado Pago necesita una URL pública `https`. En local usa un túnel
(ngrok, Cloudflare Tunnel...) y pon `MP_NOTIFICATION_URL=https://TU-TUNEL/api/webhooks/mercadopago`.
Sin túnel funciona igual gracias al regreso del cliente; solo se pierde el aviso si cierra la pestaña antes de volver.
Opcional: `MP_WEBHOOK_SECRET` (clave secreta de la sección *Webhooks* de tu aplicación) activa la verificación de firma.

Seguridad: el token nunca llega al navegador (no lo subas al repositorio ni lo pegues en chats); el estado del pago
siempre se verifica contra la API (una notificación falsa no puede marcar un pago como pagado); se valida monto y
moneda; un pago se confirma una sola vez.
