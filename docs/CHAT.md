# Chat del pedido (RF-22)

Conversación entre el cliente y el prestador de un pedido. Solo texto.

## Cómo funciona

- **Tiempo real por Web Push, no por consultas constantes.** Cada mensaje nuevo dispara un push
  a la otra persona (`NuevoMensajeChat`, payload con `tipo: 'chat'` y `pedido_id`). El Service
  Worker (`resources/js/sw.js`) lo recibe y avisa a las ventanas abiertas con `postMessage`; la
  conversación abierta entonces pide solo lo nuevo con `GET /pedidos/{id}/mensajes?despues=ID`.
  Si la app está a la vista no se muestra notificación; si no, sí (misma etiqueta por pedido:
  se reemplazan, no se apilan). Safari/iPhone exige mostrar siempre la notificación, así que
  ahí se muestra aunque la app esté abierta.
- **Red de seguridad.** Un push puede no llegar (permiso denegado, suscripción caducada,
  navegador sin soporte, VAPID sin configurar). Por eso el chat abierto consulta además:
  cada 60 s si el dispositivo tiene push, y de 8 a 30 s (más espaciado si la charla está
  quieta) si no lo tiene. Con la pestaña oculta o sin red se pausa. Si falla: retroceso 8/16/32/60 s.
  La primera carga trae los últimos 100 mensajes.
- **Enviar.** `POST /pedidos/{id}/mensajes` con `Idempotency-Key`. Sin red, o si la respuesta se
  pierde, el mensaje pasa a la cola offline (`resources/js/offline/sync.js`) **con la misma
  clave**, así que el servidor no lo duplica ni vuelve a mandar el push. Mientras tanto se ve
  como "Pendiente de enviar".
- **Leído.** `POST /pedidos/{id}/mensajes/leidos` marca lo recibido; el emisor ve "Visto"
  (`leidos_hasta` en la respuesta de lectura). Leer no genera push, así que el "Visto" puede
  tardar hasta la siguiente consulta de respaldo (≈ 1 min) o hasta el siguiente mensaje.
- **Contador.** `GET /chat/no-leidos` da los mensajes sin leer por pedido. El dashboard lo
  consulta al montar, al recibir un aviso del Service Worker, al volver a la pestaña y cada 60 s.
- **El aviso no incluye el mensaje** (se vería en la pantalla de bloqueo). Abre `/dashboard?chat=ID`.

## Reglas

- Solo cliente y prestador del pedido. A cualquier otra persona, incluido un mediador,
  se le responde 404.
- Se escribe mientras el pedido está pendiente, cotizado, aceptado, pagado, en progreso,
  completado o en disputa. Confirmado, cancelado o rechazado: solo lectura.
- Máximo `CHAT_MAX_CARACTERES` (1000) por mensaje. Límites por persona: 30 envíos/min y 90 lecturas/min.
- El navegador no guarda los mensajes; solo la cola de envío, que se borra al cerrar sesión.
- El frontend muestra el texto con `{{ }}` (nunca `v-html`).

## Retención de datos

`php artisan chat:purgar-mensajes` (diario 03:45) borra las conversaciones de pedidos
confirmados, cancelados o rechazados cuya última actualización superó `CHAT_RETENCION_DIAS`
(180). Un pedido en disputa no es final, así que su chat se conserva. Con un valor inválido
(< 1) no borra nada. Deja un registro en `log_auditoria` solo con cantidades.
En Render gratis el scheduler solo corre si el servicio está despierto; puede lanzarse a mano.

## Pendiente

- Imágenes en el chat (las columnas `adjunto_ruta` y `adjunto_hash` ya existen).
- Lectura del chat por el mediador cuando el pedido está en disputa (con auditoría).
- Paginación hacia atrás de conversaciones de más de 100 mensajes.
- Tiempo real con WebSocket (Reverb) si se migra a un servidor con proceso persistente.
- Enviar el push después de responder (`defer()`): hoy se envía dentro de la petición y suma latencia al emisor.
