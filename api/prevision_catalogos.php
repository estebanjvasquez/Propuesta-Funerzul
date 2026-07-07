<?php
/**
 * API de Catálogos de Previsión:  api/prevision_catalogos.php?action=...
 *
 *   GET  all                 -> sucursales + servicios + cobradores + rutas (para selects)
 *
 *   GET  sucursales          POST sucursal_save | sucursal_toggle | sucursal_delete(admin)
 *   GET  servicios           POST servicio_save | servicio_toggle | servicio_delete(admin)
 *   GET  cobradores          POST cobrador_save | cobrador_toggle | cobrador_delete(admin)
 *   GET  rutas               POST ruta_save     | ruta_toggle     | ruta_delete(admin)
 *
 *   Servicios contratados (add-ons de un contrato):
 *   POST contrato_servicio_add | contrato_servicio_toggle | contrato_servicio_delete
 *
 * *_save crea (sin id) o actualiza (con id). Todo requiere staff (admin/editor).
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'all';

/** ¿La tabla referencia al registro? Evita borrar catálogos en uso. */
function cat_en_uso(string $tabla, string $columna, int $id): bool
{
    $st = db()->prepare("SELECT COUNT(*) FROM $tabla WHERE $columna = ?");
    $st->execute([$id]);
    return (int)$st->fetchColumn() > 0;
}

/** Activa/desactiva un registro de catálogo. */
function cat_toggle(string $tabla, string $accion)
{
    require_method('POST');
    require_role('admin', 'editor');
    require_csrf();
    $b = body_json();
    $id = (int)($b['id'] ?? 0);
    if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
    $activo = !empty($b['activo']) ? 1 : 0;
    db()->prepare("UPDATE $tabla SET activo = ? WHERE id = ?")->execute([$activo, $id]);
    audit($accion, $tabla, $id, ['activo' => (bool)$activo]);
    json_out(['ok' => true]);
}

switch ($action) {

    case 'all': {
        require_method('GET');
        require_role('admin', 'editor');
        $pdo = db();
        json_out(['ok' => true,
            'sucursales' => $pdo->query("SELECT * FROM prev_sucursales ORDER BY nombre")->fetchAll(),
            'servicios'  => array_map('prev_servicio_out',
                $pdo->query("SELECT * FROM prev_servicios ORDER BY nombre")->fetchAll()),
            'cobradores' => $pdo->query("SELECT * FROM prev_cobradores ORDER BY nombre")->fetchAll(),
            'rutas'      => $pdo->query(
                "SELECT r.*, cb.nombre AS cobrador_nombre,
                        (SELECT COUNT(*) FROM prev_contratos c WHERE c.ruta_id = r.id) AS contratos
                 FROM prev_rutas r LEFT JOIN prev_cobradores cb ON cb.id = r.cobrador_id
                 ORDER BY r.nombre")->fetchAll(),
        ]);
    }

    // ==================== SUCURSALES ====================

    case 'sucursales': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->query(
            "SELECT s.*, (SELECT COUNT(*) FROM prev_contratos c WHERE c.sucursal_id = s.id) AS contratos
             FROM prev_sucursales s ORDER BY s.activo DESC, s.nombre"
        );
        json_out(['ok' => true, 'items' => $st->fetchAll()]);
    }

    case 'sucursal_save': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $nombre = clean_str($b['nombre'] ?? '', 100);
        if ($nombre === '') json_out(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        $vals = [$nombre, clean_str($b['direccion'] ?? '', 255) ?: null, clean_str($b['telefono'] ?? '', 20) ?: null];
        if ($id = (int)($b['id'] ?? 0)) {
            db()->prepare("UPDATE prev_sucursales SET nombre=?, direccion=?, telefono=? WHERE id=?")
                ->execute([...$vals, $id]);
        } else {
            db()->prepare("INSERT INTO prev_sucursales (nombre, direccion, telefono) VALUES (?,?,?)")->execute($vals);
            $id = (int)db()->lastInsertId();
        }
        audit('prev_sucursal.save', 'prev_sucursales', $id, ['nombre' => $nombre]);
        json_out(['ok' => true, 'id' => $id]);
    }

    case 'sucursal_toggle': cat_toggle('prev_sucursales', 'prev_sucursal.toggle');

    case 'sucursal_delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        if (cat_en_uso('prev_contratos', 'sucursal_id', $id) || cat_en_uso('prev_vendedores', 'sucursal_id', $id)) {
            json_out(['ok' => false, 'error' => 'La sucursal tiene contratos o vendedores; desactívela en su lugar.'], 409);
        }
        db()->prepare("DELETE FROM prev_sucursales WHERE id = ?")->execute([$id]);
        audit('prev_sucursal.delete', 'prev_sucursales', $id);
        json_out(['ok' => true]);
    }

    // ==================== SERVICIOS ADICIONALES ====================

    case 'servicios': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->query("SELECT * FROM prev_servicios ORDER BY activo DESC, nombre");
        json_out(['ok' => true, 'items' => array_map('prev_servicio_out', $st->fetchAll())]);
    }

    case 'servicio_save': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $nombre = clean_str($b['nombre'] ?? '', 100);
        if ($nombre === '') json_out(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        $vals = [
            $nombre,
            clean_str($b['descripcion'] ?? '', 255) ?: null,
            prev_moneda($b['moneda'] ?? ''),
            prev_money($b['precio'] ?? 0),
            !empty($b['recurrente']) ? 1 : 0,
        ];
        if ($id = (int)($b['id'] ?? 0)) {
            db()->prepare("UPDATE prev_servicios SET nombre=?, descripcion=?, moneda=?, precio=?, recurrente=? WHERE id=?")
                ->execute([...$vals, $id]);
        } else {
            db()->prepare("INSERT INTO prev_servicios (nombre, descripcion, moneda, precio, recurrente) VALUES (?,?,?,?,?)")
                ->execute($vals);
            $id = (int)db()->lastInsertId();
        }
        audit('prev_servicio.save', 'prev_servicios', $id, ['nombre' => $nombre]);
        json_out(['ok' => true, 'id' => $id]);
    }

    case 'servicio_toggle': cat_toggle('prev_servicios', 'prev_servicio.toggle');

    case 'servicio_delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        if (cat_en_uso('prev_contrato_servicios', 'servicio_id', $id)) {
            json_out(['ok' => false, 'error' => 'El servicio está contratado; desactívelo en su lugar.'], 409);
        }
        db()->prepare("DELETE FROM prev_servicios WHERE id = ?")->execute([$id]);
        audit('prev_servicio.delete', 'prev_servicios', $id);
        json_out(['ok' => true]);
    }

    // ==================== COBRADORES ====================

    case 'cobradores': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->query(
            "SELECT cb.*, (SELECT COUNT(*) FROM prev_rutas r WHERE r.cobrador_id = cb.id) AS rutas
             FROM prev_cobradores cb ORDER BY cb.activo DESC, cb.nombre"
        );
        json_out(['ok' => true, 'items' => $st->fetchAll()]);
    }

    case 'cobrador_save': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $nombre = clean_str($b['nombre'] ?? '', 100);
        if ($nombre === '') json_out(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        $vals = [prev_cedula($b['cedula'] ?? '') ?: null, $nombre, clean_str($b['telefono'] ?? '', 20) ?: null];
        if ($id = (int)($b['id'] ?? 0)) {
            db()->prepare("UPDATE prev_cobradores SET cedula=?, nombre=?, telefono=? WHERE id=?")
                ->execute([...$vals, $id]);
        } else {
            db()->prepare("INSERT INTO prev_cobradores (cedula, nombre, telefono) VALUES (?,?,?)")->execute($vals);
            $id = (int)db()->lastInsertId();
        }
        audit('prev_cobrador.save', 'prev_cobradores', $id, ['nombre' => $nombre]);
        json_out(['ok' => true, 'id' => $id]);
    }

    case 'cobrador_toggle': cat_toggle('prev_cobradores', 'prev_cobrador.toggle');

    case 'cobrador_delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        if (cat_en_uso('prev_rutas', 'cobrador_id', $id)) {
            json_out(['ok' => false, 'error' => 'El cobrador tiene rutas asignadas; reasígnelas primero.'], 409);
        }
        db()->prepare("DELETE FROM prev_cobradores WHERE id = ?")->execute([$id]);
        audit('prev_cobrador.delete', 'prev_cobradores', $id);
        json_out(['ok' => true]);
    }

    // ==================== RUTAS DE COBRANZA ====================

    case 'rutas': {
        require_method('GET');
        require_role('admin', 'editor');
        $st = db()->query(
            "SELECT r.*, cb.nombre AS cobrador_nombre,
                    (SELECT COUNT(*) FROM prev_contratos c WHERE c.ruta_id = r.id) AS contratos
             FROM prev_rutas r LEFT JOIN prev_cobradores cb ON cb.id = r.cobrador_id
             ORDER BY r.activo DESC, r.nombre"
        );
        json_out(['ok' => true, 'items' => $st->fetchAll()]);
    }

    case 'ruta_save': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $nombre = clean_str($b['nombre'] ?? '', 100);
        if ($nombre === '') json_out(['ok' => false, 'error' => 'El nombre es obligatorio.'], 422);
        $cobradorId = (int)($b['cobrador_id'] ?? 0) ?: null;
        if ($cobradorId) {
            $st = db()->prepare("SELECT id FROM prev_cobradores WHERE id = ?");
            $st->execute([$cobradorId]);
            if (!$st->fetchColumn()) json_out(['ok' => false, 'error' => 'Cobrador no encontrado.'], 422);
        }
        $vals = [$nombre, clean_str($b['zona'] ?? '', 150) ?: null, clean_str($b['dia_cobro'] ?? '', 20) ?: null, $cobradorId];
        if ($id = (int)($b['id'] ?? 0)) {
            db()->prepare("UPDATE prev_rutas SET nombre=?, zona=?, dia_cobro=?, cobrador_id=? WHERE id=?")
                ->execute([...$vals, $id]);
        } else {
            db()->prepare("INSERT INTO prev_rutas (nombre, zona, dia_cobro, cobrador_id) VALUES (?,?,?,?)")->execute($vals);
            $id = (int)db()->lastInsertId();
        }
        audit('prev_ruta.save', 'prev_rutas', $id, ['nombre' => $nombre]);
        json_out(['ok' => true, 'id' => $id]);
    }

    case 'ruta_toggle': cat_toggle('prev_rutas', 'prev_ruta.toggle');

    case 'ruta_delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        if (cat_en_uso('prev_contratos', 'ruta_id', $id)) {
            json_out(['ok' => false, 'error' => 'La ruta tiene contratos asignados; reasígnelos primero.'], 409);
        }
        db()->prepare("DELETE FROM prev_rutas WHERE id = ?")->execute([$id]);
        audit('prev_ruta.delete', 'prev_rutas', $id);
        json_out(['ok' => true]);
    }

    // ==================== SERVICIOS DE UN CONTRATO ====================

    case 'contrato_servicio_add': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $contratoId = (int)($b['contrato_id'] ?? 0);
        $servicioId = (int)($b['servicio_id'] ?? 0);
        if (!$contratoId || !$servicioId) json_out(['ok' => false, 'error' => 'Faltan contrato y/o servicio.'], 422);

        $st = db()->prepare("SELECT id FROM prev_contratos WHERE id = ?");
        $st->execute([$contratoId]);
        if (!$st->fetchColumn()) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);
        $st = db()->prepare("SELECT * FROM prev_servicios WHERE id = ? AND activo = 1");
        $st->execute([$servicioId]);
        $srv = $st->fetch();
        if (!$srv) json_out(['ok' => false, 'error' => 'Servicio no encontrado o inactivo.'], 422);

        $precio = isset($b['precio']) && $b['precio'] !== '' ? prev_money($b['precio']) : (float)$srv['precio'];
        db()->prepare(
            "INSERT INTO prev_contrato_servicios (contrato_id, servicio_id, moneda, precio, recurrente, fecha, notas)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([
            $contratoId, $servicioId, $srv['moneda'], $precio, (int)$srv['recurrente'],
            prev_date($b['fecha'] ?? '') ?: date('Y-m-d'),
            clean_str($b['notas'] ?? '', 255) ?: null,
        ]);
        $id = (int)db()->lastInsertId();
        audit('prev_contrato_servicio.add', 'prev_contrato_servicios', $id,
              ['contrato_id' => $contratoId, 'servicio' => $srv['nombre'], 'precio' => $precio]);
        json_out(['ok' => true, 'id' => $id], 201);
    }

    case 'contrato_servicio_toggle': cat_toggle('prev_contrato_servicios', 'prev_contrato_servicio.toggle');

    case 'contrato_servicio_delete': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("DELETE FROM prev_contrato_servicios WHERE id = ?")->execute([$id]);
        audit('prev_contrato_servicio.delete', 'prev_contrato_servicios', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
