<?php
/**
 * API de Solicitudes Públicas de Pago Electrónico:  api/prevision_solicitudes.php?action=...
 *
 *   POST crear              (público)  captura un lead desde planes/servicios; NO cobra
 *                                       nada ni crea un contrato — solo registra el interés.
 *   GET  list                (staff)   ?estado=nueva|contactada|convertida|descartada
 *   POST actualizar_estado    (staff)   {id, estado: 'contactada'|'descartada'}
 *   POST vincular_contrato     (staff)   {id, contrato_id}  marca 'convertida'
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'list';

function sol_out(array $r): array
{
    return [
        'id'          => (int)$r['id'],
        'tipo'        => $r['tipo'],
        'plan_id'     => $r['plan_id'] !== null ? (int)$r['plan_id'] : null,
        'interes'     => $r['interes'],
        'nombres'     => $r['nombres'],
        'apellidos'   => $r['apellidos'],
        'nombre_completo' => trim($r['nombres'] . ' ' . $r['apellidos']),
        'cedula'      => $r['cedula'],
        'telefono'    => $r['telefono'],
        'email'       => $r['email'],
        'metodo_pago' => $r['metodo_pago'],
        'estado'      => $r['estado'],
        'contrato_id' => $r['contrato_id'] !== null ? (int)$r['contrato_id'] : null,
        'notas'       => $r['notas'],
        'created_at'  => $r['created_at'] ?? null,
    ];
}

switch ($action) {

    case 'list': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = '1=1'; $params = [];
        if (in_array($_GET['estado'] ?? '', ['nueva', 'contactada', 'convertida', 'descartada'], true)) {
            $where .= ' AND estado = ?'; $params[] = $_GET['estado'];
        }
        $st = db()->prepare("SELECT * FROM prev_solicitudes_publicas WHERE $where ORDER BY created_at DESC LIMIT 300");
        $st->execute($params);
        json_out(['ok' => true, 'items' => array_map('sol_out', $st->fetchAll())]);
    }

    case 'crear': {
        require_method('POST');
        $b = body_json();

        // Honeypot anti-spam: campo oculto que un visitante real nunca llena.
        if (!empty($b['hp'])) json_out(['ok' => true, 'message' => 'Gracias, un asesor te contactará pronto.'], 201);

        $tipo      = in_array($b['tipo'] ?? '', ['plan', 'servicio'], true) ? $b['tipo'] : 'plan';
        $interes   = clean_str($b['interes'] ?? '', 150);
        $nombres   = clean_str($b['nombres'] ?? '', 100);
        $apellidos = clean_str($b['apellidos'] ?? '', 100);
        $telefono  = clean_str($b['telefono'] ?? '', 30);
        $cedula    = clean_str($b['cedula'] ?? '', 20) ?: null;
        $email     = filter_var(trim((string)($b['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null;
        $metodo    = in_array($b['metodo_pago'] ?? '', ['boton_web', 'c2p', 'otro'], true) ? $b['metodo_pago'] : 'boton_web';
        $planId    = (int)($b['plan_id'] ?? 0) ?: null;

        if ($interes === '' || $nombres === '' || $apellidos === '' || $telefono === '') {
            json_out(['ok' => false, 'error' => 'Completa nombre, apellido, teléfono y el plan/servicio de interés.'], 422);
        }

        if ($planId) {
            $chk = db()->prepare('SELECT id FROM prev_planes WHERE id = ? AND activo = 1');
            $chk->execute([$planId]);
            if (!$chk->fetchColumn()) $planId = null;
        }

        $st = db()->prepare(
            'INSERT INTO prev_solicitudes_publicas
             (tipo, plan_id, interes, nombres, apellidos, cedula, telefono, email, metodo_pago, ip)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([$tipo, $planId, $interes, $nombres, $apellidos, $cedula, $telefono, $email, $metodo, client_ip()]);
        $id = (int)db()->lastInsertId();
        audit('prev_solicitud.crear', 'prev_solicitudes_publicas', $id, ['interes' => $interes, 'tipo' => $tipo]);

        json_out([
            'ok' => true,
            'message' => 'Hemos recibido tu solicitud. Un asesor te contactará en breve para completar el pago de forma segura.',
        ], 201);
    }

    case 'actualizar_estado': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $estado = $b['estado'] ?? '';
        if (!$id || !in_array($estado, ['contactada', 'descartada'], true)) {
            json_out(['ok' => false, 'error' => 'Datos inválidos.'], 422);
        }
        db()->prepare('UPDATE prev_solicitudes_publicas SET estado = ? WHERE id = ?')->execute([$estado, $id]);
        audit('prev_solicitud.actualizar_estado', 'prev_solicitudes_publicas', $id, ['estado' => $estado]);
        json_out(['ok' => true]);
    }

    case 'vincular_contrato': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $contratoId = (int)($b['contrato_id'] ?? 0);
        if (!$id || !$contratoId) json_out(['ok' => false, 'error' => 'Faltan datos.'], 422);
        $chk = db()->prepare('SELECT id FROM prev_contratos WHERE id = ?');
        $chk->execute([$contratoId]);
        if (!$chk->fetchColumn()) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);
        db()->prepare("UPDATE prev_solicitudes_publicas SET estado = 'convertida', contrato_id = ? WHERE id = ?")
            ->execute([$contratoId, $id]);
        audit('prev_solicitud.vincular_contrato', 'prev_solicitudes_publicas', $id, ['contrato_id' => $contratoId]);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
