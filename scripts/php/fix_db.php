<?php
include __DIR__ . '/../../app/core/db.php';

/**
 * Función para agregar una columna si no existe (compatible con versiones viejas de MySQL)
 */
function addColumnSafe($conn, $table, $column, $definition) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (mysqli_num_rows($res) == 0) {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if (mysqli_query($conn, $sql)) {
            echo "✔ Columna '$column' agregada a '$table'.<br>";
        } else {
            echo "❌ Error agregando '$column' a '$table': " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "ℹ La columna '$column' ya existe en '$table'.<br>";
    }
}

echo "<h2>Migración de Base de Datos</h2>";

// Tablas y columnas
addColumnSafe($conn, 'usuarios', 'updated_at', "DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
addColumnSafe($conn, 'usuarios', 'deleted_at', "DATETIME NULL DEFAULT NULL");

addColumnSafe($conn, 'servicios', 'updated_at', "DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
addColumnSafe($conn, 'servicios', 'deleted_at', "DATETIME NULL DEFAULT NULL");

echo "<br><a href='../../app/pages/admin_panel.php'>Ir al Panel de Administración</a>";
?>
