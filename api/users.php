<?php
/**
 * API de Usuarios (solo admin):  api/users.php?action=...
 *   GET  list
 *   POST create        { email, password, full_name, role }
 *   POST update        { id, full_name, role, is_active }
 *   POST set_password  { id, password }
 *   POST delete        { id }
 */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'list';

function user_row_out(array $r): array
{
    return [
        'id'            => (int)$r['id'],
        'email'         => $r['email'],
        'full_name'     => $r['full_name'],
        'role'          => $r['role'],
        'is_active'     => (bool)$r['is_active'],
        'last_login_at' => $r['last_login_at'],
        'created_at'    => $r['created_at'],
    ];
}

switch ($action) {

    case 'list': {
        require_method('GET');
        require_role('admin');
        $rows = db()->query("SELECT id,email,full_name,role,is_active,last_login_at,created_at FROM users ORDER BY created_at DESC")->fetchAll();
        json_out(['ok' => true, 'items' => array_map('user_row_out', $rows)]);
    }

    case 'create': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $b = body_json();
        $email = clean_str($b['email'] ?? '', 190);
        $pass  = (string)($b['password'] ?? '');
        $name  = clean_str($b['full_name'] ?? '', 150);
        $role  = in_array($b['role'] ?? 'editor', ['admin', 'editor'], true) ? $b['role'] : 'editor';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            json_out(['ok' => false, 'error' => 'Correo válido y contraseña de al menos 8 caracteres son obligatorios.'], 422);
        }
        $dup = db()->prepare("SELECT 1 FROM users WHERE email = ?");
        $dup->execute([$email]);
        if ($dup->fetchColumn()) json_out(['ok' => false, 'error' => 'Ya existe un usuario con ese correo.'], 409);

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        db()->prepare("INSERT INTO users (email, password_hash, full_name, role) VALUES (?,?,?,?)")
            ->execute([$email, $hash, $name, $role]);
        $id = (int)db()->lastInsertId();
        audit('user.create', 'users', $id, ['email' => $email, 'role' => $role]);
        json_out(['ok' => true, 'id' => $id], 201);
    }

    case 'update': {
        require_method('POST');
        $me = require_role('admin');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        $name = clean_str($b['full_name'] ?? '', 150);
        $role = in_array($b['role'] ?? 'editor', ['admin', 'editor'], true) ? $b['role'] : 'editor';
        $active = !empty($b['is_active']) ? 1 : 0;

        // Evita que el admin se quite a sí mismo su propio acceso/rol
        if ($id === (int)$me['id'] && ($role !== 'admin' || $active !== 1)) {
            json_out(['ok' => false, 'error' => 'No puede revocar su propio acceso de administrador.'], 409);
        }
        db()->prepare("UPDATE users SET full_name=?, role=?, is_active=? WHERE id=?")
            ->execute([$name, $role, $active, $id]);
        audit('user.update', 'users', $id, ['role' => $role, 'is_active' => (bool)$active]);
        json_out(['ok' => true]);
    }

    case 'set_password': {
        require_method('POST');
        require_role('admin');
        require_csrf();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        $pass = (string)($b['password'] ?? '');
        if (!$id || strlen($pass) < 8) json_out(['ok' => false, 'error' => 'Contraseña de al menos 8 caracteres.'], 422);
        db()->prepare("UPDATE users SET password_hash=? WHERE id=?")
            ->execute([password_hash($pass, PASSWORD_BCRYPT), $id]);
        audit('user.set_password', 'users', $id);
        json_out(['ok' => true]);
    }

    case 'delete': {
        require_method('POST');
        $me = require_role('admin');
        require_csrf();
        $id = (int)(body_json()['id'] ?? 0);
        if (!$id) json_out(['ok' => false, 'error' => 'Falta id.'], 422);
        if ($id === (int)$me['id']) json_out(['ok' => false, 'error' => 'No puede eliminar su propio usuario.'], 409);
        db()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        audit('user.delete', 'users', $id);
        json_out(['ok' => true]);
    }

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
