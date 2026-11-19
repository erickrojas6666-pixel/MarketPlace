# Verificación de identidad (RF-05 a RF-08)

## Flujo
1. El prestador abre **Verificación de identidad** (`/prestador/verificacion`) y sube INE (frente y reverso) y comprobante de domicilio.
2. Acepta el aviso de privacidad y pulsa **Enviar a revisión** → estado visible `en_revision` (internamente `pendiente` + `verificacion_enviada_at`).
3. El revisor abre el perfil del prestador (`/admin/prestadores/{id}`), declara el **motivo** y ve los documentos. Puede marcar cada uno como válido o rechazado (con motivo).
4. Aprueba (`verificado`) o rechaza (con motivo, visible para el prestador). Al aprobar, los documentos pendientes pasan a `valido`.
5. Tras un rechazo, el prestador corrige y vuelve a enviar.

## Seguridad
- Imágenes re-codificadas (sin EXIF/GPS) y **cifradas con AES-256 (APP_KEY)** antes de guardarse en el disco privado (`.enc`). Nunca hay URL: se leen por la aplicación tras autorizar.
- Huella sha256 verificada en cada lectura (detecta archivos alterados).
- Acceso del revisor: motivo obligatorio → autorización de 15 min sobre ese perfil → cada lectura queda en `log_auditoria` (`documento.consultado`).
- El Service Worker nunca cachea estas rutas; las respuestas llevan `no-store`.
- Retención: `php artisan verificacion:purgar-documentos` (diario 03:30) elimina el archivo de documentos resueltos tras `VERIFICACION_RETENCION_DIAS` días (180 por defecto) y conserva el registro.

## Importante
- **No cambies ni pierdas `APP_KEY`**: los documentos cifrados dejarían de poder leerse. Si la rotas, conserva la anterior en `APP_PREVIOUS_KEYS`.
- `VERIFICACION_EXIGIR_DOCUMENTOS=false` permite aprobar sin documentos (útil solo para demos).

## Pendiente
- Referencias personales y antecedentes (RF-05) y documentos en PDF.
- Que el rol `verificador` entre al panel (hoy el panel admite `admin` y `superadmin`).
- Notificar a los revisores cuando llega una solicitud nueva.
