<?php
include __DIR__ . '/../../app/core/db.php';

$updates = [
    1 => 'srv_6997df9334fb93.54144178.jpg',
    2 => 'srv_6997dff9868110.56492246.jpg',
    3 => 'srv_6999c904e43622.78016189.png',
    4 => 'srv_69be3f249f6f77.71725512.png'
];

foreach ($updates as $id => $img) {
    mysqli_query($conn, "UPDATE servicios SET imagen = '$img' WHERE id = $id AND (imagen IS NULL OR imagen = '')");
    echo "Servicio $id actualizado con imagen $img.\n";
}
echo "Proceso completado.\n";
?>
