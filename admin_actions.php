<?php
session_start();
require_once __DIR__ . '/auth_guard.php';
requireAdmin();

include __DIR__ . '/db.php';

$action = $_POST['action'] ?? '';

// ═══════════════════════════════════════════════════════════
// VERIFICACIONES
// ═══════════════════════════════════════════════════════════
if ($action === 'verify_approve') {
    $id = (int)($_POST['id'] ?? 0);
    
    // Obtener la verificación
    $stmt = mysqli_prepare($conn, "SELECT usuario_id FROM verificaciones WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $verificacion = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($verificacion) {
        // Actualizar estado de verificación
        $stmt_upd = mysqli_prepare($conn, "UPDATE verificaciones SET estado = 'aprobado' WHERE id = ?");
        mysqli_stmt_bind_param($stmt_upd, 'i', $id);
        mysqli_stmt_execute($stmt_upd);
        mysqli_stmt_close($stmt_upd);

        // Actualizar los servicios del usuario a verificados
        $uid = $verificacion['usuario_id'];
        $stmt_srv = mysqli_prepare($conn, "UPDATE servicios SET verificado = 1 WHERE usuario_id = ?");
        mysqli_stmt_bind_param($stmt_srv, 'i', $uid);
        mysqli_stmt_execute($stmt_srv);
        mysqli_stmt_close($stmt_srv);
    }
    
    header('Location: admin_panel.php?tab=verificaciones&msg=aprobado');
    exit;
}

if ($action === 'verify_reject') {
    $id = (int)($_POST['id'] ?? 0);
    
    $stmt = mysqli_prepare($conn, "UPDATE verificaciones SET estado = 'rechazado' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header('Location: admin_panel.php?tab=verificaciones&msg=rechazado');
    exit;
}

// ═══════════════════════════════════════════════════════════
// SERVICIOS
// ═══════════════════════════════════════════════════════════
if ($action === 'toggle_destacado') {
    $id = (int)($_POST['id'] ?? 0);
    
    $stmt = mysqli_prepare($conn, "UPDATE servicios SET es_destacado = NOT es_destacado WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header('Location: admin_panel.php?tab=servicios');
    exit;
}

if ($action === 'delete_service') {
    $id = (int)($_POST['id'] ?? 0);
    
    // Soft Delete
    $stmt = mysqli_prepare($conn, "UPDATE servicios SET deleted_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header('Location: admin_panel.php?tab=servicios&msg=deleted');
    exit;
}

// ═══════════════════════════════════════════════════════════
// USUARIOS
// ═══════════════════════════════════════════════════════════
if ($action === 'delete_user') {
    $id = (int)($_POST['id'] ?? 0);
    $mi_id = idUsuario();
    
    if ($id !== $mi_id && $id > 0) {
        // Soft delete del usuario
        $stmt = mysqli_prepare($conn, "UPDATE usuarios SET deleted_at = NOW() WHERE id = ? AND role != 'admin'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $afectados = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        // Si se eliminó el usuario, también hacer soft delete de sus servicios
        if ($afectados > 0) {
            $stmt_srv = mysqli_prepare($conn, "UPDATE servicios SET deleted_at = NOW() WHERE usuario_id = ?");
            mysqli_stmt_bind_param($stmt_srv, 'i', $id);
            mysqli_stmt_execute($stmt_srv);
            mysqli_stmt_close($stmt_srv);
        }
    }
    
    header('Location: admin_panel.php?tab=usuarios&msg=user_deleted');
    exit;
}

// ═══════════════════════════════════════════════════════════
// CATEGORÍAS
// ═══════════════════════════════════════════════════════════
if ($action === 'add_categoria') {
    $nombre = trim($_POST['nombre'] ?? '');
    
    if (!empty($nombre)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO categorias (nombre) VALUES (?)");
        mysqli_stmt_bind_param($stmt, 's', $nombre);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    header('Location: admin_panel.php?tab=categorias&msg=cat_added');
    exit;
}

header('Location: admin_panel.php');
exit;
