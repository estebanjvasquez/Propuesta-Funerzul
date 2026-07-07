<?php
/**
 * API de Cobranza de Previsión:  api/prevision_cobranza.php?action=...
 *
 * Morosidad y gestiones:
 *   GET  morosos          (staff; ?min= cuotas vencidas mínimas, ?ruta_id=, ?sucursal_id=)
 *   GET  gestiones        (?contrato_id= | global recientes)
 *   POST gestion_add      (staff; bitácora de contacto / promesa de pago)
 *   POST gestion_delete   (admin)
 *
 * Auto-lapsado (suspensión automática por cuotas vencidas):
 *   GET  lapsado_config       -> {enabled, cuotas}
 *   POST lapsado_config_set   (admin)
 *   GET  lapsado_preview      (?cuotas= override)
 *   POST lapsado_ejecutar     (admin; suspende ya los contratos del preview)
 *   (la corrida periódica va por cron: api/cron/prevision_lapsar.php)
 *
 * Rutas de cobro:
 *   GET  hoja_cobro       (?ruta_id=; contratos de la ruta con su saldo para
 *                         imprimir la hoja del cobrador)
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'morosos';

switch ($action) {

    case 'morosos': {
        require_method('GET');
        require_role('admin', 'editor');
        $min = max(1, (int)($_GET['min'] ?? 1));
        $where = "c.estatus IN ('activo','suspendido')";
        $params = [];
        if (($rid = (int)($_GET['ruta_id'] ?? 0)) > 0)     { $where .= " AND c.ruta_id = ?";     $params[] = $rid; }
        if (($sid = (int)($_GET['sucursal_id'] ?? 0)) > 0) { $where .= " AND c.sucursal_id = ?"; $params[] = $sid; }
        $params[] = $min;

        $st = db()->prepare(
            "SELECT c.id, c.numero, c.estatus, c.moneda, c.monto_cuota,
                    CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente_nombre,
                    cl.telefono_celular, cl.telefono_habitacion,
                    COUNT(q.id) AS cuotas_vencidas,
                    COALESCE(SUM(q.saldo),0) AS saldo_vencido,
                    MIN(q.fecha_vencimiento) AS vencida_desde,
                    DATEDIFF(CURDATE(), MIN(q.fecha_vencimiento)) AS dias_mora,
                    (SELECT MAX(g.fecha) FROM prev_gestiones g WHERE g.contrato_id = c.id) AS ultima_gestion
             FROM prev_contratos c
             JOIN prev_clientes cl ON cl.id = c.cliente_id
             JOIN prev_cuotas q ON q.contrato_id = c.id
                  AND q.estado IN ('pendiente','parcial') AND q.fecha_vencimiento < CURDATE()
             WHERE $where
             GROUP BY c.id, c.numero, c.estatus, c.moneda, c.monto_cuota, cliente_nombre,
                      cl.telefono_celular, cl.telefono_habitacion
             HAVING COUNT(q.id) >= ?
             ORDER BY dias_mora DESC, saldo_vencido DESC
             LIMIT 500"
        );
        $st->execute($params);
        $items = array_map(fn($r) => [
            'contrato_id'     => (int)$r['id'],
            'numero'          => $r['numero'],
            'estatus'         => $r['estatus'],
            'cliente_nombre'  => $r['cliente_nombre'],
            'telefono'        => $r['telefono_celular'] ?: $r['telefono_habitacion'],
            'moneda'          => $r['moneda'],
            'monto_cuota'     => (float)$r['monto_cuota'],
            'cuotas_vencidas' => (int)$r['cuotas_vencidas'],
            'saldo_vencido'   => (float)$r['saldo_vencido'],
            'vencida_desde'   => $r['vencida_desde'],
            'dias_mora'       => (int)$r['dias_mora'],
            'ultima_gestion'  => $r['ultima_gestion'],
        ], $st->fetchAll());
        json_out(['ok' => true, 'items' => $items]);
    }

    // ==================== GESTIONES ====================

    case 'gestiones': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = '1=1';
        $params = [];
        if (($cid = (int)($_GET['contrato_id'] ?? 0)) > 0) { $where .= " AND g.contrato_id = ?"; $params[] = $cid; }
        $st = db()->prepare(
            "SELECT g.*, c.numero AS contrato_numero,
                    CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente_nombre,
                    u.email AS usuario
             FROM prev_gestiones g
             JOIN prev_contratos c ON c.id = g.contrato_id
             JOIN prev_clientes cl ON cl.id = c.cliente_id
             LEFT JOIN users u ON u.id = g.usuario_id
             WHERE $where ORDER BY g.fecha DESC, g.id DESC LIMIT 200"
        );
        $st->execute($params);
        json_out(['ok' => true, 'items' => array_map('prev_gestion_out', $st->fetchAll())]);
    }

    case 'gestion_add': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $contratoId = (int)($b['contrato_id'] ?? 0);
        if (!$contratoId) json_out(['ok' => false, 'error' => 'Falta el contrato.'], 422);
        $st = db()->prepare("SELECT numero FROM prev_contratos WHERE id = ?");
        $st->execute([$contratoId]);
        $numero = $st->fetchColumn();
        if (!$numero) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);

        $resultado = prev_enum($b['resultado'] ?? '', PREV_RESULTADOS_GESTION, 'contactado');
        db()->prepare(
            "INSERT INTO prev_gestiones
             (contrato_id, fecha, tipo, resultado, promesa_fecha, promesa_monto, notas, usuario_id)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([
            $contratoId,
            (prev_date($b['fecha'] ?? '') ?: date('Y-m-d')) . ' ' . date('H:i:s'),
            prev_enum($b['tipo'] ?? '', PREV_TIPOS_GESTION, 'llamada'),
            $resultado,
            $resultado === 'promesa_pago' ? prev_date($b['promesa_fecha'] ?? '') : null,
            $resultado === 'promesa_pago' && isset($b['promesa_monto']) && $b['promesa_monto'] !== ''
                ? prev_money($b['promesa_monto']) : null,
            clean_str($b['notas'] ?? '', 500) ?: null,
            $u['id'],
        ]);
        $id = (int)db()->lastInsertId();
        audit('prev_gestion.add', 'prev_gestiones', $id, ['contrato' => $numero, 'resultado' => $resultado]);
        json_out(['ok' => true, 'id' => $id], 201);
    }

    case 'gestion_delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("DELETE FROM prev_gestiones WHERE id = ?")->execute([$id]);
        audit('prev_gestion.delete', 'prev_gestiones', $id);
        json_out(['ok' => true]);
    }

    // ==================== AUTO-LAPSADO ====================

    case 'lapsado_config': {
        require_method('GET');
        require_role('admin', 'editor');
        json_out(['ok' => true,
            'enabled' => setting_bool('prev_lapse_enabled', false),
            'cuotas'  => setting_int('prev_lapse_cuotas', 3)]);
    }

    case 'lapsado_config_set': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $b = body_json();
        $enabled = !empty($b['enabled']) ? '1' : '0';
        $cuotas = max(1, min(24, (int)($b['cuotas'] ?? 3)));
        set_setting('prev_lapse_enabled', $enabled);
        set_setting('prev_lapse_cuotas', (string)$cuotas);
        audit('prev_lapse.config', 'app_settings', null, ['enabled' => $enabled === '1', 'cuotas' => $cuotas]);
        json_out(['ok' => true, 'enabled' => $enabled === '1', 'cuotas' => $cuotas]);
    }

    case 'lapsado_preview': {
        require_method('GET');
        require_role('admin', 'editor');
        $cuotas = max(1, (int)($_GET['cuotas'] ?? setting_int('prev_lapse_cuotas', 3)));
        json_out(['ok' => true, 'cuotas' => $cuotas, 'items' => prev_lapsar($cuotas, true)]);
    }

    case 'lapsado_ejecutar': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $cuotas = max(1, (int)(body_json()['cuotas'] ?? setting_int('prev_lapse_cuotas', 3)));
        $items = prev_lapsar($cuotas, false);
        audit('prev_lapse.ejecutar', 'prev_contratos', null,
              ['cuotas_minimas' => $cuotas, 'suspendidos' => count($items)]);
        json_out(['ok' => true, 'suspendidos' => count($items), 'items' => $items]);
    }

    // ==================== HOJA DE COBRO POR RUTA ====================

    case 'hoja_cobro': {
        require_method('GET');
        require_role('admin', 'editor');
        $rutaId = (int)($_GET['ruta_id'] ?? 0);
        if (!$rutaId) json_out(['ok' => false, 'error' => 'Indique la ruta.'], 422);
        $st = db()->prepare(
            "SELECT r.*, cb.nombre AS cobrador_nombre, cb.telefono AS cobrador_telefono
             FROM prev_rutas r LEFT JOIN prev_cobradores cb ON cb.id = r.cobrador_id WHERE r.id = ?"
        );
        $st->execute([$rutaId]);
        $ruta = $st->fetch();
        if (!$ruta) json_out(['ok' => false, 'error' => 'Ruta no encontrada.'], 404);

        $st = db()->prepare(
            "SELECT c.id, c.numero, c.moneda, c.monto_cuota, c.frecuencia_pago, c.estatus,
                    CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente_nombre,
                    cl.telefono_celular, cl.telefono_habitacion, cl.direccion, cl.ciudad,
                    (SELECT COUNT(*) FROM prev_cuotas q WHERE q.contrato_id = c.id
                       AND q.estado IN ('pendiente','parcial') AND q.fecha_vencimiento < CURDATE()) AS cuotas_vencidas,
                    (SELECT COALESCE(SUM(q.saldo),0) FROM prev_cuotas q WHERE q.contrato_id = c.id
                       AND q.estado IN ('pendiente','parcial') AND q.fecha_vencimiento < CURDATE()) AS saldo_vencido,
                    (SELECT MIN(q.fecha_vencimiento) FROM prev_cuotas q WHERE q.contrato_id = c.id
                       AND q.estado IN ('pendiente','parcial')) AS proxima_cuota
             FROM prev_contratos c
             JOIN prev_clientes cl ON cl.id = c.cliente_id
             WHERE c.ruta_id = ? AND c.estatus IN ('activo','suspendido')
             ORDER BY cl.direccion, cliente_nombre"
        );
        $st->execute([$rutaId]);
        $items = array_map(fn($r) => [
            'contrato_id'     => (int)$r['id'],
            'numero'          => $r['numero'],
            'estatus'         => $r['estatus'],
            'cliente_nombre'  => $r['cliente_nombre'],
            'telefono'        => $r['telefono_celular'] ?: $r['telefono_habitacion'],
            'direccion'       => trim(($r['direccion'] ?? '') . ($r['ciudad'] ? ', ' . $r['ciudad'] : '')),
            'moneda'          => $r['moneda'],
            'monto_cuota'     => (float)$r['monto_cuota'],
            'frecuencia'      => $r['frecuencia_pago'],
            'cuotas_vencidas' => (int)$r['cuotas_vencidas'],
            'saldo_vencido'   => (float)$r['saldo_vencido'],
            'proxima_cuota'   => $r['proxima_cuota'],
        ], $st->fetchAll());

        json_out(['ok' => true, 'ruta' => [
            'id' => (int)$ruta['id'], 'nombre' => $ruta['nombre'], 'zona' => $ruta['zona'],
            'dia_cobro' => $ruta['dia_cobro'], 'cobrador' => $ruta['cobrador_nombre'],
            'cobrador_telefono' => $ruta['cobrador_telefono'],
        ], 'items' => $items]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
