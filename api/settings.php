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

        $applied = [];
        foreach ($incoming as $key => $val) {
            if (!isset(EDITABLE_SETTINGS[$key])) continue;
            $type = EDITABLE_SETTINGS[$key];
            if ($type === 'int') {
                $v = (string)max(0, (int)$val);
            } elseif ($type === 'bool') {
                $v = ($val === true || $val === '1' || $val === 1 || $val === 'true') ? '1' : '0';
            } else {
                $v = clean_str($val, 255);
            }
            set_setting($key, $v);
            $applied[$key] = $v;
        }
        audit('settings.update', 'app_settings', null, $applied);
        json_out(['ok' => true, 'applied' => $applied]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
