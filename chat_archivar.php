<?php
// chat_archivar.php — Archivar o desarchivar una conversación
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$servicio_id  = (int)($_POST['servicio_id']  ?? 0);
$cliente_id   = (int)($_POST['cliente_id']   ?? 0);
$proveedor_id = (int)($_POST['proveedor_id'] ?? 0);
$archivar     = (int)($_POST['archivar']     ?? 1); // 1 = archivar, 0 = desarchivar
$mi_id        = (int)$_SESSION['user_id'];

if ($servicio_id <= 0 || $cliente_id <= 0 || $proveedor_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

// Sólo los participantes pueden archivar
if ($mi_id !== $cliente_id && $mi_id !== $proveedor_id) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Determinar qué columna actualizar según quién es
$columna = ($mi_id === $proveedor_id) ? 'archivado_proveedor' : 'archivado_cliente';
$valor   = $archivar ? 1 : 0;

$stmt = mysqli_prepare($conn,
    "UPDATE chat_mensajes
     SET $columna = ?
     WHERE servicio_id = ? AND cliente_id = ?"
);
mysqli_stmt_bind_param($stmt, 'iii', $valor, $servicio_id, $cliente_id);

if (mysqli_stmt_execute($stmt)) {
    $tipo_evento = $archivar ? 'chat_archivado' : 'chat_desarchivado';
    audit($tipo_evento, $mi_id, 'chat_mensajes', null, "Servicio #$servicio_id | Cliente #$cliente_id | Columna: $columna");
    echo json_encode([
        'ok'       => true,
        'archivado'=> (bool)$valor,
        'columna'  => $columna,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al actualizar']);
}
