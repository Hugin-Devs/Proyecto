<?php
include "db.php";
$res = mysqli_query($conn, "SELECT * FROM valoraciones");
while($r = mysqli_fetch_assoc($res)) print_r($r);
echo "\nContrataciones:\n";
$res = mysqli_query($conn, "SELECT id, estado FROM contrataciones");
while($r = mysqli_fetch_assoc($res)) print_r($r);
