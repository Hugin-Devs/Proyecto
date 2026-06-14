<?php
session_start();
include __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$uid = idUsuario();
if ($uid <= 0) {
    header('Location: login.php');
    exit;
}

// Determinar URL de redirección según el rol
$role = $_SESSION['user_role'] ?? '';
$redirect_url = ($role === 'proveedor') ? 'proveedor_panel.php?tab=perfil' : 'index.php';
$sep = (strpos($redirect_url, '?') === false) ? '?' : '&';

$nombre    = trim($_POST['nombre'] ?? '');
$municipio = trim($_POST['municipio'] ?? '');

if (empty($nombre) || empty($municipio)) {
    // Redirigir con error (esto debería ser manejado mejor en frontend)
    header("Location: {$redirect_url}{$sep}verify_err=campos_vacios");
    exit;
}

// ── Validar y subir documento ──────────────────────────────────
if (empty($_FILES['id_doc']['name'])) {
    header("Location: {$redirect_url}{$sep}verify_err=sin_documento");
    exit;
}

$file = $_FILES['id_doc'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['pdf', 'jpg', 'jpeg', 'png'];

if (!in_array($ext, $allowed)) {
    header("Location: {$redirect_url}{$sep}verify_err=formato_invalido");
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) { // 5MB
    header("Location: {$redirect_url}{$sep}verify_err=archivo_muy_grande");
    exit;
}

$carpeta = __DIR__ . '/uploads/docs/';
if (!is_dir($carpeta)) {
    mkdir($carpeta, 0755, true);
}

$nombre_archivo = uniqid('doc_', true) . '.' . $ext;
$ruta_destino = $carpeta . $nombre_archivo;

if (!move_uploaded_file($file['tmp_name'], $ruta_destino)) {
    header("Location: {$redirect_url}{$sep}verify_err=error_subida");
    exit;
}

// ── Insertar en BD ─────────────────────────────────────────────
$stmt = mysqli_prepare($conn,
    "INSERT INTO verificaciones (nombre, municipio, doc_path, estado, usuario_id, created_at)
     VALUES (?, ?, ?, 'pendiente', ?, NOW())"
);

if (!$stmt) {
    // Si falla, borramos el archivo que acabamos de subir
    unlink($ruta_destino);
    header("Location: {$redirect_url}{$sep}verify_err=db_error");
    exit;
}

mysqli_stmt_bind_param($stmt, 'sssi', $nombre, $municipio, $nombre_archivo, $uid);

if (mysqli_stmt_execute($stmt)) {
    $verificacion_id = mysqli_insert_id($conn);
    audit('verificacion_solicitada', $uid, 'verificaciones', $verificacion_id, "Solicitud de verificación de identidad, documento: $nombre_archivo");
    mysqli_stmt_close($stmt);
    header("Location: {$redirect_url}{$sep}verify_ok=1");
} else {
    unlink($ruta_destino);
    mysqli_stmt_close($stmt);
    header("Location: {$redirect_url}{$sep}verify_err=db_error");
}
exit;
