<?php
/**
 * API de Obituarios:  api/obituaries.php?action=...
 *   GET  list      (público: activos; staff con ?scope=admin: todos)
 *   GET  get       (?id= | ?slug=)  público ve activos; staff ve todo
 *   GET  homepage  (destacados + recientes, según configuración)
 *   POST create    (staff)
 *   POST update    (staff)
 *   POST pin       (staff)  -> fijar/desfijar
 *   POST delete    (staff = baja lógica; admin con ?hard=1 = definitivo)
 *   POST restore   (staff)
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'list';

/** Da formato de salida a una fila de obituario, resolviendo la foto. */
function obit_out(array $r): array
{
    $placeholder = get_setting('photo_placeholder_path', 'uploads/obituarios/_placeholder.webp');
    $photo = ($r['photo_purged'] || empty($r['photo_path'])) ? $placeholder : $r['photo_path'];
    return [
        'id'               => (int)$r['id'],
        'slug'             => $r['slug'],
        'full_name'        => $r['full_name'],
        'birth_year'       => $r['birth_year'] !== null ? (int)$r['birth_year'] : null,
        'death_date'       => $r['death_date'],
        'service_type'     => $r['service_type'],
        'location_name'    => $r['location_name'],
        'location_address' => $r['location_address'],
        'event_schedule'   => $r['event_schedule'],
        'biography'        => $r['biography'],
        'photo_url'        => $photo,
        'photo_purged'     => (bool)$r['photo_purged'],
        'is_pinned'        => (bool)$r['is_pinned'],
        'pin_order'        => $r['pin_order'] !== null ? (int)$r['pin_order'] : null,
        'status'           => $r['status'],
        'template_id'      => $r['template_id'] !== null ? (int)$r['template_id'] : null,
        'meta_description' => $r['meta_description'],
        'created_at'       => $r['created_at'],
        'updated_at'       => $r['updated_at'],
    ];
}

/** Recoge y valida los campos del cuerpo para crear/editar. */
function obit_input(array $b): array
{
    $allowedTypes = ['Velación', 'Cremación', 'Homenaje Póstumo', 'Traslado', 'Otro'];
    $type = $b['service_type'] ?? 'Velación';
    if (!in_array($type, $allowedTypes, true)) $type = 'Otro';

    $full = clean_str($b['full_name'] ?? '', 200);
    $death = clean_str($b['death_date'] ?? '', 10);
    if ($full === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $death)) {
        json_out(['ok' => false, 'error' => 'Nombre y fecha de fallecimiento (YYYY-MM-DD) son obligatorios.'], 422);
    }
    $status = in_array($b['status'] ?? 'active', ['draft', 'active', 'inactive'], true) ? $b['status'] : 'active';

    return [
        'full_name'        => $full,
        'birth_year'       => isset($b['birth_year']) && $b['birth_year'] !== '' ? (int)$b['birth_year'] : null,
        'death_date'       => $death,
        'service_type'     => $type,
        'location_name'    => clean_str($b['location_name'] ?? '', 200),
        'location_address' => clean_str($b['location_address'] ?? '', 255),
        'event_schedule'   => clean_str($b['event_schedule'] ?? '', 2000),
        'biography'        => clean_str($b['biography'] ?? '', 8000),
        'photo_path'       => clean_str($b['photo_path'] ?? '', 255),
        'meta_description' => clean_str($b['meta_description'] ?? '', 320),
        'template_id'      => isset($b['template_id']) && $b['template_id'] !== '' ? (int)$b['template_id'] : null,
        'status'           => $status,
    ];
}

switch ($action) {

    // ---- LISTADO ----------------------------------------------------------
    case 'list': {
        require_method('GET');
        $adminScope = (($_GET['scope'] ?? '') === 'admin');
        if ($adminScope) require_role('admin', 'editor');

        $where = $adminScope ? "deleted_at IS NULL" : "status = 'active' AND deleted_at IS NULL";
        $params = [];

        if (($q = trim($_GET['q'] ?? '')) !== '') {
            $where .= " AND (full_name LIKE ? OR location_name LIKE ? OR death_date LIKE ?)";
            $like = "%$q%";
            array_push($params, $like, $like, $like);
        }
        if (($type = $_GET['type'] ?? '') !== '' && $type !== 'all') {
            $where .= " AND service_type = ?";
            $params[] = $type;
        }
        $time = $_GET['time'] ?? 'all';
        if ($time === 'week') {
            $where .= " AND death_date >= (CURRENT_DATE - INTERVAL 7 DAY)";
        } elseif ($time === 'month') {
            $where .= " AND YEAR(death_date) = YEAR(CURRENT_DATE) AND MONTH(death_date) = MONTH(CURRENT_DATE)";
        }

        $limit  = min(max((int)($_GET['limit'] ?? 24), 1), 100);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $countSt = db()->prepare("SELECT COUNT(*) FROM obituaries WHERE $where");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $sql = "SELECT * FROM obituaries WHERE $where ORDER BY is_pinned DESC, death_date DESC, id DESC LIMIT $limit OFFSET $offset";
        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = array_map('obit_out', $st->fetchAll());

        json_out(['ok' => true, 'total' => $total, 'limit' => $limit, 'offset' => $offset, 'items' => $rows]);
    }

    // ---- PORTADA (destacados + recientes) ---------------------------------
    case 'homepage': {
        require_method('GET');
        $limit = setting_int('homepage_recent_count', 3);
        $sql = "SELECT * FROM (
                    SELECT *, 0 AS grp, COALESCE(pin_order, 999999) AS ord
                      FROM obituaries
                      WHERE status='active' AND deleted_at IS NULL AND is_pinned=1
                    UNION ALL
                    SELECT *, 1 AS grp, 0 AS ord
                      FROM obituaries
                      WHERE status='active' AND deleted_at IS NULL AND is_pinned=0
                ) q
                ORDER BY grp ASC, ord ASC, death_date DESC, id DESC
                LIMIT " . max($limit, 0);
        $rows = array_map('obit_out', db()->query($sql)->fetchAll());
        json_out(['ok' => true, 'items' => $rows]);
    }

    // ---- DETALLE ----------------------------------------------------------
    case 'get': {
        require_method('GET');
        $id   = $_GET['id']   ?? null;
        $slug = $_GET['slug'] ?? null;
        if ($id) { $st = db()->prepare("SELECT * FROM obituaries WHERE id = ?");   $st->execute([(int)$id]); }
        elseif ($slug) { $st = db()->prepare("SELECT * FROM obituaries WHERE slug = ?"); $st->execute([$slug]); }
        else json_out(['ok' => false, 'error' => 'Falta id o slug.'], 422);

        $r = $st->fetch();
        if (!$r || $r['deleted_at']) json_out(['ok' => false, 'error' => 'Obituario no encontrado.'], 404);
        if ($r['status'] !== 'active' && !is_staff()) json_out(['ok' => false, 'error' => 'Obituario no disponible.'], 404);

        if ($r['status'] === 'active' && !is_staff()) {
            db()->prepare("UPDATE obituaries SET view_count = view_count + 1 WHERE id = ?")->execute([$r['id']]);
        }
        json_out(['ok' => true, 'item' => obit_out($r)]);
    }

    // ---- CREAR ------------------------------------------------------------
    case 'create': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $in = obit_input(body_json());

        $slug = unique_slug($in['full_name'] . '-' . $in['death_date']);
        $purgeAt = null; $uploadedAt = null;
        if ($in['photo_path'] !== '') {
            $days = setting_int('photo_retention_days', 30);
            $uploadedAt = date('Y-m-d H:i:s');
            $purgeAt    = date('Y-m-d H:i:s', strtotime("+$days days"));
        }

        $st = db()->prepare(
            "INSERT INTO obituaries
             (slug, full_name, birth_year, death_date, service_type, location_name, location_address,
              event_schedule, biography, photo_path, photo_uploaded_at, photo_purge_at,
              status, template_id, meta_description, created_by, updated_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $slug, $in['full_name'], $in['birth_year'], $in['death_date'], $in['service_type'],
            $in['location_name'], $in['location_address'], $in['event_schedule'], $in['biography'],
            $in['photo_path'] ?: null, $uploadedAt, $purgeAt,
            $in['status'], $in['template_id'], $in['meta_description'], $u['id'], $u['id'],
        ]);
        $id = (int)db()->lastInsertId();
        audit('obituary.create', 'obituaries', $id, ['full_name' => $in['full_name']]);

        $row = db()->prepare("SELECT * FROM obituaries WHERE id = ?");
        $row->execute([$id]);
        json_out(['ok' => true, 'item' => obit_out($row->fetch())], 201);
    }

    // ---- ACTUALIZAR -------------------------------------------------------
    case 'update': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);

        $cur = db()->prepare("SELECT * FROM obituaries WHERE id = ?");
        $cur->execute([$id]);
        $existing = $cur->fetch();
        if (!$existing) json_out(['ok' => false, 'error' => 'Obituario no encontrado.'], 404);

        $in = obit_input($b);

        // Si cambió la foto, reinicia el contador de purga
        $photoChanged = ($in['photo_path'] !== '' && $in['photo_path'] !== ($existing['photo_path'] ?? ''));
        $uploadedAt = $existing['photo_uploaded_at'];
        $purgeAt    = $existing['photo_purge_at'];
        $purged     = (int)$existing['photo_purged'];
        if ($photoChanged) {
            $days = setting_int('photo_retention_days', 30);
            $uploadedAt = date('Y-m-d H:i:s');
            $purgeAt    = date('Y-m-d H:i:s', strtotime("+$days days"));
            $purged     = 0;
        }

        $st = db()->prepare(
            "UPDATE obituaries SET
               full_name=?, birth_year=?, death_date=?, service_type=?, location_name=?, location_address=?,
               event_schedule=?, biography=?, photo_path=?, photo_uploaded_at=?, photo_purge_at=?, photo_purged=?,
               status=?, template_id=?, meta_description=?, updated_by=?
             WHERE id=?"
        );
        $st->execute([
            $in['full_name'], $in['birth_year'], $in['death_date'], $in['service_type'],
            $in['location_name'], $in['location_address'], $in['event_schedule'], $in['biography'],
            ($in['photo_path'] !== '' ? $in['photo_path'] : $existing['photo_path']),
            $uploadedAt, $purgeAt, $purged,
            $in['status'], $in['template_id'], $in['meta_description'], $u['id'], $id,
        ]);
        audit('obituary.update', 'obituaries', $id, ['full_name' => $in['full_name']]);

        $row = db()->prepare("SELECT * FROM obituaries WHERE id = ?");
        $row->execute([$id]);
        json_out(['ok' => true, 'item' => obit_out($row->fetch())]);
    }

    // ---- FIJAR / DESFIJAR -------------------------------------------------
    case 'pin': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $pinned = !empty($b['pinned']) ? 1 : 0;
        $order  = isset($b['pin_order']) && $b['pin_order'] !== '' ? (int)$b['pin_order'] : null;
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);

        db()->prepare("UPDATE obituaries SET is_pinned=?, pin_order=? WHERE id=?")
            ->execute([$pinned, $pinned ? $order : null, $id]);
        audit('obituary.pin', 'obituaries', $id, ['pinned' => (bool)$pinned, 'order' => $order]);
        json_out(['ok' => true]);
    }

    // ---- DAR DE BAJA / ELIMINAR ------------------------------------------
    case 'delete': {
        require_method('POST');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? ($_GET['id'] ?? 0));
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $hard = (($_GET['hard'] ?? '') === '1');

        if ($hard) {
            require_role('admin');
            db()->prepare("DELETE FROM obituaries WHERE id = ?")->execute([$id]);
            audit('obituary.hard_delete', 'obituaries', $id);
        } else {
            require_role('admin', 'editor');
            db()->prepare("UPDATE obituaries SET deleted_at = NOW(), status='inactive' WHERE id = ?")->execute([$id]);
            audit('obituary.soft_delete', 'obituaries', $id);
        }
        json_out(['ok' => true]);
    }

    case 'restore': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("UPDATE obituaries SET deleted_at = NULL, status='active' WHERE id = ?")->execute([$id]);
        audit('obituary.restore', 'obituaries', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
