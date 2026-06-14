<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "service_libre";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Charset UTF-8 para evitar problemas con tildes
mysqli_set_charset($conn, "utf8mb4");

// Helper global de auditoría (Fase 2)
if (!function_exists('audit')) {
    function audit(string $tipo, ?int $usuario_id = null, ?string $entidad = null, ?int $entidad_id = null, ?string $desc = null): void {
        global $conn;
        if (!$conn) return;
        try {
            $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $conn->prepare(
                "INSERT INTO audit_log (usuario_id, tipo, entidad, entidad_id, descripcion, ip) VALUES (?,?,?,?,?,?)"
            );
            $stmt->bind_param('ississ', $usuario_id, $tipo, $entidad, $entidad_id, $desc, $ip);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // Silenciosa — nunca interrumpe el flujo principal
        }
    }
}
