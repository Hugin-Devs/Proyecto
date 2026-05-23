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
?>
