<?php
/**
 * API de Preguntas Frecuentes:  api/faqs.php?action=...
 *   GET  list      (público: activas; staff con ?scope=admin: todas)
 *   GET  get       (?id=)
 *   POST create    (staff)
 *   POST update    (staff)
 *   POST toggle    (staff)  -> pausar / activar
 *   POST delete    (staff)  -> elimina definitivamente
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'list';

/** Da formato de salida a una fila de FAQ. */
function faq_out(array $r): array
{
    return [
        'id'         => (int)$r['id'],
        'question'   => $r['question'],
        'answer'     => $r['answer'],
        'sort_order' => (int)$r['sort_order'],
        'is_active'  => (bool)$r['is_active'],
        'created_at' => $r['created_at'],
        'updated_at' => $r['updated_at'],
    ];
}

/** Recoge y valida los campos del cuerpo para crear/editar. */
function faq_input(array $b): array
{
    $question = clean_str($b['question'] ?? '', 300);
    $answer   = clean_str($b['answer'] ?? '', 4000);
    if ($question === '' || $answer === '') {
        json_out(['ok' => false, 'error' => 'La pregunta y la respuesta son obligatorias.'], 422);
    }
    return [
        'question'   => $question,
        'answer'     => $answer,
        'sort_order' => isset($b['sort_order']) && $b['sort_order'] !== '' ? (int)$b['sort_order'] : 0,
        'is_active'  => array_key_exists('is_active', $b) ? (!empty($b['is_active']) ? 1 : 0) : 1,
    ];
}

switch ($action) {

    // ---- LISTADO ----------------------------------------------------------
    case 'list': {
        require_method('GET');
        $adminScope = (($_GET['scope'] ?? '') === 'admin');
        if ($adminScope) require_role('admin', 'editor');

        $where = $adminScope ? '1=1' : 'is_active = 1';
        $sql = "SELECT * FROM faqs WHERE $where ORDER BY sort_order ASC, id ASC";
        $rows = array_map('faq_out', db()->query($sql)->fetchAll());
        json_out(['ok' => true, 'items' => $rows]);
    }

    // ---- DETALLE ----------------------------------------------------------
    case 'get': {
        require_method('GET');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $st = db()->prepare("SELECT * FROM faqs WHERE id = ?");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) json_out(['ok' => false, 'error' => 'Pregunta no encontrada.'], 404);
        json_out(['ok' => true, 'item' => faq_out($r)]);
    }

    // ---- CREAR ------------------------------------------------------------
    case 'create': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $in = faq_input(body_json());

        $st = db()->prepare(
            "INSERT INTO faqs (question, answer, sort_order, is_active, created_by, updated_by)
             VALUES (?,?,?,?,?,?)"
        );
        $st->execute([$in['question'], $in['answer'], $in['sort_order'], $in['is_active'], $u['id'], $u['id']]);
        $id = (int)db()->lastInsertId();
        audit('faq.create', 'faqs', $id, ['question' => $in['question']]);

        $row = db()->prepare("SELECT * FROM faqs WHERE id = ?");
        $row->execute([$id]);
        json_out(['ok' => true, 'item' => faq_out($row->fetch())], 201);
    }

    // ---- ACTUALIZAR -------------------------------------------------------
    case 'update': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);

        $cur = db()->prepare("SELECT id FROM faqs WHERE id = ?");
        $cur->execute([$id]);
        if (!$cur->fetch()) json_out(['ok' => false, 'error' => 'Pregunta no encontrada.'], 404);

        $in = faq_input($b);
        $st = db()->prepare(
            "UPDATE faqs SET question=?, answer=?, sort_order=?, is_active=?, updated_by=? WHERE id=?"
        );
        $st->execute([$in['question'], $in['answer'], $in['sort_order'], $in['is_active'], $u['id'], $id]);
        audit('faq.update', 'faqs', $id, ['question' => $in['question']]);

        $row = db()->prepare("SELECT * FROM faqs WHERE id = ?");
        $row->execute([$id]);
        json_out(['ok' => true, 'item' => faq_out($row->fetch())]);
    }

    // ---- PAUSAR / ACTIVAR -------------------------------------------------
    case 'toggle': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $active = !empty($b['is_active']) ? 1 : 0;

        db()->prepare("UPDATE faqs SET is_active=?, updated_by=? WHERE id=?")->execute([$active, $u['id'], $id]);
        audit('faq.toggle', 'faqs', $id, ['is_active' => (bool)$active]);
        json_out(['ok' => true]);
    }

    // ---- ELIMINAR ---------------------------------------------------------
    case 'delete': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("DELETE FROM faqs WHERE id = ?")->execute([$id]);
        audit('faq.delete', 'faqs', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
