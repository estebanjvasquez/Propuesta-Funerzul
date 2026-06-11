<?php
/**
 * API de Condolencias:  api/condolences.php?action=...
 *   GET  list      (?obituary_id=)  público ve aprobadas; staff ve todas
 *   POST create    (público)        entra como 'pending' (o 'approved' si la moderación está desactivada)
 *   POST moderate  (staff)          set status: approved | hidden | pending
 *   POST update    (staff)          editar autor/mensaje
 *   POST delete    (staff)
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'list';

function cond_out(array $r): array
{
    return [
        'id'          => (int)$r['id'],
        'obituary_id' => (int)$r['obituary_id'],
        'author_name' => $r['author_name'],
        'message'     => $r['message'],
        'status'      => $r['status'],
        'created_at'  => $r['created_at'],
    ];
}

switch ($action) {

    case 'list': {
        require_method('GET');
        $obid = (int)($_GET['obituary_id'] ?? 0);
        if (!$obid) json_out(['ok' => false, 'error' => 'Falta obituary_id.'], 422);

        if (is_staff()) {
            $st = db()->prepare("SELECT * FROM condolences WHERE obituary_id = ? ORDER BY created_at DESC");
            $st->execute([$obid]);
        } else {
            $st = db()->prepare("SELECT * FROM condolences WHERE obituary_id = ? AND status = 'approved' ORDER BY created_at DESC");
            $st->execute([$obid]);
        }
        json_out(['ok' => true, 'items' => array_map('cond_out', $st->fetchAll())]);
    }

    case 'admin_list': {
        require_method('GET');
        require_role('admin', 'editor');
        $status = $_GET['status'] ?? 'all';
        $where = "1=1"; $params = [];
        if (in_array($status, ['pending', 'approved', 'hidden'], true)) {
            $where .= " AND c.status = ?"; $params[] = $status;
        }
        $sql = "SELECT c.*, o.full_name AS obituary_name, o.slug AS obituary_slug
                  FROM condolences c
                  JOIN obituaries o ON o.id = c.obituary_id
                 WHERE $where
                 ORDER BY c.created_at DESC
                 LIMIT 300";
        $st = db()->prepare($sql);
        $st->execute($params);
        $items = array_map(function ($r) {
            $o = cond_out($r);
            $o['obituary_name'] = $r['obituary_name'];
            $o['obituary_slug'] = $r['obituary_slug'];
            return $o;
        }, $st->fetchAll());
        json_out(['ok' => true, 'items' => $items]);
    }

    case 'create': {
        require_method('POST');
        $b = body_json();
        $obid   = (int)($b['obituary_id'] ?? 0);
        $author = clean_str($b['author_name'] ?? '', 150);
        $msg    = clean_str($b['message'] ?? '', 2000);
        if (!$obid || $author === '' || $msg === '') {
            json_out(['ok' => false, 'error' => 'Complete su nombre y su mensaje.'], 422);
        }
        // Verifica que el obituario exista y esté activo
        $chk = db()->prepare("SELECT id FROM obituaries WHERE id = ? AND status='active' AND deleted_at IS NULL");
        $chk->execute([$obid]);
        if (!$chk->fetchColumn()) json_out(['ok' => false, 'error' => 'Obituario no disponible.'], 404);

        $status = setting_bool('condolence_moderation', true) ? 'pending' : 'approved';
        $st = db()->prepare(
            "INSERT INTO condolences (obituary_id, author_name, message, status, author_ip) VALUES (?,?,?,?,?)"
        );
        $st->execute([$obid, $author, $msg, $status, client_ip()]);
        audit('condolence.create', 'condolences', db()->lastInsertId(), ['obituary_id' => $obid, 'status' => $status]);

        json_out([
            'ok' => true,
            'status' => $status,
            'message' => $status === 'pending'
                ? 'Gracias. Su mensaje será publicado tras una breve revisión.'
                : 'Gracias. Su mensaje ha sido publicado.',
        ], 201);
    }

    case 'moderate': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $status = $b['status'] ?? '';
        if (!$id || !in_array($status, ['pending', 'approved', 'hidden'], true)) {
            json_out(['ok' => false, 'error' => 'Datos de moderación inválidos.'], 422);
        }
        db()->prepare("UPDATE condolences SET status=?, moderated_by=?, moderated_at=NOW() WHERE id=?")
            ->execute([$status, $u['id'], $id]);
        audit('condolence.moderate', 'condolences', $id, ['status' => $status]);
        json_out(['ok' => true]);
    }

    case 'update': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $author = clean_str($b['author_name'] ?? '', 150);
        $msg    = clean_str($b['message'] ?? '', 2000);
        if (!$id || $author === '' || $msg === '') json_out(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        db()->prepare("UPDATE condolences SET author_name=?, message=? WHERE id=?")->execute([$author, $msg, $id]);
        audit('condolence.update', 'condolences', $id);
        json_out(['ok' => true]);
    }

    case 'delete': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("DELETE FROM condolences WHERE id = ?")->execute([$id]);
        audit('condolence.delete', 'condolences', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
