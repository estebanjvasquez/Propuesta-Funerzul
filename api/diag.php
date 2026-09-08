<?php
/**
 * Diagnóstico de instalación (protegido).
 * Uso:  api/diag.php?token=TU_CRON_SECRET   (o estando logueado como admin)
 * Verifica: conexión a BD, tablas existentes + conteos, GD/WebP y permisos de uploads.
 * No expone credenciales.
 */
require __DIR__ . '/lib/bootstrap.php';

$secret = $GLOBALS['CONFIG']['security']['cron_secret'] ?? '';
$token  = $_GET['token'] ?? '';
if (!(is_admin() || ($secret !== '' && hash_equals($secret, (string)$token)))) {
    json_out(['ok' => false, 'error' => 'No autorizado. Use ?token=CRON_SECRET o inicie sesión como admin.'], 403);
}

$dbc = $GLOBALS['CONFIG']['db'];
$report = [
    'php_version' => PHP_VERSION,
    'db_connected' => false,
    'db_config' => [
        'host' => $dbc['host'],
        'name' => $dbc['name'],
        'user' => $dbc['user'],
        'pass_len' => strlen((string)($dbc['pass'] ?? '')),
        'pass_is_placeholder' => (($dbc['pass'] ?? '') === 'PON_AQUI_LA_CONTRASEÑA_DE_MYSQL'),
        'config_file' => realpath(__DIR__ . '/config.php'),
    ],
    'tables' => [],
    'gd_webp' => function_exists('imagewebp'),
    'uploads_dir' => $GLOBALS['CONFIG']['paths']['uploads_dir'],
    'uploads_writable' => @is_writable($GLOBALS['CONFIG']['paths']['uploads_dir']),
];

try {
    db()->query('SELECT 1');
    $report['db_connected'] = true;
} catch (\Throwable $e) {
    $report['db_error'] = $e->getMessage();
    json_out(['ok' => true, 'diag' => $report]);
}

$expected = ['users', 'obituary_templates', 'obituaries', 'condolences', 'flower_offerings', 'app_settings', 'audit_log'];
foreach ($expected as $t) {
    try {
        $c = (int)db()->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $report['tables'][$t] = ['exists' => true, 'rows' => $c];
    } catch (\Throwable $e) {
        $report['tables'][$t] = ['exists' => false, 'error' => $e->getMessage()];
    }
}

// Plantillas "Cinta Conmemorativa" (id 4) y "Esquela Familiar" (id 5) --
// confirma si database/12_plantillas_esquela.sql ya se importó.
try {
    $tplRows = db()->query("SELECT id, name FROM obituary_templates WHERE id IN (4,5) ORDER BY id")->fetchAll();
    $report['plantillas_esquela'] = [
        'migracion_12_aplicada' => count($tplRows) === 2,
        'encontradas' => $tplRows,
    ];
} catch (\Throwable $e) {
    $report['plantillas_esquela'] = ['error' => $e->getMessage()];
}

// Tarjeta de obituario descargable (ver docs/specs/2026-09-08-tarjetas-obituario.md):
// GD necesita FreeType para imagettftext() y las 3 fuentes .ttf tienen que existir en disco.
// Se hace una prueba real de render (no solo "el archivo existe") para detectar cualquier
// falla silenciosa de GD/FreeType en este hosting específico.
$gdInfo = function_exists('gd_info') ? gd_info() : [];
$fonts = [
    'PlayfairDisplay-Variable'        => __DIR__ . '/../assets/fonts/PlayfairDisplay-Variable.ttf',
    'PlayfairDisplay-Italic-Variable' => __DIR__ . '/../assets/fonts/PlayfairDisplay-Italic-Variable.ttf',
    'Inter-Variable'                  => __DIR__ . '/../assets/fonts/Inter-Variable.ttf',
];
$fontStatus = [];
foreach ($fonts as $name => $path) {
    $exists = is_file($path);
    $renderOk = false; $renderError = null;
    if ($exists && function_exists('imagettftext')) {
        try {
            $test = imagecreatetruecolor(10, 10);
            $box = @imagettftext($test, 20, 0, 0, 15, imagecolorallocate($test, 0, 0, 0), $path, 'Aa');
            $renderOk = $box !== false;
            imagedestroy($test);
        } catch (\Throwable $e) {
            $renderError = $e->getMessage();
        }
    }
    $fontStatus[$name] = ['file_exists' => $exists, 'size_bytes' => $exists ? filesize($path) : null, 'render_ok' => $renderOk, 'render_error' => $renderError];
}
$report['obituary_card'] = [
    'gd_freetype_support' => $gdInfo['FreeType Support'] ?? null,
    'gd_png_support'      => $gdInfo['PNG Support'] ?? null,
    'fonts'                => $fontStatus,
    'seal_logo_exists'     => is_file(__DIR__ . '/../logo-seal-footer.png'),
];

// Pagos electrónicos: solo confirma que hay algo configurado, nunca expone el valor del secreto.
$pay = $GLOBALS['CONFIG']['payments'] ?? [];
$merc = $pay['mercantil'] ?? [];
$report['payments'] = [
    'provider'          => $pay['provider'] ?? null,
    'environment'       => $merc['environment'] ?? null,
    'client_id_set'     => !empty($merc['client_id']) && $merc['client_id'] !== 'PENDIENTE',
    'client_secret_set' => !empty($merc['client_secret']) && $merc['client_secret'] !== 'PENDIENTE',
    'return_url'        => $merc['return_url'] ?? null,
    'notification_url'  => $merc['notification_url'] ?? null,
];

// Conectividad de salida hacia el host de Mercantil (solo TCP/TLS, sin credenciales).
// Un error aquí (timeout, no se pudo resolver, conexión rechazada) suele indicar que
// el hosting bloquea salidas HTTPS o que la IP del servidor no está autorizada por
// el banco — no que el código esté mal. La extensión curl también la usa
// api/lib/prevision.php (prev_msg_http) para WhatsApp/SMS, así que si falta aquí
// hay que activarla en cPanel de todas formas.
if (!function_exists('curl_init')) {
    $report['mercantil_connectivity'] = [
        'reachable' => false,
        'error' => "La extensión 'curl' de PHP no está activada en este servidor. Actívala en cPanel → Select PHP Version → Extensions (también la necesita la mensajería de WhatsApp/SMS de Previsión).",
    ];
} else {
    $host = 'https://gw.3be3-22336bfa.us-east.apiconnect.appdomain.cloud/';
    $ch = curl_init($host);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_NOBODY => true]);
    $ok = curl_exec($ch);
    $report['mercantil_connectivity'] = [
        'host'      => $host,
        'reachable' => $ok !== false,
        'http_code' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'curl_error' => curl_error($ch) ?: null,
    ];
    curl_close($ch);
}

json_out(['ok' => true, 'diag' => $report]);
