<?php
require_once __DIR__ . '/auth_guard.php';
requireAdmin();

include __DIR__ . '/db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ═══════════════════════════════════════════════════════════
// VERIFICACIONES
// ═══════════════════════════════════════════════════════════
if ($action === 'verify_approve') {
    $id = (int)($_POST['id'] ?? 0);
    
    // Obtener la verificación
    $stmt = mysqli_prepare($conn, "SELECT usuario_id FROM verificaciones WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $verificacion = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($verificacion) {
        // Actualizar estado de verificación
        $stmt_upd = mysqli_prepare($conn, "UPDATE verificaciones SET estado = 'aprobado' WHERE id = ?");
        mysqli_stmt_bind_param($stmt_upd, 'i', $id);
        mysqli_stmt_execute($stmt_upd);
        mysqli_stmt_close($stmt_upd);

        // Actualizar los servicios del usuario a verificados
        $uid = $verificacion['usuario_id'];
        $stmt_srv = mysqli_prepare($conn, "UPDATE servicios SET verificado = 1 WHERE usuario_id = ?");
        mysqli_stmt_bind_param($stmt_srv, 'i', $uid);
        mysqli_stmt_execute($stmt_srv);
        mysqli_stmt_close($stmt_srv);
        
        audit('verificacion_aprobada', idUsuario(), 'verificaciones', $id, "Admin aprobó verificación #$id del usuario #$uid");
    }
    
    header('Location: admin_panel.php?tab=verificaciones&msg=aprobado');
    exit;
}

if ($action === 'verify_reject') {
    $id = (int)($_POST['id'] ?? 0);
    
    $stmt = mysqli_prepare($conn, "UPDATE verificaciones SET estado = 'rechazado' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    audit('verificacion_rechazada', idUsuario(), 'verificaciones', $id, "Admin rechazó verificación #$id");
    
    header('Location: admin_panel.php?tab=verificaciones&msg=rechazado');
    exit;
}

// ═══════════════════════════════════════════════════════════
// SERVICIOS
// ═══════════════════════════════════════════════════════════
if ($action === 'toggle_destacado') {
    $id = (int)($_POST['id'] ?? 0);
    
    $stmt = mysqli_prepare($conn, "UPDATE servicios SET es_destacado = NOT es_destacado WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    audit('admin_toggle_destacado', idUsuario(), 'servicios', $id, "Admin cambió destacado del servicio #$id");
    
    header('Location: admin_panel.php?tab=servicios');
    exit;
}

if ($action === 'delete_service') {
    $id = (int)($_POST['id'] ?? 0);
    
    // Soft Delete
    $stmt = mysqli_prepare($conn, "UPDATE servicios SET deleted_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    audit('admin_delete_service', idUsuario(), 'servicios', $id, "Admin eliminó (soft-delete) servicio #$id");
    
    header('Location: admin_panel.php?tab=servicios&msg=deleted');
    exit;
}

// ═══════════════════════════════════════════════════════════
// USUARIOS
// ═══════════════════════════════════════════════════════════
if ($action === 'delete_user') {
    $id = (int)($_POST['id'] ?? 0);
    $mi_id = idUsuario();
    
    if ($id !== $mi_id && $id > 0) {
        // Verificar contratos activos (Fase 0.D)
        $stmt_contratos = mysqli_prepare($conn,
            "SELECT COUNT(*) as c FROM contrataciones WHERE (proveedor_id = ? OR cliente_id = ?) AND estado IN ('pendiente','aceptado')"
        );
        mysqli_stmt_bind_param($stmt_contratos, 'ii', $id, $id);
        mysqli_stmt_execute($stmt_contratos);
        $contratos_activos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_contratos))['c'] ?? 0;
        mysqli_stmt_close($stmt_contratos);

        if ($contratos_activos > 0 && !isset($_POST['confirmar_forzar'])) {
            header("Location: admin_panel.php?tab=usuarios&warn=contratos_activos&uid=$id&n=$contratos_activos");
            exit;
        }

        // Recuperar datos del usuario antes de borrarlo (Fase 0.C)
        $stmt_info = mysqli_prepare($conn, "SELECT nombre, email, role FROM usuarios WHERE id = ?");
        mysqli_stmt_bind_param($stmt_info, 'i', $id);
        mysqli_stmt_execute($stmt_info);
        $info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
        mysqli_stmt_close($stmt_info);

        // Contar servicios activos del usuario antes de borrar
        $stmt_cnt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM servicios WHERE usuario_id = ? AND deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt_cnt, 'i', $id);
        mysqli_stmt_execute($stmt_cnt);
        $count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cnt))['c'] ?? 0;
        mysqli_stmt_close($stmt_cnt);

        // Soft delete del usuario
        $stmt = mysqli_prepare($conn, "UPDATE usuarios SET deleted_at = NOW() WHERE id = ? AND role != 'admin'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $afectados = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        // Si se eliminó el usuario, también hacer soft delete de sus servicios
        if ($afectados > 0) {
            $stmt_srv = mysqli_prepare($conn, "UPDATE servicios SET deleted_at = NOW() WHERE usuario_id = ?");
            mysqli_stmt_bind_param($stmt_srv, 'i', $id);
            mysqli_stmt_execute($stmt_srv);
            mysqli_stmt_close($stmt_srv);

            if (function_exists('audit')) {
                audit('admin_delete_user', idUsuario(), 'usuarios', $id,
                    "Eliminó usuario: " . ($info['nombre'] ?? '') . " (" . ($info['email'] ?? '') . ") rol: " . ($info['role'] ?? '') . " — {$count} servicios ocultados");
            }
        }
    }
    
    header('Location: admin_panel.php?tab=usuarios&msg=user_deleted');
    exit;
}

// ═══════════════════════════════════════════════════════════
// CATEGORÍAS
// ═══════════════════════════════════════════════════════════
if ($action === 'add_categoria') {
    $nombre = trim($_POST['nombre'] ?? '');
    
    if (!empty($nombre)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO categorias (nombre) VALUES (?)");
        mysqli_stmt_bind_param($stmt, 's', $nombre);
        if (mysqli_stmt_execute($stmt)) {
            $cat_id = mysqli_insert_id($conn);
            audit('admin_add_categoria', idUsuario(), 'categorias', $cat_id, "Admin agregó categoría: $nombre");
        }
        mysqli_stmt_close($stmt);
    }
    
    header('Location: admin_panel.php?tab=categorias&msg=cat_added');
    exit;
}

// ── SUSPENDER USUARIO ────────────────────────────────────────
if ($action === 'suspender_usuario') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $id !== idUsuario()) {
        $stmt = mysqli_prepare($conn,
            "UPDATE usuarios SET suspendido_at = NOW() WHERE id = ? AND role != 'admin' AND deleted_at IS NULL"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            audit('admin_suspendio_usuario', idUsuario(), 'usuarios', $id, "Admin suspendió al usuario #$id");
        }
        mysqli_stmt_close($stmt);
    }
    header('Location: admin_panel.php?tab=usuarios&msg=suspendido');
    exit;
}

// ── REACTIVAR USUARIO ────────────────────────────────────────
if ($action === 'reactivar_usuario') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn,
            "UPDATE usuarios SET suspendido_at = NULL WHERE id = ? AND role != 'admin'"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            audit('admin_reactivo_usuario', idUsuario(), 'usuarios', $id, "Admin reactivó al usuario #$id");
        }
        mysqli_stmt_close($stmt);
    }
    header('Location: admin_panel.php?tab=usuarios&msg=reactivado');
    exit;
}

// ── VERIFICAR/DESVERIFICAR SERVICIO INDIVIDUAL ───────────────
if ($action === 'toggle_verificado') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn,
            "UPDATE servicios SET verificado = NOT verificado WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        audit('admin_toggle_verificado', idUsuario(), 'servicios', $id, "Admin cambió estado verificado del servicio #$id");
    }
    header('Location: admin_panel.php?tab=servicios');
    exit;
}

// ── GET: LOG DE ACTIVIDAD ───────────────────────────────────
if ($action === 'get_audit_log') {
    header('Content-Type: application/json');
    $tipo  = $_GET['tipo']  ?? '';
    $uid   = (int)($_GET['uid'] ?? 0);
    $desde = $_GET['desde'] ?? '';
    $hasta = $_GET['hasta'] ?? '';
    $page  = max(1, (int)($_GET['p'] ?? 1));
    $limit = 25;
    $offset = ($page - 1) * $limit;

    $where = "WHERE 1=1";
    $params = [];
    $types = '';
    if ($tipo) {
        $where .= " AND al.tipo = ?";
        $params[] = $tipo;
        $types .= 's';
    }
    if ($uid) {
        $where .= " AND al.usuario_id = ?";
        $params[] = $uid;
        $types .= 'i';
    }
    if ($desde) {
        $where .= " AND al.created_at >= ?";
        $params[] = $desde . ' 00:00:00';
        $types .= 's';
    }
    if ($hasta) {
        $where .= " AND al.created_at <= ?";
        $params[] = $hasta . ' 23:59:59';
        $types .= 's';
    }

    // Contar total para paginación
    $count_sql = "SELECT COUNT(*) as total FROM audit_log al $where";
    $stmt_c = mysqli_prepare($conn, $count_sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_c, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_c);
    $total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c))['total'] ?? 0;
    mysqli_stmt_close($stmt_c);

    $sql = "SELECT al.*, u.nombre, u.email FROM audit_log al
            LEFT JOIN usuarios u ON al.usuario_id = u.id
            $where ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    $logs = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $logs[] = $row;
    }
    mysqli_stmt_close($stmt);

    echo json_encode([
        'ok' => true,
        'logs' => $logs,
        'total' => (int)$total,
        'page' => (int)$page,
        'pages' => (int)ceil($total / $limit)
    ]);
    exit;
}

// ── GET: HILOS DE CHAT (Monitor DT-0) ───────────────────────
if ($action === 'get_chat_hilos') {
    header('Content-Type: application/json');
    // Devuelve todos los hilos únicos agrupados por (servicio_id + cliente_id)
    $sql = "SELECT cm.servicio_id, cm.cliente_id, cm.proveedor_id,
                   s.titulo AS servicio_titulo,
                   uc.nombre AS cliente_nombre, uc.email AS cliente_email,
                   up.nombre AS proveedor_nombre, up.email AS proveedor_email,
                   COUNT(*) AS total_mensajes,
                   MAX(cm.created_at) AS ultimo_mensaje
            FROM chat_mensajes cm
            JOIN servicios s ON cm.servicio_id = s.id
            JOIN usuarios uc ON cm.cliente_id  = uc.id
            JOIN usuarios up ON cm.proveedor_id = up.id
            GROUP BY cm.servicio_id, cm.cliente_id, cm.proveedor_id
            ORDER BY ultimo_mensaje DESC";
    $res = mysqli_query($conn, $sql);
    $hilos = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $hilos[] = $row;
        }
    }
    echo json_encode(['ok' => true, 'hilos' => $hilos]);
    exit;
}

// ── GET: MENSAJES DE UN HILO (Monitor DT-0) ───────────────────
if ($action === 'get_chat_mensajes_hilo') {
    header('Content-Type: application/json');
    $sid = (int)($_GET['servicio_id'] ?? 0);
    $cid = (int)($_GET['cliente_id'] ?? 0);

    $sql = "SELECT cm.*, u.nombre AS emisor_nombre, u.role AS emisor_role
            FROM chat_mensajes cm
            JOIN usuarios u ON cm.emisor_id = u.id
            WHERE cm.servicio_id = ? AND cm.cliente_id = ?
            ORDER BY cm.created_at ASC";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $sid, $cid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    $mensajes = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $mensajes[] = $row;
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['ok' => true, 'mensajes' => $mensajes]);
    exit;
}

// ── GET: BÚSQUEDA DE USUARIOS POR TEXTO ─────────────────────
if ($action === 'search_users') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');

    if (strlen($q) < 2) {
        echo json_encode(['ok' => false, 'error' => 'Consulta demasiado corta']);
        exit;
    }

    $like = "%$q%";
    $stmt = mysqli_prepare($conn,
        "SELECT id, nombre, apellido, email, role, created_at, suspendido_at
         FROM usuarios
         WHERE deleted_at IS NULL AND role != 'admin'
           AND (nombre LIKE ? OR apellido LIKE ? OR email LIKE ? OR CONCAT(nombre,' ',apellido) LIKE ?)
         ORDER BY nombre ASC
         LIMIT 15"
    );
    mysqli_stmt_bind_param($stmt, 'ssss', $like, $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $users = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $users[] = $row;
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['ok' => true, 'users' => $users]);
    exit;
}

// ── GET: PERFIL COMPLETO DE USUARIO ──────────────────────────
if ($action === 'get_user_profile') {
    header('Content-Type: application/json');
    $uid = (int)($_GET['uid'] ?? 0);

    // Datos personales
    $stmt = mysqli_prepare($conn, "SELECT id, nombre, apellido, email, telefono, role, created_at, last_login, suspendido_at, deleted_at FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$user) {
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
        exit;
    }

    // Servicios publicados
    $servicios = [];
    $sql_srv = "SELECT s.*, 
                   (SELECT COUNT(*) FROM contrataciones WHERE servicio_id = s.id) as total_contrataciones,
                   (SELECT AVG(puntuacion) FROM valoraciones WHERE proveedor_id = s.usuario_id) as avg_valoracion
                FROM servicios s 
                WHERE s.usuario_id = ? AND s.deleted_at IS NULL";
    $stmt_srv = mysqli_prepare($conn, $sql_srv);
    mysqli_stmt_bind_param($stmt_srv, 'i', $uid);
    mysqli_stmt_execute($stmt_srv);
    $res_srv = mysqli_stmt_get_result($stmt_srv);
    while ($row = mysqli_fetch_assoc($res_srv)) {
        $servicios[] = $row;
    }
    mysqli_stmt_close($stmt_srv);

    // Historial de contrataciones
    $contrataciones = [];
    $sql_cnt = "SELECT c.*, s.titulo as servicio_titulo, uc.nombre as cliente_nombre, up.nombre as proveedor_nombre 
                FROM contrataciones c
                JOIN servicios s ON c.servicio_id = s.id
                JOIN usuarios uc ON c.cliente_id = uc.id
                JOIN usuarios up ON c.proveedor_id = up.id
                WHERE c.cliente_id = ? OR c.proveedor_id = ?
                ORDER BY c.created_at DESC";
    $stmt_cnt = mysqli_prepare($conn, $sql_cnt);
    mysqli_stmt_bind_param($stmt_cnt, 'ii', $uid, $uid);
    mysqli_stmt_execute($stmt_cnt);
    $res_cnt = mysqli_stmt_get_result($stmt_cnt);
    while ($row = mysqli_fetch_assoc($res_cnt)) {
        $contrataciones[] = $row;
    }
    mysqli_stmt_close($stmt_cnt);

    // Hilos de chat activos
    $chats = [];
    $sql_chat = "SELECT cm.servicio_id, cm.cliente_id, cm.proveedor_id,
                        s.titulo AS servicio_titulo,
                        uc.nombre AS cliente_nombre, up.nombre AS proveedor_nombre,
                        MAX(cm.created_at) AS ultimo_mensaje
                 FROM chat_mensajes cm
                 JOIN servicios s ON cm.servicio_id = s.id
                 JOIN usuarios uc ON cm.cliente_id  = uc.id
                 JOIN usuarios up ON cm.proveedor_id = up.id
                 WHERE cm.cliente_id = ? OR cm.proveedor_id = ?
                 GROUP BY cm.servicio_id, cm.cliente_id, cm.proveedor_id";
    $stmt_chat = mysqli_prepare($conn, $sql_chat);
    mysqli_stmt_bind_param($stmt_chat, 'ii', $uid, $uid);
    mysqli_stmt_execute($stmt_chat);
    $res_chat = mysqli_stmt_get_result($stmt_chat);
    while ($row = mysqli_fetch_assoc($res_chat)) {
        $chats[] = $row;
    }
    mysqli_stmt_close($stmt_chat);

    // Historial de verificación
    $verificacion = null;
    $stmt_ver = mysqli_prepare($conn, "SELECT * FROM verificaciones WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt_ver, 'i', $uid);
    mysqli_stmt_execute($stmt_ver);
    $verificacion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ver));
    mysqli_stmt_close($stmt_ver);

    echo json_encode([
        'ok' => true,
        'user' => $user,
        'servicios' => $servicios,
        'contrataciones' => $contrataciones,
        'chats' => $chats,
        'verificacion' => $verificacion
    ]);
    exit;
}

header('Location: admin_panel.php');
exit;
