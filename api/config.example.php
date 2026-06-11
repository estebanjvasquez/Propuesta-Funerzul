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
];
