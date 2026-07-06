<?php
/**
 * API de Clientes de Previsión:  api/prevision_clientes.php?action=...
 *   GET  list     (staff; ?q= cédula/nombre/teléfono, ?eliminados=1, paginado)
 *   GET  get      (?id= | ?cedula=; incluye sus contratos)
 *   POST create   (staff)
 *   POST update   (staff)
 *   POST delete   (staff = baja lógica; admin con ?hard=1 si no tiene contratos)
 *   POST restore  (staff)
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'list';

/** Valida los campos del cuerpo para crear/editar un cliente. */
function cliente_input(array $b): array
{
    $cedula  = prev_cedula($b['cedula'] ?? '');
    $nombres = clean_str($b['nombres'] ?? '', 100);
    if ($cedula === '' || $nombres === '') {
        json_out(['ok' => false, 'error' => 'Cédula y nombres son obligatorios.'], 422);
    }
    $sexo = strtoupper(trim((string)($b['sexo'] ?? '')));

    return [
        'tipo_persona'        => prev_enum($b['tipo_persona'] ?? '', ['natural', 'juridica'], 'natural'),
        'nacionalidad'        => prev_nacionalidad($b['nacionalidad'] ?? 'V'),
        'cedula'              => $cedula,
        'nombres'             => $nombres,
        'apellidos'           => clean_str($b['apellidos'] ?? '', 100),
        'fecha_nacimiento'    => prev_date($b['fecha_nacimiento'] ?? ''),
        'sexo'                => in_array($sexo, ['M', 'F'], true) ? $sexo : null,
        'estado_civil'        => clean_str($b['estado_civil'] ?? '', 30) ?: null,
        'telefono_habitacion' => clean_str($b['telefono_habitacion'] ?? '', 20) ?: null,
        'telefono_celular'    => clean_str($b['telefono_celular'] ?? '', 20) ?: null,
        'telefono_oficina'    => clean_str($b['telefono_oficina'] ?? '', 20) ?: null,
        'email'               => clean_str($b['email'] ?? '', 190) ?: null,
        'direccion'           => clean_str($b['direccion'] ?? '', 255) ?: null,
        'ciudad'              => clean_str($b['ciudad'] ?? '', 100) ?: null,
        'estado'              => clean_str($b['estado'] ?? '', 100) ?: null,
        'municipio'           => clean_str($b['municipio'] ?? '', 100) ?: null,
        'parroquia'           => clean_str($b['parroquia'] ?? '', 100) ?: null,
        'empleador'           => clean_str($b['empleador'] ?? '', 200) ?: null,
        'cargo'               => clean_str($b['cargo'] ?? '', 100) ?: null,
        'profesion'           => clean_str($b['profesion'] ?? '', 100) ?: null,
        'origen'              => clean_str($b['origen'] ?? '', 60) ?: null,
        'info_adicional'      => clean_str($b['info_adicional'] ?? '', 500) ?: null,
    ];
}

switch ($action) {

    case 'list': {
        require_method('GET');
        require_role('admin', 'editor');
        $where  = (($_GET['eliminados'] ?? '') === '1') ? "cl.deleted_at IS NOT NULL" : "cl.deleted_at IS NULL";
        $params = [];
        if (($q = trim($_GET['q'] ?? '')) !== '') {
            $where .= " AND (cl.cedula LIKE ? OR CONCAT(cl.nombres, ' ', cl.apellidos) LIKE ?
                        OR cl.telefono_celular LIKE ? OR cl.email LIKE ?)";
            $like = "%$q%";
            array_push($params, $like, $like, $like, $like);
        }
        $limit  = min(max((int)($_GET['limit'] ?? 50), 1), 200);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $countSt = db()->prepare("SELECT COUNT(*) FROM prev_clientes cl WHERE $where");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $st = db()->prepare(
            "SELECT cl.*, (SELECT COUNT(*) FROM prev_contratos c WHERE c.cliente_id = cl.id) AS contratos
             FROM prev_clientes cl WHERE $where
             ORDER BY cl.nombres ASC, cl.apellidos ASC
             LIMIT $limit OFFSET $offset"
        );
        $st->execute($params);
        json_out(['ok' => true, 'total' => $total, 'limit' => $limit, 'offset' => $offset,
                  'items' => array_map('prev_cliente_out', $st->fetchAll())]);
    }

    case 'get': {
        require_method('GET');
        require_role('admin', 'editor');
        if (($id = (int)($_GET['id'] ?? 0)) > 0) {
            $st = db()->prepare("SELECT * FROM prev_clientes WHERE id = ?");
            $st->execute([$id]);
        } elseif (($ced = prev_cedula($_GET['cedula'] ?? '')) !== '') {
            $st = db()->prepare("SELECT * FROM prev_clientes WHERE cedula = ?");
            $st->execute([$ced]);
        } else {
            json_out(['ok' => false, 'error' => 'Falta id o cédula.'], 422);
        }
        $r = $st->fetch();
        if (!$r) json_out(['ok' => false, 'error' => 'Cliente no encontrado.'], 404);

        $cs = db()->prepare(
            "SELECT c.*, p.nombre AS plan_nombre, v.nombre AS vendedor_nombre,
                    NULL AS cliente_nombre, NULL AS cliente_cedula
             FROM prev_contratos c
             LEFT JOIN prev_planes p ON p.id = c.plan_id
             LEFT JOIN prev_vendedores v ON v.id = c.vendedor_id
             WHERE c.cliente_id = ? ORDER BY c.fecha_ingreso DESC"
        );
        $cs->execute([(int)$r['id']]);

        json_out(['ok' => true, 'item' => prev_cliente_out($r),
                  'contratos' => array_map('prev_contrato_out', $cs->fetchAll())]);
    }

    case 'create': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $in = cliente_input(body_json());

        $st = db()->prepare("SELECT id FROM prev_clientes WHERE cedula = ?");
        $st->execute([$in['cedula']]);
        if ($st->fetchColumn()) json_out(['ok' => false, 'error' => 'Ya existe un cliente con esa cédula.'], 409);

        $cols = array_keys($in);
        $sql  = "INSERT INTO prev_clientes (" . implode(',', $cols) . ", created_by, updated_by)
                 VALUES (" . rtrim(str_repeat('?,', count($cols)), ',') . ", ?, ?)";
        $st = db()->prepare($sql);
        $st->execute([...array_values($in), $u['id'], $u['id']]);
        $id = (int)db()->lastInsertId();
        audit('prev_cliente.create', 'prev_clientes', $id, ['cedula' => $in['cedula'], 'nombres' => $in['nombres']]);
        json_out(['ok' => true, 'id' => $id], 201);
    }

    case 'update': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $in = cliente_input($b);

        $st = db()->prepare("SELECT id FROM prev_clientes WHERE cedula = ? AND id <> ?");
        $st->execute([$in['cedula'], $id]);
        if ($st->fetchColumn()) json_out(['ok' => false, 'error' => 'Ya existe otro cliente con esa cédula.'], 409);

        $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($in)));
        $st = db()->prepare("UPDATE prev_clientes SET $sets, updated_by = ? WHERE id = ?");
        $st->execute([...array_values($in), $u['id'], $id]);
        audit('prev_cliente.update', 'prev_clientes', $id, ['cedula' => $in['cedula']]);
        json_out(['ok' => true]);
    }

    case 'delete': {
        require_method('POST');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $hard = (($_GET['hard'] ?? '') === '1');

        $st = db()->prepare("SELECT COUNT(*) FROM prev_contratos WHERE cliente_id = ?");
        $st->execute([$id]);
        $tieneContratos = (int)$st->fetchColumn() > 0;

        if ($hard) {
            require_role('admin');
            if ($tieneContratos) {
                json_out(['ok' => false, 'error' => 'El cliente tiene contratos; use la baja lógica.'], 409);
            }
            db()->prepare("DELETE FROM prev_clientes WHERE id = ?")->execute([$id]);
            audit('prev_cliente.hard_delete', 'prev_clientes', $id);
        } else {
            require_role('admin', 'editor');
            db()->prepare("UPDATE prev_clientes SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
            audit('prev_cliente.soft_delete', 'prev_clientes', $id);
        }
        json_out(['ok' => true]);
    }

    case 'restore': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        db()->prepare("UPDATE prev_clientes SET deleted_at = NULL WHERE id = ?")->execute([$id]);
        audit('prev_cliente.restore', 'prev_clientes', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
