<?php
/**
 * API de Reportes de Previsión:  api/prevision_reportes.php?action=...
 * Todos los reportes son de solo lectura (staff) y admiten ?formato=csv
 * para descargar el detalle en Excel (CSV con ; y BOM UTF-8).
 *
 *   GET aging       Antigüedad de cuentas por cobrar (al día, 1-30, 31-60,
 *                   61-90, +90 días) por contrato, con totales por moneda.
 *   GET produccion  Contratos vendidos por vendedor en un período (?desde&hasta).
 *   GET cobranza    Pagos recibidos en un período, por forma de pago y por día.
 *   GET cartera     Contratos por plan y estatus (foto actual de la cartera).
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'aging';
$csv    = ($_GET['formato'] ?? '') === 'csv';

function rep_periodo(): array
{
    $desde = prev_date($_GET['desde'] ?? '') ?: date('Y-m-01');
    $hasta = prev_date($_GET['hasta'] ?? '') ?: date('Y-m-d');
    if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }
    return [$desde, $hasta];
}

switch ($action) {

    // ==================== ANTIGÜEDAD DE CxC (AGING) ====================

    case 'aging': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->query(
            "SELECT c.id, c.numero, c.moneda, c.estatus,
                    CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente_nombre,
                    p.nombre AS plan_nombre,
                    SUM(CASE WHEN q.fecha_vencimiento >= CURDATE() THEN q.saldo ELSE 0 END) AS al_dia,
                    SUM(CASE WHEN q.fecha_vencimiento < CURDATE() AND DATEDIFF(CURDATE(), q.fecha_vencimiento) <= 30 THEN q.saldo ELSE 0 END) AS d1_30,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), q.fecha_vencimiento) BETWEEN 31 AND 60 THEN q.saldo ELSE 0 END) AS d31_60,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), q.fecha_vencimiento) BETWEEN 61 AND 90 THEN q.saldo ELSE 0 END) AS d61_90,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), q.fecha_vencimiento) > 90 THEN q.saldo ELSE 0 END) AS d90_mas,
                    SUM(q.saldo) AS total
             FROM prev_contratos c
             JOIN prev_clientes cl ON cl.id = c.cliente_id
             LEFT JOIN prev_planes p ON p.id = c.plan_id
             JOIN prev_cuotas q ON q.contrato_id = c.id AND q.estado IN ('pendiente','parcial')
             WHERE c.estatus IN ('activo','suspendido')
             GROUP BY c.id, c.numero, c.moneda, c.estatus, cliente_nombre, plan_nombre
             HAVING SUM(q.saldo) > 0
             ORDER BY d90_mas DESC, total DESC"
        );
        $items = array_map(fn($r) => [
            'contrato_id'    => (int)$r['id'],
            'numero'         => $r['numero'],
            'cliente_nombre' => $r['cliente_nombre'],
            'plan_nombre'    => $r['plan_nombre'],
            'estatus'        => $r['estatus'],
            'moneda'         => $r['moneda'],
            'al_dia'         => (float)$r['al_dia'],
            'd1_30'          => (float)$r['d1_30'],
            'd31_60'         => (float)$r['d31_60'],
            'd61_90'         => (float)$r['d61_90'],
            'd90_mas'        => (float)$r['d90_mas'],
            'total'          => (float)$r['total'],
        ], $st->fetchAll());

        if ($csv) {
            prev_csv_out('aging_cxc_' . date('Ymd') . '.csv',
                ['Contrato', 'Cliente', 'Plan', 'Estatus', 'Moneda', 'Al dia', '1-30', '31-60', '61-90', '+90', 'Total'],
                array_map(fn($i) => [$i['numero'], $i['cliente_nombre'], $i['plan_nombre'], $i['estatus'], $i['moneda'],
                                     $i['al_dia'], $i['d1_30'], $i['d31_60'], $i['d61_90'], $i['d90_mas'], $i['total']], $items));
        }

        $totales = [];
        foreach ($items as $i) {
            $m = $i['moneda'];
            if (!isset($totales[$m])) $totales[$m] = ['al_dia' => 0, 'd1_30' => 0, 'd31_60' => 0, 'd61_90' => 0, 'd90_mas' => 0, 'total' => 0];
            foreach (['al_dia', 'd1_30', 'd31_60', 'd61_90', 'd90_mas', 'total'] as $k) $totales[$m][$k] += $i[$k];
        }
        json_out(['ok' => true, 'items' => $items, 'totales' => $totales]);
    }

    // ==================== PRODUCCIÓN POR VENDEDOR ====================

    case 'produccion': {
        require_method('GET');
        require_role('admin', 'editor');
        [$desde, $hasta] = rep_periodo();
        $st = db()->prepare(
            "SELECT COALESCE(v.nombre, 'Sin vendedor') AS vendedor_nombre, v.id AS vendedor_id,
                    COUNT(*) AS contratos,
                    SUM(CASE WHEN c.estatus = 'activo' THEN 1 ELSE 0 END) AS activos,
                    SUM(CASE WHEN c.moneda = 'USD' THEN c.cuota_inicial ELSE 0 END) AS iniciales_usd,
                    SUM(CASE WHEN c.moneda = 'BS'  THEN c.cuota_inicial ELSE 0 END) AS iniciales_bs,
                    SUM(CASE WHEN c.moneda = 'USD' THEN c.monto_cuota ELSE 0 END) AS cuotas_usd,
                    SUM(CASE WHEN c.moneda = 'BS'  THEN c.monto_cuota ELSE 0 END) AS cuotas_bs
             FROM prev_contratos c
             LEFT JOIN prev_vendedores v ON v.id = c.vendedor_id
             WHERE c.fecha_ingreso BETWEEN ? AND ?
             GROUP BY v.id, vendedor_nombre
             ORDER BY contratos DESC, vendedor_nombre"
        );
        $st->execute([$desde, $hasta]);
        $items = array_map(fn($r) => [
            'vendedor_id'     => $r['vendedor_id'] !== null ? (int)$r['vendedor_id'] : null,
            'vendedor_nombre' => $r['vendedor_nombre'],
            'contratos'       => (int)$r['contratos'],
            'activos'         => (int)$r['activos'],
            'iniciales_usd'   => (float)$r['iniciales_usd'],
            'iniciales_bs'    => (float)$r['iniciales_bs'],
            'cuotas_usd'      => (float)$r['cuotas_usd'],
            'cuotas_bs'       => (float)$r['cuotas_bs'],
        ], $st->fetchAll());

        if ($csv) {
            prev_csv_out("produccion_{$desde}_{$hasta}.csv",
                ['Vendedor', 'Contratos', 'Activos', 'Iniciales USD', 'Iniciales Bs', 'Cuotas USD', 'Cuotas Bs'],
                array_map(fn($i) => [$i['vendedor_nombre'], $i['contratos'], $i['activos'],
                                     $i['iniciales_usd'], $i['iniciales_bs'], $i['cuotas_usd'], $i['cuotas_bs']], $items));
        }
        json_out(['ok' => true, 'desde' => $desde, 'hasta' => $hasta, 'items' => $items]);
    }

    // ==================== COBRANZA POR PERÍODO ====================

    case 'cobranza': {
        require_method('GET');
        require_role('admin', 'editor');
        [$desde, $hasta] = rep_periodo();

        // Por forma de pago
        $st = db()->prepare(
            "SELECT g.forma_pago,
                    COUNT(*) AS operaciones,
                    SUM(CASE WHEN g.moneda = 'USD' THEN g.monto ELSE 0 END) AS total_usd,
                    SUM(CASE WHEN g.moneda = 'BS'  THEN g.monto ELSE 0 END) AS total_bs
             FROM prev_pagos g
             WHERE g.fecha BETWEEN ? AND ?
             GROUP BY g.forma_pago ORDER BY total_usd DESC, total_bs DESC"
        );
        $st->execute([$desde, $hasta]);
        $formas = array_map(fn($r) => [
            'forma_pago'  => $r['forma_pago'],
            'operaciones' => (int)$r['operaciones'],
            'total_usd'   => (float)$r['total_usd'],
            'total_bs'    => (float)$r['total_bs'],
        ], $st->fetchAll());

        // Por día
        $st = db()->prepare(
            "SELECT g.fecha,
                    SUM(CASE WHEN g.moneda = 'USD' THEN g.monto ELSE 0 END) AS total_usd,
                    SUM(CASE WHEN g.moneda = 'BS'  THEN g.monto ELSE 0 END) AS total_bs
             FROM prev_pagos g
             WHERE g.fecha BETWEEN ? AND ?
             GROUP BY g.fecha ORDER BY g.fecha"
        );
        $st->execute([$desde, $hasta]);
        $dias = array_map(fn($r) => [
            'fecha'     => $r['fecha'],
            'total_usd' => (float)$r['total_usd'],
            'total_bs'  => (float)$r['total_bs'],
        ], $st->fetchAll());

        if ($csv) {
            // El CSV baja el detalle de pagos del período
            $st = db()->prepare(
                "SELECT g.fecha, c.numero, CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente,
                        g.recibo, g.forma_pago, g.moneda, g.monto, g.tasa, g.referencia
                 FROM prev_pagos g
                 JOIN prev_contratos c ON c.id = g.contrato_id
                 JOIN prev_clientes cl ON cl.id = c.cliente_id
                 WHERE g.fecha BETWEEN ? AND ? ORDER BY g.fecha, g.id"
            );
            $st->execute([$desde, $hasta]);
            prev_csv_out("cobranza_{$desde}_{$hasta}.csv",
                ['Fecha', 'Contrato', 'Cliente', 'Recibo', 'Forma de pago', 'Moneda', 'Monto', 'Tasa', 'Referencia'],
                array_map(fn($r) => [$r['fecha'], $r['numero'], $r['cliente'], $r['recibo'],
                                     $r['forma_pago'], $r['moneda'], $r['monto'], $r['tasa'], $r['referencia']],
                          $st->fetchAll()));
        }
        json_out(['ok' => true, 'desde' => $desde, 'hasta' => $hasta,
                  'formas' => $formas, 'dias' => $dias]);
    }

    // ==================== CARTERA (PLAN × ESTATUS) ====================

    case 'cartera': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->query(
            "SELECT COALESCE(p.nombre, 'Sin plan') AS plan_nombre, c.moneda,
                    COUNT(*) AS total,
                    SUM(CASE WHEN c.estatus = 'activo' THEN 1 ELSE 0 END) AS activos,
                    SUM(CASE WHEN c.estatus = 'suspendido' THEN 1 ELSE 0 END) AS suspendidos,
                    SUM(CASE WHEN c.estatus = 'anulado' THEN 1 ELSE 0 END) AS anulados,
                    SUM(CASE WHEN c.estatus = 'renuncia' THEN 1 ELSE 0 END) AS renuncias,
                    SUM(CASE WHEN c.estatus = 'finalizado' THEN 1 ELSE 0 END) AS finalizados,
                    SUM(CASE WHEN c.estatus = 'activo' THEN c.monto_cuota ELSE 0 END) AS facturacion
             FROM prev_contratos c
             LEFT JOIN prev_planes p ON p.id = c.plan_id
             GROUP BY p.nombre, c.moneda
             ORDER BY total DESC"
        );
        $items = array_map(fn($r) => [
            'plan_nombre' => $r['plan_nombre'],
            'moneda'      => $r['moneda'],
            'total'       => (int)$r['total'],
            'activos'     => (int)$r['activos'],
            'suspendidos' => (int)$r['suspendidos'],
            'anulados'    => (int)$r['anulados'],
            'renuncias'   => (int)$r['renuncias'],
            'finalizados' => (int)$r['finalizados'],
            'facturacion' => (float)$r['facturacion'],
        ], $st->fetchAll());

        if ($csv) {
            prev_csv_out('cartera_' . date('Ymd') . '.csv',
                ['Plan', 'Moneda', 'Total', 'Activos', 'Suspendidos', 'Anulados', 'Renuncias', 'Finalizados', 'Facturación mensual (activos)'],
                array_map(fn($i) => [$i['plan_nombre'], $i['moneda'], $i['total'], $i['activos'], $i['suspendidos'],
                                     $i['anulados'], $i['renuncias'], $i['finalizados'], $i['facturacion']], $items));
        }
        json_out(['ok' => true, 'items' => $items]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
