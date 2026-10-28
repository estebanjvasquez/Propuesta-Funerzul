<?php
if (!defined('OBIT_APP')) { http_response_code(403); exit('Forbidden'); }

/**
 * "Entrar con Previsión Funeraria": inicio de sesión único del panel contra las
 * cuentas de staff de la empresa fdz en Prevision-Funeraria (PF).
 * Ver docs/specs/2026-10-28-sso-prevision-funeraria.md (contrato del lado de PF:
 * docs/auth/sso-sitios.md de ese repo).
 *
 * OAuth 2.0 authorization code + PKCE S256. El canje y la revalidación van
 * servidor a servidor por cURL; el navegador nunca ve el client_secret ni el
 * token de acceso (quedan en la sesión PHP).
 */

const SSO_REVALIDATE_SECONDS = 300;   // cada cuánto se pregunta a PF si la sesión sigue viva
const SSO_OUTAGE_GRACE_SECONDS = 1800; // PF caído: se tolera hasta 30 min sin poder verificar
const SSO_STATE_TTL_SECONDS = 600;

/** Configuración del bloque 'pf_sso' de config.php, o null si falta algo (SSO apagado). */
function sso_config(): ?array
{
    $c = $GLOBALS['CONFIG']['pf_sso'] ?? null;
    if (!is_array($c)) return null;
    foreach (['base_url', 'client_id', 'client_secret', 'redirect_uri'] as $k) {
        if (!isset($c[$k]) || !is_string($c[$k]) || $c[$k] === '') return null;
    }
    if (!is_https_url($c['base_url']) || !is_https_url($c['redirect_uri'])) return null;
    $c['base_url'] = rtrim($c['base_url'], '/');
    return $c;
}

function sso_enabled(): bool { return sso_config() !== null; }

/** Base URL del sitio relativa a la raíz (ej. '/funerzul'), para redirigir al panel. */
function sso_admin_url(array $query = []): string
{
    $base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/x.php'))), '/');
    return $base . '/admin.html' . ($query ? '?' . http_build_query($query) : '');
}

function sso_redirect(string $url): void
{
    header('Cache-Control: no-store');
    header('Location: ' . $url, true, 302);
    exit;
}

function sso_b64url(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }

/** POST/GET a PF. Devuelve ['code' => int, 'data' => ?array]; code 0 = sin respuesta. */
function sso_http(string $method, string $path, array $form = [], ?string $bearer = null): array
{
    $cfg = sso_config();
    $ch = curl_init($cfg['base_url'] . $path);
    $headers = ['Accept: application/json'];
    if ($bearer !== null) $headers[] = 'Authorization: Bearer ' . $bearer;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($method === 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    $resp = curl_exec($ch);
    $code = $resp === false ? 0 : (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $data = is_string($resp) ? json_decode($resp, true) : null;
    return ['code' => $code, 'data' => is_array($data) ? $data : null];
}

/** Solo los permisos del sitio que este panel entiende. */
function sso_clean_perms($perms): array
{
    if (!is_array($perms)) return [];
    return array_values(array_intersect(SITE_PERMS, array_filter($perms, 'is_string')));
}

/** Paso 1: genera state + PKCE y manda al navegador a PF. */
function sso_start(): void
{
    $cfg = sso_config();
    if (!$cfg) sso_redirect(sso_admin_url(['sso_error' => 'no_configurado']));

    $verifier = sso_b64url(random_bytes(48));
    $state    = sso_b64url(random_bytes(24));
    $_SESSION['sso'] = ['state' => $state, 'verifier' => $verifier, 'at' => time()];

    sso_redirect($cfg['base_url'] . '/oauth/authorize?' . http_build_query([
        'response_type'         => 'code',
        'client_id'             => $cfg['client_id'],
        'redirect_uri'          => $cfg['redirect_uri'],
        'state'                 => $state,
        'code_challenge'        => sso_b64url(hash('sha256', $verifier, true)),
        'code_challenge_method' => 'S256',
    ]));
}

/** Paso 2: PF vuelve con ?code&state (o ?error). Canje servidor a servidor y sesión local. */
function sso_callback(): void
{
    $cfg = sso_config();
    if (!$cfg) sso_redirect(sso_admin_url(['sso_error' => 'no_configurado']));

    $pending = $_SESSION['sso'] ?? null;
    unset($_SESSION['sso']);
    $state = (string)($_GET['state'] ?? '');
    if (!is_array($pending) || $state === '' || !hash_equals((string)$pending['state'], $state)
        || time() - (int)$pending['at'] > SSO_STATE_TTL_SECONDS) {
        sso_redirect(sso_admin_url(['sso_error' => 'expirado']));
    }
    if (isset($_GET['error'])) {
        audit('auth.sso_denied', 'users', null, ['error' => clean_str($_GET['error'], 40)]);
        sso_redirect(sso_admin_url(['sso_error' => $_GET['error'] === 'access_denied' ? 'sin_permiso' : 'fallo']));
    }

    $r = sso_http('POST', '/oauth/token', [
        'grant_type'    => 'authorization_code',
        'client_id'     => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'code'          => (string)($_GET['code'] ?? ''),
        'code_verifier' => (string)$pending['verifier'],
        'redirect_uri'  => $cfg['redirect_uri'],
    ]);
    $d = $r['data'];
    $perms = sso_clean_perms($d['permisos'] ?? null);
    if ($r['code'] !== 200 || !$d || empty($d['access_token']) || empty($d['usuario']['id'])
        || ($d['tenant'] ?? '') !== 'fdz' || !$perms) {
        error_log('[obit][sso] canje fallido: HTTP ' . $r['code']);
        sso_redirect(sso_admin_url(['sso_error' => $r['code'] === 0 ? 'pf_caido' : 'fallo']));
    }

    $user = sso_upsert_user($d['usuario'], $perms);
    if (!$user) sso_redirect(sso_admin_url(['sso_error' => 'desactivado']));

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['csrf']    = bin2hex(random_bytes(16));
    $now = time();
    $_SESSION['pf'] = [
        'token'      => (string)$d['access_token'],
        'permisos'   => $perms,
        'checked_at' => $now,
        'ok_at'      => $now,
        'expires_at' => $now + max(0, (int)($d['expires_in'] ?? 0)),
    ];
    db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
    audit('auth.login', 'users', $user['id'], ['via' => 'pf', 'permisos' => $perms]);
    sso_redirect(sso_admin_url());
}

/**
 * Crea o actualiza la fila local del usuario de PF (la usan auditoría y las
 * columnas created_by). Se vincula por pf_user_id; la primera vez, por correo
 * (convierte una cuenta local con el mismo correo -- por eso las cuentas de
 * emergencia deben usar un correo propio). null si la cuenta está desactivada
 * aquí (interruptor local además del de PF).
 */
function sso_upsert_user(array $pf, array $perms): ?array
{
    $pfId  = (int)$pf['id'];
    $email = clean_str($pf['email'] ?? '', 190);
    $name  = clean_str($pf['nombre'] ?? '', 150);
    $role  = in_array('sitio.admin', $perms, true) ? 'admin' : 'editor';

    $st = db()->prepare("SELECT * FROM users WHERE pf_user_id = ? LIMIT 1");
    $st->execute([$pfId]);
    $u = $st->fetch();
    if (!$u && $email !== '') {
        $st = db()->prepare("SELECT * FROM users WHERE email = ? AND pf_user_id IS NULL LIMIT 1");
        $st->execute([$email]);
        $u = $st->fetch();
    }

    if ($u) {
        if ((int)$u['is_active'] !== 1) return null;
        db()->prepare("UPDATE users SET email = ?, full_name = ?, role = ?, auth_source = 'pf', pf_user_id = ? WHERE id = ?")
            ->execute([$email ?: $u['email'], $name, $role, $pfId, $u['id']]);
        if ($u['auth_source'] !== 'pf') audit('user.linked_pf', 'users', $u['id'], ['pf_user_id' => $pfId]);
        return ['id' => (int)$u['id']];
    }

    // Clave aleatoria imposible de usar: las cuentas 'pf' no entran por el login local.
    db()->prepare("INSERT INTO users (email, password_hash, full_name, role, auth_source, pf_user_id) VALUES (?,?,?,?, 'pf', ?)")
        ->execute([$email, password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT), $name, $role, $pfId]);
    $id = (int)db()->lastInsertId();
    audit('user.create', 'users', $id, ['via' => 'pf', 'pf_user_id' => $pfId]);
    return ['id' => $id];
}

/**
 * ¿Sigue viva la sesión de PF que originó esta sesión? La llama auth_user() en
 * cada request de un usuario 'pf'; solo consulta a PF cada SSO_REVALIDATE_SECONDS.
 * Actualiza los permisos en la sesión. false = cerrar la sesión local.
 */
function sso_session_valid(): bool
{
    $pf = $_SESSION['pf'] ?? null;
    if (!is_array($pf) || empty($pf['token']) || !sso_enabled()) return false;
    $now = time();
    if ($now >= (int)$pf['expires_at']) return false;
    if ($now - (int)$pf['checked_at'] < SSO_REVALIDATE_SECONDS) return true;

    $r = sso_http('GET', '/oauth/userinfo', [], (string)$pf['token']);
    if ($r['code'] === 200 && $r['data']) {
        $perms = sso_clean_perms($r['data']['permisos'] ?? null);
        if (!$perms) return false;
        $_SESSION['pf']['permisos'] = $perms;
        $_SESSION['pf']['checked_at'] = $_SESSION['pf']['ok_at'] = $now;
        return true;
    }
    if ($r['code'] === 401 || $r['code'] === 403) return false;

    // PF no responde: se reintenta en 1 min y se tolera la caída un rato.
    error_log('[obit][sso] userinfo sin respuesta válida: HTTP ' . $r['code']);
    $_SESSION['pf']['checked_at'] = $now - SSO_REVALIDATE_SECONDS + 60;
    return $now - (int)$pf['ok_at'] <= SSO_OUTAGE_GRACE_SECONDS;
}

/** Al cerrar sesión aquí: revoca el token en PF (la sesión de PF sigue abierta). */
function sso_revoke_current(): void
{
    $cfg = sso_config();
    $token = $_SESSION['pf']['token'] ?? '';
    if (!$cfg || $token === '') return;
    sso_http('POST', '/oauth/revoke', [
        'client_id'     => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'token'         => $token,
    ]);
}
