<?php
include __DIR__ . '/../../app/core/db.php';

echo "<h2>Reset de Administrador y Base de Datos</h2>";

// 1. Asegurar columnas de auditoría
function checkColumn($conn, $table, $column, $definition) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (mysqli_num_rows($res) == 0) {
        mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "✔ Columna '$column' agregada a '$table'.<br>";
    }
}

checkColumn($conn, 'usuarios', 'updated_at', "DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
checkColumn($conn, 'usuarios', 'deleted_at', "DATETIME NULL DEFAULT NULL");
checkColumn($conn, 'servicios', 'updated_at', "DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
checkColumn($conn, 'servicios', 'deleted_at', "DATETIME NULL DEFAULT NULL");

// 2. Crear o actualizar el usuario admin
$admin_email = 'admin@servijob.com';
$admin_pass  = 'admin1234';
$hash = password_hash($admin_pass, PASSWORD_BCRYPT);

// Verificar si existe
$check = mysqli_query($conn, "SELECT id FROM usuarios WHERE email = '$admin_email' LIMIT 1");

if (mysqli_num_rows($check) > 0) {
    // Actualizar
    $sql = "UPDATE usuarios SET password = '$hash', role = 'admin', deleted_at = NULL WHERE email = '$admin_email'";
    if (mysqli_query($conn, $sql)) {
        echo "✔ Usuario admin actualizado correctamente.<br>";
    } else {
        echo "❌ Error actualizando admin: " . mysqli_error($conn) . "<br>";
    }
} else {
    // Insertar
    $sql = "INSERT INTO usuarios (nombre, apellido, email, password, role, created_at) 
            VALUES ('Admin', 'ServiJob', '$admin_email', '$hash', 'admin', NOW())";
    if (mysqli_query($conn, $sql)) {
        echo "✔ Usuario admin creado correctamente.<br>";
    } else {
        echo "❌ Error creando admin: " . mysqli_error($conn) . "<br>";
    }
}

echo "<br><b>Credenciales:</b><br>";
echo "Email: $admin_email<br>";
echo "Password: $admin_pass<br>";
echo "<br><a href='login.php'>Ir al Login</a>";
?>
