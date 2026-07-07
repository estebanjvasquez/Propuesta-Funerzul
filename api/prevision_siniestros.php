<?php
/**
 * API de Siniestros / Reclamos:  api/prevision_siniestros.php?action=...
 * (equivale a cm_siniestros + cmsiniestrosdetalles del sistema SIEMPRE)
 *
 *   GET  list        (staff; ?q=, ?estado=, paginado)
 *   GET  preparar    (?numero= | ?contrato_id=, ?fecha= (defunción, def. hoy))
 *                    -> contrato + beneficiarios con pre-chequeo de cobertura
 *   POST create      (staff; registra el siniestro con la validación de
 *                    cobertura del momento y marca el beneficiario fallecido)
 *   GET  get         (?id=; expediente completo con detalles)
 *   POST detalle_add | detalle_pagado | detalle_delete
 *   POST set_estado  (abierto | liquidado | cerrado | rechazado(+motivo);
 *                    al cerrar un siniestro del titular el contrato pasa a
 *                    'finalizado')
 *   POST delete      (admin; borra el expediente y restaura el beneficiario)
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'list';

const SINIESTRO_SELECT = "
    SELECT s.*, c.numero AS contrato_numero,
           CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente_nombre,
           p.nombre AS plan_nombre, pa.nombre AS parentesco
    FROM prev_siniestros s
    JOIN prev_contratos c ON c.id = s.contrato_id
    JOIN prev_clientes cl ON cl.id = c.cliente_id
    LEFT JOIN prev_planes p ON p.id = c.plan_id
    JOIN prev_beneficiarios b ON b.id = s.beneficiario_id
    JOIN prev_parentescos pa ON pa.id = b.parentesco_id";

/** Recalcula el monto total del siniestro (suma de sus detalles). */
function siniestro_recalcular(int $id): void
{
    db()->prepare(
        "UPDATE prev_siniestros s
         SET s.monto_total = (SELECT COALESCE(SUM(d.monto),0) FROM prev_siniestro_detalles d WHERE d.siniestro_id = s.id)
         WHERE s.id = ?"
    )->execute([$id]);
}

/** Carga un siniestro (fila cruda del SELECT base) o responde 404. */
function siniestro_row(int $id): array
{
    $st = db()->prepare(SINIESTRO_SELECT . " WHERE s.id = ?");
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) json_out(['ok' => false, 'error' => 'Siniestro no encontrado.'], 404);
    return $r;
}

switch ($action) {

    case 'list': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = '1=1';
        $params = [];
        if (($q = trim($_GET['q'] ?? '')) !== '') {
            $where .= " AND (c.numero LIKE ? OR s.nombre_fallecido LIKE ? OR s.cedula_fallecido LIKE ?
                        OR CONCAT(cl.nombres,' ',cl.apellidos) LIKE ?)";
            $like = "%$q%";
            array_push($params, $like, $like, $like, $like);
        }
        if ($e = prev_enum($_GET['estado'] ?? '', PREV_ESTADOS_SINIESTRO)) { $where .= " AND s.estado = ?"; $params[] = $e; }
        $limit  = min(max((int)($_GET['limit'] ?? 50), 1), 200);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $countSt = db()->prepare("SELECT COUNT(*) FROM (" . SINIESTRO_SELECT . " WHERE $where) t");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $st = db()->prepare(SINIESTRO_SELECT . " WHERE $where
            ORDER BY s.estado = 'abierto' DESC, s.fecha_defuncion DESC, s.id DESC LIMIT $limit OFFSET $offset");
        $st->execute($params);
        json_out(['ok' => true, 'total' => $total, 'limit' => $limit, 'offset' => $offset,
                  'items' => array_map('prev_siniestro_out', $st->fetchAll())]);
    }

    case 'preparar': {
        require_method('GET');
        require_role('admin', 'editor');
        if (($id = (int)($_GET['contrato_id'] ?? 0)) > 0) {
            $st = db()->prepare("SELECT * FROM prev_contratos WHERE id = ?");
            $st->execute([$id]);
        } elseif (($num = trim($_GET['numero'] ?? '')) !== '') {
            $st = db()->prepare("SELECT * FROM prev_contratos WHERE numero = ?");
            $st->execute([$num]);
        } else {
            json_out(['ok' => false, 'error' => 'Indique el contrato (id o número).'], 422);
        }
        $contrato = $st->fetch();
        if (!$contrato) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);
        $fecha = prev_date($_GET['fecha'] ?? '') ?: date('Y-m-d');

        $cli = db()->prepare("SELECT CONCAT(nombres,' ',apellidos) AS n FROM prev_clientes WHERE id = ?");
        $cli->execute([(int)$contrato['cliente_id']]);

        $bs = db()->prepare(
            "SELECT b.*, pa.nombre AS parentesco FROM prev_beneficiarios b
             JOIN prev_parentescos pa ON pa.id = b.parentesco_id
             WHERE b.contrato_id = ? AND b.estatus <> 'fallecido'
             ORDER BY pa.id ASC, b.id ASC"
        );
        $bs->execute([(int)$contrato['id']]);

        $beneficiarios = [];
        foreach ($bs->fetchAll() as $b) {
            $val = prev_validar_cobertura($contrato, $b, $fecha);
            $beneficiarios[] = [
                'id'           => (int)$b['id'],
                'nombre'       => trim($b['nombres'] . ' ' . $b['apellidos']),
                'cedula'       => $b['cedula'],
                'parentesco'   => $b['parentesco'],
                'es_titular'   => (int)$b['parentesco_id'] === 1,
                'estatus'      => $b['estatus'],
                'edad'         => prev_edad($b['fecha_nacimiento'], $fecha),
                'cobertura'    => $val['cobertura'],
                'checks'       => $val['checks'],
            ];
        }
        json_out(['ok' => true,
            'contrato' => ['id' => (int)$contrato['id'], 'numero' => $contrato['numero'],
                           'estatus' => $contrato['estatus'], 'moneda' => $contrato['moneda'],
                           'cliente_nombre' => $cli->fetchColumn()],
            'fecha' => $fecha, 'beneficiarios' => $beneficiarios]);
    }

    case 'create': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $contratoId = (int)($b['contrato_id'] ?? 0);
        $beneficiarioId = (int)($b['beneficiario_id'] ?? 0);
        $fechaDef = prev_date($b['fecha_defuncion'] ?? '');
        if (!$contratoId || !$beneficiarioId || !$fechaDef) {
            json_out(['ok' => false, 'error' => 'Contrato, beneficiario y fecha de defunción son obligatorios.'], 422);
        }
        $st = db()->prepare("SELECT * FROM prev_contratos WHERE id = ?");
        $st->execute([$contratoId]);
        $contrato = $st->fetch();
        if (!$contrato) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);
        $st = db()->prepare("SELECT * FROM prev_beneficiarios WHERE id = ? AND contrato_id = ?");
        $st->execute([$beneficiarioId, $contratoId]);
        $benef = $st->fetch();
        if (!$benef) json_out(['ok' => false, 'error' => 'El beneficiario no pertenece a ese contrato.'], 422);

        $st = db()->prepare(
            "SELECT id FROM prev_siniestros WHERE beneficiario_id = ? AND estado <> 'rechazado'"
        );
        $st->execute([$beneficiarioId]);
        if ($st->fetchColumn()) {
            json_out(['ok' => false, 'error' => 'Ya existe un siniestro registrado para ese beneficiario.'], 409);
        }

        $val = prev_validar_cobertura($contrato, $benef, $fechaDef);
        $esTitular = (int)$benef['parentesco_id'] === 1;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO prev_siniestros
                 (contrato_id, beneficiario_id, cedula_fallecido, nombre_fallecido, es_titular,
                  fecha_defuncion, fecha_reporte, reportado_por, telefono_reporta,
                  cobertura, validacion, estado, moneda, observaciones, created_by, updated_by)
                 VALUES (?,?,?,?,?,?,NOW(),?,?,?,?,'abierto',?,?,?,?)"
            )->execute([
                $contratoId, $beneficiarioId, $benef['cedula'],
                trim($benef['nombres'] . ' ' . $benef['apellidos']), $esTitular ? 1 : 0,
                $fechaDef,
                clean_str($b['reportado_por'] ?? '', 100) ?: null,
                clean_str($b['telefono_reporta'] ?? '', 20) ?: null,
                $val['cobertura'], json_encode($val['checks'], JSON_UNESCAPED_UNICODE),
                $contrato['moneda'],
                clean_str($b['observaciones'] ?? '', 500) ?: null,
                $u['id'], $u['id'],
            ]);
            $id = (int)$pdo->lastInsertId();

            // Marcar el beneficiario como fallecido
            $pdo->prepare("UPDATE prev_beneficiarios SET estatus='fallecido', fecha_defuncion=? WHERE id=?")
                ->execute([$fechaDef, $beneficiarioId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        audit('prev_siniestro.create', 'prev_siniestros', $id,
              ['contrato' => $contrato['numero'], 'fallecido' => trim($benef['nombres'] . ' ' . $benef['apellidos']),
               'cobertura' => $val['cobertura']]);
        json_out(['ok' => true, 'id' => $id, 'cobertura' => $val['cobertura'], 'checks' => $val['checks']], 201);
    }

    case 'get': {
        require_method('GET');
        require_role('admin', 'editor');
        $r = siniestro_row((int)($_GET['id'] ?? 0));
        $det = db()->prepare("SELECT * FROM prev_siniestro_detalles WHERE siniestro_id = ? ORDER BY id ASC");
        $det->execute([(int)$r['id']]);
        json_out(['ok' => true, 'item' => prev_siniestro_out($r),
                  'detalles' => array_map('prev_sin_detalle_out', $det->fetchAll())]);
    }

    case 'detalle_add': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $sinId = (int)($b['siniestro_id'] ?? 0);
        $descripcion = clean_str($b['descripcion'] ?? '', 200);
        $monto = prev_money($b['monto'] ?? 0);
        if (!$sinId || $descripcion === '') json_out(['ok' => false, 'error' => 'Faltan siniestro y/o descripción.'], 422);
        $r = siniestro_row($sinId);
        if (in_array($r['estado'], ['cerrado', 'rechazado'], true)) {
            json_out(['ok' => false, 'error' => 'El siniestro está ' . $r['estado'] . '; no admite cambios.'], 409);
        }
        db()->prepare(
            "INSERT INTO prev_siniestro_detalles (siniestro_id, tipo, descripcion, proveedor, moneda, monto, pagado, fecha_pago, notas)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([
            $sinId, prev_enum($b['tipo'] ?? '', PREV_TIPOS_SIN_DETALLE, 'servicio'),
            $descripcion, clean_str($b['proveedor'] ?? '', 150) ?: null,
            prev_moneda($b['moneda'] ?? $r['moneda']), $monto,
            !empty($b['pagado']) ? 1 : 0,
            !empty($b['pagado']) ? (prev_date($b['fecha_pago'] ?? '') ?: date('Y-m-d')) : null,
            clean_str($b['notas'] ?? '', 255) ?: null,
        ]);
        $id = (int)db()->lastInsertId();
        siniestro_recalcular($sinId);
        audit('prev_siniestro.detalle_add', 'prev_siniestro_detalles', $id,
              ['siniestro_id' => $sinId, 'descripcion' => $descripcion, 'monto' => $monto]);
        json_out(['ok' => true, 'id' => $id], 201);
    }

    case 'detalle_pagado': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $pagado = !empty($b['pagado']) ? 1 : 0;
        db()->prepare("UPDATE prev_siniestro_detalles SET pagado=?, fecha_pago=? WHERE id=?")
            ->execute([$pagado, $pagado ? (prev_date($b['fecha_pago'] ?? '') ?: date('Y-m-d')) : null, $id]);
        audit('prev_siniestro.detalle_pagado', 'prev_siniestro_detalles', $id, ['pagado' => (bool)$pagado]);
        json_out(['ok' => true]);
    }

    case 'detalle_delete': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $st = db()->prepare("SELECT siniestro_id FROM prev_siniestro_detalles WHERE id = ?");
        $st->execute([$id]);
        $sinId = (int)$st->fetchColumn();
        if (!$sinId) json_out(['ok' => false, 'error' => 'Detalle no encontrado.'], 404);
        db()->prepare("DELETE FROM prev_siniestro_detalles WHERE id = ?")->execute([$id]);
        siniestro_recalcular($sinId);
        audit('prev_siniestro.detalle_delete', 'prev_siniestro_detalles', $id, ['siniestro_id' => $sinId]);
        json_out(['ok' => true]);
    }

    case 'set_estado': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $estado = prev_enum($b['estado'] ?? '', PREV_ESTADOS_SINIESTRO);
        if (!$id || !$estado) json_out(['ok' => false, 'error' => 'Faltan id y/o estado.'], 422);
        $r = siniestro_row($id);
        $motivo = clean_str($b['motivo'] ?? '', 255) ?: null;
        if ($estado === 'rechazado' && !$motivo) {
            json_out(['ok' => false, 'error' => 'Indique el motivo del rechazo.'], 422);
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE prev_siniestros SET estado=?, motivo_rechazo=?, updated_by=? WHERE id=?")
                ->execute([$estado, $estado === 'rechazado' ? $motivo : null, $u['id'], $id]);

            // Al cerrar el siniestro del titular, el contrato queda finalizado
            // (servicio cumplido) y se anulan las cuotas que quedaban por cobrar.
            if ($estado === 'cerrado' && (int)$r['es_titular'] === 1) {
                $pdo->prepare(
                    "UPDATE prev_contratos SET estatus='finalizado', fecha_estatus=CURDATE(),
                            motivo_estatus=?, updated_by=? WHERE id=?"
                )->execute(['Servicio cumplido (siniestro #' . $id . ')', $u['id'], (int)$r['contrato_id']]);
                $pdo->prepare(
                    "UPDATE prev_cuotas SET estado='anulada' WHERE contrato_id=? AND estado IN ('pendiente','parcial')"
                )->execute([(int)$r['contrato_id']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        audit('prev_siniestro.set_estado', 'prev_siniestros', $id,
              ['de' => $r['estado'], 'a' => $estado, 'motivo' => $motivo]);
        json_out(['ok' => true]);
    }

    case 'delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $r = siniestro_row($id);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM prev_siniestros WHERE id = ?")->execute([$id]);
            // Restaurar el beneficiario si no tiene otro siniestro registrado
            $st = $pdo->prepare("SELECT COUNT(*) FROM prev_siniestros WHERE beneficiario_id = ?");
            $st->execute([(int)$r['beneficiario_id']]);
            if ((int)$st->fetchColumn() === 0) {
                $pdo->prepare("UPDATE prev_beneficiarios SET estatus='activo', fecha_defuncion=NULL WHERE id=?")
                    ->execute([(int)$r['beneficiario_id']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        audit('prev_siniestro.delete', 'prev_siniestros', $id,
              ['contrato' => $r['contrato_numero'], 'fallecido' => $r['nombre_fallecido']]);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
