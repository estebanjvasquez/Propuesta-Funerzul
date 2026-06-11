<?php
/**
 * RUTINA DE PURGA DE FOTOS  (ejecutar por cron de cPanel)
 * --------------------------------------------------------
 * Borra del disco las fotos de obituarios con más de N días (configurable en
 * app_settings.photo_retention_days) y las reemplaza por el placeholder del logo.
 * El obituario y su texto PERMANECEN en la web e indexables (SEO/GEO).
 *
 * Activar/desactivar:  app_settings.photo_purge_enabled (1/0)
 *
 * Uso por cron (cPanel -> Cron Jobs), una vez al día:
 *   /usr/local/bin/php /home/legadoholding/public_html/funerzul/api/cron/purge_photos.php
 *
 * Uso por URL (alternativo, requiere token):
 *   https://www.funerariadelzulia.com/api/cron/purge_photos.php?token=TU_CRON_SECRET
 */
declare(strict_types=1);
define('OBIT_APP', true);

$cfgFile = __DIR__ . '/../config.php';
if (!is_file($cfgFile)) { fwrite(STDERR, "Falta api/config.php\n"); exit(1); }
$GLOBALS['CONFIG'] = require $cfgFile;

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/auth.php';   // audit() usa auth_user() (null en CLI = sistema)

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

// ¿Purga activada?
if (!setting_bool('photo_purge_enabled', true)) {
    out_line('Purga DESACTIVADA (photo_purge_enabled = 0). Nada que hacer.');
    if (PHP_SAPI !== 'cli') echo json_encode(['ok' => true, 'enabled' => false, 'purged' => 0]);
    exit(0);
}

$placeholder = get_setting('photo_placeholder_path', 'uploads/obituarios/_placeholder.webp');
$uploadsDir  = $GLOBALS['CONFIG']['paths']['uploads_dir'];

// Obituarios cuya foto ya venció y aún no fue purgada
$st = db()->query(
    "SELECT id, full_name, photo_path
       FROM obituaries
      WHERE photo_purged = 0
        AND photo_path IS NOT NULL AND photo_path <> ''
        AND photo_purge_at IS NOT NULL
        AND photo_purge_at <= NOW()"
);
$rows = $st->fetchAll();

$purged = 0;
$freedBytes = 0;
$update = db()->prepare("UPDATE obituaries SET photo_purged = 1, photo_path = ? WHERE id = ?");

foreach ($rows as $r) {
    // Nunca borrar el propio placeholder
    if ($r['photo_path'] === $placeholder) {
        $update->execute([$placeholder, $r['id']]);
        continue;
    }
    $physical = $uploadsDir . '/' . basename($r['photo_path']);
    if (is_file($physical)) {
        $freedBytes += (int)@filesize($physical);
        @unlink($physical);
    }
    $update->execute([$placeholder, $r['id']]);
    audit('photo.purge', 'obituaries', $r['id'], [
        'full_name' => $r['full_name'],
        'old_path'  => $r['photo_path'],
    ]);
    $purged++;
    out_line("Purgada foto de #{$r['id']} ({$r['full_name']})");
}

$summary = sprintf('Purga completada: %d foto(s), %.1f KB liberados.', $purged, $freedBytes / 1024);
out_line($summary);

if ($purged > 0) {
    audit('photo.purge_run', null, null, ['count' => $purged, 'freed_bytes' => $freedBytes]);
}

if (PHP_SAPI !== 'cli') {
    echo json_encode(['ok' => true, 'enabled' => true, 'purged' => $purged, 'freed_bytes' => $freedBytes]);
}
exit(0);
