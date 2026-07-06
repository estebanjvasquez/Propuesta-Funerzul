<?php
/**
 * API de Planes de Previsión:  api/prevision_planes.php?action=...
 *   GET  list     (staff; ?q= búsqueda, ?all=1 incluye inactivos)
 *   GET  get      (?id=)
 *   POST create   (staff)
 *   POST update   (staff)
 *   POST toggle   (staff)  -> activar/desactivar
 *   POST delete   (admin; solo si no tiene contratos)
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'list';

/** Valida los campos del cuerpo para crear/editar un plan. */
function plan_input(array $b): array
{
    $nombre = clean_str($b['nombre'] ?? '', 100);
    if ($nombre === '') json_out(['ok' => false, 'error' => 'El nombre del plan es obligatorio.'], 422);

    return [
        'codigo'              => clean_str($b['codigo'] ?? '', 20),
        'nombre'              => $nombre,
        'descripcion'         => clean_str($b['descripcion'] ?? '', 255),
        'moneda'              => prev_moneda($b['moneda'] ?? ''),
        'cuota_mensual'       => prev_money($b['cuota_mensual'] ?? 0),
        'cuota_inicial'       => prev_money($b['cuota_inicial'] ?? 0),
        'monto_servicio'      => prev_money($b['monto_servicio'] ?? 0),
        'max_beneficiarios'   => max(0, min(255, (int)($b['max_beneficiarios'] ?? 0))),
        'solo_nuevo_contrato' => !empty($b['solo_nuevo_contrato']) ? 1 : 0,
        'es_apoyo'            => !empty($b['es_apoyo']) ? 1 : 0,
        'bloqueado'           => !empty($b['bloqueado']) ? 1 : 0,
        'activo'              => isset($b['activo']) ? (!empty($b['activo']) ? 1 : 0) : 1,
    ];
}

switch ($action) {

    case 'list': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = (($_GET['all'] ?? '') === '1') ? '1=1' : 'p.activo = 1';
        $params = [];
        if (($q = trim($_GET['q'] ?? '')) !== '') {
            $where .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)";
            array_push($params, "%$q%", "%$q%");
        }
        $st = db()->prepare(
            "SELECT p.*, (SELECT COUNT(*) FROM prev_contratos c WHERE c.plan_id = p.id) AS contratos
             FROM prev_planes p WHERE $where ORDER BY p.activo DESC, p.nombre ASC"
        );
        $st->execute($params);
        json_out(['ok' => true, 'items' => array_map('prev_plan_out', $st->fetchAll())]);
    }

    case 'get': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->prepare("SELECT * FROM prev_planes WHERE id = ?");
        $st->execute([(int)($_GET['id'] ?? 0)]);
        $r = $st->fetch();
        if (!$r) json_out(['ok' => false, 'error' => 'Plan no encontrado.'], 404);
        json_out(['ok' => true, 'item' => prev_plan_out($r)]);
    }

    case 'create': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $in = plan_input(body_json());
        $st = db()->prepare(
            "INSERT INTO prev_planes
             (codigo, nombre, descripcion, moneda, cuota_mensual, cuota_inicial, monto_servicio,
              max_beneficiarios, solo_nuevo_contrato, es_apoyo, bloqueado, activo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $in['codigo'] ?: null, $in['nombre'], $in['descripcion'] ?: null, $in['moneda'],
            $in['cuota_mensual'], $in['cuota_inicial'], $in['monto_servicio'],
            $in['max_beneficiarios'], $in['solo_nuevo_contrato'], $in['es_apoyo'],
            $in['bloqueado'], $in['activo'],
        ]);
        $id = (int)db()->lastInsertId();
        audit('prev_plan.create', 'prev_planes', $id, ['nombre' => $in['nombre']]);
        json_out(['ok' => true, 'id' => $id], 201);
    }

    case 'update': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $in = plan_input($b);
        $st = db()->prepare(
            "UPDATE prev_planes SET
               codigo=?, nombre=?, descripcion=?, moneda=?, cuota_mensual=?, cuota_inicial=?,
               monto_servicio=?, max_beneficiarios=?, solo_nuevo_contrato=?, es_apoyo=?,
               bloqueado=?, activo=?
             WHERE id=?"
        );
        $st->execute([
            $in['codigo'] ?: null, $in['nombre'], $in['descripcion'] ?: null, $in['moneda'],
            $in['cuota_mensual'], $in['cuota_inicial'], $in['monto_servicio'],
            $in['max_beneficiarios'], $in['solo_nuevo_contrato'], $in['es_apoyo'],
            $in['bloqueado'], $in['activo'], $id,
        ]);
        audit('prev_plan.update', 'prev_planes', $id, ['nombre' => $in['nombre']]);
        json_out(['ok' => true]);
    }

    case 'toggle': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $activo = !empty($b['activo']) ? 1 : 0;
        db()->prepare("UPDATE prev_planes SET activo=? WHERE id=?")->execute([$activo, $id]);
        audit('prev_plan.toggle', 'prev_planes', $id, ['activo' => (bool)$activo]);
        json_out(['ok' => true]);
    }

    case 'delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $st = db()->prepare("SELECT COUNT(*) FROM prev_contratos WHERE plan_id = ?");
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            json_out(['ok' => false, 'error' => 'El plan tiene contratos asociados; desactívelo en su lugar.'], 409);
        }
        db()->prepare("DELETE FROM prev_planes WHERE id = ?")->execute([$id]);
        audit('prev_plan.delete', 'prev_planes', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
