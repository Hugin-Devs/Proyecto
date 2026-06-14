<?php
session_start();
include __DIR__ . '/../core/db.php';

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../login.php');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');
// ──────────────────────────────────────────────
// ACCESO UNIFICADO (CLIENTE, PROVEEDOR, ADMIN)
// ──────────────────────────────────────────────
if (empty($email) || empty($password)) {
    header('Location: ../../login.php?error=campos_vacios');
    exit;
}

// Bloqueo por intentos fallidos (mejora de seguridad)
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$stmt_intentos = mysqli_prepare($conn,
    "SELECT COUNT(*) as c FROM audit_log
     WHERE tipo = 'login_fallido' AND ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
);
if ($stmt_intentos) {
    mysqli_stmt_bind_param($stmt_intentos, 's', $ip);
    mysqli_stmt_execute($stmt_intentos);
    $row_intentos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_intentos));
    mysqli_stmt_close($stmt_intentos);
    if (($row_intentos['c'] ?? 0) >= 5) {
        header('Location: ../../login.php?error=bloqueado');
        exit;
    }
}

$stmt = mysqli_prepare($conn,
    "SELECT id, nombre, password, role, suspendido_at FROM usuarios WHERE email = ? AND deleted_at IS NULL LIMIT 1"
);

if (!$stmt) {
    header('Location: ../../login.php?error=db_error');
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$res    = mysqli_stmt_get_result($stmt);
$usuario = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// Verificar si el usuario está suspendido
if ($usuario && $usuario['suspendido_at'] !== null) {
    header('Location: ../../login.php?error=suspendido');
    exit;
}

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

    audit('login', $usuario['id'], 'usuarios', $usuario['id'], "Login unificado exitoso");

    header('Location: ../../index.php');
    exit;
}

// Credenciales incorrectas
audit('login_fallido', null, 'usuarios', null, "Intento de login fallido para: " . $email);
header('Location: ../../login.php?error=credenciales_invalidas');
exit;
