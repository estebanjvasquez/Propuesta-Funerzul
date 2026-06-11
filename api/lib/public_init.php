<?php
/**
 * Inicialización ligera para páginas públicas PHP (obituario.php, obituarios.php).
 * Carga config + BD + helpers. No inicia sesión ni CORS.
 */
declare(strict_types=1);
define('OBIT_APP', true);

$cfgFile = __DIR__ . '/../config.php';
if (!is_file($cfgFile)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><h1>Servicio en preparación</h1><p>El sistema de obituarios está siendo configurado.</p>';
    exit;
}
$GLOBALS['CONFIG'] = require $cfgFile;

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';   // auth_user() devuelve null sin sesión (suficiente para audit)
require __DIR__ . '/render.php';
