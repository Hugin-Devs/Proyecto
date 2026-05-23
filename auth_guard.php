<?php
session_start();

header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Verificar sesión válida con datos reales
$valid = !empty($_SESSION['user_id']) 
      && !empty($_SESSION['user_role']) 
      && !empty($_SESSION['user_name']);

if (!$valid) {
    // Limpiar cualquier resto de sesión corrupta
    session_unset();
    session_destroy();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    header('Location: login.php');
    exit;
}

function esAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}
function nombreUsuario(): string {
    return htmlspecialchars($_SESSION['user_name'] ?? 'Usuario');
}
function idUsuario(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function requireAdmin(): void {
    if (!esAdmin()) {
        header('Location: index.php');
        exit;
    }
}
