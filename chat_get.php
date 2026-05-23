<?php
// chat_get.php — Devuelve mensajes de una conversación + marca como leídos
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$servicio_id  = (int)($_GET['servicio_id']  ?? 0);
$proveedor_id = (int)($_GET['proveedor_id'] ?? 0);
$cliente_id   = (int)($_GET['cliente_id']   ?? 0);
$desde_id     = (int)($_GET['desde_id']     ?? 0); // para polling incremental
$mi_id        = (int)$_SESSION['user_id'];

if ($servicio_id <= 0 || $proveedor_id <= 0 || $cliente_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

// Seguridad: solo los participantes pueden leer la conversación
if ($mi_id !== $cliente_id && $mi_id !== $proveedor_id) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Marcar como leídos los mensajes del otro participante
$stmt_read = mysqli_prepare($conn,
    "UPDATE chat_mensajes
     SET leido = 1
     WHERE servicio_id = ? AND cliente_id = ? AND proveedor_id = ?
       AND emisor_id != ? AND leido = 0"
);
mysqli_stmt_bind_param($stmt_read, 'iiii',
    $servicio_id, $cliente_id, $proveedor_id, $mi_id
);
mysqli_stmt_execute($stmt_read);

// Traer mensajes (todos o solo los nuevos si se envía desde_id)
$extra = $desde_id > 0 ? " AND id > $desde_id" : "";
$stmt = mysqli_prepare($conn,
    "SELECT id, emisor_id, mensaje, leido, created_at
     FROM chat_mensajes
     WHERE servicio_id = ? AND cliente_id = ? AND proveedor_id = ?
       $extra
     ORDER BY created_at ASC
     LIMIT 200"
);
mysqli_stmt_bind_param($stmt, 'iii', $servicio_id, $cliente_id, $proveedor_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$mensajes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $mensajes[] = [
        'id'         => (int)$row['id'],
        'emisor_id'  => (int)$row['emisor_id'],
        'es_mio'     => (int)$row['emisor_id'] === $mi_id,
        'mensaje'    => htmlspecialchars($row['mensaje']),
        'leido'      => (bool)$row['leido'],
        'created_at' => $row['created_at'],
    ];
}

echo json_encode(['ok' => true, 'mensajes' => $mensajes, 'mi_id' => $mi_id]);
