<?php
/**
 * Webhook de Confirmación de Operación — Mercantil Banco.
 *
 * URL pública de este endpoint (registrar en Mercantil como URL de notificación):
 *   AMBIENTE DE PRUEBA ACTUAL: https://legadoholding.com/funerzul/api/prevision_mercantil_webhook.php
 *   PRODUCCIÓN (pendiente, ver docs/payments/mercantil/MIGRATION_PLAN.md):
 *     https://www.funerariadelzulia.com/api/prevision_mercantil_webhook.php
 *
 * Contrato tomado de docs/payments/mercantil/api_servicio_confirmacion_descripcion_de_atributos_y_campos_0.md
 * (documento oficial). Mercantil envía POST {"data": "<payload cifrado>"} y espera
 * exactamente esta respuesta de éxito:
 *   {"codigo":"0000","mensajeCliente":"...","mensajeSistema":"...","idRegistro":"..."}
 *
 * PENDIENTE (ver docs/payments/mercantil/STATUS.md, bloqueo MRC-004): el documento
 * indica cifrado "sha256 con RSA usando una MasterKey" pero no especifica modo,
 * padding, codificación de clave/texto ni la MasterKey en sí (se entrega al afiliarse
 * al servicio). Sin esos datos NO se puede descifrar ni verificar el payload —
 * implementarlo a ciegas violaría la regla de docs/mercantil.md §21 (solo es válido
 * si coincide con vectores de prueba reales). Por ahora este endpoint SOLO registra
 * el evento cifrado tal cual llega (evidencia/auditoría) y responde el envelope de
 * éxito documentado; NUNCA aprueba un pago por sí solo — la aprobación sigue
 * pasando exclusivamente por PaymentService::conciliar().
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/payments/PaymentService.php';

require_method('POST');
$b = body_json();

if (!isset($b['data']) || !is_string($b['data']) || $b['data'] === '') {
    json_out(['codigo' => '99', 'mensajeCliente' => 'Solicitud inválida.', 'mensajeSistema' => 'Falta "data".', 'idRegistro' => ''], 400);
}

// No podemos descifrar todavía (falta la MasterKey, ver MRC-004), y sin descifrar
// no hay forma de saber a qué intento (prev_pagos_electronicos) corresponde este
// payload — prev_pago_eventos exige un pago_electronico_id, así que todavía no
// se puede loggear ahí. Se deja evidencia en error_log/audit_log mientras tanto.
$hash = hash('sha256', $b['data']);
error_log('[mercantil][webhook] Payload cifrado recibido, pendiente de descifrar (MRC-004). Hash: ' . $hash);
audit('mercantil.webhook_recibido', null, null, ['payload_hash' => $hash]);

$idRegistro = bin2hex(random_bytes(16));
json_out([
    'codigo'         => '0000',
    'mensajeCliente' => 'Notificacion recibida con exito!',
    'mensajeSistema' => 'Notificacion recibida con exito!!',
    'idRegistro'     => $idRegistro,
]);
