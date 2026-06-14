<?php
session_start();
include __DIR__ . '/../core/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../register.php');
    exit;
}

// ── Recoger datos (NO trim en passwords) ─────
$nombre    = trim($_POST['nombre']    ?? '');
$apellido  = trim($_POST['apellido']  ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$telefono  = trim($_POST['telefono']  ?? '');
$password  = $_POST['password']  ?? '';
$password2 = $_POST['password2'] ?? '';
$role      = trim($_POST['role']      ?? 'cliente');

if (!in_array($role, ['cliente', 'proveedor'])) {
    $role = 'cliente';
}

// ── Validaciones ──────────────────────────────
if (empty($nombre) || empty($email) || empty($password)) {
    header('Location: ../../register.php?error=campos_vacios');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../../register.php?error=email_invalido');
    exit;
}

if ($password !== $password2) {
    header('Location: ../../register.php?error=passwords_no_coinciden');
    exit;
}

if (strlen($password) < 6) {
    header('Location: ../../register.php?error=password_corta');
    exit;
}

// ── Email duplicado ───────────────────────────
$check = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE email = ? LIMIT 1");
if (!$check) {
    header('Location: ../../register.php?error=db_error');
    exit;
}
mysqli_stmt_bind_param($check, 's', $email);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    header('Location: ../../register.php?error=email_existente');
    exit;
}
mysqli_stmt_close($check);

// ── Hashear e insertar ────────────────────────
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = mysqli_prepare($conn,
    "INSERT INTO usuarios (nombre, apellido, email, telefono, password, role, created_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())"
);
if (!$stmt) {
    error_log('prepare error: ' . mysqli_error($conn));
    header('Location: ../../register.php?error=db_error');
    exit;
}

mysqli_stmt_bind_param($stmt, 'ssssss', $nombre, $apellido, $email, $telefono, $hash, $role);

if (!mysqli_stmt_execute($stmt)) {
    error_log('execute error: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    header('Location: ../../register.php?error=insert_failed');
    exit;
}

$nuevo_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

// ── Proveedor: guardar su servicio ────────────
if ($role === 'proveedor') {
    $negocio   = trim($_POST['nombre_negocio'] ?? '');
    $categoria = trim($_POST['categoria']      ?? 'Otro');
    $municipio = trim($_POST['municipio']      ?? '');
    $precio    = floatval($_POST['precio']     ?? 0);

    if (!empty($negocio)) {
        $ins = mysqli_prepare($conn,
            "INSERT INTO servicios (titulo, categoria, municipio, precio, usuario_id, es_destacado, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())"
        );
        if ($ins) {
            mysqli_stmt_bind_param($ins, 'sssdi', $negocio, $categoria, $municipio, $precio, $nuevo_id);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }
    }
}

// ── Sesión e ir al panel ──────────────────────
$_SESSION['user_id']   = $nuevo_id;
$_SESSION['user_name'] = $nombre;
$_SESSION['user_role'] = $role;

audit('register', $nuevo_id, 'usuarios', $nuevo_id, "Registro exitoso: " . $email . " como " . $role);

header('Location: ../../index.php');
exit;
