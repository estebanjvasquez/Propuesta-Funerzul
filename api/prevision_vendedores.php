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
 * Comisiones (etapas del esquema SIEMPRE: semana1, fin_mes1, mes2, mes13).
 * Flujo por estados: calculada -> aprobada -> pagada (o anulada).
 *   GET  comisiones            (pagadas; ?vendedor_id=, ?contrato_id=, ?desde=, ?hasta=)
 *   GET  comisiones_resumen    (totales pagados por vendedor)
 *   GET  comisiones_pendientes (?vendedor_id=; etapas vencidas aún sin generar)
 *   GET  comisiones_estado     (?estado=calculada|aprobada|pagada, ?vendedor_id=)
 *   POST comision_calcular     (staff; genera una etapa {contrato_id,etapa} o todas {all:1})
 *   POST comision_actualizar   (staff; ajusta el monto de una comisión 'calculada' antes de aprobar)
 *   POST comision_aprobar      (staff; calculada -> aprobada; {ids:[...]} o {contrato_id,etapa})
 *   POST comision_pagar        (staff; aprobada/calculada -> pagada; {id} o {contrato_id,etapa})
 *   POST comision_anular       (staff; descarta una comisión no pagada para poder recalcularla)
 *   POST comision_delete       (admin; elimina un pago de comisión)
 *   GET  descuentos            (staff; ?vendedor_id=, ?estado= — descuentos por anulación de
 *                               contratos comisionados; se compensan al pagar la siguiente comisión)
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

    $supervisorId = (int)($b['supervisor_id'] ?? 0) ?: null;
    if ($supervisorId) {
        $st = db()->prepare("SELECT id FROM prev_vendedores WHERE id = ?");
        $st->execute([$supervisorId]);
        if (!$st->fetchColumn()) json_out(['ok' => false, 'error' => 'Supervisor no encontrado.'], 422);
    }

    return [
        'cedula'           => prev_cedula($b['cedula'] ?? '') ?: null,
        'nombre'           => $nombre,
        'cargo'            => prev_enum($b['cargo'] ?? '', ['vendedor', 'coordinador', 'gerente'], 'vendedor'),
        'supervisor_id'    => $supervisorId,
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
        'zelle'            => clean_str($b['zelle'] ?? '', 120) ?: null,
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

/**
 * Datos para generar la comisión de una etapa de un contrato activo:
 * [vendedor_id, base, porcentaje, monto, moneda, vence] o null si no aplica
 * (contrato inexistente/no activo/sin vendedor, o etapa aún no vencida).
 */
function comision_calc_row(int $contratoId, string $etapa): ?array
{
    $st = db()->prepare(
        "SELECT c.id, c.vendedor_id, c.fecha_ingreso, c.monto_cuota, c.moneda,
                c.comision_venta, c.estatus, v.comision_mensual,
                COALESCE(c.fecha_corte, c.fecha_ingreso) AS fecha_base
         FROM prev_contratos c JOIN prev_vendedores v ON v.id = c.vendedor_id
         WHERE c.id = ?"
    );
    $st->execute([$contratoId]);
    $r = $st->fetch();
    if (!$r || $r['estatus'] !== 'activo' || !$r['vendedor_id']) return null;
    // Las etapas vencen contadas desde la fecha de corte (si existe) o la de ingreso.
    $vence = etapa_vence($r['fecha_base'], $etapa);
    if ($vence > date('Y-m-d')) return null;
    $pct  = (float)$r['comision_venta'] > 0 ? (float)$r['comision_venta'] : (float)$r['comision_mensual'];
    $base = (float)$r['monto_cuota'];
    return [
        'vendedor_id' => (int)$r['vendedor_id'],
        'base'        => $base,
        'porcentaje'  => $pct,
        'monto'       => round($base * $pct / 100, 2),
        'moneda'      => $r['moneda'],
        'vence'       => $vence,
    ];
}

/**
 * Aplica los descuentos pendientes del vendedor (por anulaciones) a un pago de
 * comisión, sin dejar el pago en negativo. Marca los aplicados y devuelve
 * [monto_neto_usd, total_descontado_usd, ids_aplicados]. Tolerante si la tabla
 * prev_com_descuentos aún no existe (09).
 */
function descuentos_aplicar(int $vendedorId, float $montoUsd, int $comisionId): array
{
    try {
        $st = db()->prepare(
            "SELECT id, monto_usd FROM prev_com_descuentos
             WHERE vendedor_id = ? AND estado = 'pendiente' ORDER BY id ASC"
        );
        $st->execute([$vendedorId]);
        $total = 0.0; $ids = [];
        foreach ($st->fetchAll() as $d) {
            if ($total + (float)$d['monto_usd'] > $montoUsd + 0.009) break;
            $total += (float)$d['monto_usd'];
            $ids[] = (int)$d['id'];
        }
        if ($ids) {
            $in = implode(',', $ids);
            db()->prepare(
                "UPDATE prev_com_descuentos SET estado = 'aplicado',
                        aplicado_en_comision_id = ?, aplicado_at = NOW()
                 WHERE id IN ($in)"
            )->execute([$comisionId]);
        }
        return [round($montoUsd - $total, 2), round($total, 2), $ids];
    } catch (\Throwable $e) {
        return [$montoUsd, 0.0, []];
    }
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
            "SELECT v.*, su.nombre AS sucursal_nombre, sup.nombre AS supervisor_nombre,
                    (SELECT COUNT(*) FROM prev_contratos c WHERE c.vendedor_id = v.id) AS contratos
             FROM prev_vendedores v
             LEFT JOIN prev_sucursales su ON su.id = v.sucursal_id
             LEFT JOIN prev_vendedores sup ON sup.id = v.supervisor_id
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
            "SELECT v.*, su.nombre AS sucursal_nombre, sup.nombre AS supervisor_nombre
             FROM prev_vendedores v
             LEFT JOIN prev_sucursales su ON su.id = v.sucursal_id
             LEFT JOIN prev_vendedores sup ON sup.id = v.supervisor_id
             WHERE v.id = ?"
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
        // Por defecto muestra las pagadas; ?contrato_id lista todas las del contrato.
        $where = ($cid0 = (int)($_GET['contrato_id'] ?? 0)) > 0 ? '1=1' : "k.estado = 'pagada'";
        $params = [];
        if (($vid = (int)($_GET['vendedor_id'] ?? 0)) > 0) { $where .= " AND k.vendedor_id = ?"; $params[] = $vid; }
        if ($cid0 > 0) { $where .= " AND k.contrato_id = ?"; $params[] = $cid0; }
        if ($d = prev_date($_GET['desde'] ?? '')) { $where .= " AND k.fecha_pago >= ?"; $params[] = $d; }
        if ($h = prev_date($_GET['hasta'] ?? '')) { $where .= " AND k.fecha_pago <= ?"; $params[] = $h; }

        $st = db()->prepare(
            "SELECT k.*, c.numero AS contrato_numero, c.moneda AS moneda,
                    v.nombre AS vendedor_nombre, ua.email AS aprobador
             FROM prev_comisiones k
             JOIN prev_contratos  c ON c.id = k.contrato_id
             JOIN prev_vendedores v ON v.id = k.vendedor_id
             LEFT JOIN users ua ON ua.id = k.aprobado_por
             WHERE $where ORDER BY k.fecha_pago DESC, k.id DESC LIMIT 500"
        );
        $st->execute($params);
        json_out(['ok' => true, 'items' => array_map('prev_comision_out', $st->fetchAll())]);
    }

    case 'comisiones_estado': {
        require_method('GET');
        require_role('admin', 'editor');
        $estado = prev_enum($_GET['estado'] ?? '', PREV_ESTADOS_COMISION, 'calculada');
        $where = "k.estado = ?";
        $params = [$estado];
        if (($vid = (int)($_GET['vendedor_id'] ?? 0)) > 0) { $where .= " AND k.vendedor_id = ?"; $params[] = $vid; }

        $st = db()->prepare(
            "SELECT k.*, c.numero AS contrato_numero, c.moneda AS moneda,
                    v.nombre AS vendedor_nombre, ua.email AS aprobador
             FROM prev_comisiones k
             JOIN prev_contratos  c ON c.id = k.contrato_id
             JOIN prev_vendedores v ON v.id = k.vendedor_id
             LEFT JOIN users ua ON ua.id = k.aprobado_por
             WHERE $where ORDER BY k.fecha_calculo ASC, k.id ASC LIMIT 1000"
        );
        $st->execute($params);
        $items = array_map('prev_comision_out', $st->fetchAll());
        $totUsd = array_sum(array_map(fn($i) => $i['monto_calculado'] ?: $i['monto_usd'], $items));
        json_out(['ok' => true, 'estado' => $estado, 'items' => $items, 'total_usd' => round($totUsd, 2)]);
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
             LEFT JOIN prev_comisiones k ON k.vendedor_id = v.id AND k.estado = 'pagada'
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
                    COALESCE(c.fecha_corte, c.fecha_ingreso) AS fecha_base,
                    v.id AS vendedor_id, v.nombre AS vendedor_nombre, v.comision_mensual,
                    GROUP_CONCAT(k.etapa) AS etapas_pagadas
             FROM prev_contratos c
             JOIN prev_vendedores v ON v.id = c.vendedor_id
             LEFT JOIN prev_comisiones k ON k.contrato_id = c.id
             WHERE $where
             GROUP BY c.id, c.numero, c.fecha_ingreso, c.monto_cuota, c.moneda, c.comision_venta,
                      c.fecha_corte, v.id, v.nombre, v.comision_mensual
             ORDER BY c.fecha_ingreso ASC"
        );
        $st->execute($params);

        $hoy = date('Y-m-d');
        $items = [];
        foreach ($st->fetchAll() as $r) {
            $pagadas = $r['etapas_pagadas'] ? explode(',', $r['etapas_pagadas']) : [];
            foreach (PREV_ETAPAS_COMISION as $etapa) {
                if (in_array($etapa, $pagadas, true)) continue;
                $vence = etapa_vence($r['fecha_base'], $etapa);
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

    case 'comision_calcular': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();

        // Lista de (contrato, etapa) a generar: una específica o todas las vencidas.
        $objetivos = [];
        if (!empty($b['all'])) {
            $where = "c.estatus = 'activo' AND c.vendedor_id IS NOT NULL";
            $params = [];
            if (($vid = (int)($b['vendedor_id'] ?? 0)) > 0) { $where .= " AND c.vendedor_id = ?"; $params[] = $vid; }
            $st = db()->prepare(
                "SELECT c.id, GROUP_CONCAT(k.etapa) AS generadas
                 FROM prev_contratos c
                 LEFT JOIN prev_comisiones k ON k.contrato_id = c.id
                 WHERE $where GROUP BY c.id"
            );
            $st->execute($params);
            foreach ($st->fetchAll() as $r) {
                $ya = $r['generadas'] ? explode(',', $r['generadas']) : [];
                foreach (PREV_ETAPAS_COMISION as $etapa) {
                    if (!in_array($etapa, $ya, true)) $objetivos[] = [(int)$r['id'], $etapa];
                }
            }
        } else {
            $contratoId = (int)($b['contrato_id'] ?? 0);
            $etapa = prev_enum($b['etapa'] ?? '', PREV_ETAPAS_COMISION);
            if (!$contratoId || !$etapa) json_out(['ok' => false, 'error' => 'Faltan contrato y/o etapa.'], 422);
            $objetivos[] = [$contratoId, $etapa];
        }

        $ins = db()->prepare(
            "INSERT IGNORE INTO prev_comisiones
             (contrato_id, vendedor_id, etapa, estado, base_monto, porcentaje, monto_calculado,
              monto_usd, fecha_calculo, registrado_por)
             VALUES (?,?,?,'calculada',?,?,?,?,CURDATE(),?)"
        );
        $generadas = 0;
        foreach ($objetivos as [$cid, $etapa]) {
            $calc = comision_calc_row($cid, $etapa);
            if (!$calc) continue;                       // no vencida / contrato no activo
            $ins->execute([$cid, $calc['vendedor_id'], $etapa, $calc['base'], $calc['porcentaje'],
                           $calc['monto'], $calc['monto'], $u['id']]);
            if ($ins->rowCount() > 0) $generadas++;
        }
        audit('prev_comision.calcular', 'prev_comisiones', null,
              ['generadas' => $generadas, 'evaluadas' => count($objetivos)]);
        json_out(['ok' => true, 'generadas' => $generadas]);
    }

    case 'comision_actualizar': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $st = db()->prepare("SELECT estado FROM prev_comisiones WHERE id = ?");
        $st->execute([$id]);
        $estado = $st->fetchColumn();
        if ($estado === false) json_out(['ok' => false, 'error' => 'Comisión no encontrada.'], 404);
        if ($estado !== 'calculada') json_out(['ok' => false, 'error' => 'Solo se puede ajustar una comisión en estado calculada.'], 409);
        $monto = prev_money($b['monto_calculado'] ?? 0);
        if ($monto <= 0) json_out(['ok' => false, 'error' => 'Indique el monto.'], 422);
        db()->prepare("UPDATE prev_comisiones SET monto_calculado = ?, monto_usd = ?, comentario = ? WHERE id = ?")
            ->execute([$monto, $monto, clean_str($b['comentario'] ?? '', 200) ?: null, $id]);
        audit('prev_comision.actualizar', 'prev_comisiones', $id, ['monto_calculado' => $monto]);
        json_out(['ok' => true]);
    }

    case 'comision_aprobar': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $ids = [];
        if (isset($b['ids']) && is_array($b['ids'])) {
            $ids = array_values(array_filter(array_map('intval', $b['ids'])));
        } elseif (($cid = (int)($b['contrato_id'] ?? 0)) && ($et = prev_enum($b['etapa'] ?? '', PREV_ETAPAS_COMISION))) {
            $st = db()->prepare("SELECT id FROM prev_comisiones WHERE contrato_id = ? AND etapa = ? AND estado = 'calculada'");
            $st->execute([$cid, $et]);
            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        }
        if (!$ids) json_out(['ok' => false, 'error' => 'No hay comisiones calculadas para aprobar.'], 422);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare(
            "UPDATE prev_comisiones SET estado = 'aprobada', aprobado_por = ?, fecha_aprobacion = NOW()
             WHERE estado = 'calculada' AND id IN ($in)"
        );
        $st->execute(array_merge([$u['id']], $ids));
        audit('prev_comision.aprobar', 'prev_comisiones', null, ['aprobadas' => $st->rowCount()]);
        json_out(['ok' => true, 'aprobadas' => $st->rowCount()]);
    }

    case 'comision_pagar': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();

        $fecha = prev_date($b['fecha_pago'] ?? '') ?: date('Y-m-d');
        $tasa  = (float)($b['tasa'] ?? 0) ?: prev_tasa_del_dia($fecha);
        $montoUsd = prev_money($b['monto_usd'] ?? 0);
        $montoBs  = prev_money($b['monto_bs'] ?? 0);

        // Caso 1: pagar una comisión ya generada (aprobada/calculada) por su id.
        if (($id = (int)($b['id'] ?? 0)) > 0) {
            $st = db()->prepare(
                "SELECT k.*, c.numero AS contrato_numero FROM prev_comisiones k
                 JOIN prev_contratos c ON c.id = k.contrato_id WHERE k.id = ?"
            );
            $st->execute([$id]);
            $k = $st->fetch();
            if (!$k) json_out(['ok' => false, 'error' => 'Comisión no encontrada.'], 404);
            if ($k['estado'] === 'pagada') json_out(['ok' => false, 'error' => 'Esta comisión ya está pagada.'], 409);
            if ($montoUsd <= 0 && $montoBs <= 0) $montoUsd = (float)$k['monto_calculado'];
            if ($montoBs <= 0 && $tasa > 0) $montoBs = round($montoUsd * $tasa, 2);
            if ($montoUsd <= 0 && $tasa > 0) $montoUsd = round($montoBs / $tasa, 2);

            // Compensar descuentos pendientes del vendedor (anulaciones previas).
            $comentario = clean_str($b['comentario'] ?? '', 200) ?: null;
            [$netoUsd, $descontado, ] = descuentos_aplicar((int)$k['vendedor_id'], $montoUsd, $id);
            if ($descontado > 0) {
                $montoUsd = $netoUsd;
                if ($tasa > 0) $montoBs = round($montoUsd * $tasa, 2);
                $nota = sprintf('Descuento por anulaciones: -%s USD', number_format($descontado, 2, ',', '.'));
                $comentario = mb_substr(trim(($comentario ? $comentario . ' · ' : '') . $nota), 0, 200);
            }

            db()->prepare(
                "UPDATE prev_comisiones SET estado = 'pagada', monto_usd = ?, monto_bs = ?, tasa = ?,
                        fecha_pago = ?, comentario = COALESCE(?, comentario), registrado_por = ? WHERE id = ?"
            )->execute([$montoUsd, $montoBs, $tasa, $fecha, $comentario, $u['id'], $id]);
            audit('prev_comision.pagar', 'prev_comisiones', $id,
                  ['contrato' => $k['contrato_numero'], 'etapa' => $k['etapa'],
                   'monto_usd' => $montoUsd, 'descuento_usd' => $descontado]);
            json_out(['ok' => true, 'id' => $id, 'descuento_usd' => $descontado, 'monto_usd' => $montoUsd]);
        }

        // Caso 2 (directo): registrar el pago de una etapa sin pasar por el flujo.
        $contratoId = (int)($b['contrato_id'] ?? 0);
        $etapa = prev_enum($b['etapa'] ?? '', PREV_ETAPAS_COMISION);
        if (!$contratoId || !$etapa) json_out(['ok' => false, 'error' => 'Faltan contrato y/o etapa.'], 422);
        $st = db()->prepare("SELECT id, vendedor_id, numero FROM prev_contratos WHERE id = ?");
        $st->execute([$contratoId]);
        $c = $st->fetch();
        if (!$c) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);
        $vendedorId = (int)($b['vendedor_id'] ?? 0) ?: (int)$c['vendedor_id'];
        if (!$vendedorId) json_out(['ok' => false, 'error' => 'El contrato no tiene vendedor asignado.'], 422);
        if ($montoUsd <= 0 && $montoBs <= 0) json_out(['ok' => false, 'error' => 'Indique el monto de la comisión.'], 422);
        if ($montoBs <= 0 && $tasa > 0) $montoBs = round($montoUsd * $tasa, 2);
        if ($montoUsd <= 0 && $tasa > 0) $montoUsd = round($montoBs / $tasa, 2);

        try {
            db()->prepare(
                "INSERT INTO prev_comisiones
                 (contrato_id, vendedor_id, etapa, estado, monto_calculado, monto_bs, tasa, monto_usd,
                  fecha_calculo, fecha_pago, comentario, registrado_por)
                 VALUES (?,?,?,'pagada',?,?,?,?,CURDATE(),?,?,?)"
            )->execute([
                $contratoId, $vendedorId, $etapa, $montoUsd, $montoBs, $tasa, $montoUsd, $fecha,
                clean_str($b['comentario'] ?? '', 200) ?: null, $u['id'],
            ]);
        } catch (\PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                json_out(['ok' => false, 'error' => 'Esa etapa ya fue generada/pagada para este contrato.'], 409);
            }
            throw $e;
        }
        $id = (int)db()->lastInsertId();
        audit('prev_comision.pagar', 'prev_comisiones', $id,
              ['contrato' => $c['numero'], 'etapa' => $etapa, 'monto_usd' => $montoUsd]);
        json_out(['ok' => true, 'id' => $id], 201);
    }

    case 'descuentos': {
        // Descuentos de comisión por anulaciones (?vendedor_id=, ?estado=pendiente|aplicado)
        require_method('GET');
        require_role('admin', 'editor');
        $where = '1=1';
        $params = [];
        if (($vid = (int)($_GET['vendedor_id'] ?? 0)) > 0) { $where .= ' AND d.vendedor_id = ?'; $params[] = $vid; }
        if (($est = prev_enum($_GET['estado'] ?? '', ['pendiente', 'aplicado', 'anulado'])) !== null) {
            $where .= ' AND d.estado = ?'; $params[] = $est;
        }
        try {
            $st = db()->prepare(
                "SELECT d.*, v.nombre AS vendedor_nombre, c.numero AS contrato_numero
                 FROM prev_com_descuentos d
                 JOIN prev_vendedores v ON v.id = d.vendedor_id
                 LEFT JOIN prev_contratos c ON c.id = d.contrato_id
                 WHERE $where ORDER BY d.id DESC LIMIT 200"
            );
            $st->execute($params);
            $items = array_map(fn($r) => [
                'id'              => (int)$r['id'],
                'vendedor_id'     => (int)$r['vendedor_id'],
                'vendedor_nombre' => $r['vendedor_nombre'],
                'contrato_numero' => $r['contrato_numero'],
                'monto_usd'       => (float)$r['monto_usd'],
                'motivo'          => $r['motivo'],
                'estado'          => $r['estado'],
                'created_at'      => $r['created_at'],
                'aplicado_at'     => $r['aplicado_at'],
            ], $st->fetchAll());
            $pend = array_sum(array_map(fn($i) => $i['estado'] === 'pendiente' ? $i['monto_usd'] : 0, $items));
            json_out(['ok' => true, 'items' => $items, 'total_pendiente' => round($pend, 2)]);
        } catch (\Throwable $e) {
            json_out(['ok' => true, 'items' => [], 'total_pendiente' => 0]);  // 09 aún no importado
        }
    }

    case 'comision_anular': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        $st = db()->prepare("SELECT estado FROM prev_comisiones WHERE id = ?");
        $st->execute([$id]);
        $estado = $st->fetchColumn();
        if ($estado === false) json_out(['ok' => false, 'error' => 'Comisión no encontrada.'], 404);
        if ($estado === 'pagada') json_out(['ok' => false, 'error' => 'Una comisión pagada no se anula; use Eliminar (admin).'], 409);
        // Se elimina para liberar la etapa (contrato_id+etapa es único) y poder recalcularla.
        db()->prepare("DELETE FROM prev_comisiones WHERE id = ? AND estado <> 'pagada'")->execute([$id]);
        audit('prev_comision.anular', 'prev_comisiones', $id, ['estado_previo' => $estado]);
        json_out(['ok' => true]);
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
