<?php
/**
 * Imagen de vista previa para redes (Open Graph, 1200x630 PNG) de un obituario:
 *   obituario_og.php?id=45&v=<versión>
 *
 * Pública (la piden Facebook/WhatsApp al leer og:image), solo para obituarios
 * activos. Se cachea en cache/og/ (cache/ niega acceso web directo, por eso la
 * sirve este script); "v" es solo para que las redes no reutilicen una versión
 * vieja cuando cambia el obituario. Ver
 * docs/specs/2026-10-27-enlaces-redes-y-analitica.md.
 */
require __DIR__ . '/api/lib/public_init.php';
require __DIR__ . '/api/lib/obituary_card.php';

$id = (string)(int)($_GET['id'] ?? 0);
$o  = (site_section_enabled('obituarios') && $id !== '0') ? get_public_obituary($id) : null;
if (!$o) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('No encontrado');
}

$o['death_date'] = fmt_date_es($o['death_date']);
$version = obit_og_version($o);
$dir  = __DIR__ . '/cache/og';
$file = $dir . '/' . (int)$o['id'] . '-' . $version . '.png';

if (!is_file($file)) {
    $img = obit_og_image($o);
    $saved = false;
    if ((is_dir($dir) || @mkdir($dir, 0755, true)) && is_writable($dir)) {
        // Borra versiones anteriores de este mismo obituario antes de guardar la nueva.
        foreach (glob($dir . '/' . (int)$o['id'] . '-*.png') ?: [] as $old) @unlink($old);
        $saved = @imagepng($img, $file);
    }
    if (!$saved) {
        // Sin caché disponible: se entrega generada al vuelo.
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=3600');
        imagepng($img);
        imagedestroy($img);
        exit;
    }
    imagedestroy($img);
}

header('Content-Type: image/png');
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=86400');
readfile($file);
