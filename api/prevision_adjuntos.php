<?php
/**
 * Adjuntos de contratos de Previsión:  api/prevision_adjuntos.php?action=...
 * (equivale a la pestaña "Adjuntar archivos" de SIEMPRE)
 *
 *   GET  list      (staff; ?contrato_id=)
 *   POST subir     (staff; multipart: contrato_id + archivo — PDF/JPG/PNG/WebP, máx 5 MB)
 *   POST eliminar  (admin; {id})
 *
 * Los archivos viven en uploads/prevision/{contrato_id}/ (sin ejecución de
 * scripts vía .htaccess); la tabla prev_adjuntos guarda la referencia.
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'list';

const ADJ_MAX_BYTES = 5242880; // 5 MB
const ADJ_MIME = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
];

/** Directorio y URL base de uploads/prevision (derivados de la config). */
function adj_paths(): array
{
    $baseDir = dirname(rtrim($GLOBALS['CONFIG']['paths']['uploads_dir'], '/'));
    $baseUrl = dirname(rtrim($GLOBALS['CONFIG']['paths']['uploads_url'], '/'));
    if ($baseUrl === '.' || $baseUrl === DIRECTORY_SEPARATOR) { $baseUrl = ''; }
    return [$baseDir . '/prevision', ($baseUrl !== '' ? $baseUrl . '/' : '') . 'prevision'];
}

switch ($action) {

    case 'list': {
        require_method('GET');
        require_role('admin', 'editor');
        $cid = (int)($_GET['contrato_id'] ?? 0);
        if (!$cid) json_out(['ok' => false, 'error' => 'Falta contrato_id.'], 422);
        [, $baseUrl] = adj_paths();
        $st = db()->prepare(
            "SELECT a.*, u.email AS usuario FROM prev_adjuntos a
             LEFT JOIN users u ON u.id = a.subido_por
             WHERE a.contrato_id = ? ORDER BY a.id DESC"
        );
        $st->execute([$cid]);
        $items = array_map(fn($r) => [
            'id'             => (int)$r['id'],
            'nombre_archivo' => $r['nombre_archivo'],
            'url'            => $r['ruta'],
            'mime'           => $r['mime'],
            'tamano'         => (int)$r['tamano'],
            'usuario'        => $r['usuario'],
            'created_at'     => $r['created_at'],
        ], $st->fetchAll());
        json_out(['ok' => true, 'items' => $items]);
    }

    case 'subir': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $cid = (int)($_POST['contrato_id'] ?? 0);
        if (!$cid) json_out(['ok' => false, 'error' => 'Falta contrato_id.'], 422);
        $st = db()->prepare("SELECT id FROM prev_contratos WHERE id = ?");
        $st->execute([$cid]);
        if (!$st->fetchColumn()) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            json_out(['ok' => false, 'error' => 'No se recibió ningún archivo válido.'], 422);
        }
        $file = $_FILES['archivo'];
        if ($file['size'] > ADJ_MAX_BYTES) {
            json_out(['ok' => false, 'error' => 'El archivo supera el máximo de 5 MB.'], 422);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!isset(ADJ_MIME[$mime])) {
            json_out(['ok' => false, 'error' => 'Formato no permitido. Use PDF, JPG, PNG o WebP.'], 422);
        }

        [$baseDir, $baseUrl] = adj_paths();
        $dir = $baseDir . '/' . $cid;
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $filename = 'adj_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ADJ_MIME[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            json_out(['ok' => false, 'error' => 'No se pudo guardar el archivo en el servidor.'], 500);
        }

        $ruta = $baseUrl . '/' . $cid . '/' . $filename;
        $nombre = clean_str($file['name'] ?: $filename, 190) ?: $filename;
        db()->prepare(
            "INSERT INTO prev_adjuntos (contrato_id, nombre_archivo, ruta, mime, tamano, subido_por)
             VALUES (?,?,?,?,?,?)"
        )->execute([$cid, $nombre, $ruta, $mime, (int)$file['size'], $u['id']]);
        $id = (int)db()->lastInsertId();
        audit('prev_adjunto.subir', 'prev_adjuntos', $id, ['contrato_id' => $cid, 'archivo' => $nombre]);
        json_out(['ok' => true, 'id' => $id, 'url' => $ruta], 201);
    }

    case 'eliminar': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        $st = db()->prepare("SELECT * FROM prev_adjuntos WHERE id = ?");
        $st->execute([$id]);
        $a = $st->fetch();
        if (!$a) json_out(['ok' => false, 'error' => 'Adjunto no encontrado.'], 404);

        // Borrar el archivo físico (la ruta guardada es relativa a la raíz del sitio)
        [$baseDir, $baseUrl] = adj_paths();
        $rel = $baseUrl !== '' ? substr($a['ruta'], strlen($baseUrl)) : $a['ruta'];
        $full = $baseDir . '/' . ltrim(str_replace(['..', '\\'], '', $rel), '/');
        if (is_file($full)) { @unlink($full); }

        db()->prepare("DELETE FROM prev_adjuntos WHERE id = ?")->execute([$id]);
        audit('prev_adjunto.eliminar', 'prev_adjuntos', $id, ['contrato_id' => (int)$a['contrato_id']]);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
