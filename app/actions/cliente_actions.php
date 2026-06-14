<?php
require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../index.php');
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'change_password') {
    $uid = idUsuario();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';

    // Fetch current hash
    $stmt = mysqli_prepare($conn, "SELECT password FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($user && password_verify($current, $user['password'])) {
        $new_hash = password_hash($new, PASSWORD_BCRYPT);
        $stmt_upd = mysqli_prepare($conn, "UPDATE usuarios SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_upd, 'si', $new_hash, $uid);
        if (mysqli_stmt_execute($stmt_upd)) {
            audit('password_cambiada', $uid, 'usuarios', $uid, "Cambió su contraseña (cliente)");
            header('Location: ../../index.php?tab=perfil&ok=1');
            exit;
        }
    }
    
    header('Location: ../../index.php?tab=perfil&err=1');
    exit;
}

header('Location: ../../index.php');
exit;
