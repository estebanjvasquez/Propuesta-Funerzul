<?php
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

/** Responde JSON y termina. */
function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Cuerpo JSON de la petición como array. */
function body_json(): array
{
    $d = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($d) ? $d : [];
}

/** Exige uno de los métodos HTTP indicados. */
function require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', $methods, true)) {
        json_out(['ok' => false, 'error' => 'Método no permitido.'], 405);
    }
}

function client_ip(): ?string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip && str_contains($ip, ',')) { $ip = trim(explode(',', $ip)[0]); }
    return $ip !== '' ? substr($ip, 0, 45) : null;
}

/** Cabeceras CORS (solo aplica si el origen está en la lista blanca). */
function cors(): void
{
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = $GLOBALS['CONFIG']['security']['allowed_origins'] ?? [];
    if ($origin && in_array($origin, $allowed, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/** Registra una entrada en la bitácora de auditoría. */
function audit(string $action, ?string $entityType = null, $entityId = null, array $details = []): void
{
    try {
        $u = auth_user();
        $st = db()->prepare(
            "INSERT INTO audit_log (actor_id, actor_email, action, entity_type, entity_id, details, ip_address, user_agent)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $u['id'] ?? null,
            $u['email'] ?? null,
            $action,
            $entityType,
            $entityId !== null ? (string)$entityId : null,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (\Throwable $e) {
        error_log('[obit][audit] ' . $e->getMessage());
    }
}

/** Lee un valor de configuración (string) o $default. */
function get_setting(string $key, $default = null)
{
    $st = db()->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}

function setting_int(string $key, int $default): int
{
    $v = get_setting($key);
    return $v === null ? $default : (int)$v;
}

function setting_bool(string $key, bool $default): bool
{
    $v = get_setting($key);
    if ($v === null) return $default;
    return $v === '1' || $v === 'true';
}

function set_setting(string $key, string $value): void
{
    $u = auth_user();
    db()->prepare(
        "INSERT INTO app_settings (setting_key, setting_value, updated_by)
         VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)"
    )->execute([$key, $value, $u['id'] ?? null]);
}

/** Convierte un texto a slug ASCII para URLs (sin depender de iconv). */
function slugify(string $text): string
{
    $from = ['á','à','ä','â','ã','é','è','ë','ê','í','ì','ï','î','ó','ò','ö','ô','õ','ú','ù','ü','û','ñ','ç',
             'Á','À','Ä','Â','Ã','É','È','Ë','Ê','Í','Ì','Ï','Î','Ó','Ò','Ö','Ô','Õ','Ú','Ù','Ü','Û','Ñ','Ç'];
    $to   = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','n','c',
             'a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','n','c'];
    $t = str_replace($from, $to, trim($text));
    $t = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
    $t = trim($t, '-');
    return $t !== '' ? $t : 'obituario';
}

/** Slug único en la tabla indicada (agrega sufijo numérico si choca). */
function unique_slug(string $base, ?int $ignoreId = null, string $table = 'obituaries'): string
{
    // Lista blanca de tablas con columna slug (evita inyección por $table).
    $allowed = ['obituaries', 'doctors', 'articles'];
    if (!in_array($table, $allowed, true)) { $table = 'obituaries'; }

    $slug = slugify($base);
    $candidate = $slug;
    $i = 2;
    while (true) {
        $sql = "SELECT 1 FROM $table WHERE slug = ?" . ($ignoreId ? " AND id <> ?" : "") . " LIMIT 1";
        $st = db()->prepare($sql);
        $st->execute($ignoreId ? [$candidate, $ignoreId] : [$candidate]);
        if (!$st->fetchColumn()) return $candidate;
        $candidate = $slug . '-' . $i++;
    }
}

/** Sanitiza string de entrada (trim + longitud máx). */
function clean_str($v, int $max = 1000): string
{
    return mb_substr(trim((string)$v), 0, $max);
}

/** Construye la URL pública de una foto a partir de su ruta almacenada. */
function photo_public_url(?string $path): ?string
{
    return $path ? $path : null; // las rutas ya se guardan relativas a la raíz pública
}
