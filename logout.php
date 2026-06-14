<?php
session_start();
include __DIR__ . '/app/core/db.php';
audit('logout', $_SESSION['user_id'] ?? null, 'usuarios', $_SESSION['user_id'] ?? null, "Logout");
session_unset();
session_destroy();

// Borrar la cookie de sesión del navegador explícitamente
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// No cachear esta página tampoco
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: login.php');
exit;
