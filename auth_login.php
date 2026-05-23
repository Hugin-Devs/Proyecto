<?php
session_start();
include __DIR__ . '/db.php';

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');
// ──────────────────────────────────────────────
// ACCESO UNIFICADO (CLIENTE, PROVEEDOR, ADMIN)
// ──────────────────────────────────────────────
if (empty($email) || empty($password)) {
    header('Location: login.php?error=campos_vacios');
    exit;
}

$stmt = mysqli_prepare($conn,
    "SELECT id, nombre, password, role FROM usuarios WHERE email = ? AND deleted_at IS NULL LIMIT 1"
);

if (!$stmt) {
    header('Location: login.php?error=db_error');
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$res    = mysqli_stmt_get_result($stmt);
$usuario = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// Verifica contraseña con hash bcrypt
if ($usuario && password_verify($password, $usuario['password'])) {
    $_SESSION['user_id']   = $usuario['id'];
    $_SESSION['user_name'] = $usuario['nombre'];
    $_SESSION['user_role'] = $usuario['role'];

    // Registrar último acceso (opcional)
    $upd = mysqli_prepare($conn,
        "UPDATE usuarios SET last_login = NOW() WHERE id = ?"
    );
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'i', $usuario['id']);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }

    header('Location: index.php');
    exit;
}

// Credenciales incorrectas
header('Location: login.php?error=credenciales_invalidas');
exit;
