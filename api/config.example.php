<?php
/**
 * CONFIGURACIÓN DEL BACKEND — Funeraria del Zulia (Obituarios)
 * --------------------------------------------------------------
 * 1. Copia este archivo a  api/config.php  en el servidor.
 * 2. Completa la contraseña de MySQL y revisa los demás valores.
 * 3. NUNCA subas config.php al repositorio (ya está en .gitignore).
 */

return [
    'db' => [
        'host'    => 'localhost',                 // o 160.153.190.119 si localhost falla
        'name'    => 'legadoholding_obituarios',
        'user'    => 'legadoholding_chat',
        'pass'    => 'PON_AQUI_LA_CONTRASEÑA_DE_MYSQL',
        'charset' => 'utf8mb4',
    ],

    'paths' => [
        // Carpeta física donde se guardan las fotos (en el disco del servidor)
        'uploads_dir' => __DIR__ . '/../uploads/obituarios',
        // Ruta pública (relativa a la raíz del sitio) para construir las URL
        'uploads_url' => 'uploads/obituarios',
    ],

    'security' => [
        // Orígenes permitidos para CORS (solo si el panel se sirve de otro dominio).
        // Mismo dominio = no hace falta tocar nada.
        'allowed_origins' => [
            'https://www.funerariadelzulia.com',
            'https://funerariadelzulia.com',
        ],
        // Token secreto para ejecutar el cron por URL (opcional). Cambia este valor.
        'cron_secret' => 'CAMBIA_ESTE_TOKEN_LARGO_Y_ALEATORIO',
    ],

    'uploads' => [
        'max_bytes'  => 6 * 1024 * 1024,                       // 6 MB por foto
        'mime_allow' => ['image/jpeg', 'image/png', 'image/webp'],
        'max_dim'    => 1600,                                   // lado máximo en px (se redimensiona)
        'webp_quality' => 82,
    ],

    'app' => [
        'env'       => 'production',  // 'development' muestra errores
        'site_url'  => 'https://www.funerariadelzulia.com',
    ],

    // Pagos electrónicos. Ver docs/mercantil.md y
    // docs/payments/mercantil/STATUS.md antes de tocar esta sección.
    // Mientras 'provider' sea 'simulado', ningún cobro es real.
    //
    // ARCHIVADO (2026-08-28): los endpoints prevision_mercantil_callback.php /
    // prevision_mercantil_webhook.php a los que apuntaban return_url/cancel_url/
    // notification_url se retiraron junto con el módulo de Previsión (consultaban
    // prev_pagos_electronicos / prev_pago_eventos, ver
    // docs/specs/2026-08-28-fase-e-corte-admin-prevision.md). api/lib/payments/
    // (PaymentProviderInterface, PaymentService, MercantilProvider) se conserva
    // como referencia de diseño para el adaptador de pagos de Prevision-Funeraria,
    // pero este bloque y sus URLs ya no apuntan a nada real — si este repo vuelve
    // a necesitar cobro electrónico propio (no ligado a previsión), hay que
    // reconstruir los endpoints antes de activar 'provider' => 'mercantil'.
    'payments' => [
        'provider' => 'simulado', // 'simulado' | 'mercantil' (no cambiar a 'mercantil' sin credenciales reales)
        'mercantil' => [
            'environment'      => 'sandbox',
            'base_url'         => '',
            'client_id'        => '',
            'client_secret'    => '',
            'integrator_id'    => '',
            'merchant_id'      => '',
            'terminal_id'      => '',
            'return_url'       => '',
            'cancel_url'       => '',
            'notification_url' => '',
        ],
    ],

    // Integración con Prevision-Funeraria (repo hermano, tenant `fdz`). Ver
    // docs/specs/2026-08-28-migracion-a-prevision-funeraria.md antes de tocar esto.
    // Mientras 'enabled' sea false, ninguna página ni endpoint la llama -- se
    // sigue usando el catálogo/flujo local de MySQL sin cambios.
    'prevision_funeraria' => [
        'enabled'   => false,
        'base_url'  => 'https://prevision-funeraria.sisteg.workers.dev/api/public/t/fdz',
        // Ninguno de los endpoints usados hoy (planes, servicios, solicitudes) pide
        // token -- déjalo vacío salvo que se empiece a usar /compras o /parentescos.
        'api_token' => '',
        // Segundos que se cachea el catálogo de planes/servicios en cache/prevision_funeraria/
        // antes de volver a pedirlo. No bajar de 60 (protege a PF de tráfico repetido).
        'cache_ttl' => 900,
    ],
];
