<?php
/**
 * AUTO-LAPSADO DE CONTRATOS DE PREVISIÓN  (ejecutar por cron de cPanel)
 * ----------------------------------------------------------------------
 * Suspende automáticamente los contratos ACTIVOS que acumulen N o más
 * cuotas vencidas (N configurable en el panel: Previsión -> Cobranza ->
 * Auto-lapsado, guardado en app_settings.prev_lapse_cuotas).
 *
 * Activar/desactivar:  app_settings.prev_lapse_enabled (1/0)
 *
 * Uso por cron (cPanel -> Cron Jobs), una vez al día:
 *   /usr/local/bin/php /home/legadoholding/public_html/funerzul/api/cron/prevision_lapsar.php
 *
 * Uso por URL (alternativo, requiere token):
 *   https://www.funerariadelzulia.com/api/cron/prevision_lapsar.php?token=TU_CRON_SECRET
 */
declare(strict_types=1);
define('OBIT_APP', true);

$cfgFile = __DIR__ . '/../config.php';
if (!is_file($cfgFile)) { fwrite(STDERR, "Falta api/config.php\n"); exit(1); }
$GLOBALS['CONFIG'] = require $cfgFile;

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/auth.php';       // audit() usa auth_user() (null en CLI = sistema)
require __DIR__ . '/../lib/prevision.php';  // prev_lapsar()

$isCli = (PHP_SAPI === 'cli');

// Si se ejecuta por web, exigir token secreto
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_GET['token'] ?? '';
    $secret = $GLOBALS['CONFIG']['security']['cron_secret'] ?? '';
    if ($secret === '' || !hash_equals($secret, (string)$token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
        exit;
    }
}

function out_line(string $msg): void
{
    if (PHP_SAPI === 'cli') { echo $msg . "\n"; }
}

// ¿Módulo instalado?
try {
    db()->query("SELECT 1 FROM prev_contratos LIMIT 1");
} catch (\Throwable $e) {
    out_line('Módulo de Previsión no instalado (importe database/04_prevision.sql).');
    if (!$isCli) echo json_encode(['ok' => false, 'error' => 'Módulo de Previsión no instalado.']);
    exit(0);
}

// ¿Auto-lapsado activado?
if (!setting_bool('prev_lapse_enabled', false)) {
    out_line('Auto-lapsado DESACTIVADO (prev_lapse_enabled = 0). Nada que hacer.');
    if (!$isCli) echo json_encode(['ok' => true, 'enabled' => false, 'suspendidos' => 0]);
    exit(0);
}

$cuotas = max(1, setting_int('prev_lapse_cuotas', 3));
$items = prev_lapsar($cuotas, false);

audit('prev_lapse.cron', 'prev_contratos', null,
      ['cuotas_minimas' => $cuotas, 'suspendidos' => count($items)]);

out_line('Auto-lapsado: ' . count($items) . " contrato(s) suspendido(s) (>= $cuotas cuotas vencidas).");
foreach ($items as $c) {
    out_line("  - {$c['numero']} {$c['cliente_nombre']}: {$c['cuotas_vencidas']} cuotas, saldo {$c['saldo_vencido']} {$c['moneda']}");
}
if (!$isCli) {
    echo json_encode(['ok' => true, 'enabled' => true, 'cuotas_minimas' => $cuotas,
                      'suspendidos' => count($items), 'items' => $items], JSON_UNESCAPED_UNICODE);
}
