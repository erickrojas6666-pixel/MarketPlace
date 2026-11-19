<?php

/**
 * Reglas de negocio de OficiosYa (configurables sin tocar código).
 * Los módulos aún no construidos se activan con su variable en .env.
 */
return [

    // RF-26: horas que el cliente tiene para confirmar un trabajo completado.
    // Pasado el plazo, el scheduler lo confirma solo (48 a 72 h según el documento).
    'horas_confirmacion_automatica' => (int) env('MARKETPLACE_HORAS_CONFIRMACION', 72),

    // Comisión de la plataforma sobre cada trabajo concretado (10 % a 15 % según el documento).
    'comision_porcentaje' => (float) env('MARKETPLACE_COMISION', 12),

    // Correo transaccional: 'resend' (API REST) o 'smtp' (usa MAIL_* de Laravel; MAIL_MAILER=log para probar).
    'correo' => env('MARKETPLACE_CORREO', 'resend'),

    // Pasarela de pago: 'simulada' (por defecto; retiene el pago al instante, sin dinero real)
    // o 'mercadopago' (Checkout Pro; requiere MP_ACCESS_TOKEN).
    'pasarela' => env('MARKETPLACE_PASARELA', 'simulada'),

    // Fotos (perfil, portafolio y evidencia de pedidos). Ver docs/FOTOS.md.
    // En local: 'public' y 'local'. En producción: 'r2_publico' y 'r2_privado' (Cloudflare R2).
    'fotos' => [
        'disco_publico'            => env('FOTOS_DISCO_PUBLICO', 'public'),
        'disco_privado'            => env('FOTOS_DISCO_PRIVADO', 'local'),
        'max_kb'                   => (int) env('FOTOS_MAX_KB', 6144),
        'lado_max'                 => 1600,   // px, imagen principal
        'lado_miniatura'           => 400,    // px, miniatura
        'max_por_servicio'         => 8,      // fotos de portafolio por servicio
        'max_evidencias_por_parte' => 12,     // fotos de evidencia por pedido y por persona
        'minutos_url_privada'      => 5,
    ],

    // Verificación de identidad (RF-05 a RF-08). Los documentos van cifrados en el disco privado.
    'verificacion' => [
        'tipos_requeridos'       => ['ine_frente', 'ine_reverso', 'comprobante_domicilio'],
        'lado_max'               => 2000,   // px: la INE debe seguir legible
        'exigir_documentos'      => (bool) env('VERIFICACION_EXIGIR_DOCUMENTOS', true),
        'retencion_dias'         => (int) env('VERIFICACION_RETENCION_DIAS', 180),
        'minutos_acceso_revisor' => 15,
    ],

    // Interruptores de módulos (ver TransicionesPedido::mapa()).
    'cotizaciones_habilitadas' => (bool) env('MARKETPLACE_COTIZACIONES', false),
    'escrow_habilitado'        => (bool) env('MARKETPLACE_ESCROW', false),
    'disputas_habilitadas'     => (bool) env('MARKETPLACE_DISPUTAS', false),

];
