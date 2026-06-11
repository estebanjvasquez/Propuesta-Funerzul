<?php
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

/** Usuario autenticado (o null). Cacheado por request. */
function auth_user(): ?array
{
    static $cached = false, $user = null;
    if ($cached) return $user;
    $cached = true;

    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) return $user = null;

    $st = db()->prepare("SELECT id, email, full_name, role, is_active FROM users WHERE id = ? AND is_active = 1");
    $st->execute([$uid]);
    $u = $st->fetch();
    return $user = ($u ?: null);
}

/** Datos públicos del usuario (sin hash). */
function public_user(array $u): array
{
    return [
        'id'        => (int)$u['id'],
        'email'     => $u['email'],
        'full_name' => $u['full_name'],
        'role'      => $u['role'],
    ];
}

function require_auth(): array
{
    $u = auth_user();
    if (!$u) json_out(['ok' => false, 'error' => 'No autenticado.'], 401);
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_auth();
    if (!in_array($u['role'], $roles, true)) {
        json_out(['ok' => false, 'error' => 'No autorizado.'], 403);
    }
    return $u;
}

function is_admin(): bool { $u = auth_user(); return $u && $u['role'] === 'admin'; }
function is_staff(): bool { $u = auth_user(); return $u && in_array($u['role'], ['admin', 'editor'], true); }

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

/** Intenta iniciar sesión; responde error si falla. */
function login_attempt(string $email, string $password): array
{
    $st = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u || (int)$u['is_active'] !== 1 || !password_verify($password, $u['password_hash'])) {
        audit('auth.login_failed', 'users', $u['id'] ?? null, ['email' => $email]);
        json_out(['ok' => false, 'error' => 'Correo o contraseña incorrectos.'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['csrf']    = bin2hex(random_bytes(16));

    db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$u['id']]);
    audit('auth.login', 'users', $u['id']);

    return $u;
}

function logout_current(): void
{
    if (auth_user()) audit('auth.logout', 'users', auth_user()['id']);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
