<?php
/**
 * Diagnóstico de instalación (protegido).
 * Uso:  api/diag.php?token=TU_CRON_SECRET   (o estando logueado como admin)
 * Verifica: conexión a BD, tablas existentes + conteos, GD/WebP y permisos de uploads.
 * No expone credenciales.
 */
require __DIR__ . '/lib/bootstrap.php';

$secret = $GLOBALS['CONFIG']['security']['cron_secret'] ?? '';
$token  = $_GET['token'] ?? '';
if (!(is_admin() || ($secret !== '' && hash_equals($secret, (string)$token)))) {
    json_out(['ok' => false, 'error' => 'No autorizado. Use ?token=CRON_SECRET o inicie sesión como admin.'], 403);
}

$report = [
    'php_version' => PHP_VERSION,
    'db_connected' => false,
    'tables' => [],
    'gd_webp' => function_exists('imagewebp'),
    'uploads_dir' => $GLOBALS['CONFIG']['paths']['uploads_dir'],
    'uploads_writable' => @is_writable($GLOBALS['CONFIG']['paths']['uploads_dir']),
];

try {
    db()->query('SELECT 1');
    $report['db_connected'] = true;
} catch (\Throwable $e) {
    $report['db_error'] = $e->getMessage();
    json_out(['ok' => true, 'diag' => $report]);
}

$expected = ['users', 'obituary_templates', 'obituaries', 'condolences', 'flower_offerings', 'app_settings', 'audit_log'];
foreach ($expected as $t) {
    try {
        $c = (int)db()->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $report['tables'][$t] = ['exists' => true, 'rows' => $c];
    } catch (\Throwable $e) {
        $report['tables'][$t] = ['exists' => false, 'error' => $e->getMessage()];
    }
}

json_out(['ok' => true, 'diag' => $report]);
