<?php
/** Endpoints de autenticación:  api/auth.php?action=login|logout|me */
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        require_method('POST');
        $b = body_json();
        $email = clean_str($b['email'] ?? '', 190);
        $pass  = (string)($b['password'] ?? '');
        if ($email === '' || $pass === '') {
            json_out(['ok' => false, 'error' => 'Ingrese correo y contraseña.'], 422);
        }
        $u = login_attempt($email, $pass);
        json_out(['ok' => true, 'user' => public_user($u), 'csrf' => csrf_token()]);

    case 'logout':
        require_method('POST');
        require_auth();
        require_csrf();
        logout_current();
        json_out(['ok' => true]);

    case 'me':
        $u = auth_user();
        json_out(['ok' => true, 'user' => $u ? public_user($u) : null, 'csrf' => csrf_token()]);

    default:
        json_out(['ok' => false, 'error' => 'Acción no encontrada.'], 404);
}
