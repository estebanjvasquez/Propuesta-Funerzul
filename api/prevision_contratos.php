<?php
/**
 * API de Contratos de Previsión:  api/prevision_contratos.php?action=...
 *
 * Contratos:
 *   GET  list            (staff; ?q=, ?estatus=, ?plan_id=, ?vendedor_id=, ?forma_pago=, ?morosos=1, paginado)
 *   GET  get             (?id= | ?numero=; detalle completo)
 *   POST create          (staff; número automático si se omite; puede generar cuotas iniciales)
 *   POST update          (staff)
 *   POST set_estatus     (staff; activo/suspendido/anulado/renuncia — anular liquida cuotas pendientes)
 *
 * Beneficiarios:
 *   GET  parentescos     (catálogo)
 *   POST beneficiario_add / beneficiario_update / beneficiario_estatus / beneficiario_delete(admin)
 *
 * Cuotas y pagos (cobranza):
 *   GET  cuotas          (?contrato_id= | ?vencidas=1 global)
 *   POST cuotas_generar  (staff; n cuotas según la frecuencia del contrato)
 *   POST cuota_update    (staff; solo cuotas sin abonos)
 *   POST cuota_anular    (staff)
 *   GET  pagos           (?contrato_id= | ?desde=&?hasta=)
 *   POST pago_registrar  (staff; aplica en cascada a las cuotas pendientes más antiguas)
 *   POST pago_delete     (admin; revierte el saldo de la cuota)
 *
 * Bitácora:
 *   GET  eventos         (staff; ?id= — auditoría del contrato y sus entidades)
 *
 * Tablero y tasa:
 *   GET  stats           (indicadores del módulo)
 *   GET  tasa            (tasa Bs/USD vigente)
 *   POST tasa_set        (staff; registra la tasa del día en el histórico)
 *   GET  tasa_historial  (staff; histórico de tasas con fecha y usuario)
 *   GET  tasa_preview    (staff; cuántas cuotas en Bs se recalcularían a ?tasa=)
 *   POST tasa_aplicar    (admin; recalcula las cuotas en Bs a la tasa — manual)
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'list';

/** SELECT base de contratos con datos relacionados. */
const CONTRATO_SELECT = "
    SELECT c.*,
           CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente_nombre,
           CONCAT(cl.nacionalidad, '-', cl.cedula) AS cliente_cedula,
           p.nombre AS plan_nombre,
           v.nombre AS vendedor_nombre,
           su.nombre AS sucursal_nombre,
           ru.nombre AS ruta_nombre,
           (SELECT COUNT(*) FROM prev_beneficiarios b WHERE b.contrato_id = c.id AND b.estatus = 'activo') AS beneficiarios,
           (SELECT COUNT(*) FROM prev_cuotas q WHERE q.contrato_id = c.id
              AND q.estado IN ('pendiente','parcial') AND q.fecha_vencimiento < CURDATE()) AS cuotas_vencidas,
           (SELECT COALESCE(SUM(q.saldo),0) FROM prev_cuotas q WHERE q.contrato_id = c.id
              AND q.estado IN ('pendiente','parcial') AND q.fecha_vencimiento < CURDATE()) AS saldo_vencido
    FROM prev_contratos c
    JOIN prev_clientes cl ON cl.id = c.cliente_id
    LEFT JOIN prev_planes p ON p.id = c.plan_id
    LEFT JOIN prev_vendedores v ON v.id = c.vendedor_id
    LEFT JOIN prev_sucursales su ON su.id = c.sucursal_id
    LEFT JOIN prev_rutas ru ON ru.id = c.ruta_id";

/** Carga un contrato (fila cruda) o responde 404. */
function contrato_row(int $id): array
{
    $st = db()->prepare(CONTRATO_SELECT . " WHERE c.id = ?");
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);
    return $r;
}

/** Valida los campos del cuerpo para crear/editar un contrato. */
function contrato_input(array $b): array
{
    $clienteId = (int)($b['cliente_id'] ?? 0);
    if (!$clienteId) json_out(['ok' => false, 'error' => 'Falta el cliente titular.'], 422);
    $st = db()->prepare("SELECT * FROM prev_clientes WHERE id = ? AND deleted_at IS NULL");
    $st->execute([$clienteId]);
    $cliente = $st->fetch();
    if (!$cliente) json_out(['ok' => false, 'error' => 'Cliente no encontrado o dado de baja.'], 422);

    $planId = (int)($b['plan_id'] ?? 0) ?: null;
    $plan = null;
    if ($planId) {
        $st = db()->prepare("SELECT * FROM prev_planes WHERE id = ?");
        $st->execute([$planId]);
        $plan = $st->fetch();
        if (!$plan) json_out(['ok' => false, 'error' => 'Plan no encontrado.'], 422);
    }

    $vendedorId = (int)($b['vendedor_id'] ?? 0) ?: null;
    if ($vendedorId) {
        $st = db()->prepare("SELECT id FROM prev_vendedores WHERE id = ?");
        $st->execute([$vendedorId]);
        if (!$st->fetchColumn()) json_out(['ok' => false, 'error' => 'Vendedor no encontrado.'], 422);
    }

    $sucursalId = (int)($b['sucursal_id'] ?? 0) ?: null;
    if ($sucursalId) {
        $st = db()->prepare("SELECT id FROM prev_sucursales WHERE id = ?");
        $st->execute([$sucursalId]);
        if (!$st->fetchColumn()) json_out(['ok' => false, 'error' => 'Sucursal no encontrada.'], 422);
    }
    $rutaId = (int)($b['ruta_id'] ?? 0) ?: null;
    if ($rutaId) {
        $st = db()->prepare("SELECT id FROM prev_rutas WHERE id = ?");
        $st->execute([$rutaId]);
        if (!$st->fetchColumn()) json_out(['ok' => false, 'error' => 'Ruta no encontrada.'], 422);
    }

    $fechaIngreso = prev_date($b['fecha_ingreso'] ?? '') ?: date('Y-m-d');
    $plazo = max(0, min(60, (int)($b['plazo_espera_meses'] ?? 4)));
    $vigenteDesde = prev_date($b['vigente_desde'] ?? '')
        ?: (new DateTime($fechaIngreso))->modify("+$plazo months")->format('Y-m-d');

    $moneda = isset($b['moneda']) && trim((string)$b['moneda']) !== ''
        ? prev_moneda($b['moneda'])
        : ($plan ? $plan['moneda'] : 'USD');
    $montoCuota = prev_money($b['monto_cuota'] ?? 0);
    if ($montoCuota <= 0 && $plan) $montoCuota = (float)$plan['cuota_mensual'];
    $cuotaInicial = prev_money($b['cuota_inicial'] ?? ($plan ? $plan['cuota_inicial'] : 0));

    // Anclaje a la tasa de cambio para contratos en Bs:
    //   monto_ref_usd = valor de la cuota en USD (referencia estable; el plan SIEMPRE está en USD)
    //   tasa_cambio   = tasa con la que se obtuvo el monto en Bs
    // Regla: si hay referencia en USD, el monto en Bs SIEMPRE se calcula en el
    // servidor (ref × tasa vigente), sin importar lo que envíe el navegador.
    // Así el precio queda uniforme en el tiempo aunque la tasa sea volátil.
    $tasaCambio  = null;
    $montoRefUsd = null;
    if ($moneda === 'BS') {
        $tasaCambio = round((float)str_replace(',', '.', (string)($b['tasa_cambio'] ?? 0)), 4) ?: prev_tasa_del_dia();
        $montoRefUsd = prev_money($b['monto_ref_usd'] ?? 0);
        if ($montoRefUsd <= 0 && $plan && $plan['moneda'] === 'USD') $montoRefUsd = (float)$plan['cuota_mensual'];
        if ($montoRefUsd > 0) {
            if ($tasaCambio <= 0) {
                json_out(['ok' => false, 'error' => 'No hay tasa de cambio registrada. Registre la tasa del día (botón Tasa) para calcular la cuota en bolívares.'], 422);
            }
            $montoCuota = round($montoRefUsd * $tasaCambio, 2);   // siempre recalculado
        } elseif ($tasaCambio > 0 && $montoCuota > 0) {
            // Sin plan en USD: se escribió el monto en Bs y se deriva la referencia.
            $montoRefUsd = round($montoCuota / $tasaCambio, 2);
        }
        // La cuota inicial del plan también está en USD: se convierte si el
        // valor recibido es exactamente el del plan (no fue editado a Bs).
        if ($plan && $plan['moneda'] === 'USD' && $tasaCambio > 0
            && $cuotaInicial > 0 && abs($cuotaInicial - (float)$plan['cuota_inicial']) < 0.005) {
            $cuotaInicial = round($cuotaInicial * $tasaCambio, 2);
        }
        if ($tasaCambio <= 0) $tasaCambio = null;
        if ($montoRefUsd <= 0) $montoRefUsd = null;
    } else {
        $montoRefUsd = $montoCuota > 0 ? $montoCuota : null;   // USD: la referencia es el propio monto
    }

    return [
        'cliente_id'         => $clienteId,
        'plan_id'            => $planId,
        'vendedor_id'        => $vendedorId,
        'sucursal_id'        => $sucursalId,
        'ruta_id'            => $rutaId,
        'origen'             => clean_str($b['origen'] ?? '', 60) ?: null,
        'fecha_solicitud'    => prev_date($b['fecha_solicitud'] ?? ''),
        'fecha_ingreso'      => $fechaIngreso,
        'fecha_corte'        => prev_date($b['fecha_corte'] ?? ''),   // base de comisiones; NULL = ingreso
        'vigente_desde'      => $vigenteDesde,
        'plazo_espera_meses' => $plazo,
        'frecuencia_pago'    => prev_enum($b['frecuencia_pago'] ?? '', PREV_FRECUENCIAS, 'mensual'),
        'forma_pago'         => prev_enum($b['forma_pago'] ?? '', PREV_FORMAS_PAGO_CONTRATO, 'caja'),
        'moneda'             => $moneda,
        'cuota_inicial'      => $cuotaInicial,
        'monto_cuota'        => $montoCuota,
        'monto_ref_usd'      => $montoRefUsd,
        'tasa_cambio'        => $tasaCambio,
        'numero_cuotas'      => max(0, (int)($b['numero_cuotas'] ?? 0)),
        'comision_venta'     => max(0.0, min(100.0, round((float)($b['comision_venta'] ?? 0), 2))),
        'edad_ingreso'       => prev_edad($cliente['fecha_nacimiento'], $fechaIngreso),
        'banco'              => clean_str($b['banco'] ?? '', 100) ?: null,
        'numero_cuenta'      => clean_str($b['numero_cuenta'] ?? '', 24) ?: null,
        'titular_cuenta'     => clean_str($b['titular_cuenta'] ?? '', 100) ?: null,
        'tipo_cuenta'        => prev_enum($b['tipo_cuenta'] ?? '', PREV_TIPOS_CUENTA),
        'comentarios'        => clean_str($b['comentarios'] ?? '', 500) ?: null,
    ];
}

/** Próximo número de contrato (correlativo numérico). */
function proximo_numero(): string
{
    $max = db()->query("SELECT MAX(CAST(numero AS UNSIGNED)) FROM prev_contratos WHERE numero REGEXP '^[0-9]+$'")
               ->fetchColumn();
    return (string)((int)$max + 1 ?: 1);
}

/** Genera $cantidad cuotas para el contrato, avanzando según su frecuencia. */
function generar_cuotas(array $contrato, int $cantidad, string $desde, float $monto, string $tipo = 'programada'): int
{
    $pdo = db();
    $st = $pdo->prepare("SELECT COALESCE(MAX(numero),0) FROM prev_cuotas WHERE contrato_id = ?");
    $st->execute([(int)$contrato['id']]);
    $correlativo = (int)$st->fetchColumn();

    $ins = $pdo->prepare(
        "INSERT INTO prev_cuotas (contrato_id, numero, tipo, fecha_vencimiento, moneda, monto, saldo)
         VALUES (?,?,?,?,?,?,?)"
    );
    $fecha = new DateTime($desde);
    for ($i = 0; $i < $cantidad; $i++) {
        $ins->execute([
            (int)$contrato['id'], ++$correlativo, $tipo,
            $fecha->format('Y-m-d'), $contrato['moneda'], $monto, $monto,
        ]);
        $fecha = prev_avanzar_fecha($fecha, $contrato['frecuencia_pago']);
    }
    return $cantidad;
}

switch ($action) {

    // ==================== CONTRATOS ====================

    case 'list': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = '1=1';
        $params = [];
        if (($q = trim($_GET['q'] ?? '')) !== '') {
            $where .= " AND (c.numero LIKE ? OR cl.cedula LIKE ? OR CONCAT(cl.nombres,' ',cl.apellidos) LIKE ?)";
            $like = "%$q%";
            array_push($params, $like, $like, $like);
        }
        if ($e = prev_enum($_GET['estatus'] ?? '', PREV_ESTATUS_CONTRATO)) { $where .= " AND c.estatus = ?"; $params[] = $e; }
        if (($pid = (int)($_GET['plan_id'] ?? 0)) > 0)     { $where .= " AND c.plan_id = ?";     $params[] = $pid; }
        if (($vid = (int)($_GET['vendedor_id'] ?? 0)) > 0) { $where .= " AND c.vendedor_id = ?"; $params[] = $vid; }
        if (($sid = (int)($_GET['sucursal_id'] ?? 0)) > 0) { $where .= " AND c.sucursal_id = ?"; $params[] = $sid; }
        if (($rid = (int)($_GET['ruta_id'] ?? 0)) > 0)     { $where .= " AND c.ruta_id = ?";     $params[] = $rid; }
        if ($fp = prev_enum($_GET['forma_pago'] ?? '', PREV_FORMAS_PAGO_CONTRATO)) { $where .= " AND c.forma_pago = ?"; $params[] = $fp; }

        if (($_GET['morosos'] ?? '') === '1') {
            $where .= " AND EXISTS (SELECT 1 FROM prev_cuotas q2 WHERE q2.contrato_id = c.id
                        AND q2.estado IN ('pendiente','parcial') AND q2.fecha_vencimiento < CURDATE())";
        }
        $limit  = min(max((int)($_GET['limit'] ?? 50), 1), 200);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $countSt = db()->prepare(
            "SELECT COUNT(*) FROM prev_contratos c JOIN prev_clientes cl ON cl.id = c.cliente_id WHERE $where"
        );
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $st = db()->prepare(
            CONTRATO_SELECT . " WHERE $where
             ORDER BY c.fecha_ingreso DESC, c.id DESC LIMIT $limit OFFSET $offset"
        );
        $st->execute($params);
        json_out(['ok' => true, 'total' => $total, 'limit' => $limit, 'offset' => $offset,
                  'items' => array_map('prev_contrato_out', $st->fetchAll())]);
    }

    case 'get': {
        require_method('GET');
        require_role('admin', 'editor');
        if (($id = (int)($_GET['id'] ?? 0)) > 0) {
            $r = contrato_row($id);
        } elseif (($num = trim($_GET['numero'] ?? '')) !== '') {
            $st = db()->prepare(CONTRATO_SELECT . " WHERE c.numero = ?");
            $st->execute([$num]);
            $r = $st->fetch();
            if (!$r) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);
        } else {
            json_out(['ok' => false, 'error' => 'Falta id o número.'], 422);
        }
        $id = (int)$r['id'];

        $cli = db()->prepare("SELECT * FROM prev_clientes WHERE id = ?");
        $cli->execute([(int)$r['cliente_id']]);

        $ben = db()->prepare(
            "SELECT b.*, pa.nombre AS parentesco FROM prev_beneficiarios b
             JOIN prev_parentescos pa ON pa.id = b.parentesco_id
             WHERE b.contrato_id = ? ORDER BY b.estatus = 'activo' DESC, pa.id ASC, b.id ASC"
        );
        $ben->execute([$id]);

        $cuo = db()->prepare(
            "SELECT q.*, NULL AS contrato_numero FROM prev_cuotas q
             WHERE q.contrato_id = ? ORDER BY q.numero ASC"
        );
        $cuo->execute([$id]);

        $pag = db()->prepare(
            "SELECT g.*, q.numero AS cuota_numero, NULL AS contrato_numero FROM prev_pagos g
             LEFT JOIN prev_cuotas q ON q.id = g.cuota_id
             WHERE g.contrato_id = ? ORDER BY g.fecha DESC, g.id DESC"
        );
        $pag->execute([$id]);

        $com = db()->prepare(
            "SELECT k.*, v.nombre AS vendedor_nombre, NULL AS contrato_numero FROM prev_comisiones k
             JOIN prev_vendedores v ON v.id = k.vendedor_id
             WHERE k.contrato_id = ? ORDER BY k.fecha_pago ASC"
        );
        $com->execute([$id]);

        $res = db()->prepare(
            "SELECT COALESCE(SUM(monto - saldo),0) AS cobrado,
                    COALESCE(SUM(CASE WHEN estado IN ('pendiente','parcial') THEN saldo ELSE 0 END),0) AS por_cobrar
             FROM prev_cuotas WHERE contrato_id = ? AND estado <> 'anulada'"
        );
        $res->execute([$id]);
        $totales = $res->fetch();

        // Servicios adicionales, gestiones de cobranza y siniestros (v2)
        $srv = db()->prepare(
            "SELECT cs.*, s.nombre FROM prev_contrato_servicios cs
             JOIN prev_servicios s ON s.id = cs.servicio_id
             WHERE cs.contrato_id = ? ORDER BY cs.activo DESC, cs.id ASC"
        );
        $srv->execute([$id]);
        $servicios = array_map(fn($x) => [
            'id'         => (int)$x['id'],
            'servicio_id' => (int)$x['servicio_id'],
            'nombre'     => $x['nombre'],
            'moneda'     => $x['moneda'],
            'precio'     => (float)$x['precio'],
            'recurrente' => (bool)$x['recurrente'],
            'fecha'      => $x['fecha'],
            'notas'      => $x['notas'],
            'activo'     => (bool)$x['activo'],
        ], $srv->fetchAll());

        $ges = db()->prepare(
            "SELECT g.*, NULL AS contrato_numero, NULL AS cliente_nombre, u.email AS usuario
             FROM prev_gestiones g LEFT JOIN users u ON u.id = g.usuario_id
             WHERE g.contrato_id = ? ORDER BY g.fecha DESC LIMIT 5"
        );
        $ges->execute([$id]);

        $sin = db()->prepare(
            "SELECT id, nombre_fallecido, fecha_defuncion, estado, cobertura FROM prev_siniestros
             WHERE contrato_id = ? ORDER BY id DESC"
        );
        $sin->execute([$id]);

        // ¿Ya se envió la bienvenida? (tolerante: las tablas de mensajería son del 06)
        $bienvenida = null;
        try {
            $bw = db()->prepare(
                "SELECT COUNT(*) FROM prev_msg_envios e
                 JOIN prev_msg_plantillas p ON p.id = e.plantilla_id
                 WHERE e.contrato_id = ? AND p.clave = 'bienvenida' AND e.estado <> 'fallido'"
            );
            $bw->execute([$id]);
            $bienvenida = (int)$bw->fetchColumn() > 0;
        } catch (\Throwable $e) { /* mensajería aún no instalada */ }

        json_out([
            'ok'            => true,
            'item'          => prev_contrato_out($r),
            'cliente'       => ($c = $cli->fetch()) ? prev_cliente_out($c) : null,
            'beneficiarios' => array_map('prev_beneficiario_out', $ben->fetchAll()),
            'cuotas'        => array_map('prev_cuota_out', $cuo->fetchAll()),
            'pagos'         => array_map('prev_pago_out', $pag->fetchAll()),
            'comisiones'    => array_map('prev_comision_out', $com->fetchAll()),
            'servicios'     => $servicios,
            'gestiones'     => array_map('prev_gestion_out', $ges->fetchAll()),
            'siniestros'    => array_map(fn($x) => [
                'id' => (int)$x['id'], 'nombre_fallecido' => $x['nombre_fallecido'],
                'fecha_defuncion' => $x['fecha_defuncion'], 'estado' => $x['estado'],
                'cobertura' => $x['cobertura'],
            ], $sin->fetchAll()),
            'totales'       => [
                'cobrado'    => (float)$totales['cobrado'],
                'por_cobrar' => (float)$totales['por_cobrar'],
            ],
            'bienvenida_enviada' => $bienvenida,
        ]);
    }

    case 'create': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $in = contrato_input($b);

        $numero = clean_str($b['numero'] ?? '', 20) ?: proximo_numero();
        $st = db()->prepare("SELECT id FROM prev_contratos WHERE numero = ?");
        $st->execute([$numero]);
        if ($st->fetchColumn()) json_out(['ok' => false, 'error' => "Ya existe el contrato número $numero."], 409);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $cols = array_keys($in);
            $sql = "INSERT INTO prev_contratos (numero, " . implode(',', $cols) . ", estatus, fecha_estatus, created_by, updated_by)
                    VALUES (?, " . rtrim(str_repeat('?,', count($cols)), ',') . ", 'activo', ?, ?, ?)";
            $st = $pdo->prepare($sql);
            $st->execute([$numero, ...array_values($in), $in['fecha_ingreso'], $u['id'], $u['id']]);
            $id = (int)$pdo->lastInsertId();

            // Titular como primer beneficiario (parentesco 1 = TITULAR)
            $cli = $pdo->prepare("SELECT * FROM prev_clientes WHERE id = ?");
            $cli->execute([$in['cliente_id']]);
            $cliente = $cli->fetch();
            $pdo->prepare(
                "INSERT INTO prev_beneficiarios
                 (contrato_id, parentesco_id, nacionalidad, cedula, nombres, apellidos,
                  fecha_nacimiento, sexo, fecha_inclusion)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([
                $id, 1, $cliente['nacionalidad'], $cliente['cedula'], $cliente['nombres'],
                $cliente['apellidos'], $cliente['fecha_nacimiento'], $cliente['sexo'], $in['fecha_ingreso'],
            ]);

            // Cuotas iniciales opcionales
            $contrato = ['id' => $id, 'moneda' => $in['moneda'], 'frecuencia_pago' => $in['frecuencia_pago']];
            if ($in['cuota_inicial'] > 0) {
                generar_cuotas($contrato, 1, $in['fecha_ingreso'], $in['cuota_inicial'], 'inicial');
            }
            $genCuotas = max(0, min(120, (int)($b['generar_cuotas'] ?? 0)));
            if ($genCuotas > 0 && $in['monto_cuota'] > 0) {
                $desde = prev_date($b['primera_cuota'] ?? '')
                    ?: prev_avanzar_fecha(new DateTime($in['fecha_ingreso']), $in['frecuencia_pago'])->format('Y-m-d');
                generar_cuotas($contrato, $genCuotas, $desde, $in['monto_cuota']);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        audit('prev_contrato.create', 'prev_contratos', $id, ['numero' => $numero]);
        json_out(['ok' => true, 'id' => $id, 'numero' => $numero], 201);
    }

    case 'update': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $old = contrato_row($id);
        $in = contrato_input($b);

        $numero = clean_str($b['numero'] ?? '', 20);
        if ($numero !== '') {
            $st = db()->prepare("SELECT id FROM prev_contratos WHERE numero = ? AND id <> ?");
            $st->execute([$numero, $id]);
            if ($st->fetchColumn()) json_out(['ok' => false, 'error' => "Ya existe otro contrato número $numero."], 409);
        }

        $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($in)));
        $sql = "UPDATE prev_contratos SET $sets" . ($numero !== '' ? ", numero = ?" : "") . ", updated_by = ? WHERE id = ?";
        $params = array_values($in);
        if ($numero !== '') $params[] = $numero;
        array_push($params, $u['id'], $id);
        db()->prepare($sql)->execute($params);

        // Sincronización de cuotas programadas pendientes SIN abonos (saldo = monto):
        //   · Re-anclaje del monto en Bs a la tasa vigente (misma moneda): automático.
        //   · Cambio de moneda del contrato (Bs↔USD): solo si el usuario lo confirma
        //     (recalcular_cuotas), para no reescribir montos sin querer. Reescribe la
        //     moneda Y el monto de esas cuotas al nuevo valor del contrato, de modo que
        //     el cliente deje de "deber bolívares etiquetados como dólares".
        // Las cuotas ya cobradas, parciales o anuladas conservan su moneda/monto
        // original (registro histórico); por eso los reportes agrupan por la moneda
        // de la cuota, no la del contrato.
        $cuotasSync   = 0;
        $monedaCambio = $old['moneda'] !== $in['moneda'];
        $montoCambio  = round((float)$old['monto_cuota'], 2) !== round((float)$in['monto_cuota'], 2);
        $recalcular   = $in['monto_cuota'] > 0 && (
            (!$monedaCambio && $in['moneda'] === 'BS' && $montoCambio)          // re-anclaje Bs automático
            || (!empty($b['recalcular_cuotas']) && ($monedaCambio || $montoCambio))
        );
        if ($recalcular) {
            $st = db()->prepare(
                "UPDATE prev_cuotas SET moneda = ?, monto = ?, saldo = ?
                 WHERE contrato_id = ? AND tipo = 'programada'
                   AND estado = 'pendiente' AND saldo = monto"
            );
            $st->execute([$in['moneda'], $in['monto_cuota'], $in['monto_cuota'], $id]);
            $cuotasSync = $st->rowCount();
        }
        audit('prev_contrato.update', 'prev_contratos', $id,
              $cuotasSync ? ['cuotas_sincronizadas' => $cuotasSync, 'moneda' => $in['moneda'],
                             'monto' => $in['monto_cuota'], 'cambio_moneda' => $monedaCambio] : []);
        json_out(['ok' => true, 'monto_cuota' => $in['monto_cuota'], 'moneda' => $in['moneda'],
                  'cambio_moneda' => $monedaCambio, 'cuotas_actualizadas' => $cuotasSync]);
    }

    case 'set_estatus': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $estatus = prev_enum($b['estatus'] ?? '', PREV_ESTATUS_CONTRATO);
        if (!$id || !$estatus) json_out(['ok' => false, 'error' => 'Faltan id y/o estatus.'], 422);
        $r = contrato_row($id);
        $fecha  = prev_date($b['fecha'] ?? '') ?: date('Y-m-d');
        $motivo = clean_str($b['motivo'] ?? '', 255) ?: null;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE prev_contratos SET estatus=?, fecha_estatus=?, motivo_estatus=?, updated_by=? WHERE id=?")
                ->execute([$estatus, $fecha, $motivo, $u['id'], $id]);
            // Al anular o renunciar se anulan las cuotas que quedaban por cobrar.
            $descuentos = 0;
            if (in_array($estatus, ['anulado', 'renuncia'], true)) {
                $pdo->prepare("UPDATE prev_cuotas SET estado='anulada' WHERE contrato_id=? AND estado IN ('pendiente','parcial')")
                    ->execute([$id]);

                // Comisiones ya pagadas del contrato -> descuento pendiente al
                // vendedor, a compensar en su próximo pago (como en la app KM).
                try {
                    $st = $pdo->prepare(
                        "SELECT id, vendedor_id, monto_usd, etapa FROM prev_comisiones
                         WHERE contrato_id = ? AND estado = 'pagada' AND monto_usd > 0"
                    );
                    $st->execute([$id]);
                    $insDes = $pdo->prepare(
                        "INSERT INTO prev_com_descuentos (vendedor_id, contrato_id, comision_id, monto_usd, motivo, registrado_por)
                         VALUES (?,?,?,?,?,?)"
                    );
                    foreach ($st->fetchAll() as $k) {
                        $insDes->execute([
                            (int)$k['vendedor_id'], $id, (int)$k['id'], (float)$k['monto_usd'],
                            ucfirst($estatus) . " contrato #{$r['numero']} (etapa {$k['etapa']})", $u['id'],
                        ]);
                        $descuentos++;
                    }
                } catch (\Throwable $e) { /* prev_com_descuentos aún no instalada (09) */ }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        audit('prev_contrato.set_estatus', 'prev_contratos', $id,
              ['numero' => $r['numero'], 'de' => $r['estatus'], 'a' => $estatus, 'motivo' => $motivo,
               'descuentos_comision' => $descuentos]);
        json_out(['ok' => true, 'descuentos_comision' => $descuentos]);
    }

    // ==================== BENEFICIARIOS ====================

    case 'parentescos': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->query("SELECT * FROM prev_parentescos WHERE activo = 1 ORDER BY id ASC");
        json_out(['ok' => true, 'items' => array_map(fn($r) => [
            'id'                 => (int)$r['id'],
            'nombre'             => $r['nombre'],
            'familiar_directo'   => (bool)$r['familiar_directo'],
            'edad_min'           => (int)$r['edad_min'],
            'edad_max'           => (int)$r['edad_max'],
            'permite_sin_cedula' => (bool)$r['permite_sin_cedula'],
        ], $st->fetchAll())]);
    }

    case 'beneficiario_add': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $contratoId = (int)($b['contrato_id'] ?? 0);
        $parentescoId = (int)($b['parentesco_id'] ?? 0);
        $nombres = clean_str($b['nombres'] ?? '', 100);
        if (!$contratoId || !$parentescoId || $nombres === '') {
            json_out(['ok' => false, 'error' => 'Contrato, parentesco y nombres son obligatorios.'], 422);
        }
        contrato_row($contratoId);
        $st = db()->prepare("SELECT * FROM prev_parentescos WHERE id = ? AND activo = 1");
        $st->execute([$parentescoId]);
        $pa = $st->fetch();
        if (!$pa) json_out(['ok' => false, 'error' => 'Parentesco no válido.'], 422);

        $cedula = prev_cedula($b['cedula'] ?? '') ?: null;
        if (!$cedula && !$pa['permite_sin_cedula']) {
            json_out(['ok' => false, 'error' => 'Este parentesco requiere cédula.'], 422);
        }
        $fnac = prev_date($b['fecha_nacimiento'] ?? '');
        $edad = prev_edad($fnac);
        if ($edad !== null && ($edad < (int)$pa['edad_min'] || $edad > (int)$pa['edad_max'])) {
            json_out(['ok' => false, 'error' =>
                "La edad ($edad) está fuera del rango permitido para {$pa['nombre']} ({$pa['edad_min']}-{$pa['edad_max']})."], 422);
        }
        $sexo = strtoupper(trim((string)($b['sexo'] ?? '')));

        $ins = db()->prepare(
            "INSERT INTO prev_beneficiarios
             (contrato_id, parentesco_id, nacionalidad, cedula, nombres, apellidos, fecha_nacimiento,
              sexo, estado_civil, cuota_adicional, plazo_espera_meses, fecha_inclusion, comentarios)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $ins->execute([
            $contratoId, $parentescoId, prev_nacionalidad($b['nacionalidad'] ?? 'V'), $cedula,
            $nombres, clean_str($b['apellidos'] ?? '', 100), $fnac,
            in_array($sexo, ['M', 'F'], true) ? $sexo : null,
            clean_str($b['estado_civil'] ?? '', 30) ?: null,
            prev_money($b['cuota_adicional'] ?? 0),
            isset($b['plazo_espera_meses']) && $b['plazo_espera_meses'] !== '' ? max(0, min(60, (int)$b['plazo_espera_meses'])) : null,
            prev_date($b['fecha_inclusion'] ?? '') ?: date('Y-m-d'),
            clean_str($b['comentarios'] ?? '', 500) ?: null,
        ]);
        $id = (int)db()->lastInsertId();
        audit('prev_beneficiario.add', 'prev_beneficiarios', $id, ['contrato_id' => $contratoId, 'nombres' => $nombres]);
        json_out(['ok' => true, 'id' => $id], 201);
    }

    case 'beneficiario_update': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $st = db()->prepare("SELECT * FROM prev_beneficiarios WHERE id = ?");
        $st->execute([$id]);
        if (!$st->fetch()) json_out(['ok' => false, 'error' => 'Beneficiario no encontrado.'], 404);

        $parentescoId = (int)($b['parentesco_id'] ?? 0);
        $nombres = clean_str($b['nombres'] ?? '', 100);
        if (!$parentescoId || $nombres === '') {
            json_out(['ok' => false, 'error' => 'Parentesco y nombres son obligatorios.'], 422);
        }
        $sexo = strtoupper(trim((string)($b['sexo'] ?? '')));
        db()->prepare(
            "UPDATE prev_beneficiarios SET
               parentesco_id=?, nacionalidad=?, cedula=?, nombres=?, apellidos=?, fecha_nacimiento=?,
               sexo=?, estado_civil=?, cuota_adicional=?, plazo_espera_meses=?, comentarios=?
             WHERE id=?"
        )->execute([
            $parentescoId, prev_nacionalidad($b['nacionalidad'] ?? 'V'),
            prev_cedula($b['cedula'] ?? '') ?: null, $nombres, clean_str($b['apellidos'] ?? '', 100),
            prev_date($b['fecha_nacimiento'] ?? ''),
            in_array($sexo, ['M', 'F'], true) ? $sexo : null,
            clean_str($b['estado_civil'] ?? '', 30) ?: null,
            prev_money($b['cuota_adicional'] ?? 0),
            isset($b['plazo_espera_meses']) && $b['plazo_espera_meses'] !== '' ? max(0, min(60, (int)$b['plazo_espera_meses'])) : null,
            clean_str($b['comentarios'] ?? '', 500) ?: null, $id,
        ]);
        audit('prev_beneficiario.update', 'prev_beneficiarios', $id);
        json_out(['ok' => true]);
    }

    case 'beneficiario_estatus': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $estatus = prev_enum($b['estatus'] ?? '', PREV_ESTATUS_BENEFICIARIO);
        if (!$id || !$estatus) json_out(['ok' => false, 'error' => 'Faltan id y/o estatus.'], 422);
        $fecha = prev_date($b['fecha'] ?? '') ?: date('Y-m-d');

        $campoFecha = ['suspendido' => 'fecha_suspension', 'excluido' => 'fecha_exclusion', 'fallecido' => 'fecha_defuncion'][$estatus] ?? null;
        $sql = "UPDATE prev_beneficiarios SET estatus = ?" . ($campoFecha ? ", $campoFecha = ?" : "") . " WHERE id = ?";
        $params = $campoFecha ? [$estatus, $fecha, $id] : [$estatus, $id];
        db()->prepare($sql)->execute($params);
        audit('prev_beneficiario.estatus', 'prev_beneficiarios', $id, ['estatus' => $estatus, 'fecha' => $fecha]);
        json_out(['ok' => true]);
    }

    case 'beneficiario_delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("DELETE FROM prev_beneficiarios WHERE id = ?")->execute([$id]);
        audit('prev_beneficiario.delete', 'prev_beneficiarios', $id);
        json_out(['ok' => true]);
    }

    // ==================== CUOTAS ====================

    case 'cuotas': {
        require_method('GET');
        require_role('admin', 'editor');
        if (($cid = (int)($_GET['contrato_id'] ?? 0)) > 0) {
            $st = db()->prepare(
                "SELECT q.*, NULL AS contrato_numero FROM prev_cuotas q
                 WHERE q.contrato_id = ? ORDER BY q.numero ASC"
            );
            $st->execute([$cid]);
        } else {
            // Cuotas vencidas de todos los contratos (vista de cobranza)
            $st = db()->prepare(
                "SELECT q.*, c.numero AS contrato_numero FROM prev_cuotas q
                 JOIN prev_contratos c ON c.id = q.contrato_id
                 WHERE q.estado IN ('pendiente','parcial') AND q.fecha_vencimiento < CURDATE()
                 ORDER BY q.fecha_vencimiento ASC LIMIT 500"
            );
            $st->execute();
        }
        json_out(['ok' => true, 'items' => array_map('prev_cuota_out', $st->fetchAll())]);
    }

    case 'cuotas_generar': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['contrato_id'] ?? 0);
        $cantidad = max(1, min(120, (int)($b['cantidad'] ?? 0)));
        if (!$id || !$cantidad) json_out(['ok' => false, 'error' => 'Faltan contrato y cantidad.'], 422);
        $r = contrato_row($id);
        if ($r['estatus'] !== 'activo') json_out(['ok' => false, 'error' => 'El contrato no está activo.'], 409);

        $monto = prev_money($b['monto'] ?? 0) ?: (float)$r['monto_cuota'];
        if ($monto <= 0) json_out(['ok' => false, 'error' => 'Indique el monto de la cuota.'], 422);

        // Por defecto continúa después de la última cuota generada.
        $desde = prev_date($b['desde'] ?? '');
        if (!$desde) {
            $st = db()->prepare("SELECT MAX(fecha_vencimiento) FROM prev_cuotas WHERE contrato_id = ? AND estado <> 'anulada'");
            $st->execute([$id]);
            $ultima = $st->fetchColumn();
            $desde = $ultima
                ? prev_avanzar_fecha(new DateTime($ultima), $r['frecuencia_pago'])->format('Y-m-d')
                : $r['fecha_ingreso'];
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $n = generar_cuotas($r, $cantidad, $desde, $monto);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        audit('prev_cuotas.generar', 'prev_contratos', $id,
              ['numero' => $r['numero'], 'cantidad' => $n, 'desde' => $desde, 'monto' => $monto]);
        json_out(['ok' => true, 'generadas' => $n, 'desde' => $desde]);
    }

    case 'cuota_update': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $st = db()->prepare("SELECT * FROM prev_cuotas WHERE id = ?");
        $st->execute([$id]);
        $q = $st->fetch();
        if (!$q) json_out(['ok' => false, 'error' => 'Cuota no encontrada.'], 404);
        if ((float)$q['saldo'] < (float)$q['monto'] || $q['estado'] === 'cobrada') {
            json_out(['ok' => false, 'error' => 'La cuota ya tiene abonos; no se puede modificar.'], 409);
        }
        $monto = prev_money($b['monto'] ?? 0) ?: (float)$q['monto'];
        $fecha = prev_date($b['fecha_vencimiento'] ?? '') ?: $q['fecha_vencimiento'];
        db()->prepare("UPDATE prev_cuotas SET monto=?, saldo=?, fecha_vencimiento=?, estado='pendiente' WHERE id=?")
            ->execute([$monto, $monto, $fecha, $id]);
        audit('prev_cuota.update', 'prev_cuotas', $id, ['monto' => $monto, 'vence' => $fecha]);
        json_out(['ok' => true]);
    }

    case 'cuota_anular': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("UPDATE prev_cuotas SET estado='anulada' WHERE id=? AND estado IN ('pendiente','parcial')")
            ->execute([$id]);
        audit('prev_cuota.anular', 'prev_cuotas', $id);
        json_out(['ok' => true]);
    }

    // ==================== PAGOS ====================

    case 'pagos': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = '1=1';
        $params = [];
        if (($cid = (int)($_GET['contrato_id'] ?? 0)) > 0) { $where .= " AND g.contrato_id = ?"; $params[] = $cid; }
        if ($d = prev_date($_GET['desde'] ?? '')) { $where .= " AND g.fecha >= ?"; $params[] = $d; }
        if ($h = prev_date($_GET['hasta'] ?? '')) { $where .= " AND g.fecha <= ?"; $params[] = $h; }
        $st = db()->prepare(
            "SELECT g.*, q.numero AS cuota_numero, c.numero AS contrato_numero
             FROM prev_pagos g
             JOIN prev_contratos c ON c.id = g.contrato_id
             LEFT JOIN prev_cuotas q ON q.id = g.cuota_id
             WHERE $where ORDER BY g.fecha DESC, g.id DESC LIMIT 500"
        );
        $st->execute($params);
        json_out(['ok' => true, 'items' => array_map('prev_pago_out', $st->fetchAll())]);
    }

    case 'pago_registrar': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['contrato_id'] ?? 0);
        $monto = prev_money($b['monto'] ?? 0);
        if (!$id || $monto <= 0) json_out(['ok' => false, 'error' => 'Faltan contrato y/o monto.'], 422);
        $r = contrato_row($id);

        $fecha      = prev_date($b['fecha'] ?? '') ?: date('Y-m-d');
        $monedaPago = prev_moneda($b['moneda'] ?? $r['moneda']);
        $tasa       = (float)($b['tasa'] ?? 0) ?: prev_tasa_del_dia($fecha);
        $formaPago  = prev_enum($b['forma_pago'] ?? '', PREV_FORMAS_PAGO_PAGO, 'efectivo');
        $referencia = clean_str($b['referencia'] ?? '', 60) ?: null;
        $banco      = clean_str($b['banco'] ?? '', 100) ?: null;
        $recibo     = clean_str($b['recibo'] ?? '', 20) ?: null;
        $obs        = clean_str($b['observaciones'] ?? '', 255) ?: null;

        // Convierte el pago a la moneda del contrato para aplicarlo a las cuotas.
        $aplicable = $monto;
        $notaConversion = null;
        if ($monedaPago !== $r['moneda']) {
            if ($tasa <= 0) json_out(['ok' => false, 'error' => 'Registre la tasa del día para convertir la moneda del pago.'], 422);
            $aplicable = $monedaPago === 'BS' ? round($monto / $tasa, 2) : round($monto * $tasa, 2);
            $notaConversion = sprintf('Pago %s %s @ tasa %s', $monedaPago, number_format($monto, 2, ',', '.'),
                                      number_format($tasa, 2, ',', '.'));
        }

        // Cuotas a las que se aplicará: una específica o las pendientes más antiguas.
        $cuotaEspecifica = (int)($b['cuota_id'] ?? 0);
        if ($cuotaEspecifica) {
            $st = db()->prepare(
                "SELECT * FROM prev_cuotas WHERE id = ? AND contrato_id = ? AND estado IN ('pendiente','parcial')"
            );
            $st->execute([$cuotaEspecifica, $id]);
            $cuotas = $st->fetchAll();
            if (!$cuotas) json_out(['ok' => false, 'error' => 'La cuota indicada no está pendiente.'], 422);
        } else {
            $st = db()->prepare(
                "SELECT * FROM prev_cuotas WHERE contrato_id = ? AND estado IN ('pendiente','parcial')
                 ORDER BY fecha_vencimiento ASC, numero ASC"
            );
            $st->execute([$id]);
            $cuotas = $st->fetchAll();
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $insPago = $pdo->prepare(
                "INSERT INTO prev_pagos
                 (contrato_id, cuota_id, fecha, recibo, moneda, monto, tasa, forma_pago,
                  referencia, banco, observaciones, registrado_por)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $updCuota = $pdo->prepare(
                "UPDATE prev_cuotas SET saldo = ?, estado = ?, fecha_cobro = ? WHERE id = ?"
            );

            $restante = $aplicable;
            $aplicadas = [];
            foreach ($cuotas as $q) {
                if ($restante <= 0) break;
                $abono = min($restante, (float)$q['saldo']);
                $nuevoSaldo = round((float)$q['saldo'] - $abono, 2);
                $cobrada = $nuevoSaldo <= 0.009;
                $updCuota->execute([
                    $cobrada ? 0 : $nuevoSaldo,
                    $cobrada ? 'cobrada' : 'parcial',
                    $cobrada ? $fecha : null,
                    (int)$q['id'],
                ]);
                $insPago->execute([
                    $id, (int)$q['id'], $fecha, $recibo, $r['moneda'], $abono, $tasa, $formaPago,
                    $referencia, $banco, trim(($obs ? $obs . ' ' : '') . ($notaConversion ?? '')) ?: null, $u['id'],
                ]);
                $aplicadas[] = ['cuota' => (int)$q['numero'], 'abono' => $abono, 'cobrada' => $cobrada];
                $restante = round($restante - $abono, 2);
            }
            // Excedente sin cuota: se registra como abono a favor del contrato.
            if ($restante > 0.009) {
                $insPago->execute([
                    $id, null, $fecha, $recibo, $r['moneda'], $restante, $tasa, $formaPago,
                    $referencia, $banco,
                    trim(($obs ? $obs . ' ' : '') . 'Abono a favor (sin cuota pendiente). ' . ($notaConversion ?? '')),
                    $u['id'],
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        audit('prev_pago.registrar', 'prev_contratos', $id,
              ['numero' => $r['numero'], 'monto' => $monto, 'moneda' => $monedaPago, 'aplicadas' => $aplicadas]);
        json_out(['ok' => true, 'aplicadas' => $aplicadas, 'sin_aplicar' => $restante > 0.009 ? $restante : 0]);
    }

    case 'pago_delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $st = db()->prepare("SELECT * FROM prev_pagos WHERE id = ?");
        $st->execute([$id]);
        $g = $st->fetch();
        if (!$g) json_out(['ok' => false, 'error' => 'Pago no encontrado.'], 404);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($g['cuota_id']) {
                $qs = $pdo->prepare("SELECT * FROM prev_cuotas WHERE id = ?");
                $qs->execute([(int)$g['cuota_id']]);
                if ($q = $qs->fetch()) {
                    $nuevoSaldo = min((float)$q['monto'], round((float)$q['saldo'] + (float)$g['monto'], 2));
                    $estado = $nuevoSaldo >= (float)$q['monto'] ? 'pendiente' : 'parcial';
                    $pdo->prepare("UPDATE prev_cuotas SET saldo=?, estado=?, fecha_cobro=NULL WHERE id=?")
                        ->execute([$nuevoSaldo, $estado, (int)$q['id']]);
                }
            }
            $pdo->prepare("DELETE FROM prev_pagos WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        audit('prev_pago.delete', 'prev_pagos', $id, ['contrato_id' => (int)$g['contrato_id'], 'monto' => (float)$g['monto']]);
        json_out(['ok' => true]);
    }

    // ==================== TABLERO Y TASA ====================

    case 'stats': {
        require_method('GET');
        require_role('admin', 'editor');
        $pdo = db();
        $contratos = $pdo->query(
            "SELECT COUNT(*) AS total,
                    SUM(estatus='activo') AS activos,
                    SUM(estatus='suspendido') AS suspendidos,
                    SUM(estatus='anulado' OR estatus='renuncia') AS anulados
             FROM prev_contratos"
        )->fetch();
        $clientes = (int)$pdo->query("SELECT COUNT(*) FROM prev_clientes WHERE deleted_at IS NULL")->fetchColumn();
        $beneficiarios = (int)$pdo->query("SELECT COUNT(*) FROM prev_beneficiarios WHERE estatus='activo'")->fetchColumn();
        $vencidas = $pdo->query(
            "SELECT COUNT(*) AS cuotas, COALESCE(SUM(saldo),0) AS monto
             FROM prev_cuotas WHERE estado IN ('pendiente','parcial') AND fecha_vencimiento < CURDATE()"
        )->fetch();
        $mes = date('Y-m-01');
        $cobradoSt = $pdo->prepare(
            "SELECT moneda, COALESCE(SUM(monto),0) AS total FROM prev_pagos WHERE fecha >= ? GROUP BY moneda"
        );
        $cobradoSt->execute([$mes]);
        $cobrado = ['BS' => 0.0, 'USD' => 0.0];
        foreach ($cobradoSt->fetchAll() as $row) { $cobrado[$row['moneda']] = (float)$row['total']; }
        $comisionesSt = $pdo->prepare("SELECT COALESCE(SUM(monto_usd),0) FROM prev_comisiones WHERE fecha_pago >= ?");
        $comisionesSt->execute([$mes]);

        // Siniestros abiertos (0 si aún no se importó database/05_prevision_v2.sql)
        $siniestrosAbiertos = 0;
        try {
            $siniestrosAbiertos = (int)$pdo->query(
                "SELECT COUNT(*) FROM prev_siniestros WHERE estado IN ('abierto','liquidado')"
            )->fetchColumn();
        } catch (\Throwable $e) { /* módulo v2 sin instalar */ }

        json_out(['ok' => true, 'stats' => [
            'siniestros_abiertos'  => $siniestrosAbiertos,
            'contratos_total'      => (int)$contratos['total'],
            'contratos_activos'    => (int)$contratos['activos'],
            'contratos_suspendidos' => (int)$contratos['suspendidos'],
            'contratos_anulados'   => (int)$contratos['anulados'],
            'clientes'             => $clientes,
            'beneficiarios'        => $beneficiarios,
            'cuotas_vencidas'      => (int)$vencidas['cuotas'],
            'monto_vencido'        => (float)$vencidas['monto'],
            'cobrado_mes_bs'       => $cobrado['BS'],
            'cobrado_mes_usd'      => $cobrado['USD'],
            'comisiones_mes_usd'   => (float)$comisionesSt->fetchColumn(),
            'tasa_dia'             => prev_tasa_del_dia(),
        ]]);
    }

    case 'eventos': {
        // Bitácora del contrato (equivale a la pestaña "Eventos" de SIEMPRE):
        // entradas de audit_log del propio contrato + las de entidades hijas
        // que guardan contrato_id en los detalles (beneficiarios, adjuntos...).
        require_method('GET');
        require_role('admin', 'editor');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $st = db()->prepare(
            "SELECT action, details, actor_email, created_at FROM audit_log
             WHERE (entity_type = 'prev_contratos' AND entity_id = ?)
                OR (details LIKE ? OR details LIKE ?)
             ORDER BY id DESC LIMIT 100"
        );
        $st->execute([(string)$id, '%"contrato_id":' . $id . ',%', '%"contrato_id":' . $id . '}%']);
        $items = array_map(function ($r) {
            $det = $r['details'] ? json_decode($r['details'], true) : null;
            $resumen = '';
            if (is_array($det)) {
                $partes = [];
                foreach ($det as $k => $v) {
                    if (is_scalar($v)) $partes[] = "$k: $v";
                    if (count($partes) >= 4) break;
                }
                $resumen = implode(' · ', $partes);
            }
            return [
                'fecha'   => $r['created_at'],
                'accion'  => $r['action'],
                'detalle' => mb_substr($resumen, 0, 160),
                'usuario' => $r['actor_email'],
            ];
        }, $st->fetchAll());
        json_out(['ok' => true, 'items' => $items]);
    }

    case 'tasa': {
        require_method('GET');
        require_role('admin', 'editor');
        json_out(['ok' => true, 'fecha' => date('Y-m-d'), 'tasa' => prev_tasa_del_dia()]);
    }

    case 'tasa_set': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $tasa = round((float)str_replace(',', '.', (string)($b['tasa'] ?? 0)), 4);
        if ($tasa <= 0) json_out(['ok' => false, 'error' => 'Indique una tasa válida (Bs por USD).'], 422);
        $fecha = prev_date($b['fecha'] ?? '') ?: date('Y-m-d');
        db()->prepare(
            "INSERT INTO prev_tasas (fecha, tasa, registrado_por) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE tasa = VALUES(tasa), registrado_por = VALUES(registrado_por)"
        )->execute([$fecha, $tasa, $u['id']]);
        audit('prev_tasa.set', 'prev_tasas', $fecha, ['tasa' => $tasa]);
        json_out(['ok' => true, 'fecha' => $fecha, 'tasa' => $tasa]);
    }

    case 'tasa_historial': {
        require_method('GET');
        require_role('admin', 'editor');
        $limit = max(1, min(60, (int)($_GET['limit'] ?? 24)));
        $st = db()->query(
            "SELECT t.fecha, t.tasa, t.created_at, u.email AS usuario
             FROM prev_tasas t LEFT JOIN users u ON u.id = t.registrado_por
             ORDER BY t.fecha DESC LIMIT $limit"
        );
        $items = array_map(fn($r) => [
            'fecha' => $r['fecha'], 'tasa' => (float)$r['tasa'],
            'usuario' => $r['usuario'], 'created_at' => $r['created_at'],
        ], $st->fetchAll());
        json_out(['ok' => true, 'items' => $items, 'actual' => prev_tasa_del_dia()]);
    }

    case 'tasa_preview': {
        // Cuántas cuotas en Bs se recalcularían a una tasa (para confirmar antes de aplicar).
        require_method('GET');
        require_role('admin', 'editor');
        $tasa = round((float)str_replace(',', '.', (string)($_GET['tasa'] ?? 0)), 4) ?: prev_tasa_del_dia();
        json_out(['ok' => true, 'tasa' => $tasa] + prev_preview_cuotas_bs($tasa));
    }

    case 'tasa_aplicar': {
        // Aplica la tasa a las cuotas en Bs pendientes. Manual (por decisión del usuario).
        require_method('POST');
        $u = require_role('admin');
        require_csrf();
        $b = body_json();
        $tasa = round((float)str_replace(',', '.', (string)($b['tasa'] ?? 0)), 4) ?: prev_tasa_del_dia();
        if ($tasa <= 0) json_out(['ok' => false, 'error' => 'Registre una tasa válida antes de actualizar.'], 422);
        $res = prev_actualizar_cuotas_bs($tasa);
        audit('prev_tasa.aplicar_cuotas', 'prev_contratos', null, $res);
        json_out(['ok' => true] + $res);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
