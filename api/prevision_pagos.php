<?php
/**
 * API de Pagos Electrónicos de Previsión:  api/prevision_pagos.php?action=...
 *
 * Todo el módulo es staff-only (require_role). El sitio público nunca llama
 * este endpoint directamente — solo crea leads vía api/prevision_solicitudes.php.
 *
 * Mientras api/config.php tenga payments.provider = 'simulado' (por defecto),
 * ningún intento se aprueba solo; "conciliar" simula la confirmación del
 * banco a pedido explícito del staff (ver api/lib/payments/PaymentService.php).
 *
 *   GET  list           (?contrato_id=)         intentos de un contrato
 *   GET  estado          ?id=                    estado de un intento
 *   POST crear_intento   {contrato_id, cuota_id?, moneda, monto, metodo}
 *   POST conciliar        {id}                    confirma como APPROVED
 *   POST cancelar          {id, motivo?}
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision.php';
require __DIR__ . '/lib/payments/PaymentService.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    case 'list': {
        require_method('GET');
        require_role('admin', 'editor');
        $contratoId = (int)($_GET['contrato_id'] ?? 0);
        if (!$contratoId) json_out(['ok' => false, 'error' => 'Falta contrato_id.'], 422);
        $st = db()->prepare('SELECT * FROM prev_pagos_electronicos WHERE contrato_id = ? ORDER BY id DESC');
        $st->execute([$contratoId]);
        json_out(['ok' => true, 'items' => array_map([PaymentService::class, 'out'], $st->fetchAll())]);
    }

    case 'estado': {
        require_method('GET');
        require_role('admin', 'editor');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        json_out(['ok' => true, 'item' => PaymentService::estado($id)]);
    }

    case 'crear_intento': {
        require_method('POST');
        $u = require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $b['registrado_por'] = $u['id'];
        json_out(['ok' => true, 'item' => PaymentService::crearIntento($b)], 201);
    }

    case 'conciliar': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        json_out(['ok' => true, 'item' => PaymentService::conciliar($id)]);
    }

    case 'cancelar': {
        require_method('POST');
        require_role('admin', 'editor');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        json_out(['ok' => true, 'item' => PaymentService::cancelar($id, $b['motivo'] ?? null)]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
