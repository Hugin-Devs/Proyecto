<?php
session_start();

header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Construir la URL base del proyecto (ej: /Proyecto o /)
function _base_url(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Subir hasta la raíz del proyecto (donde está index.php)
    // La raíz es el directorio que contiene index.php, login.php, etc.
    // Detectamos la raíz buscando cuántos niveles está el archivo actual del root
    $root = dirname(__DIR__, 2); // app/core -> app -> raíz del proyecto
    $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $base = str_replace($doc_root, '', $root);
    return rtrim(str_replace('\\', '/', $base), '/');
}

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
    header('Location: ' . _base_url() . '/login.php');
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
        header('Location: ' . _base_url() . '/index.php');
        exit;
    }
}
