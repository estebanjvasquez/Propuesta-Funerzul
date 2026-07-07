<?php
/**
 * API de Ajustes Masivos de Tarifas:  api/prevision_ajustes.php?action=...
 *
 *   GET  list       (staff; historial de ajustes)
 *   GET  get        (?id=; ajuste + detalle valor anterior/nuevo)
 *   POST preview    (staff; calcula los contratos/planes/cuotas afectados SIN guardar)
 *   POST aplicar    (admin; ejecuta el ajuste y registra el detalle para poder revertirlo)
 *   POST revertir   (admin; restaura los valores anteriores de un ajuste aplicado)
 *
 * El ajuste puede ser por porcentaje (+10 = sube 10%, -5 = baja 5%) o por monto
 * (delta fijo en la moneda del contrato), filtrado por plan y/o moneda, con
 * redondeo a céntimos o al entero. Opcionalmente actualiza también la cuota
 * mensual del plan y las cuotas pendientes futuras ya generadas.
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'list';

/** Aplica el tipo/valor/redondeo del ajuste a un monto. Nunca negativo. */
function ajuste_calcular(float $monto, string $tipo, float $valor, string $redondeo): float
{
    $nuevo = $tipo === 'porcentaje' ? $monto * (1 + $valor / 100) : $monto + $valor;
    $nuevo = $redondeo === 'entero' ? round($nuevo) : round($nuevo, 2);
    return max(0.0, $nuevo);
}

/** Lee y valida los parámetros comunes de preview/aplicar. */
function ajuste_input(array $b): array
{
    $tipo = prev_enum($b['tipo'] ?? '', PREV_AJUSTE_TIPOS, 'porcentaje');
    $valor = (float)str_replace(',', '.', (string)($b['valor'] ?? 0));
    if ($valor == 0.0) json_out(['ok' => false, 'error' => 'Indique el valor del ajuste (distinto de cero).'], 422);
    if ($tipo === 'porcentaje' && ($valor < -90 || $valor > 500)) {
        json_out(['ok' => false, 'error' => 'El porcentaje debe estar entre -90 y 500.'], 422);
    }
    $planId = (int)($b['plan_id'] ?? 0) ?: null;
    $moneda = isset($b['moneda']) && $b['moneda'] !== '' ? prev_moneda($b['moneda']) : null;
    return [
        'tipo'           => $tipo,
        'valor'          => $valor,
        'redondeo'       => prev_enum($b['redondeo'] ?? '', PREV_AJUSTE_REDONDEOS, 'centimos'),
        'plan_id'        => $planId,
        'moneda'         => $moneda,
        'aplicar_planes' => !empty($b['aplicar_planes']),
        'aplicar_cuotas' => !empty($b['aplicar_cuotas']),
        'descripcion'    => clean_str($b['descripcion'] ?? '', 255),
    ];
}

/** Contratos activos alcanzados por los filtros del ajuste. */
function ajuste_contratos(?int $planId, ?string $moneda): array
{
    $where = "c.estatus = 'activo' AND c.monto_cuota > 0";
    $params = [];
    if ($planId) { $where .= ' AND c.plan_id = ?'; $params[] = $planId; }
    if ($moneda) { $where .= ' AND c.moneda = ?';  $params[] = $moneda; }
    $st = db()->prepare(
        "SELECT c.id, c.numero, c.moneda, c.monto_cuota, c.plan_id,
                p.nombre AS plan_nombre,
                CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente_nombre
         FROM prev_contratos c
         JOIN prev_clientes cl ON cl.id = c.cliente_id
         LEFT JOIN prev_planes p ON p.id = c.plan_id
         WHERE $where ORDER BY c.numero"
    );
    $st->execute($params);
    return $st->fetchAll();
}

/** Planes activos alcanzados por los filtros del ajuste. */
function ajuste_planes(?int $planId, ?string $moneda): array
{
    $where = 'activo = 1 AND cuota_mensual > 0';
    $params = [];
    if ($planId) { $where .= ' AND id = ?';     $params[] = $planId; }
    if ($moneda) { $where .= ' AND moneda = ?'; $params[] = $moneda; }
    $st = db()->prepare("SELECT id, codigo, nombre, moneda, cuota_mensual FROM prev_planes WHERE $where ORDER BY nombre");
    $st->execute($params);
    return $st->fetchAll();
}

switch ($action) {

    case 'list': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->query(
            "SELECT a.*, p.nombre AS plan_nombre, u.email AS usuario
             FROM prev_ajustes a
             LEFT JOIN prev_planes p ON p.id = a.plan_id
             LEFT JOIN users u ON u.id = a.usuario_id
             ORDER BY a.id DESC LIMIT 100"
        );
        json_out(['ok' => true, 'items' => array_map('prev_ajuste_out', $st->fetchAll())]);
    }

    case 'get': {
        require_method('GET');
        require_role('admin', 'editor');
        $id = (int)($_GET['id'] ?? 0);
        $st = db()->prepare(
            "SELECT a.*, p.nombre AS plan_nombre, u.email AS usuario
             FROM prev_ajustes a
             LEFT JOIN prev_planes p ON p.id = a.plan_id
             LEFT JOIN users u ON u.id = a.usuario_id WHERE a.id = ?"
        );
        $st->execute([$id]);
        $a = $st->fetch();
        if (!$a) json_out(['ok' => false, 'error' => 'Ajuste no encontrado.'], 404);

        $st = db()->prepare(
            "SELECT d.*,
                    CASE d.objeto
                        WHEN 'contrato' THEN (SELECT numero FROM prev_contratos WHERE id = d.objeto_id)
                        WHEN 'plan'     THEN (SELECT nombre FROM prev_planes WHERE id = d.objeto_id)
                        ELSE CONCAT('Cuota #', d.objeto_id)
                    END AS referencia
             FROM prev_ajuste_detalles d WHERE d.ajuste_id = ? ORDER BY d.objeto, d.id"
        );
        $st->execute([$id]);
        $detalles = array_map(fn($d) => [
            'objeto'         => $d['objeto'],
            'objeto_id'      => (int)$d['objeto_id'],
            'referencia'     => $d['referencia'],
            'valor_anterior' => (float)$d['valor_anterior'],
            'valor_nuevo'    => (float)$d['valor_nuevo'],
        ], $st->fetchAll());
        json_out(['ok' => true, 'item' => prev_ajuste_out($a), 'detalles' => $detalles]);
    }

    case 'preview': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $in = ajuste_input(body_json());

        $contratos = array_map(fn($c) => [
            'contrato_id'    => (int)$c['id'],
            'numero'         => $c['numero'],
            'cliente_nombre' => $c['cliente_nombre'],
            'plan_nombre'    => $c['plan_nombre'],
            'moneda'         => $c['moneda'],
            'valor_anterior' => (float)$c['monto_cuota'],
            'valor_nuevo'    => ajuste_calcular((float)$c['monto_cuota'], $in['tipo'], $in['valor'], $in['redondeo']),
        ], ajuste_contratos($in['plan_id'], $in['moneda']));

        $planes = $in['aplicar_planes'] ? array_map(fn($p) => [
            'plan_id'        => (int)$p['id'],
            'nombre'         => $p['nombre'],
            'moneda'         => $p['moneda'],
            'valor_anterior' => (float)$p['cuota_mensual'],
            'valor_nuevo'    => ajuste_calcular((float)$p['cuota_mensual'], $in['tipo'], $in['valor'], $in['redondeo']),
        ], ajuste_planes($in['plan_id'], $in['moneda'])) : [];

        $cuotas = 0;
        if ($in['aplicar_cuotas'] && $contratos) {
            $ids = implode(',', array_map(fn($c) => $c['contrato_id'], $contratos));
            $cuotas = (int)db()->query(
                "SELECT COUNT(*) FROM prev_cuotas
                 WHERE contrato_id IN ($ids) AND estado = 'pendiente' AND tipo = 'programada'
                   AND monto = saldo AND fecha_vencimiento >= CURDATE()"
            )->fetchColumn();
        }

        json_out(['ok' => true, 'contratos' => $contratos, 'planes' => $planes,
                  'cuotas_afectadas' => $cuotas]);
    }

    case 'aplicar': {
        require_method('POST');
        $u = require_role('admin');
        require_csrf();
        $in = ajuste_input(body_json());
        if ($in['descripcion'] === '') {
            json_out(['ok' => false, 'error' => 'Indique una descripción del ajuste (p.ej. "Aumento tarifario julio 2026").'], 422);
        }

        $contratos = ajuste_contratos($in['plan_id'], $in['moneda']);
        if (!$contratos) json_out(['ok' => false, 'error' => 'No hay contratos activos que coincidan con los filtros.'], 422);

        $db = db();
        $db->beginTransaction();
        try {
            $db->prepare(
                "INSERT INTO prev_ajustes
                 (descripcion, tipo, valor, redondeo, plan_id, moneda, aplicar_planes, aplicar_cuotas, usuario_id)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([
                $in['descripcion'], $in['tipo'], $in['valor'], $in['redondeo'],
                $in['plan_id'], $in['moneda'],
                $in['aplicar_planes'] ? 1 : 0, $in['aplicar_cuotas'] ? 1 : 0, $u['id'],
            ]);
            $ajusteId = (int)$db->lastInsertId();

            $insDet = $db->prepare(
                "INSERT INTO prev_ajuste_detalles (ajuste_id, objeto, objeto_id, valor_anterior, valor_nuevo)
                 VALUES (?,?,?,?,?)"
            );
            $afectados = 0;

            // 1. Contratos
            $updCon = $db->prepare("UPDATE prev_contratos SET monto_cuota = ? WHERE id = ?");
            $selCuotas = $db->prepare(
                "SELECT id, monto FROM prev_cuotas
                 WHERE contrato_id = ? AND estado = 'pendiente' AND tipo = 'programada'
                   AND monto = saldo AND fecha_vencimiento >= CURDATE()"
            );
            $updCuota = $db->prepare("UPDATE prev_cuotas SET monto = ?, saldo = ? WHERE id = ?");
            foreach ($contratos as $c) {
                $ant = (float)$c['monto_cuota'];
                $nvo = ajuste_calcular($ant, $in['tipo'], $in['valor'], $in['redondeo']);
                if ($nvo === $ant) continue;
                $updCon->execute([$nvo, (int)$c['id']]);
                $insDet->execute([$ajusteId, 'contrato', (int)$c['id'], $ant, $nvo]);
                $afectados++;

                // 2. Cuotas pendientes futuras sin abonos del contrato
                if ($in['aplicar_cuotas']) {
                    $selCuotas->execute([(int)$c['id']]);
                    foreach ($selCuotas->fetchAll() as $q) {
                        $qa = (float)$q['monto'];
                        $qn = ajuste_calcular($qa, $in['tipo'], $in['valor'], $in['redondeo']);
                        if ($qn === $qa) continue;
                        $updCuota->execute([$qn, $qn, (int)$q['id']]);
                        $insDet->execute([$ajusteId, 'cuota', (int)$q['id'], $qa, $qn]);
                    }
                }
            }

            // 3. Cuota mensual de los planes
            if ($in['aplicar_planes']) {
                $updPlan = $db->prepare("UPDATE prev_planes SET cuota_mensual = ? WHERE id = ?");
                foreach (ajuste_planes($in['plan_id'], $in['moneda']) as $p) {
                    $ant = (float)$p['cuota_mensual'];
                    $nvo = ajuste_calcular($ant, $in['tipo'], $in['valor'], $in['redondeo']);
                    if ($nvo === $ant) continue;
                    $updPlan->execute([$nvo, (int)$p['id']]);
                    $insDet->execute([$ajusteId, 'plan', (int)$p['id'], $ant, $nvo]);
                }
            }

            $db->prepare("UPDATE prev_ajustes SET afectados = ? WHERE id = ?")->execute([$afectados, $ajusteId]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        audit('prev_ajuste.aplicar', 'prev_ajustes', $ajusteId,
              ['descripcion' => $in['descripcion'], 'tipo' => $in['tipo'],
               'valor' => $in['valor'], 'contratos' => $afectados]);
        json_out(['ok' => true, 'id' => $ajusteId, 'afectados' => $afectados], 201);
    }

    case 'revertir': {
        require_method('POST');
        $u = require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        $st = db()->prepare("SELECT * FROM prev_ajustes WHERE id = ?");
        $st->execute([$id]);
        $a = $st->fetch();
        if (!$a) json_out(['ok' => false, 'error' => 'Ajuste no encontrado.'], 404);
        if ($a['estado'] === 'revertido') json_out(['ok' => false, 'error' => 'Este ajuste ya fue revertido.'], 409);

        $st = db()->prepare("SELECT * FROM prev_ajuste_detalles WHERE ajuste_id = ?");
        $st->execute([$id]);
        $detalles = $st->fetchAll();

        $db = db();
        $db->beginTransaction();
        try {
            $updCon   = $db->prepare("UPDATE prev_contratos SET monto_cuota = ? WHERE id = ?");
            $updPlan  = $db->prepare("UPDATE prev_planes SET cuota_mensual = ? WHERE id = ?");
            // Solo se restauran cuotas que sigan pendientes y sin abonos
            $updCuota = $db->prepare(
                "UPDATE prev_cuotas SET monto = ?, saldo = ?
                 WHERE id = ? AND estado = 'pendiente' AND monto = saldo"
            );
            foreach ($detalles as $d) {
                $ant = (float)$d['valor_anterior'];
                $oid = (int)$d['objeto_id'];
                if ($d['objeto'] === 'contrato')  $updCon->execute([$ant, $oid]);
                elseif ($d['objeto'] === 'plan')  $updPlan->execute([$ant, $oid]);
                elseif ($d['objeto'] === 'cuota') $updCuota->execute([$ant, $ant, $oid]);
            }
            $db->prepare(
                "UPDATE prev_ajustes SET estado = 'revertido', revertido_por = ?, revertido_en = NOW() WHERE id = ?"
            )->execute([$u['id'], $id]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        audit('prev_ajuste.revertir', 'prev_ajustes', $id, ['detalles' => count($detalles)]);
        json_out(['ok' => true, 'revertidos' => count($detalles)]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
