<?php
/**
 * API pública de captación de leads de planes/servicios de previsión:
 *   POST api/pf_solicitud.php   (público, sin sesión)
 *
 * Reemplaza a api/prevision_solicitudes.php, removido en el corte del
 * módulo PHP de Previsión hacia Prevision-Funeraria — ver
 * docs/specs/2026-08-28-fase-e-corte-admin-prevision.md. No escribe nada
 * en MySQL: el lead va directo al tenant `fdz` de Prevision-Funeraria
 * (api/lib/prevision_funeraria.php), que es donde el staff lo trabaja
 * ahora. Si Prevision-Funeraria no está disponible, se lo decimos al
 * visitante en vez de fingir que quedó guardado.
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/prevision_funeraria.php';

require_method('POST');
$b = body_json();

// Honeypot anti-spam: campo oculto que un visitante real nunca llena.
if (!empty($b['hp'])) {
    json_out(['ok' => true, 'message' => 'Gracias, un asesor te contactará pronto.'], 201);
}

if (!pf_habilitado()) {
    json_out([
        'ok' => false,
        'error' => 'El envío de solicitudes no está disponible en este momento. Escríbenos por WhatsApp mientras lo resolvemos.',
    ], 503);
}

$tipo      = in_array($b['tipo'] ?? '', ['plan', 'servicio'], true) ? $b['tipo'] : 'plan';
$interes   = clean_str($b['interes'] ?? '', 150);
$nombres   = clean_str($b['nombres'] ?? '', 100);
$apellidos = clean_str($b['apellidos'] ?? '', 100);
$telefono  = clean_str($b['telefono'] ?? '', 30);
$cedula    = clean_str($b['cedula'] ?? '', 20) ?: null;
$email     = filter_var(trim((string)($b['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null;

if ($interes === '' || $nombres === '' || $apellidos === '' || $telefono === '') {
    json_out(['ok' => false, 'error' => 'Completa nombre, apellido, teléfono y el plan/servicio de interés.'], 422);
}

$planId = null;
$servicioId = null;
if ($tipo === 'plan' && !empty($b['plan_slug'])) {
    $plan = pf_find_plan_by_slug(clean_str((string)$b['plan_slug'], 60));
    $planId = $plan['id'] ?? null;
}
if ($tipo === 'servicio' && !empty($b['servicio_slug'])) {
    $servicio = pf_find_servicio_by_slug(clean_str((string)$b['servicio_slug'], 60));
    $servicioId = $servicio['id'] ?? null;
}

$resultado = pf_crear_solicitud([
    'tipo'                => $tipo,
    'plan_id'             => $planId,
    'servicio_id'         => $servicioId,
    'interes'             => $interes,
    'nombres'             => $nombres,
    'apellidos'           => $apellidos,
    'documento_identidad' => $cedula,
    'telefono'            => $telefono,
    'email'               => $email,
]);

if (!$resultado['ok']) {
    error_log('[pf_solicitud] no se pudo enviar a Prevision-Funeraria: ' . $resultado['error']);
    json_out([
        'ok' => false,
        'error' => 'No pudimos enviar tu solicitud. Intenta de nuevo o escríbenos por WhatsApp.',
    ], 502);
}

audit('pf_solicitud.crear', 'pf_solicitud', $resultado['solicitud_id'] ?? null, ['tipo' => $tipo, 'interes' => $interes]);

json_out([
    'ok' => true,
    'message' => 'Hemos recibido tu solicitud. Un asesor te contactará en breve para completar el pago de forma segura.',
], 201);
