<?php
include "db.php";
$res = mysqli_query($conn, "INSERT INTO valoraciones (contratacion_id, cliente_id, proveedor_id, puntuacion, comentario) VALUES (1, 2, 1, 5, 'Excelente')");
if (!$res) echo "Error: " . mysqli_error($conn) . "\n";
else echo "Inserted!\n";
