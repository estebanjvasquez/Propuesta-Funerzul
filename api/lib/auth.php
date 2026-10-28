<?php
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

require __DIR__ . '/sso.php';

/**
 * Perfiles del panel = permisos `sitio.*` (docs/specs/2026-10-28-sso-prevision-funeraria.md).
 * Usuarios 'pf': vienen de su rol en la empresa fdz de Prevision-Funeraria.
 * Cuentas locales (emergencia): admin -> sitio.admin; editor -> los otros tres.
 * sitio.admin implica todos.
 */
const SITE_PERMS = [
    'sitio.admin',
    'sitio.obituarios.write',
    'sitio.condolencias.moderate',
    'sitio.contenido.write',
];

/** Usuario autenticado (o null). Cacheado por request. */
function auth_user(): ?array
{
    static $cached = false, $user = null;
    if ($cached) return $user;
    $cached = true;

    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) return $user = null;

    try {
        $st = db()->prepare("SELECT id, email, full_name, role, auth_source, is_active FROM users WHERE id = ? AND is_active = 1");
        $st->execute([$uid]);
    } catch (\PDOException $e) {
        // Migración 18 aún no importada: todas las cuentas son locales.
        $st = db()->prepare("SELECT id, email, full_name, role, 'local' AS auth_source, is_active FROM users WHERE id = ? AND is_active = 1");
        $st->execute([$uid]);
    }
    $u = $st->fetch();
    if ($u && $u['auth_source'] === 'pf' && !sso_session_valid()) {
        // La sesión de PF se cerró, se le quitó el acceso, o PF lleva demasiado sin responder.
        $_SESSION = [];
        session_regenerate_id(true);
        return $user = null;
    }
    return $user = ($u ?: null);
}

/** Permisos `sitio.*` efectivos del usuario actual. */
function user_perms(): array
{
    $u = auth_user();
    if (!$u) return [];
    if ($u['auth_source'] === 'pf') {
        $p = $_SESSION['pf']['permisos'] ?? [];
    } else {
        $p = $u['role'] === 'admin' ? ['sitio.admin'] : array_slice(SITE_PERMS, 1);
    }
    if (in_array('sitio.admin', $p, true)) return SITE_PERMS;
    return array_values(array_intersect(SITE_PERMS, $p));
}

/** true si el usuario tiene AL MENOS UNO de los permisos indicados. */
function can(string ...$perms): bool
{
    return (bool)array_intersect($perms, user_perms());
}

/** Datos públicos del usuario (sin hash). */
function public_user(array $u): array
{
    return [
        'id'          => (int)$u['id'],
        'email'       => $u['email'],
        'full_name'   => $u['full_name'],
        'role'        => $u['role'],
        'auth_source' => $u['auth_source'],
        'perms'       => user_perms(),
    ];
}

function require_auth(): array
{
    $u = auth_user();
    if (!$u) json_out(['ok' => false, 'error' => 'No autenticado.'], 401);
    return $u;
}

/** Exige AL MENOS UNO de los permisos indicados. */
function require_perm(string ...$perms): array
{
    $u = require_auth();
    if (!can(...$perms)) {
        json_out(['ok' => false, 'error' => 'No autorizado.'], 403);
    }
    return $u;
}

/** Cualquier perfil del panel. */
function require_staff(): array { return require_perm(...SITE_PERMS); }

function is_admin(): bool { return can('sitio.admin'); }
function is_staff(): bool { return user_perms() !== []; }

/** Token CSRF de la sesión actual. */
function csrf_token(): string { return $_SESSION['csrf'] ?? ''; }

/** Exige un token CSRF válido en peticiones que mutan datos. */
function require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        json_out(['ok' => false, 'error' => 'Token de seguridad (CSRF) inválido o ausente.'], 419);
    }
}

// --- Límite de intentos del login local (tabla auth_throttle, migración 18) ---
// 5 fallos en 15 min por IP o por correo -> bloqueo de 15 min. Si la tabla aún
// no existe, el login sigue funcionando sin límite (nunca bloquea por error).

const THROTTLE_MAX_FAILURES = 5;

function throttle_keys(string $email): array
{
    // REMOTE_ADDR y no X-Forwarded-For: el encabezado lo pone el cliente y
    // permitiría esquivar el límite por IP.
    return ['ip:' . substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64), 'em:' . hash('sha256', strtolower($email))];
}

function throttle_locked(array $keys): bool
{
    try {
        $st = db()->prepare("SELECT 1 FROM auth_throttle WHERE throttle_key IN (?, ?) AND locked_until > NOW() LIMIT 1");
        $st->execute($keys);
        return (bool)$st->fetchColumn();
    } catch (\PDOException $e) {
        return false;
    }
}

function throttle_fail(array $keys): void
{
    // MySQL evalúa las asignaciones de izquierda a derecha: `failures` ya es el
    // valor nuevo al calcular locked_until; window_start todavía es el viejo.
    $sql = "INSERT INTO auth_throttle (throttle_key, failures, window_start) VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE
              failures     = IF(window_start < NOW() - INTERVAL 15 MINUTE, 1, failures + 1),
              window_start = IF(window_start < NOW() - INTERVAL 15 MINUTE, NOW(), window_start),
              locked_until = IF(failures >= " . THROTTLE_MAX_FAILURES . ", NOW() + INTERVAL 15 MINUTE, locked_until)";
    try {
        $st = db()->prepare($sql);
        foreach ($keys as $k) $st->execute([$k]);
    } catch (\PDOException $e) {
        error_log('[obit][throttle] ' . $e->getMessage());
    }
}

function throttle_clear(array $keys): void
{
    try {
        db()->prepare("DELETE FROM auth_throttle WHERE throttle_key IN (?, ?)")->execute($keys);
    } catch (\PDOException $e) {
        // sin tabla: nada que limpiar
    }
}

/**
 * Login local (correo + clave). Solo cuentas 'local' -- con el inicio de sesión
 * único activo, son las de emergencia. Responde error si falla.
 */
function login_attempt(string $email, string $password): array
{
    $keys = throttle_keys($email);
    if (throttle_locked($keys)) {
        audit('auth.login_locked', 'users', null, ['email' => $email]);
        json_out(['ok' => false, 'error' => 'Demasiados intentos fallidos. Espere 15 minutos e intente de nuevo.'], 429);
    }

    $st = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch();

    // Con o sin usuario se corre un password_verify: mismo tiempo de respuesta.
    $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $passOk = password_verify($password, $hash);
    $local = $u && ($u['auth_source'] ?? 'local') === 'local';

    if (!$u || !$local || (int)$u['is_active'] !== 1 || !$passOk) {
        throttle_fail($keys);
        audit('auth.login_failed', 'users', $u['id'] ?? null, ['email' => $email]);
        json_out(['ok' => false, 'error' => 'Correo o contraseña incorrectos.'], 401);
    }

    throttle_clear($keys);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['csrf']    = bin2hex(random_bytes(16));
    unset($_SESSION['pf']);

    db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$u['id']]);
    audit('auth.login', 'users', $u['id'], ['via' => 'local']);

    $u['auth_source'] = 'local';
    return $u;
}

function logout_current(): void
{
    $u = auth_user();
    if ($u) {
        audit('auth.logout', 'users', $u['id']);
        if ($u['auth_source'] === 'pf') sso_revoke_current();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
