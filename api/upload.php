<?php
/**
 * Subida de fotos al DISCO del servidor:  POST api/upload.php  (multipart, campo "photo")
 * - Requiere sesión (editor/admin) + CSRF.
 * - Valida tipo/tamaño, redimensiona y guarda como WebP en uploads/obituarios/.
 * - Devuelve { path } relativo a la raíz del sitio para guardarlo en el obituario.
 */
require __DIR__ . '/lib/bootstrap.php';

require_method('POST');
require_role('admin', 'editor');
require_csrf();

$cfg = $GLOBALS['CONFIG']['uploads'];
$dir = $GLOBALS['CONFIG']['paths']['uploads_dir'];
$url = rtrim($GLOBALS['CONFIG']['paths']['uploads_url'], '/');

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    json_out(['ok' => false, 'error' => 'No se recibió ninguna foto válida.'], 422);
}
$file = $_FILES['photo'];
if ($file['size'] > $cfg['max_bytes']) {
    json_out(['ok' => false, 'error' => 'La foto supera el tamaño máximo permitido (' . round($cfg['max_bytes'] / 1048576) . ' MB).'], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!in_array($mime, $cfg['mime_allow'], true)) {
    json_out(['ok' => false, 'error' => 'Formato no permitido. Use JPG, PNG o WebP.'], 422);
}
if (!function_exists('imagewebp')) {
    json_out(['ok' => false, 'error' => 'El servidor no tiene soporte WebP (GD). Active la extensión GD.'], 500);
}

// Cargar imagen según tipo
$src = match ($mime) {
    'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
    'image/png'  => imagecreatefrompng($file['tmp_name']),
    'image/webp' => imagecreatefromwebp($file['tmp_name']),
    default      => false,
};
if (!$src) json_out(['ok' => false, 'error' => 'No se pudo procesar la imagen.'], 422);

$w = imagesx($src);
$h = imagesy($src);
$max = (int)$cfg['max_dim'];
$scale = min(1, $max / max($w, $h));
$nw = max(1, (int)round($w * $scale));
$nh = max(1, (int)round($h * $scale));

$dst = imagecreatetruecolor($nw, $nh);
// fondo blanco (por si hay transparencia en PNG)
$white = imagecolorallocate($dst, 255, 255, 255);
imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
$filename = 'obit_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.webp';
$fullpath = $dir . '/' . $filename;

if (!imagewebp($dst, $fullpath, (int)$cfg['webp_quality'])) {
    imagedestroy($src); imagedestroy($dst);
    json_out(['ok' => false, 'error' => 'No se pudo guardar la imagen.'], 500);
}
imagedestroy($src);
imagedestroy($dst);

$relPath = $url . '/' . $filename;
audit('photo.upload', 'obituaries', null, ['path' => $relPath, 'mime' => $mime]);
json_out(['ok' => true, 'path' => $relPath]);
