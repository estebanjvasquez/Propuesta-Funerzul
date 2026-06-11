<?php
/**
 * API de Plantillas de obituario:  api/templates.php?action=...
 *   GET  list / get        (público: activas; staff: todas)
 *   POST create/update     (admin)
 *   POST set_default       (admin)  -> deja una sola predeterminada
 *   POST delete            (admin)
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'list';

function tpl_out(array $r): array
{
    return [
        'id'          => (int)$r['id'],
        'name'        => $r['name'],
        'description' => $r['description'],
        'body_html'   => $r['body_html'],
        'styles'      => $r['styles'],
        'config'      => $r['config'] ? json_decode($r['config'], true) : null,
        'is_default'  => (bool)$r['is_default'],
        'is_active'   => (bool)$r['is_active'],
        'updated_at'  => $r['updated_at'],
    ];
}

switch ($action) {

    case 'list': {
        require_method('GET');
        if (is_staff()) {
            $rows = db()->query("SELECT * FROM obituary_templates ORDER BY is_default DESC, name ASC")->fetchAll();
        } else {
            $rows = db()->query("SELECT * FROM obituary_templates WHERE is_active=1 ORDER BY is_default DESC, name ASC")->fetchAll();
        }
        json_out(['ok' => true, 'items' => array_map('tpl_out', $rows)]);
    }

    case 'get': {
        require_method('GET');
        $id = (int)($_GET['id'] ?? 0);
        $st = db()->prepare("SELECT * FROM obituary_templates WHERE id = ?");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) json_out(['ok' => false, 'error' => 'Plantilla no encontrada.'], 404);
        if (!$r['is_active'] && !is_staff()) json_out(['ok' => false, 'error' => 'No disponible.'], 404);
        json_out(['ok' => true, 'item' => tpl_out($r)]);
    }

    case 'create':
    case 'update': {
        require_method('POST');
        $u = require_role('admin');
        require_csrf();
        $b = body_json();
        $name = clean_str($b['name'] ?? '', 120);
        $body = (string)($b['body_html'] ?? '');
        if ($name === '' || trim($body) === '') {
            json_out(['ok' => false, 'error' => 'Nombre y contenido de la plantilla son obligatorios.'], 422);
        }
        $desc   = clean_str($b['description'] ?? '', 255);
        $styles = (string)($b['styles'] ?? '');
        $config = isset($b['config']) ? json_encode($b['config'], JSON_UNESCAPED_UNICODE) : null;
        $active = !empty($b['is_active']) ? 1 : 0;

        if ($action === 'create') {
            $st = db()->prepare("INSERT INTO obituary_templates (name, description, body_html, styles, config, is_active, created_by) VALUES (?,?,?,?,?,?,?)");
            $st->execute([$name, $desc, $body, $styles, $config, $active, $u['id']]);
            $id = (int)db()->lastInsertId();
            audit('template.create', 'obituary_templates', $id, ['name' => $name]);
        } else {
            $id = (int)($b['id'] ?? 0);
            if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
            $st = db()->prepare("UPDATE obituary_templates SET name=?, description=?, body_html=?, styles=?, config=?, is_active=? WHERE id=?");
            $st->execute([$name, $desc, $body, $styles, $config, $active, $id]);
            audit('template.update', 'obituary_templates', $id, ['name' => $name]);
        }
        $row = db()->prepare("SELECT * FROM obituary_templates WHERE id = ?");
        $row->execute([$id]);
        json_out(['ok' => true, 'item' => tpl_out($row->fetch())]);
    }

    case 'set_default': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);

        $pdo = db();
        $pdo->beginTransaction();
        $pdo->exec("UPDATE obituary_templates SET is_default = 0");
        $pdo->prepare("UPDATE obituary_templates SET is_default = 1, is_active = 1 WHERE id = ?")->execute([$id]);
        $pdo->commit();
        audit('template.set_default', 'obituary_templates', $id);
        json_out(['ok' => true]);
    }

    case 'delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $chk = db()->prepare("SELECT is_default FROM obituary_templates WHERE id = ?");
        $chk->execute([$id]);
        $isDef = $chk->fetchColumn();
        if ($isDef === false) json_out(['ok' => false, 'error' => 'Plantilla no encontrada.'], 404);
        if ((int)$isDef === 1) json_out(['ok' => false, 'error' => 'No puede eliminar la plantilla predeterminada. Marque otra como predeterminada primero.'], 409);

        db()->prepare("DELETE FROM obituary_templates WHERE id = ?")->execute([$id]);
        audit('template.delete', 'obituary_templates', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
