<?php
session_start();
include __DIR__ . '/db.php';

// ── Seguridad: solo proveedores autenticados ──────────────
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'proveedor') {
    header('Location: index.php');
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// ── Helper: subir imagen ──────────────────────────────────
function subirImagen(string $campo): ?string {
    if (empty($_FILES[$campo]['name'])) return null;
    $file    = $_FILES[$campo];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp','gif'];
    if (!in_array($ext, $allowed))        return null;
    if ($file['size'] > 2 * 1024 * 1024) return null;
    $carpeta = __DIR__ . '/uploads/';
    if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);
    $nombre = uniqid('srv_', true) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $carpeta . $nombre);
    return $nombre;
}

// ═══════════════════════════════════════════════════════════
// CREAR
// ═══════════════════════════════════════════════════════════
if ($action === 'create') {
    $titulo      = trim($_POST['titulo']      ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria   = trim($_POST['categoria']   ?? '');
    $municipio   = trim($_POST['municipio']   ?? '');
    $precio      = floatval($_POST['precio']  ?? 0);
    $imagen      = subirImagen('imagen');

    if (empty($titulo)) { header('Location: proveedor_panel.php?err=1'); exit; }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO servicios (titulo, descripcion, categoria, municipio, precio, imagen, usuario_id, es_destacado, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())"
    );
    mysqli_stmt_bind_param($stmt, 'ssssdsi', $titulo, $descripcion, $categoria, $municipio, $precio, $imagen, $uid);

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: proveedor_panel.php?' . ($ok ? 'added=1' : 'err=1'));
    exit;
}

// ═══════════════════════════════════════════════════════════
// EDITAR
// ═══════════════════════════════════════════════════════════
if ($action === 'update') {
    $id          = (int)($_POST['id']         ?? 0);
    $titulo      = trim($_POST['titulo']      ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria   = trim($_POST['categoria']   ?? '');
    $municipio   = trim($_POST['municipio']   ?? '');
    $precio      = floatval($_POST['precio']  ?? 0);
    $nueva_img   = subirImagen('imagen');

    if ($id <= 0 || empty($titulo)) { header('Location: proveedor_panel.php?err=1'); exit; }

    $check = mysqli_prepare($conn, "SELECT id, imagen FROM servicios WHERE id=? AND usuario_id=? LIMIT 1");
    mysqli_stmt_bind_param($check, 'ii', $id, $uid);
    mysqli_stmt_execute($check);
    $row = mysqli_stmt_get_result($check)->fetch_assoc();
    mysqli_stmt_close($check);

    if (!$row) { header('Location: proveedor_panel.php?err=1'); exit; }

    if ($nueva_img && $row['imagen']) {
        $old = __DIR__ . '/uploads/' . $row['imagen'];
        if (file_exists($old)) unlink($old);
    }
    $img_final = $nueva_img ?? $row['imagen'];

    $stmt = mysqli_prepare($conn,
        "UPDATE servicios SET titulo=?, descripcion=?, categoria=?, municipio=?, precio=?, imagen=? WHERE id=? AND usuario_id=?"
    );
    mysqli_stmt_bind_param($stmt, 'ssssdsii', $titulo, $descripcion, $categoria, $municipio, $precio, $img_final, $id, $uid);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: proveedor_panel.php?' . ($ok ? 'ok=1' : 'err=1'));
    exit;
}

// ═══════════════════════════════════════════════════════════
// ELIMINAR
// ═══════════════════════════════════════════════════════════
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { header('Location: proveedor_panel.php?err=1'); exit; }

    $check = mysqli_prepare($conn, "SELECT imagen FROM servicios WHERE id=? AND usuario_id=? LIMIT 1");
    mysqli_stmt_bind_param($check, 'ii', $id, $uid);
    mysqli_stmt_execute($check);
    $row = mysqli_stmt_get_result($check)->fetch_assoc();
    mysqli_stmt_close($check);

    if (!$row) { header('Location: proveedor_panel.php?err=1'); exit; }

    $stmt = mysqli_prepare($conn, "DELETE FROM servicios WHERE id=? AND usuario_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $uid);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok && $row['imagen']) {
        $f = __DIR__ . '/uploads/' . $row['imagen'];
        if (file_exists($f)) unlink($f);
    }
    header('Location: proveedor_panel.php?' . ($ok ? 'del=1' : 'err=1'));
    exit;
}

// ═══════════════════════════════════════════════════════════
// CAMBIAR CONTRASEÑA
// ═══════════════════════════════════════════════════════════
if ($action === 'change_password') {
    $current = trim($_POST['current_password'] ?? '');
    $new     = trim($_POST['new_password'] ?? '');

    if (empty($current) || empty($new)) {
        header('Location: proveedor_panel.php?tab=perfil&err=1');
        exit;
    }

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
            header('Location: proveedor_panel.php?tab=perfil&ok=1');
            exit;
        }
    }
    
    header('Location: proveedor_panel.php?tab=perfil&err=1');
    exit;
}

header('Location: proveedor_panel.php');
exit;
