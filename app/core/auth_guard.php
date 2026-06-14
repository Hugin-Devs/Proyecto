<?php
session_start();

header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Construir la URL base del proyecto (ej: /Proyecto o /)
function _base_url(): string {
    // Normalizar barras tanto en la ruta del proyecto como en el DOCUMENT_ROOT
    $root = str_replace('\\', '/', dirname(__DIR__, 2));
    $doc_root = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
    
    // En Windows las letras de unidad (C:) pueden variar en case.
    // Comparamos en minúsculas para encontrar el prefijo correcto.
    if (strpos(strtolower($root), strtolower($doc_root)) === 0) {
        $base = substr($root, strlen($doc_root));
    } else {
        // Fallback básico
        $base = str_replace($doc_root, '', $root);
    }
    
    return rtrim($base, '/');
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
    // ── REDIRECCIÓN PRINCIPAL A LA LANDING PAGE ──
    // Si el usuario no tiene una sesión válida y entra a una página protegida
    // (como index.php que es el panel de cliente o la raíz), el sistema lo enviará 
    // automáticamente a home.php para que vea la pantalla de inicio pública.
    header('Location: ' . _base_url() . '/home.php');
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
