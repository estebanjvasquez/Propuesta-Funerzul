<?php
/**
 * Tarjeta de obituario para compartir (PNG, staff):
 *   GET api/obituary_card.php?id=123&style=cinta|esquela
 *
 * Genera la imagen con api/lib/obituary_card.php (puro GD, sin Imagick) y la
 * entrega como descarga. No requiere que el obituario esté "activo" -- el
 * staff puede generar la tarjeta de un borrador antes de publicarlo.
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/render.php';         // fmt_date_es()
require __DIR__ . '/lib/obituary_card.php';

require_method('GET');
require_role('admin', 'editor');

$id = (int)($_GET['id'] ?? 0);
$style = in_array($_GET['style'] ?? '', ['cinta', 'esquela'], true) ? $_GET['style'] : 'cinta';
if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);

$st = db()->prepare("SELECT * FROM obituaries WHERE id = ? AND deleted_at IS NULL");
$st->execute([$id]);
$o = $st->fetch();
if (!$o) json_out(['ok' => false, 'error' => 'Obituario no encontrado.'], 404);

// fmt_date_es() ya está cargada (lib/render.php, incluida por bootstrap.php).
$o['death_date'] = fmt_date_es($o['death_date']);

$img = obit_card_generate($o, $style);

audit('obituary.card_generate', 'obituaries', $id, ['style' => $style]);

$slug = $o['slug'] ?: (string)$o['id'];
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="obituario-' . preg_replace('/[^a-z0-9\-]/i', '', $slug) . '-' . $style . '.png"');
header('Cache-Control: no-store, no-cache, must-revalidate');
imagepng($img);
imagedestroy($img);
