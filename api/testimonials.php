<?php
/**
 * API de Testimonios:  api/testimonials.php?action=...
 *   GET  list      (público: activos; staff con ?scope=admin: todos)
 *   GET  get       (?id=)
 *   POST create    (contenido)
 *   POST update    (contenido)
 *   POST toggle    (contenido)  -> pausar / activar
 *   POST delete    (contenido)  -> elimina definitivamente
 * Se muestran en la portada y en nosotros.php (partials/testimonios.php).
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'list';

/** Da formato de salida a una fila de testimonio. */
function testimonial_out(array $r): array
{
    return [
        'id'         => (int)$r['id'],
        'quote'      => $r['quote'],
        'author'     => $r['author'],
        'detail'     => $r['detail'],
        'sort_order' => (int)$r['sort_order'],
        'is_active'  => (bool)$r['is_active'],
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'],
    ];
}

/** Recoge y valida los campos del cuerpo para crear/editar. */
function testimonial_input(array $b): array
{
    // Las comillas las pone el diseño: se quitan si vienen pegadas del texto original.
    $quote  = trim(clean_str($b['quote'] ?? '', 2000), " \t\n\r\"“”«»");
    $author = clean_str($b['author'] ?? '', 150);
    $detail = clean_str($b['detail'] ?? '', 150);
    if ($quote === '' || $author === '') {
        json_out(['ok' => false, 'error' => 'El testimonio y el nombre de quien lo da son obligatorios.'], 422);
    }
    return [
        'quote'      => $quote,
        'author'     => $author,
        'detail'     => $detail !== '' ? $detail : null,
        'sort_order' => isset($b['sort_order']) && $b['sort_order'] !== '' ? (int)$b['sort_order'] : 0,
        'is_active'  => array_key_exists('is_active', $b) ? (!empty($b['is_active']) ? 1 : 0) : 1,
    ];
}

function testimonial_get(int $id): ?array
{
    $st = db()->prepare("SELECT * FROM testimonials WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

switch ($action) {

    // ---- LISTADO ----------------------------------------------------------
    case 'list': {
        require_method('GET');
        $adminScope = (($_GET['scope'] ?? '') === 'admin');
        if ($adminScope) require_perm('sitio.contenido.write');

        $where = $adminScope ? '1=1' : 'is_active = 1';
        $sql = "SELECT * FROM testimonials WHERE $where ORDER BY sort_order ASC, id ASC";
        $rows = array_map('testimonial_out', db()->query($sql)->fetchAll());
        json_out(['ok' => true, 'items' => $rows]);
    }

    // ---- DETALLE ----------------------------------------------------------
    case 'get': {
        require_method('GET');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $r = testimonial_get($id);
        if (!$r || (!$r['is_active'] && !can('sitio.contenido.write'))) {
            json_out(['ok' => false, 'error' => 'Testimonio no encontrado.'], 404);
        }
        json_out(['ok' => true, 'item' => testimonial_out($r)]);
    }

    // ---- CREAR ------------------------------------------------------------
    case 'create': {
        require_method('POST');
        $u = require_perm('sitio.contenido.write');
        require_csrf();
        $in = testimonial_input(body_json());

        $st = db()->prepare(
            "INSERT INTO testimonials (quote, author, detail, sort_order, is_active, created_by, updated_by)
             VALUES (?,?,?,?,?,?,?)"
        );
        $st->execute([$in['quote'], $in['author'], $in['detail'], $in['sort_order'], $in['is_active'], $u['id'], $u['id']]);
        $id = (int)db()->lastInsertId();
        audit('testimonial.create', 'testimonials', $id, ['author' => $in['author']]);
        json_out(['ok' => true, 'item' => testimonial_out(testimonial_get($id))], 201);
    }

    // ---- ACTUALIZAR -------------------------------------------------------
    case 'update': {
        require_method('POST');
        $u = require_perm('sitio.contenido.write');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        if (!testimonial_get($id)) json_out(['ok' => false, 'error' => 'Testimonio no encontrado.'], 404);

        $in = testimonial_input($b);
        $st = db()->prepare(
            "UPDATE testimonials SET quote=?, author=?, detail=?, sort_order=?, is_active=?, updated_by=? WHERE id=?"
        );
        $st->execute([$in['quote'], $in['author'], $in['detail'], $in['sort_order'], $in['is_active'], $u['id'], $id]);
        audit('testimonial.update', 'testimonials', $id, ['author' => $in['author']]);
        json_out(['ok' => true, 'item' => testimonial_out(testimonial_get($id))]);
    }

    // ---- PAUSAR / ACTIVAR -------------------------------------------------
    case 'toggle': {
        require_method('POST');
        $u = require_perm('sitio.contenido.write');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $active = !empty($b['is_active']) ? 1 : 0;

        db()->prepare("UPDATE testimonials SET is_active=?, updated_by=? WHERE id=?")->execute([$active, $u['id'], $id]);
        audit('testimonial.toggle', 'testimonials', $id, ['is_active' => (bool)$active]);
        json_out(['ok' => true]);
    }

    // ---- ELIMINAR ---------------------------------------------------------
    case 'delete': {
        require_method('POST');
        require_perm('sitio.contenido.write');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$id]);
        audit('testimonial.delete', 'testimonials', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
