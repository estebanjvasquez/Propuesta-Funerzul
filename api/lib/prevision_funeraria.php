<?php
declare(strict_types=1);

/**
 * Cliente de la API pública de Prevision-Funeraria (repo hermano
 * estebanjvasquez/Prevision-Funeraria, tenant `fdz`).
 *
 * Fase A/B/C del plan de migración — ver
 * docs/specs/2026-08-28-migracion-a-prevision-funeraria.md.
 *
 * Contrato real verificado en vivo el 2026-08-28 (no supuesto):
 *   GET  /api/public/t/fdz/planes      -> { items: [...] }  sin token, CORS abierto
 *   GET  /api/public/t/fdz/servicios   -> { items: [...], whatsapp_emergencia }  sin token
 *   POST /api/public/t/fdz/solicitudes -> { ok: true, solicitud_id }  sin token
 * Ver docs/api-publica-wizard.md del repo Prevision-Funeraria para el contrato completo.
 *
 * Filosofía: esto NUNCA debe romper una página pública ni el flujo de un lead.
 * Cualquier fallo (timeout, red, respuesta inesperada) devuelve null/false y el
 * caller decide el respaldo (no mostrar precio, seguir usando el flujo local, etc.).
 * No requiere sesión ni CORS propio -- se llama server-to-server con cURL.
 */

/** Config con defaults seguros; nunca lanza si falta 'prevision_funeraria' en config.php. */
function pf_config(): array
{
    $c = $GLOBALS['CONFIG']['prevision_funeraria'] ?? [];
    return [
        // Apagado por defecto a propósito: hasta que alguien ponga 'enabled' => true
        // en config.php (con el token real, si hiciera falta), nada de esto se llama.
        'enabled'   => (bool)($c['enabled'] ?? false),
        'base_url'  => rtrim((string)($c['base_url'] ?? 'https://prevision-funeraria.sisteg.workers.dev/api/public/t/fdz'), '/'),
        // Ninguno de los 3 endpoints que usamos hoy (planes, servicios, solicitudes)
        // requiere token -- se deja el campo listo para /compras o /parentescos a futuro.
        'api_token' => (string)($c['api_token'] ?? ''),
        'cache_ttl' => max(60, (int)($c['cache_ttl'] ?? 900)),
    ];
}

function pf_habilitado(): bool
{
    return pf_config()['enabled'];
}

/**
 * HTTP mínimo con cURL. Timeout corto a propósito: esto se llama desde páginas
 * públicas server-rendered: PF caído o lento nunca debe colgar la carga del sitio.
 */
function pf_http(string $metodo, string $path, ?array $jsonBody = null): array
{
    $cfg = pf_config();
    $url = $cfg['base_url'] . $path;
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($cfg['api_token'] !== '') {
        $headers[] = 'Authorization: Bearer ' . $cfg['api_token'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($jsonBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody, JSON_UNESCAPED_UNICODE));
    }
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $data = null;
    if ($resp !== false && $resp !== '') {
        $decoded = json_decode($resp, true);
        if (is_array($decoded)) $data = $decoded;
    }

    return [
        'ok'    => $resp !== false && $code >= 200 && $code < 300 && $data !== null,
        'code'  => $code,
        'data'  => $data,
        'error' => $err ?: null,
    ];
}

/* ── Cache de catálogo en disco (cache/prevision_funeraria/) ──────────────────
   No usamos app_settings a propósito: es un cache interno de HTTP, no una
   configuración administrable, y no tiene que ver con la tabla que ve el panel
   en Configuración. Carpeta bloqueada por completo vía .htaccess. */

function pf_cache_path(string $recurso): string
{
    $dir = __DIR__ . '/../../cache/prevision_funeraria';
    return $dir . '/' . preg_replace('/[^a-z_]/', '', $recurso) . '.json';
}

function pf_cache_leer(string $recurso, int $ttl): ?array
{
    $path = pf_cache_path($recurso);
    if (!is_file($path)) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['at'], $decoded['data'])) return null;
    return $decoded; // el caller decide si 'at' sigue vigente o si lo usa como respaldo
}

function pf_cache_escribir(string $recurso, array $data): void
{
    $path = pf_cache_path($recurso);
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($path, json_encode(['at' => time(), 'data' => $data], JSON_UNESCAPED_UNICODE));
}

/** Catálogo genérico (planes o servicios) con cache TTL y respaldo si PF falla. */
function pf_get_catalogo(string $recurso): ?array
{
    if (!pf_habilitado()) return null;
    $cfg = pf_config();

    $cached = pf_cache_leer($recurso, $cfg['cache_ttl']);
    if ($cached !== null && (time() - $cached['at']) < $cfg['cache_ttl']) {
        return $cached['data'];
    }

    $resp = pf_http('GET', '/' . $recurso);
    if ($resp['ok']) {
        pf_cache_escribir($recurso, $resp['data']);
        return $resp['data'];
    }

    // PF no respondió a tiempo: mejor mostrar cache vieja que nada, si existe.
    return $cached['data'] ?? null;
}

function pf_get_planes(): ?array
{
    return pf_get_catalogo('planes');
}

function pf_get_servicios(): ?array
{
    return pf_get_catalogo('servicios');
}

function pf_find_plan_by_slug(string $slug): ?array
{
    $cat = pf_get_planes();
    foreach ($cat['items'] ?? [] as $p) {
        if (($p['slug'] ?? null) === $slug) return $p;
    }
    return null;
}

/** Fase C: además del servicio, hace falta saber a qué WhatsApp mandar la emergencia. */
function pf_find_servicio_by_slug(string $slug): ?array
{
    $cat = pf_get_servicios();
    foreach ($cat['items'] ?? [] as $s) {
        if (($s['slug'] ?? null) === $slug) return $s;
    }
    return null;
}

function pf_whatsapp_emergencia(): ?string
{
    $cat = pf_get_servicios();
    return $cat['whatsapp_emergencia'] ?? null;
}

/** Formatea centavos -> texto de precio. PF siempre maneja USD para fdz hoy. */
function pf_fmt_precio(int $centavos, string $moneda = 'USD'): string
{
    $simbolo = $moneda === 'USD' ? 'US$' : ($moneda . ' ');
    return $simbolo . number_format($centavos / 100, 2, ',', '.');
}

/**
 * Fase B: registra un lead en Prevision-Funeraria. Nunca lanza excepción --
 * si falla, el caller (api/prevision_solicitudes.php) sigue con el guardado
 * local igual; esto es "mejor esfuerzo", no la única fuente de verdad todavía.
 */
function pf_crear_solicitud(array $payload): array
{
    if (!pf_habilitado()) {
        return ['ok' => false, 'error' => 'prevision_funeraria no configurado'];
    }
    $resp = pf_http('POST', '/solicitudes', $payload);
    if ($resp['ok']) {
        return ['ok' => true, 'solicitud_id' => $resp['data']['solicitud_id'] ?? null];
    }
    $msg = is_array($resp['data']) ? ($resp['data']['error'] ?? null) : null;
    return [
        'ok'    => false,
        'error' => $msg ?? ($resp['error'] ?: ('HTTP ' . $resp['code'])),
    ];
}
