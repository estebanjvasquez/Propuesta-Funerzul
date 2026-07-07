<?php
/**
 * API de Mensajería de Previsión (WhatsApp/SMS):  api/prevision_mensajes.php?action=...
 *
 * Configuración (proveedor por canal, credenciales):
 *   GET  config           (admin; valores actuales, secretos enmascarados)
 *   POST config_set       (admin; solo pisa los secretos que vengan con valor)
 *   POST test             (admin; envía un mensaje de prueba a un teléfono)
 *
 * Plantillas (variables {{cliente}}, {{contrato}}, {{plan}}, {{monto_cuota}},
 * {{cuotas_vencidas}}, {{saldo_vencido}}, {{empresa}}, {{fecha}}):
 *   GET  plantillas        (staff; ?canal=)
 *   POST plantilla_save    (staff; crea o actualiza por id)
 *   POST plantilla_toggle  (staff)
 *   POST plantilla_delete  (admin)
 *
 * Envíos:
 *   POST enviar           (staff; a un contrato: plantilla o texto libre)
 *   POST enviar_morosos   (staff; lote a todos los morosos con teléfono)
 *   GET  envios           (staff; historial, ?contrato_id= ?estado= paginado)
 *
 * Proveedores soportados (configurables sin tocar código):
 *   manual          -> registra el envío; en WhatsApp devuelve el enlace wa.me
 *   whatsapp_cloud  -> WhatsApp Cloud API de Meta (token + phone_id)
 *   twilio          -> Twilio SMS y/o WhatsApp (sid + token + números origen)
 *   http            -> API HTTP genérica (gateway local: URL + método + token)
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';

$action = $_GET['action'] ?? 'envios';

const MSG_SECRETOS = ['prev_msg_wa_token', 'prev_msg_twilio_token', 'prev_msg_http_token'];
const MSG_SETTINGS = [
    'prev_msg_proveedor_whatsapp', 'prev_msg_proveedor_sms', 'prev_msg_empresa', 'prev_msg_pais',
    'prev_msg_wa_token', 'prev_msg_wa_phone_id',
    'prev_msg_twilio_sid', 'prev_msg_twilio_token', 'prev_msg_twilio_from_sms', 'prev_msg_twilio_from_wa',
    'prev_msg_http_url', 'prev_msg_http_metodo', 'prev_msg_http_token',
];

/** Contrato con joins para variables de plantilla. */
function msg_contrato(int $id): ?array
{
    $st = db()->prepare(
        "SELECT c.*, CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente_nombre,
                cl.telefono_celular, cl.telefono_habitacion, cl.id AS cliente_id,
                p.nombre AS plan_nombre
         FROM prev_contratos c
         JOIN prev_clientes cl ON cl.id = c.cliente_id
         LEFT JOIN prev_planes p ON p.id = c.plan_id
         WHERE c.id = ?"
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Cuerpo del mensaje: plantilla renderizada o texto libre. */
function msg_cuerpo(array $b, array $vars): array
{
    $plantillaId = (int)($b['plantilla_id'] ?? 0) ?: null;
    if ($plantillaId) {
        $st = db()->prepare("SELECT * FROM prev_msg_plantillas WHERE id = ? AND activo = 1");
        $st->execute([$plantillaId]);
        $p = $st->fetch();
        if (!$p) json_out(['ok' => false, 'error' => 'Plantilla no encontrada o inactiva.'], 404);
        return [$plantillaId, prev_msg_render($p['cuerpo'], $vars)];
    }
    $cuerpo = trim((string)($b['cuerpo'] ?? ''));
    if ($cuerpo === '') json_out(['ok' => false, 'error' => 'Indique una plantilla o escriba el mensaje.'], 422);
    return [null, prev_msg_render(mb_substr($cuerpo, 0, 2000), $vars)];
}

switch ($action) {

    // ==================== CONFIGURACIÓN ====================

    case 'config': {
        require_method('GET');
        require_role('admin');
        $cfg = [];
        foreach (MSG_SETTINGS as $k) {
            $v = get_setting($k, '');
            // Los secretos no viajan al navegador: solo se indica si están cargados
            $cfg[$k] = in_array($k, MSG_SECRETOS, true) ? ($v !== '' ? '__set__' : '') : $v;
        }
        json_out(['ok' => true, 'config' => $cfg, 'proveedores' => PREV_MSG_PROVEEDORES]);
    }

    case 'config_set': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $b = body_json();
        foreach (MSG_SETTINGS as $k) {
            if (!array_key_exists($k, $b)) continue;
            $v = trim((string)$b[$k]);
            // Un secreto enmascarado ('__set__') significa "no cambiar"
            if (in_array($k, MSG_SECRETOS, true) && $v === '__set__') continue;
            if ($k === 'prev_msg_proveedor_whatsapp' || $k === 'prev_msg_proveedor_sms') {
                $v = prev_enum($v, PREV_MSG_PROVEEDORES, 'manual');
            }
            if ($k === 'prev_msg_http_metodo') $v = strtoupper($v) === 'GET' ? 'GET' : 'POST';
            set_setting($k, mb_substr($v, 0, 500));
        }
        audit('prev_msg.config', 'app_settings', null, [
            'whatsapp' => get_setting('prev_msg_proveedor_whatsapp', 'manual'),
            'sms'      => get_setting('prev_msg_proveedor_sms', 'manual'),
        ]);
        json_out(['ok' => true]);
    }

    case 'test': {
        require_method('POST');
        $u = require_role('admin');
        require_csrf();
        $b = body_json();
        $canal = prev_enum($b['canal'] ?? '', PREV_MSG_CANALES, 'whatsapp');
        $tel = prev_msg_telefono($b['telefono'] ?? '');
        if (!$tel) json_out(['ok' => false, 'error' => 'Teléfono de prueba inválido.'], 422);
        $cuerpo = prev_msg_render('Mensaje de prueba de {{empresa}} — módulo de Previsión ({{fecha}}).', []);
        $r = prev_msg_enviar($canal, $tel, $cuerpo);
        $id = prev_msg_log([
            'canal' => $canal, 'destinatario' => $tel, 'cuerpo' => $cuerpo,
            'estado' => $r['estado'], 'proveedor' => $r['proveedor'],
            'respuesta' => $r['respuesta'], 'error' => $r['error'], 'usuario_id' => $u['id'],
        ]);
        audit('prev_msg.test', 'prev_msg_envios', $id, ['canal' => $canal, 'estado' => $r['estado']]);
        json_out(['ok' => $r['estado'] !== 'fallido', 'estado' => $r['estado'],
                  'proveedor' => $r['proveedor'], 'error' => $r['error'], 'wa_link' => $r['wa_link']]);
    }

    // ==================== PLANTILLAS ====================

    case 'plantillas': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = '1=1';
        $params = [];
        if (($c = prev_enum($_GET['canal'] ?? '', PREV_MSG_CANALES)) !== null) {
            $where .= ' AND canal = ?'; $params[] = $c;
        }
        $st = db()->prepare("SELECT * FROM prev_msg_plantillas WHERE $where ORDER BY canal, nombre");
        $st->execute($params);
        json_out(['ok' => true, 'items' => array_map('prev_msg_plantilla_out', $st->fetchAll())]);
    }

    case 'plantilla_save': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $nombre = clean_str($b['nombre'] ?? '', 120);
        $cuerpo = trim((string)($b['cuerpo'] ?? ''));
        if ($nombre === '' || $cuerpo === '') json_out(['ok' => false, 'error' => 'Nombre y mensaje son obligatorios.'], 422);
        $canal = prev_enum($b['canal'] ?? '', PREV_MSG_CANALES, 'whatsapp');
        $cuerpo = mb_substr($cuerpo, 0, 2000);

        if ($id) {
            db()->prepare("UPDATE prev_msg_plantillas SET nombre = ?, canal = ?, cuerpo = ? WHERE id = ?")
                ->execute([$nombre, $canal, $cuerpo, $id]);
        } else {
            $clave = substr(preg_replace('/[^a-z0-9_]+/', '_', strtolower($nombre)), 0, 34) . '_' . substr(uniqid(), -4);
            db()->prepare("INSERT INTO prev_msg_plantillas (clave, nombre, canal, cuerpo) VALUES (?,?,?,?)")
                ->execute([$clave, $nombre, $canal, $cuerpo]);
            $id = (int)db()->lastInsertId();
        }
        audit('prev_msg.plantilla_save', 'prev_msg_plantillas', $id, ['nombre' => $nombre]);
        json_out(['ok' => true, 'id' => $id]);
    }

    case 'plantilla_toggle': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        db()->prepare("UPDATE prev_msg_plantillas SET activo = ? WHERE id = ?")
            ->execute([!empty($b['activo']) ? 1 : 0, $id]);
        json_out(['ok' => true]);
    }

    case 'plantilla_delete': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        db()->prepare("DELETE FROM prev_msg_plantillas WHERE id = ?")->execute([$id]);
        audit('prev_msg.plantilla_delete', 'prev_msg_plantillas', $id);
        json_out(['ok' => true]);
    }

    // ==================== ENVÍOS ====================

    case 'enviar': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $contratoId = (int)($b['contrato_id'] ?? 0);
        $c = $contratoId ? msg_contrato($contratoId) : null;
        if (!$c) json_out(['ok' => false, 'error' => 'Contrato no encontrado.'], 404);

        $canal = prev_enum($b['canal'] ?? '', PREV_MSG_CANALES, 'whatsapp');
        $tel = prev_msg_telefono(($b['telefono'] ?? '') ?: ($c['telefono_celular'] ?: $c['telefono_habitacion']));
        if (!$tel) json_out(['ok' => false, 'error' => 'El cliente no tiene teléfono registrado; indíquelo manualmente.'], 422);

        [$plantillaId, $cuerpo] = msg_cuerpo($b, prev_msg_vars_contrato($c));
        $r = prev_msg_enviar($canal, $tel, $cuerpo);
        $id = prev_msg_log([
            'contrato_id' => $contratoId, 'cliente_id' => (int)$c['cliente_id'],
            'canal' => $canal, 'destinatario' => $tel, 'plantilla_id' => $plantillaId,
            'cuerpo' => $cuerpo, 'estado' => $r['estado'], 'proveedor' => $r['proveedor'],
            'respuesta' => $r['respuesta'], 'error' => $r['error'], 'usuario_id' => $u['id'],
        ]);
        audit('prev_msg.enviar', 'prev_msg_envios', $id,
              ['contrato' => $c['numero'], 'canal' => $canal, 'estado' => $r['estado']]);
        json_out(['ok' => $r['estado'] !== 'fallido', 'id' => $id, 'estado' => $r['estado'],
                  'proveedor' => $r['proveedor'], 'error' => $r['error'],
                  'wa_link' => $r['wa_link'], 'cuerpo' => $cuerpo], $r['estado'] !== 'fallido' ? 201 : 502);
    }

    case 'enviar_morosos': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $canal = prev_enum($b['canal'] ?? '', PREV_MSG_CANALES, 'whatsapp');
        $plantillaId = (int)($b['plantilla_id'] ?? 0);
        if (!$plantillaId) json_out(['ok' => false, 'error' => 'Seleccione la plantilla a enviar.'], 422);
        $min = max(1, (int)($b['min_cuotas'] ?? 1));

        $st = db()->prepare(
            "SELECT c.id FROM prev_contratos c
             WHERE c.estatus IN ('activo','suspendido')
               AND (SELECT COUNT(*) FROM prev_cuotas q WHERE q.contrato_id = c.id
                    AND q.estado IN ('pendiente','parcial') AND q.fecha_vencimiento < CURDATE()) >= ?
             ORDER BY c.numero LIMIT 300"
        );
        $st->execute([$min]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

        $res = ['enviados' => 0, 'manuales' => 0, 'fallidos' => 0, 'sin_telefono' => 0, 'items' => []];
        foreach ($ids as $cid) {
            $c = msg_contrato($cid);
            if (!$c) continue;
            $tel = prev_msg_telefono($c['telefono_celular'] ?: $c['telefono_habitacion']);
            if (!$tel) {
                $res['sin_telefono']++;
                $res['items'][] = ['contrato' => $c['numero'], 'cliente' => $c['cliente_nombre'],
                                   'estado' => 'sin_telefono', 'wa_link' => null];
                continue;
            }
            [$pid, $cuerpo] = msg_cuerpo(['plantilla_id' => $plantillaId], prev_msg_vars_contrato($c));
            $r = prev_msg_enviar($canal, $tel, $cuerpo);
            prev_msg_log([
                'contrato_id' => $cid, 'cliente_id' => (int)$c['cliente_id'],
                'canal' => $canal, 'destinatario' => $tel, 'plantilla_id' => $pid,
                'cuerpo' => $cuerpo, 'estado' => $r['estado'], 'proveedor' => $r['proveedor'],
                'respuesta' => $r['respuesta'], 'error' => $r['error'], 'usuario_id' => $u['id'],
            ]);
            if ($r['estado'] === 'enviado') $res['enviados']++;
            elseif ($r['estado'] === 'manual') $res['manuales']++;
            else $res['fallidos']++;
            $res['items'][] = ['contrato' => $c['numero'], 'cliente' => $c['cliente_nombre'],
                               'estado' => $r['estado'], 'error' => $r['error'], 'wa_link' => $r['wa_link']];
        }

        audit('prev_msg.enviar_morosos', 'prev_msg_envios', null,
              ['canal' => $canal, 'plantilla' => $plantillaId, 'total' => count($res['items']),
               'enviados' => $res['enviados'], 'fallidos' => $res['fallidos']]);
        json_out(['ok' => true] + $res);
    }

    case 'envios': {
        require_method('GET');
        require_role('admin', 'editor');
        $where = '1=1';
        $params = [];
        if (($cid = (int)($_GET['contrato_id'] ?? 0)) > 0) { $where .= ' AND e.contrato_id = ?'; $params[] = $cid; }
        if (($est = prev_enum($_GET['estado'] ?? '', ['enviado', 'fallido', 'manual'])) !== null) {
            $where .= ' AND e.estado = ?'; $params[] = $est;
        }
        $limit  = max(1, min(100, (int)($_GET['limit'] ?? 50)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));

        $st = db()->prepare("SELECT COUNT(*) FROM prev_msg_envios e WHERE $where");
        $st->execute($params);
        $total = (int)$st->fetchColumn();

        $st = db()->prepare(
            "SELECT e.*, c.numero AS contrato_numero,
                    CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente_nombre,
                    p.nombre AS plantilla_nombre, u.email AS usuario
             FROM prev_msg_envios e
             LEFT JOIN prev_contratos c ON c.id = e.contrato_id
             LEFT JOIN prev_clientes cl ON cl.id = e.cliente_id
             LEFT JOIN prev_msg_plantillas p ON p.id = e.plantilla_id
             LEFT JOIN users u ON u.id = e.usuario_id
             WHERE $where ORDER BY e.id DESC LIMIT $limit OFFSET $offset"
        );
        $st->execute($params);
        json_out(['ok' => true, 'total' => $total,
                  'items' => array_map('prev_msg_envio_out', $st->fetchAll())]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
