<?php
include __DIR__ . '/../core/db.php';
include_once __DIR__ . '/../api/get_lists.php';

// 1. PARÁMETROS DE FILTRADO Y PAGINACIÓN
$page      = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$limit     = 8;
$offset    = ($page - 1) * $limit;

$municipio_f = $_GET['municipio'] ?? 'todos';
$categoria_f = $_GET['categoria'] ?? 'todas';
$search_f    = trim($_GET['q'] ?? '');

// 2. CONSTRUIR LA CONSULTA BASE
$where_clauses = ["s.deleted_at IS NULL"];
$params = [];
$types  = "";

if ($municipio_f !== 'todos') {
    $where_clauses[] = "s.municipio = ?";
    $params[] = $municipio_f;
    $types   .= "s";
}
if ($categoria_f !== 'todas') {
    $where_clauses[] = "s.categoria = ?";
    $params[] = $categoria_f;
    $types   .= "s";
}
if ($search_f !== '') {
    $where_clauses[] = "(s.titulo LIKE ? OR s.descripcion LIKE ?)";
    $search_param = "%$search_f%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types   .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);

// 3. CONTAR TOTAL PARA PAGINACIÓN
$count_query = "SELECT COUNT(*) as total FROM servicios s WHERE $where_sql";
$stmt_count  = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$total_results = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$total_pages   = ceil($total_results / $limit);

// 4. CONSULTA CON LIMIT Y OFFSET
// JOIN con usuarios para obtener nombre del proveedor (necesario para el chat)
$query = "
    SELECT s.*,
           u.nombre   AS prov_nombre,
           u.apellido AS prov_apellido,
           (SELECT AVG(v.puntuacion) FROM valoraciones v JOIN contrataciones c ON c.id = v.contratacion_id WHERE c.servicio_id = s.id) AS avg_rating,
           (SELECT COUNT(v.id)       FROM valoraciones v JOIN contrataciones c ON c.id = v.contratacion_id WHERE c.servicio_id = s.id) AS num_reviews
    FROM servicios s
    LEFT JOIN usuarios u ON u.id = s.usuario_id
    WHERE $where_sql
    ORDER BY s.es_destacado DESC, s.created_at DESC
    LIMIT ? OFFSET ?";
$stmt  = mysqli_prepare($conn, $query);

$params_limit   = $params;
$params_limit[] = $limit;
$params_limit[] = $offset;
$types_limit    = $types . "ii";

mysqli_stmt_bind_param($stmt, $types_limit, ...$params_limit);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

// 5. RENDERIZAR CARDS
if (mysqli_num_rows($resultado) > 0) {
    while ($s = mysqli_fetch_assoc($resultado)) {
        $claseDestacado   = $s['es_destacado'] ? 'featured' : '';
        $imagen           = !empty($s['imagen']) ? _base_url() . "/uploads/" . htmlspecialchars($s['imagen']) : '';
        $titulo           = htmlspecialchars($s['titulo']);
        $municipio        = htmlspecialchars($s['municipio']);
        $categoria        = htmlspecialchars($s['categoria'] ?? '');
        $precio           = htmlspecialchars($s['precio']);
        $avg_rating       = $s['avg_rating'] > 0 ? round($s['avg_rating'], 1) : 0;
        $num_reviews      = (int)$s['num_reviews'];
        $proveedor_nombre = trim(($s['prov_nombre'] ?? '') . ' ' . ($s['prov_apellido'] ?? ''));

        $data = htmlspecialchars(json_encode([
            'servicio_id'      => (int)$s['id'],
            'proveedor_id'     => (int)($s['usuario_id'] ?? 0),
            'proveedor_nombre' => $proveedor_nombre,
            'titulo'           => $s['titulo'],
            'descripcion'      => $s['descripcion'] ?? '',
            'municipio'        => $s['municipio'],
            'categoria'        => $s['categoria'] ?? '',
            'precio'           => $s['precio'],
            'imagen'           => $imagen,
            'destacado'        => (bool)$s['es_destacado'],
            'avg_rating'       => $avg_rating,
            'num_reviews'      => $num_reviews,
        ]), ENT_QUOTES);

        $rating_html = "";
        if ($avg_rating > 0) {
            $rating_html = "<div style='color:#f59e0b; font-size:14px; font-weight:700; margin-top:8px;'>
                                $avg_rating <span style='font-size:12px;'>★</span>
                                <span style='color:#8898bb; font-size:12px; font-weight:400; margin-left:4px;'>($num_reviews)</span>
                            </div>";
        }

        echo "
        <div class='card $claseDestacado'>
            <div class='card-content'>
                " . ($imagen ? "<div class='card-img'><img src='$imagen' alt='$titulo'></div>" : "") . "
                <div class='price'>$ $precio</div>
                " . ($s['es_destacado'] ? "<span class='verified-tag'>DESTACADO</span>" : "") . "
                <p>$titulo</p>
                $rating_html
                <div class='location'>$municipio</div>
                <button class='btn-contact' onclick='verServicio($data)'>Ver información</button>
            </div>
        </div>";
    }
} else {
    echo "<div style='grid-column:1/-1; text-align:center; padding:40px; color:#8898bb;'>
              No se encontraron servicios con estos filtros.
          </div>";
}
?>