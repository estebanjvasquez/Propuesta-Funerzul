<?php
/**
 * URL de retorno/redirección OAuth para la aplicación registrada en el Portal
 * API de Mercantil Banco. Es la URL pública a la que Mercantil redirige el
 * navegador del cliente (o su propio flujo OAuth del Portal) tras completar,
 * cancelar o autorizar una operación.
 *
 * URL pública de este endpoint (usar EXACTAMENTE esta, con https, en el campo
 * "URL(s) de redirección OAuth" al registrar la aplicación):
 *
 *   AMBIENTE DE PRUEBA ACTUAL:
 *     https://legadoholding.com/funerzul/api/prevision_mercantil_callback.php
 *   PRODUCCIÓN (pendiente de migrar, ver docs/payments/mercantil/MIGRATION_PLAN.md):
 *     https://www.funerariadelzulia.com/api/prevision_mercantil_callback.php
 *
 * Al migrar de ambiente hay que volver a registrar/actualizar esta URL en el
 * Portal API de Mercantil — no basta con cambiar el código.
 *
 * Importante (docs/mercantil.md §18.2/§2.2): la redirección del navegador NO
 * es prueba de pago. Este endpoint solo muestra un estado "verificando" y
 * registra lo recibido; la aprobación real siempre pasa por
 * PaymentService::conciliar() (staff) o, cuando exista, por el webhook server-
 * to-server ya cifrado (api/prevision_mercantil_webhook.php), nunca por esta
 * redirección ni por parámetros de la URL.
 *
 * No se conocen todavía los nombres exactos de los parámetros que Mercantil
 * enviará aquí (código de autorización, estado, referencia, etc.) — no se
 * inventan; se registran genéricamente para poder mapearlos cuando llegue la
 * especificación completa (ver docs/payments/mercantil/STATUS.md, MRC-003).
 */
require __DIR__ . '/lib/bootstrap.php';

$recibido = $_SERVER['REQUEST_METHOD'] === 'POST' ? body_json() : $_GET;
$sanitizado = [];
foreach ($recibido as $k => $v) {
    $sanitizado[(string)$k] = is_scalar($v) ? substr((string)$v, 0, 200) : '[no escalar]';
}
error_log('[mercantil][callback] Retorno recibido: ' . json_encode($sanitizado, JSON_UNESCAPED_UNICODE));
audit('mercantil.callback_recibido', null, null, $sanitizado);

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificando su pago — Funeraria del Zulia</title>
    <meta name="robots" content="noindex,nofollow">
</head>
<body style="font-family:system-ui,sans-serif;max-width:520px;margin:80px auto;padding:0 20px;text-align:center;color:#222;">
    <h1 style="font-size:20px;">Estamos verificando su pago</h1>
    <p>No cierre esta ventana ni repita la operación. Un asesor confirmará el estado final de su solicitud.</p>
    <p><a href="../index.php">Volver al inicio</a></p>
</body>
</html>
