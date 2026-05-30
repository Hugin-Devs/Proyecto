<?php
include_once __DIR__ . '/db.php';

function getMunicipios($conn) {
    $result = mysqli_query($conn, "SELECT * FROM municipios ORDER BY nombre ASC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getCategorias($conn) {
    $result = mysqli_query($conn, "SELECT * FROM categorias ORDER BY nombre ASC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Elimina tildes y pasa a minúsculas para data-attributes
function slugify(string $str): string {
    $from = ['á','é','í','ó','ú','ü','ñ','Á','É','Í','Ó','Ú','Ü','Ñ'];
    $to   = ['a','e','i','o','u','u','n','a','e','i','o','u','u','n'];
    return strtolower(str_replace($from, $to, $str));
}
