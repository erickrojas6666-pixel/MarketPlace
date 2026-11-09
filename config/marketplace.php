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

    // Interruptores de módulos (ver TransicionesPedido::mapa()).
    'cotizaciones_habilitadas' => (bool) env('MARKETPLACE_COTIZACIONES', false),
    'escrow_habilitado'        => (bool) env('MARKETPLACE_ESCROW', false),
    'disputas_habilitadas'     => (bool) env('MARKETPLACE_DISPUTAS', false),

];
