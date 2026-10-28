<?php
/**
 * "Entrar con Previsión Funeraria":  api/sso.php?action=start|callback
 *   GET start     -> redirige a Prevision-Funeraria (/oauth/authorize)
 *   GET callback  -> PF vuelve aquí con ?code&state; crea la sesión y redirige a admin.html
 * Los errores vuelven a admin.html?sso_error=<motivo>. Ver lib/sso.php.
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'start':
        require_method('GET');
        sso_start();

    case 'callback':
        require_method('GET');
        sso_callback();

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
