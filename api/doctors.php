<?php
/**
 * API del Directorio Médico:  api/doctors.php?action=...
 *   GET  list      (público: activos; staff con ?scope=admin: todos)
 *   GET  get       (?id= | ?slug=)  público ve activos; staff ve todo
 *   POST create    (staff)
 *   POST update    (staff)
 *   POST feature   (staff)  -> destacar/quitar
 *   POST delete    (staff = baja lógica; admin con ?hard=1 = definitivo)
 *   POST restore   (staff)
 *   GET  stats     (staff)
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'list';

/** Da formato de salida a una fila de médico. */
function doctor_out(array $r): array
{
    return [
        'id'               => (int)$r['id'],
        'slug'             => $r['slug'],
        'full_name'        => $r['full_name'],
        'specialty'        => $r['specialty'],
        'bio'              => $r['bio'],
        'phone'            => $r['phone'],
        'email'            => $r['email'],
        'location_name'    => $r['location_name'],
        'location_address' => $r['location_address'],
        'photo_url'        => !empty($r['photo_path']) ? $r['photo_path'] : null,
        'is_featured'      => (bool)$r['is_featured'],
        'feature_order'    => $r['feature_order'] !== null ? (int)$r['feature_order'] : null,
        'status'           => $r['status'],
        'meta_description' => $r['meta_description'],
        'view_count'       => (int)$r['view_count'],
        'created_at'       => $r['created_at'],
        'updated_at'       => $r['updated_at'],
    ];
}

/** Recoge y valida los campos del cuerpo para crear/editar. */
function doctor_input(array $b): array
{
    $name = clean_str($b['full_name'] ?? '', 200);
    $specialty = clean_str($b['specialty'] ?? '', 120);
    if ($name === '' || $specialty === '') {
        json_out(['ok' => false, 'error' => 'Nombre y especialidad son obligatorios.'], 422);
    }
    $status = in_array($b['status'] ?? 'active', ['draft', 'active', 'inactive'], true) ? $b['status'] : 'active';

    return [
        'full_name'        => $name,
        'specialty'        => $specialty,
        'bio'              => clean_str($b['bio'] ?? '', 5000),
        'phone'            => clean_str($b['phone'] ?? '', 40),
        'email'            => clean_str($b['email'] ?? '', 190),
        'location_name'    => clean_str($b['location_name'] ?? '', 200),
        'location_address' => clean_str($b['location_address'] ?? '', 255),
        'photo_path'       => clean_str($b['photo_path'] ?? '', 255),
        'meta_description' => clean_str($b['meta_description'] ?? '', 320),
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
            $where .= " AND (full_name LIKE ? OR specialty LIKE ? OR location_name LIKE ?)";
            $like = "%$q%";
            array_push($params, $like, $like, $like);
        }
        if (($sp = trim($_GET['specialty'] ?? '')) !== '' && $sp !== 'all') {
            $where .= " AND specialty = ?";
            $params[] = $sp;
        }

        $limit  = min(max((int)($_GET['limit'] ?? 60), 1), 200);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $countSt = db()->prepare("SELECT COUNT(*) FROM doctors WHERE $where");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $sql = "SELECT * FROM doctors WHERE $where
                ORDER BY is_featured DESC, COALESCE(feature_order, 999999) ASC, full_name ASC
                LIMIT $limit OFFSET $offset";
        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = array_map('doctor_out', $st->fetchAll());

        json_out(['ok' => true, 'total' => $total, 'limit' => $limit, 'offset' => $offset, 'items' => $rows]);
    }

    // ---- DETALLE ----------------------------------------------------------
    case 'get': {
        require_method('GET');
        $id   = $_GET['id']   ?? null;
        $slug = $_GET['slug'] ?? null;
        if ($id) { $st = db()->prepare("SELECT * FROM doctors WHERE id = ?");   $st->execute([(int)$id]); }
        elseif ($slug) { $st = db()->prepare("SELECT * FROM doctors WHERE slug = ?"); $st->execute([$slug]); }
        else json_out(['ok' => false, 'error' => 'Falta id o slug.'], 422);

        $r = $st->fetch();
        if (!$r || $r['deleted_at']) json_out(['ok' => false, 'error' => 'Médico no encontrado.'], 404);
        if ($r['status'] !== 'active' && !is_staff()) json_out(['ok' => false, 'error' => 'Ficha no disponible.'], 404);

        json_out(['ok' => true, 'item' => doctor_out($r)]);
    }

    // ---- CREAR ------------------------------------------------------------
    case 'create': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $in = doctor_input(body_json());

        $slug = unique_slug($in['full_name'] . '-' . $in['specialty'], null, 'doctors');

        $st = db()->prepare(
            "INSERT INTO doctors
             (slug, full_name, specialty, bio, phone, email, location_name, location_address,
              photo_path, status, meta_description, created_by, updated_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $slug, $in['full_name'], $in['specialty'], $in['bio'], $in['phone'], $in['email'],
            $in['location_name'], $in['location_address'], $in['photo_path'] ?: null,
            $in['status'], $in['meta_description'], $u['id'], $u['id'],
        ]);
        $id = (int)db()->lastInsertId();
        audit('doctor.create', 'doctors', $id, ['full_name' => $in['full_name']]);

        $row = db()->prepare("SELECT * FROM doctors WHERE id = ?");
        $row->execute([$id]);
        json_out(['ok' => true, 'item' => doctor_out($row->fetch())], 201);
    }

    // ---- ACTUALIZAR -------------------------------------------------------
    case 'update': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);

        $cur = db()->prepare("SELECT * FROM doctors WHERE id = ?");
        $cur->execute([$id]);
        $existing = $cur->fetch();
        if (!$existing) json_out(['ok' => false, 'error' => 'Médico no encontrado.'], 404);

        $in = doctor_input($b);

        $st = db()->prepare(
            "UPDATE doctors SET
               full_name=?, specialty=?, bio=?, phone=?, email=?, location_name=?, location_address=?,
               photo_path=?, status=?, meta_description=?, updated_by=?
             WHERE id=?"
        );
        $st->execute([
            $in['full_name'], $in['specialty'], $in['bio'], $in['phone'], $in['email'],
            $in['location_name'], $in['location_address'],
            ($in['photo_path'] !== '' ? $in['photo_path'] : $existing['photo_path']),
            $in['status'], $in['meta_description'], $u['id'], $id,
        ]);
        audit('doctor.update', 'doctors', $id, ['full_name' => $in['full_name']]);

        $row = db()->prepare("SELECT * FROM doctors WHERE id = ?");
        $row->execute([$id]);
        json_out(['ok' => true, 'item' => doctor_out($row->fetch())]);
    }

    // ---- DESTACAR / QUITAR ------------------------------------------------
    case 'feature': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $featured = !empty($b['featured']) ? 1 : 0;
        $order  = isset($b['feature_order']) && $b['feature_order'] !== '' ? (int)$b['feature_order'] : null;
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);

        db()->prepare("UPDATE doctors SET is_featured=?, feature_order=? WHERE id=?")
            ->execute([$featured, $featured ? $order : null, $id]);
        audit('doctor.feature', 'doctors', $id, ['featured' => (bool)$featured, 'order' => $order]);
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
            db()->prepare("DELETE FROM doctors WHERE id = ?")->execute([$id]);
            audit('doctor.hard_delete', 'doctors', $id);
        } else {
            require_role('admin', 'editor');
            db()->prepare("UPDATE doctors SET deleted_at = NOW(), status='inactive' WHERE id = ?")->execute([$id]);
            audit('doctor.soft_delete', 'doctors', $id);
        }
        json_out(['ok' => true]);
    }

    case 'restore': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("UPDATE doctors SET deleted_at = NULL, status='active' WHERE id = ?")->execute([$id]);
        audit('doctor.restore', 'doctors', $id);
        json_out(['ok' => true]);
    }

    // ---- ESTADÍSTICAS -----------------------------------------------------
    case 'stats': {
        require_method('GET');
        require_role('admin', 'editor');
        $pdo = db();
        $total    = (int)$pdo->query("SELECT COUNT(*) FROM doctors WHERE deleted_at IS NULL")->fetchColumn();
        $active   = (int)$pdo->query("SELECT COUNT(*) FROM doctors WHERE deleted_at IS NULL AND status='active'")->fetchColumn();
        $featured = (int)$pdo->query("SELECT COUNT(*) FROM doctors WHERE deleted_at IS NULL AND is_featured=1")->fetchColumn();
        json_out(['ok' => true, 'stats' => ['total' => $total, 'active' => $active, 'featured' => $featured]]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
