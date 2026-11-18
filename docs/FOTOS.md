# Fotos: perfil, portafolio y evidencia

## Qué hay
| Uso | Quién sube | Dónde se guarda | Cómo se lee |
|---|---|---|---|
| Foto de perfil | cualquier usuario | disco **público** (`perfiles/{user}/…`) | URL pública (miniatura 400 px) |
| Portafolio del servicio (catálogo y perfil público) | prestador verificado, dueño del servicio, máx. 8 por servicio | disco **público** (`portafolio/{servicio}/…`) | URL pública |
| Evidencia del pedido (antes/durante/después) | prestador (en_progreso, completado) y cliente (completado, en_disputa), máx. 12 por persona | disco **privado** (`evidencia/{pedido}/…`) | `GET /evidencias/{id}` → autoriza → redirige a URL firmada de 5 min |

Perfil público del prestador: `GET /prestadores/{id}` (solo verificados).

## Seguridad aplicada
- Tipo real por contenido (`getimagesizefromstring`), solo JPG/PNG/WebP; SVG rechazado.
- Límite de 25 megapíxeles y 6 MB; toda imagen se **re-codifica a WebP** (sin EXIF/GPS ni cargas ocultas).
- Nombres aleatorios (ULID); nunca el nombre original.
- Evidencia privada: nunca URL pública, cada lectura autorizada por `PedidoPolicy`; el mediador solo con el pedido en disputa y con rastro en `log_auditoria`.
- El Service Worker nunca cachea `/evidencias/*`.
- Subidas con `throttle` por usuario.

## Configuración
Local (sin Cloudflare): `FOTOS_DISCO_PUBLICO=public`, `FOTOS_DISCO_PRIVADO=local` y `php artisan storage:link`.
Producción: `FOTOS_DISCO_PUBLICO=r2_publico`, `FOTOS_DISCO_PRIVADO=r2_privado` + variables `R2_*` (ver `.env.example`).
Render gratis tiene disco efímero: sin R2 las fotos se pierden en cada despliegue.

## Alcance actual (pendiente)
- Subida con cola offline (hoy las fotos requieren conexión; el resto de la app sí funciona offline).
- Reseñas con fotos (RF-36) y publicar evidencia en el portafolio (requiere flujo de consentimiento del cliente).
- Limpieza de huérfanos si falla un borrado en el almacenamiento.
