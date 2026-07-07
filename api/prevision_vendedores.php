<?php
/**
 * API de Vendedores y Comisiones:  api/prevision_vendedores.php?action=...
 *
 * Vendedores:
 *   GET  list                (staff; ?q=, ?all=1 incluye retirados/inactivos)
 *   GET  get                 (?id=; incluye resumen de contratos y comisiones)
 *   POST create              (staff)
 *   POST update              (staff)
 *   POST retirar             (staff; fija fecha_retiro y desactiva)
 *   POST reactivar           (staff)
 *   POST delete              (admin; solo si no tiene contratos ni comisiones)
 *
 * Comisiones (etapas del esquema SIEMPRE: semana1, fin_mes1, mes2, mes13):
 *   GET  comisiones          (?vendedor_id=, ?contrato_id=, ?desde=, ?hasta=)
 *   GET  comisiones_resumen  (totales por vendedor)
 *   GET  comisiones_pendientes (?vendedor_id=; etapas vencidas sin pagar)
 *   POST comision_pagar      (staff; registra el pago de una etapa)
 *   POST comision_delete     (admin)
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'list';

/** Valida los campos del cuerpo para crear/editar un vendedor. */
function vendedor_input(array $b): array
{
    $nombre = clean_str($b['nombre'] ?? '', 100);
    if ($nombre === '') json_out(['ok' => false, 'error' => 'El nombre del vendedor es obligatorio.'], 422);
    $pct = fn($v) => max(0.0, min(100.0, round((float)$v, 2)));

    $sucursalId = (int)($b['sucursal_id'] ?? 0) ?: null;
    if ($sucursalId) {
        $st = db()->prepare("SELECT id FROM prev_sucursales WHERE id = ?");
        $st->execute([$sucursalId]);
        if (!$st->fetchColumn()) json_out(['ok' => false, 'error' => 'Sucursal no encontrada.'], 422);
    }

    return [
        'cedula'           => prev_cedula($b['cedula'] ?? '') ?: null,
        'nombre'           => $nombre,
        'telefono1'        => clean_str($b['telefono1'] ?? '', 20) ?: null,
        'telefono2'        => clean_str($b['telefono2'] ?? '', 20) ?: null,
        'email'            => clean_str($b['email'] ?? '', 190) ?: null,
        'direccion'        => clean_str($b['direccion'] ?? '', 255) ?: null,
        'sucursal_id'      => $sucursalId,
        'fecha_ingreso'    => prev_date($b['fecha_ingreso'] ?? ''),
        'fecha_retiro'     => prev_date($b['fecha_retiro'] ?? ''),
        'comision_semanal' => $pct($b['comision_semanal'] ?? 0),
        'comision_mensual' => $pct($b['comision_mensual'] ?? 0),
        'comision_anual'   => $pct($b['comision_anual'] ?? 0),
        'banco'            => clean_str($b['banco'] ?? '', 100) ?: null,
        'numero_cuenta'    => clean_str($b['numero_cuenta'] ?? '', 24) ?: null,
        'titular_cuenta'   => clean_str($b['titular_cuenta'] ?? '', 100) ?: null,
        'cedula_cuenta'    => prev_cedula($b['cedula_cuenta'] ?? '') ?: null,
        'notas'            => clean_str($b['notas'] ?? '', 500) ?: null,
        'activo'           => isset($b['activo']) ? (!empty($b['activo']) ? 1 : 0) : 1,
    ];
}

/** Fecha (Y-m-d) en la que "vence" cada etapa de comisión de un contrato. */
function etapa_vence(string $fechaIngreso, string $etapa): string
{
    $d = new DateTime($fechaIngreso);
    switch ($etapa) {
        case 'semana1':  $d->modify('+7 days'); break;
        case 'fin_mes1': $d->modify('last day of this month'); break;
        case 'mes2':     $d->modify('+2 months'); break;
        case 'mes13':    $d->modify('+13 months'); break;
    }
    return $d->format('Y-m-d');
}

switch ($action) {

    // ==================== VENDEDORES ====================

    case 'list': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = (($_GET['all'] ?? '') === '1') ? '1=1' : 'v.activo = 1';
        $params = [];
        if (($q = trim($_GET['q'] ?? '')) !== '') {
            $where .= " AND (v.nombre LIKE ? OR v.cedula LIKE ?)";
            array_push($params, "%$q%", "%$q%");
        }
        $st = db()->prepare(
            "SELECT v.*, su.nombre AS sucursal_nombre,
                    (SELECT COUNT(*) FROM prev_contratos c WHERE c.vendedor_id = v.id) AS contratos
             FROM prev_vendedores v
             LEFT JOIN prev_sucursales su ON su.id = v.sucursal_id
             WHERE $where ORDER BY v.activo DESC, v.nombre ASC"
        );
        $st->execute($params);
        json_out(['ok' => true, 'items' => array_map('prev_vendedor_out', $st->fetchAll())]);
    }

    case 'get': {
        require_method('GET');
        require_role('admin', 'editor');
        $id = (int)($_GET['id'] ?? 0);
        $st = db()->prepare(
            "SELECT v.*, su.nombre AS sucursal_nombre FROM prev_vendedores v
             LEFT JOIN prev_sucursales su ON su.id = v.sucursal_id WHERE v.id = ?"
        );
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) json_out(['ok' => false, 'error' => 'Vendedor no encontrado.'], 404);

        $res = db()->prepare(
            "SELECT COUNT(*) AS contratos,
                    SUM(estatus = 'activo') AS contratos_activos
             FROM prev_contratos WHERE vendedor_id = ?"
        );
        $res->execute([$id]);
        $resumenC = $res->fetch();

        $com = db()->prepare(
            "SELECT COUNT(*) AS pagos, COALESCE(SUM(monto_usd),0) AS total_usd, COALESCE(SUM(monto_bs),0) AS total_bs
             FROM prev_comisiones WHERE vendedor_id = ?"
        );
        $com->execute([$id]);
        $resumenP = $com->fetch();

        json_out(['ok' => true, 'item' => prev_vendedor_out($r), 'resumen' => [
            'contratos'         => (int)$resumenC['contratos'],
            'contratos_activos' => (int)$resumenC['contratos_activos'],
            'comisiones_pagos'  => (int)$resumenP['pagos'],
            'comisiones_usd'    => (float)$resumenP['total_usd'],
            'comisiones_bs'     => (float)$resumenP['total_bs'],
        ]]);
    }

    case 'create': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $in = vendedor_input(body_json());
        if ($in['cedula']) {
            $st = db()->prepare("SELECT id FROM prev_vendedores WHERE cedula = ?");
            $st->execute([$in['cedula']]);
            if ($st->fetchColumn()) json_out(['ok' => false, 'error' => 'Ya existe un vendedor con esa cédula.'], 409);
        }
        $st = db()->prepare(
            "INSERT INTO prev_vendedores
             (cedula, nombre, telefono1, telefono2, email, direccion, sucursal_id,
              fecha_ingreso, fecha_retiro, comision_semanal, comision_mensual, comision_anual,
              banco, numero_cuenta, titular_cuenta, cedula_cuenta, notas, activo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $in['cedula'], $in['nombre'], $in['telefono1'], $in['telefono2'], $in['email'],
            $in['direccion'], $in['sucursal_id'], $in['fecha_ingreso'], $in['fecha_retiro'],
            $in['comision_semanal'], $in['comision_mensual'], $in['comision_anual'],
            $in['banco'], $in['numero_cuenta'], $in['titular_cuenta'], $in['cedula_cuenta'],
            $in['notas'], $in['activo'],
        ]);
        $id = (int)db()->lastInsertId();
        audit('prev_vendedor.create', 'prev_vendedores', $id, ['nombre' => $in['nombre']]);
        json_out(['ok' => true, 'id' => $id], 201);
    }

    case 'update': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $in = vendedor_input($b);
        if ($in['cedula']) {
            $st = db()->prepare("SELECT id FROM prev_vendedores WHERE cedula = ? AND id <> ?");
            $st->execute([$in['cedula'], $id]);
            if ($st->fetchColumn()) json_out(['ok' => false, 'error' => 'Ya existe otro vendedor con esa cédula.'], 409);
        }
        $st = db()->prepare(
            "UPDATE prev_vendedores SET
               cedula=?, nombre=?, telefono1=?, telefono2=?, email=?, direccion=?, sucursal_id=?,
               fecha_ingreso=?, fecha_retiro=?, comision_semanal=?, comision_mensual=?, comision_anual=?,
               banco=?, numero_cuenta=?, titular_cuenta=?, cedula_cuenta=?, notas=?, activo=?
             WHERE id=?"
        );
        $st->execute([
            $in['cedula'], $in['nombre'], $in['telefono1'], $in['telefono2'], $in['email'],
            $in['direccion'], $in['sucursal_id'], $in['fecha_ingreso'], $in['fecha_retiro'],
            $in['comision_semanal'], $in['comision_mensual'], $in['comision_anual'],
            $in['banco'], $in['numero_cuenta'], $in['titular_cuenta'], $in['cedula_cuenta'],
            $in['notas'], $in['activo'], $id,
        ]);
        audit('prev_vendedor.update', 'prev_vendedores', $id, ['nombre' => $in['nombre']]);
        json_out(['ok' => true]);
    }

    case 'retirar': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $fecha = prev_date($b['fecha_retiro'] ?? '') ?: date('Y-m-d');
        db()->prepare("UPDATE prev_vendedores SET fecha_retiro=?, activo=0 WHERE id=?")->execute([$fecha, $id]);
        audit('prev_vendedor.retirar', 'prev_vendedores', $id, ['fecha_retiro' => $fecha]);
        json_out(['ok' => true]);
    }

    case 'reactivar': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("UPDATE prev_vendedores SET fecha_retiro=NULL, activo=1 WHERE id=?")->execute([$id]);
        audit('prev_vendedor.reactivar', 'prev_vendedores', $id);
        json_out(['ok' => true]);
    }

    case 'delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $st = db()->prepare(
            "SELECT (SELECT COUNT(*) FROM prev_contratos WHERE vendedor_id = ?) +
                    (SELECT COUNT(*) FROM prev_comisiones WHERE vendedor_id = ?)"
        );
        $st->execute([$id, $id]);
        if ((int)$st->fetchColumn() > 0) {
            json_out(['ok' => false, 'error' => 'El vendedor tiene contratos o comisiones; retírelo en su lugar.'], 409);
        }
        db()->prepare("DELETE FROM prev_vendedores WHERE id = ?")->execute([$id]);
        audit('prev_vendedor.delete', 'prev_vendedores', $id);
        json_out(['ok' => true]);
    }

    // ==================== COMISIONES ====================

    case 'comisiones': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = '1=1';
        $params = [];
        if (($vid = (int)($_GET['vendedor_id'] ?? 0)) > 0) { $where .= " AND k.vendedor_id = ?"; $params[] = $vid; }
        if (($cid = (int)($_GET['contrato_id'] ?? 0)) > 0) { $where .= " AND k.contrato_id = ?"; $params[] = $cid; }
        if ($d = prev_date($_GET['desde'] ?? '')) { $where .= " AND k.fecha_pago >= ?"; $params[] = $d; }
        if ($h = prev_date($_GET['hasta'] ?? '')) { $where .= " AND k.fecha_pago <= ?"; $params[] = $h; }

        $st = db()->prepare(
            "SELECT k.*, c.numero AS contrato_numero, v.nombre AS vendedor_nombre
             FROM prev_comisiones k
             JOIN prev_contratos  c ON c.id = k.contrato_id
             JOIN prev_vendedores v ON v.id = k.vendedor_id
             WHERE $where ORDER BY k.fecha_pago DESC, k.id DESC LIMIT 500"
        );
        $st->execute($params);
        json_out(['ok' => true, 'items' => array_map('prev_comision_out', $st->fetchAll())]);
    }

    case 'comisiones_resumen': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->query(
            "SELECT v.id, v.nombre, v.cedula, v.activo,
                    (SELECT COUNT(*) FROM prev_contratos c WHERE c.vendedor_id = v.id) AS contratos,
                    (SELECT COUNT(*) FROM prev_contratos c WHERE c.vendedor_id = v.id AND c.estatus='activo') AS contratos_activos,
                    COALESCE(SUM(k.monto_usd),0) AS comisiones_usd,
                    COALESCE(SUM(k.monto_bs),0)  AS comisiones_bs,
                    COUNT(k.id) AS pagos
             FROM prev_vendedores v
             LEFT JOIN prev_comisiones k ON k.vendedor_id = v.id
             GROUP BY v.id, v.nombre, v.cedula, v.activo
             ORDER BY v.activo DESC, v.nombre ASC"
        );
        $items = array_map(fn($r) => [
            'vendedor_id'       => (int)$r['id'],
            'nombre'            => $r['nombre'],
            'cedula'            => $r['cedula'],
            'activo'            => (bool)$r['activo'],
            'contratos'         => (int)$r['contratos'],
            'contratos_activos' => (int)$r['contratos_activos'],
            'pagos'             => (int)$r['pagos'],
            'comisiones_usd'    => (float)$r['comisiones_usd'],
            'comisiones_bs'     => (float)$r['comisiones_bs'],
        ], $st->fetchAll());
        json_out(['ok' => true, 'items' => $items]);
    }

    case 'comisiones_pendientes': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = "c.estatus = 'activo' AND c.vendedor_id IS NOT NULL";
        $params = [];
        if (($vid = (int)($_GET['vendedor_id'] ?? 0)) > 0) { $where .= " AND c.vendedor_id = ?"; $params[] = $vid; }

        $st = db()->prepare(
            "SELECT c.id, c.numero, c.fecha_ingreso, c.monto_cuota, c.moneda, c.comision_venta,
                    v.id AS vendedor_id, v.nombre AS vendedor_nombre, v.comision_mensual,
                    GROUP_CONCAT(k.etapa) AS etapas_pagadas
             FROM prev_contratos c
             JOIN prev_vendedores v ON v.id = c.vendedor_id
             LEFT JOIN prev_comisiones k ON k.contrato_id = c.id
             WHERE $where
             GROUP BY c.id, c.numero, c.fecha_ingreso, c.monto_cuota, c.moneda, c.comision_venta,
                      v.id, v.nombre, v.comision_mensual
             ORDER BY c.fecha_ingreso ASC"
        );
        $st->execute($params);

        $hoy = date('Y-m-d');
        $items = [];
        foreach ($st->fetchAll() as $r) {
            $pagadas = $r['etapas_pagadas'] ? explode(',', $r['etapas_pagadas']) : [];
            foreach (PREV_ETAPAS_COMISION as $etapa) {
                if (in_array($etapa, $pagadas, true)) continue;
                $vence = etapa_vence($r['fecha_ingreso'], $etapa);
                if ($vence > $hoy) continue;  // aún no le toca
                $pct = (float)$r['comision_venta'] > 0 ? (float)$r['comision_venta'] : (float)$r['comision_mensual'];
                $items[] = [
                    'contrato_id'     => (int)$r['id'],
                    'contrato_numero' => $r['numero'],
                    'vendedor_id'     => (int)$r['vendedor_id'],
                    'vendedor_nombre' => $r['vendedor_nombre'],
                    'etapa'           => $etapa,
                    'vencida_desde'   => $vence,
                    'moneda'          => $r['moneda'],
                    'monto_cuota'     => (float)$r['monto_cuota'],
                    'monto_sugerido'  => round((float)$r['monto_cuota'] * $pct / 100, 2),
                ];
            }
        }
        json_out(['ok' => true, 'items' => $items]);
    }

    case 'comision_pagar': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $contratoId = (int)($b['contrato_id'] ?? 0);
        $etapa = prev_enum($b['etapa'] ?? '', PREV_ETAPAS_COMISION);
        if (!$contratoId || !$etapa) json_out(['ok' => false, 'error' => 'Faltan contrato y/o etapa.'], 422);

        $st = db()->prepare("SELECT id, vendedor_id, numero FROM prev_contratos WHERE id = ?");
        $st->execute([$contratoId]);
        $c = $st->fetch();
        if (!$c) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);
        $vendedorId = (int)($b['vendedor_id'] ?? 0) ?: (int)$c['vendedor_id'];
        if (!$vendedorId) json_out(['ok' => false, 'error' => 'El contrato no tiene vendedor asignado.'], 422);

        $fecha = prev_date($b['fecha_pago'] ?? '') ?: date('Y-m-d');
        $tasa  = (float)($b['tasa'] ?? 0) ?: prev_tasa_del_dia($fecha);
        $montoUsd = prev_money($b['monto_usd'] ?? 0);
        $montoBs  = prev_money($b['monto_bs'] ?? 0);
        if ($montoUsd <= 0 && $montoBs <= 0) json_out(['ok' => false, 'error' => 'Indique el monto de la comisión.'], 422);
        if ($montoBs <= 0 && $tasa > 0) $montoBs = round($montoUsd * $tasa, 2);
        if ($montoUsd <= 0 && $tasa > 0) $montoUsd = round($montoBs / $tasa, 2);

        try {
            $ins = db()->prepare(
                "INSERT INTO prev_comisiones
                 (contrato_id, vendedor_id, etapa, monto_bs, tasa, monto_usd, fecha_pago, comentario, registrado_por)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([
                $contratoId, $vendedorId, $etapa, $montoBs, $tasa, $montoUsd, $fecha,
                clean_str($b['comentario'] ?? '', 200) ?: null, $u['id'],
            ]);
        } catch (\PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                json_out(['ok' => false, 'error' => 'Esa etapa ya fue pagada para este contrato.'], 409);
            }
            throw $e;
        }
        $id = (int)db()->lastInsertId();
        audit('prev_comision.pagar', 'prev_comisiones', $id,
              ['contrato' => $c['numero'], 'etapa' => $etapa, 'monto_usd' => $montoUsd]);
        json_out(['ok' => true, 'id' => $id], 201);
    }

    case 'comision_delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("DELETE FROM prev_comisiones WHERE id = ?")->execute([$id]);
        audit('prev_comision.delete', 'prev_comisiones', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
