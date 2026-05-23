<?php
session_start();
require_once __DIR__ . '/auth_guard.php';

// Solo admin puede ver estos documentos
requireAdmin();

include __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("ID inválido.");
}

$stmt = mysqli_prepare($conn, "SELECT doc_path FROM verificaciones WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
    die("Documento no encontrado en la base de datos.");
}

$file_path = __DIR__ . '/uploads/docs/' . $row['doc_path'];

if (!file_exists($file_path)) {
    die("El archivo físico no existe en el servidor.");
}

// Determinar el Content-Type basado en la extensión
$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_types = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];

$content_type = $mime_types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $content_type);
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file_path));

readfile($file_path);
exit;
