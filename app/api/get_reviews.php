<?php
require_once __DIR__ . '/../core/auth_guard.php';
include __DIR__ . '/../core/db.php';

header('Content-Type: application/json; charset=utf-8');

$servicio_id = (int)($_GET['servicio_id'] ?? 0);
if ($servicio_id < 1) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido.']);
    exit;
}

// Obtener valoraciones del servicio con nombre anonimizado del cliente
// y si el cliente tiene verificación aprobada
$stmt = mysqli_prepare($conn,
    "SELECT
        v.puntuacion,
        v.comentario,
        v.created_at,
        u.nombre,
        LEFT(u.apellido, 1) AS apellido_inicial,
        (SELECT ver.estado FROM verificaciones ver
         WHERE ver.usuario_id = u.id
         ORDER BY ver.created_at DESC LIMIT 1) AS verificacion_estado
     FROM valoraciones v
     JOIN contrataciones c ON c.id = v.contratacion_id
     JOIN usuarios u ON u.id = v.cliente_id
     WHERE c.servicio_id = ?
     ORDER BY v.created_at DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $servicio_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$reviews = [];
$sum = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $sum += $row['puntuacion'];
    $reviews[] = [
        'puntuacion'   => (int)$row['puntuacion'],
        'comentario'   => $row['comentario'],
        'fecha'        => date('d/m/Y', strtotime($row['created_at'])),
        'nombre'       => $row['nombre'] . ' ' . strtoupper($row['apellido_inicial']) . '.',
        'verificado'   => $row['verificacion_estado'] === 'aprobado',
    ];
}
mysqli_stmt_close($stmt);

$total = count($reviews);
$promedio = $total > 0 ? round($sum / $total, 1) : 0;

echo json_encode([
    'ok'       => true,
    'reviews'  => $reviews,
    'total'    => $total,
    'promedio' => $promedio,
]);
