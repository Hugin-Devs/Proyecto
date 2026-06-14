<?php
// chat_send.php
require_once __DIR__ . '/../core/auth_guard.php';
require_once __DIR__ . '/../core/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$servicio_id  = (int)($_POST['servicio_id']  ?? 0);
$proveedor_id = (int)($_POST['proveedor_id'] ?? 0);
$mensaje      = trim($_POST['mensaje']        ?? '');
$mi_id        = (int)$_SESSION['user_id'];

// ── Validaciones básicas ─────────────────────────────────────
if ($mensaje === '') {
    echo json_encode(['ok' => false, 'error' => 'El mensaje no puede estar vacío']);
    exit;
}
if (mb_strlen($mensaje) > 1000) {
    echo json_encode(['ok' => false, 'error' => 'Mensaje demasiado largo (máx 1000 caracteres)']);
    exit;
}
if ($servicio_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Servicio inválido']);
    exit;
}

// ── Si proveedor_id no vino, buscarlo en BD ──────────────────
if ($proveedor_id <= 0) {
    $chk = mysqli_prepare($conn, "SELECT usuario_id FROM servicios WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    mysqli_stmt_bind_param($chk, 'i', $servicio_id);
    mysqli_stmt_execute($chk);
    $row_srv = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    mysqli_stmt_close($chk);
    $proveedor_id = (int)($row_srv['usuario_id'] ?? 0);
}

if ($proveedor_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Este servicio no tiene proveedor asignado aún.']);
    exit;
}

// ── Determinar roles: ¿soy el proveedor o el cliente? ────────
$soy_proveedor = ($mi_id === $proveedor_id);
$cliente_id_post = (int)($_POST['cliente_id'] ?? 0);

if ($soy_proveedor) {
    // El proveedor responde → el cliente viene del POST
    if ($cliente_id_post <= 0) {
        echo json_encode(['ok' => false, 'error' => 'No se identificó al cliente destinatario']);
        exit;
    }
    $cliente_id = $cliente_id_post;
} else {
    // El cliente escribe
    if ($mi_id === $proveedor_id) {
        echo json_encode(['ok' => false, 'error' => 'No puedes enviarte mensajes a ti mismo.']);
        exit;
    }
    $cliente_id = $mi_id;
}

// ── Insertar mensaje ─────────────────────────────────────────
$stmt = mysqli_prepare($conn,
    "INSERT INTO chat_mensajes
        (servicio_id, cliente_id, proveedor_id, emisor_id, mensaje)
     VALUES (?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, 'iiiis',
    $servicio_id, $cliente_id, $proveedor_id, $mi_id, $mensaje
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'ok'         => true,
        'id'         => mysqli_insert_id($conn),
        'emisor_id'  => $mi_id,
        'mensaje'    => htmlspecialchars($mensaje),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al guardar el mensaje']);
}