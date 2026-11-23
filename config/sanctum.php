<?php

/**
 * Solo se sobrescribe lo necesario; el resto toma los valores por defecto del paquete.
 */
return [

    // La API acepta ÚNICAMENTE tokens Bearer: ninguna cookie de sesión web sirve para
    // autenticarse en /api (así no hay CSRF ni mezcla con el login de Inertia).
    'guard' => [],

    // Minutos de vigencia de cada token (null = no caduca). 10080 = 7 días.
    'expiration' => env('SANCTUM_EXPIRATION', 10080),

    // Prefijo que facilita detectar tokens filtrados con escáneres de secretos (gitleaks).
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'oficiosya_'),

];
