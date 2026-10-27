<?php
/**
 * Enlace corto de un obituario para redes sociales:
 *   /o/{id}          -> o.php?id={id}            (red "directo")
 *   /o/{id}/{red}    -> o.php?id={id}&s={red}    red: fb | wa | ig | otro
 *
 * Cuenta el clic (agregado por obituario, red y día, sin datos personales;
 * nunca cuenta robots ni generadores de vista previa) y redirige con 302 a la
 * página del obituario con parámetros UTM para Google Analytics.
 * Ver docs/specs/2026-10-27-enlaces-redes-y-analitica.md.
 */
require __DIR__ . '/api/lib/public_init.php';

const SHORTLINK_SOURCES = [
    'fb'   => 'facebook',
    'wa'   => 'whatsapp',
    'ig'   => 'instagram',
    'otro' => 'otro',
];

// Carpeta real del sitio (p. ej. "/funerzul/" o "/"), desde el script y no
// desde site_url(): así la redirección funciona igual en la subcarpeta de
// prueba y en el dominio final, aunque site_url esté mal configurado.
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';

/** Redirección temporal sin caché: un 301 quedaría guardado en el navegador y los clics siguientes no se contarían. */
function shortlink_redirect(string $location): never
{
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Location: ' . $location, true, 302);
    exit;
}

/** true para robots y generadores de vista previa (Facebook, WhatsApp, Telegram, etc.). */
function shortlink_is_bot(string $ua): bool
{
    return $ua === '' || (bool)preg_match(
        '/bot|crawl|spider|slurp|facebookexternalhit|facebookcatalog|whatsapp|telegram|preview|embedly|curl|wget|python|httpclient|headless/i',
        $ua
    );
}

if (!site_section_enabled('obituarios')) shortlink_redirect($base);

$id     = (string)(int)($_GET['id'] ?? 0);
$source = strtolower((string)($_GET['s'] ?? ''));
if (!isset(SHORTLINK_SOURCES[$source])) $source = 'directo';

$o = $id !== '0' ? get_public_obituary($id) : null;
if (!$o) shortlink_redirect($base . 'obituarios.php');

if (!shortlink_is_bot((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
    try {
        db()->prepare(
            "INSERT INTO obituary_link_clicks (obituary_id, source, click_date, clicks)
             VALUES (?, ?, CURDATE(), 1)
             ON DUPLICATE KEY UPDATE clicks = clicks + 1"
        )->execute([(int)$o['id'], $source]);
    } catch (\Throwable $e) {
        error_log('[obit][shortlink] ' . $e->getMessage()); // nunca bloquea la redirección
    }
}

$query = ['slug' => $o['slug'] ?: (string)$o['id']];
if ($source !== 'directo') {
    $query += [
        'utm_source'   => SHORTLINK_SOURCES[$source],
        'utm_medium'   => 'social',
        'utm_campaign' => 'obituarios',
        'utm_content'  => (string)$o['id'],
    ];
}
shortlink_redirect($base . 'obituario.php?' . http_build_query($query));
