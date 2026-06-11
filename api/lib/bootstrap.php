<?php
/**
 * Bootstrap del backend: carga configuración, BD, helpers y sesión.
 * Todo endpoint debe empezar con:  require __DIR__ . '/lib/bootstrap.php';
 */
declare(strict_types=1);

define('OBIT_APP', true);

$cfgFile = __DIR__ . '/../config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Falta api/config.php. Copia api/config.example.php a api/config.php y completa las credenciales.'
    ]);
    exit;
}

$GLOBALS['CONFIG'] = require $cfgFile;

$isDev = ($GLOBALS['CONFIG']['app']['env'] ?? 'production') === 'development';
error_reporting(E_ALL);
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';

set_exception_handler(function (\Throwable $e) {
    error_log('[obit] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $dev = ($GLOBALS['CONFIG']['app']['env'] ?? '') === 'development';
    json_out(['ok' => false, 'error' => $dev ? $e->getMessage() : 'Error interno del servidor.'], 500);
});

// --- Sesión segura ---
$secure = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $secure,
]);
session_name('obit_sess');
session_start();

cors();
