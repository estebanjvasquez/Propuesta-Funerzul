<?php
/**
 * API de Configuración:  api/settings.php?action=get|update
 *   GET  get     (staff)  -> todos los settings
 *   POST update  (admin)  -> { settings: { clave: valor, ... } }
 *      Controla la purga: photo_retention_days, photo_purge_enabled, etc.
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'get';

// Claves editables desde el panel y su tipo (para validación básica)
const EDITABLE_SETTINGS = [
    'photo_retention_days'   => 'int',
    'photo_purge_enabled'    => 'bool',
    'photo_placeholder_path' => 'string',
    'homepage_recent_count'  => 'int',
    'condolence_moderation'  => 'bool',
    // Secciones del sitio público (ver site_section_enabled() en lib/helpers.php)
    'section_obituarios_enabled'        => 'bool',
    'section_directorio_medico_enabled' => 'bool',
    'section_recursos_enabled'          => 'bool',
    'section_faqs_enabled'              => 'bool',
    // Portal de Clientes en Prevision-Funeraria (ver client_portal_url() en lib/helpers.php)
    'client_portal_enabled'             => 'bool',
    'client_portal_url'                 => 'url',
    // Google Analytics 4 y Search Console (ver partials/analytics.php)
    'ga4_measurement_id'                => 'ga4',
    'google_site_verification'          => 'gsc',
];

// Formato de los valores de Google: se imprimen dentro de <script>/<meta> en
// todas las páginas públicas, así que solo se aceptan caracteres seguros.
const GOOGLE_FORMATS = [
    'ga4' => ['/^G-[A-Z0-9]{4,20}$/', 'El ID de Google Analytics debe tener el formato G-XXXXXXXXXX.'],
    'gsc' => ['/^[A-Za-z0-9_-]{10,100}$/', 'El código de Search Console debe ser solo el valor de "content" del meta tag (letras, números, guiones).'],
];

switch ($action) {

    case 'get': {
        require_method('GET');
        require_role('admin', 'editor');
        $rows = db()->query("SELECT setting_key, setting_value, description FROM app_settings ORDER BY setting_key")->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = ['value' => $r['setting_value'], 'description' => $r['description']];
        }
        json_out(['ok' => true, 'settings' => $out]);
    }

    case 'update': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $incoming = body_json()['settings'] ?? [];
        if (!is_array($incoming) || !$incoming) json_out(['ok' => false, 'error' => 'Nada que actualizar.'], 422);

        // Se valida todo antes de guardar nada: un valor inválido no deja la
        // configuración a medio aplicar.
        $applied = [];
        foreach ($incoming as $key => $val) {
            if (!isset(EDITABLE_SETTINGS[$key])) continue;
            $type = EDITABLE_SETTINGS[$key];
            if ($type === 'int') {
                $v = (string)max(0, (int)$val);
            } elseif ($type === 'bool') {
                $v = ($val === true || $val === '1' || $val === 1 || $val === 'true') ? '1' : '0';
            } elseif (isset(GOOGLE_FORMATS[$type])) {
                $v = $type === 'ga4' ? strtoupper(clean_str($val, 100)) : clean_str($val, 100);
                if ($v !== '' && !preg_match(GOOGLE_FORMATS[$type][0], $v)) {
                    json_out(['ok' => false, 'error' => GOOGLE_FORMATS[$type][1]], 422);
                }
            } elseif ($type === 'url') {
                $v = clean_str($val, 255);
                if ($v !== '' && !is_https_url($v)) {
                    json_out(['ok' => false, 'error' => 'La URL debe ser una dirección completa que empiece con https://'], 422);
                }
            } else {
                $v = clean_str($val, 255);
            }
            $applied[$key] = $v;
        }
        foreach ($applied as $key => $v) set_setting($key, $v);
        audit('settings.update', 'app_settings', null, $applied);
        json_out(['ok' => true, 'applied' => $applied]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
