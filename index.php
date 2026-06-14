<?php
require_once __DIR__ . '/auth_guard.php';
// Se permite el acceso a proveedores para el "Modo Cliente"
if (($_SESSION['user_role'] ?? '') === 'admin' && ($_GET['view'] ?? '') !== 'public') {
    header('Location: admin_panel.php');
    exit;
}
include __DIR__ . '/get_lists.php';
$all_municipios = getMunicipios($conn);
$all_categorias = getCategorias($conn);

// Inicializar variables de filtro para evitar errores de "Undefined variable"
$municipio_f = $_GET['municipio'] ?? 'todos';
$categoria_f = $_GET['categoria'] ?? 'todas';
$search_f    = trim($_GET['q'] ?? '');
$view_f      = $_GET['view'] ?? '';

// ID del usuario actual (para el chat)
$mi_user_id   = (int)($_SESSION['user_id'] ?? 0);
$mi_user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Tú');

// ── LÓGICA DE PANEL DE CLIENTE ──────────────────────────────────
$tab = $_GET['tab'] ?? 'explorar';

// 1. Obtener chats como cliente (activos y archivados por separado)
$chat_filtro_cliente = isset($_GET['chats_tab']) && $_GET['chats_tab'] === 'archivados' ? 1 : 0;

$stmt_chats = mysqli_prepare($conn,
    "SELECT
        cm.servicio_id,
        cm.cliente_id,
        cm.proveedor_id,
        s.titulo        AS servicio_titulo,
        u.nombre        AS proveedor_nombre,
        u.apellido      AS proveedor_apellido,
        MAX(cm.created_at) AS ultimo_at,
        SUBSTRING_INDEX(GROUP_CONCAT(cm.mensaje ORDER BY cm.created_at DESC SEPARATOR '|||'), '|||', 1) AS ultimo_msg,
        SUM(cm.leido = 0 AND cm.emisor_id != ?)              AS no_leidos,
        MAX(cm.archivado_cliente)                            AS esta_archivado
     FROM chat_mensajes cm
     JOIN servicios s ON s.id = cm.servicio_id
     JOIN usuarios  u ON u.id = cm.proveedor_id
     WHERE cm.cliente_id = ?
     GROUP BY cm.servicio_id, cm.cliente_id, cm.proveedor_id
     HAVING MAX(cm.archivado_cliente) = ?
     ORDER BY ultimo_at DESC"
);
mysqli_stmt_bind_param($stmt_chats, 'iii', $mi_user_id, $mi_user_id, $chat_filtro_cliente);
mysqli_stmt_execute($stmt_chats);
$mis_chats = mysqli_stmt_get_result($stmt_chats)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt_chats);

// Total de no leídos solo en activos (para badge del sidebar)
$stmt_nl = mysqli_prepare($conn,
    "SELECT SUM(cm.leido = 0 AND cm.emisor_id != ?) AS total_nl
     FROM chat_mensajes cm
     WHERE cm.cliente_id = ? AND cm.archivado_cliente = 0"
);
mysqli_stmt_bind_param($stmt_nl, 'ii', $mi_user_id, $mi_user_id);
mysqli_stmt_execute($stmt_nl);
$total_no_leidos = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_nl))['total_nl'] ?? 0);
mysqli_stmt_close($stmt_nl);

// Total de archivados (para mostrar el contador en el filtro)
$stmt_arch = mysqli_prepare($conn,
    "SELECT COUNT(DISTINCT CONCAT(servicio_id,'-',cliente_id,'-',proveedor_id)) AS total_arch
     FROM chat_mensajes
     WHERE cliente_id = ? AND archivado_cliente = 1"
);
mysqli_stmt_bind_param($stmt_arch, 'i', $mi_user_id);
mysqli_stmt_execute($stmt_arch);
$total_archivados_cliente = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_arch))['total_arch'] ?? 0);
mysqli_stmt_close($stmt_arch);

// 2. Obtener estado de verificación
$stmt_verif = mysqli_prepare($conn, "SELECT estado FROM verificaciones WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 1");
mysqli_stmt_bind_param($stmt_verif, 'i', $mi_user_id);
mysqli_stmt_execute($stmt_verif);
$res_verif = mysqli_stmt_get_result($stmt_verif);
$verificacion = mysqli_fetch_assoc($res_verif);
mysqli_stmt_close($stmt_verif);
$estado_verif = $verificacion ? $verificacion['estado'] : 'no_enviado';

// 3. Obtener historial de proveedores contactados
$stmt_history = mysqli_prepare($conn,
    "SELECT DISTINCT u.id, u.nombre, u.apellido, u.email
     FROM chat_mensajes cm
     JOIN usuarios u ON u.id = cm.proveedor_id
     WHERE cm.cliente_id = ?"
);
mysqli_stmt_bind_param($stmt_history, 'i', $mi_user_id);
mysqli_stmt_execute($stmt_history);
$historial_proveedores = mysqli_stmt_get_result($stmt_history)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt_history);

// 4. Obtener contrataciones del cliente
$stmt_mis_contratos = mysqli_prepare($conn,
    "SELECT c.*, s.titulo AS servicio_titulo, u.nombre AS proveedor_nombre, u.apellido AS proveedor_apellido,
            v.id AS valoracion_id, v.puntuacion, v.comentario
     FROM contrataciones c
     JOIN servicios s ON s.id = c.servicio_id
     JOIN usuarios u ON u.id = c.proveedor_id
     LEFT JOIN valoraciones v ON v.contratacion_id = c.id
     WHERE c.cliente_id = ?
     ORDER BY c.created_at DESC"
);
mysqli_stmt_bind_param($stmt_mis_contratos, 'i', $mi_user_id);
mysqli_stmt_execute($stmt_mis_contratos);
$mis_contratos = mysqli_stmt_get_result($stmt_mis_contratos)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt_mis_contratos);
$total_mis_contratos_pendientes = count(array_filter($mis_contratos, fn($c) => $c['estado'] === 'pendiente'));

$msg_accion = '';
$msg_accion_type = '';
if (isset($_GET['ok']))  { $msg_accion = '✔ Contraseña actualizada correctamente.'; $msg_accion_type = 'success'; }
if (isset($_GET['err'])) { $msg_accion = '✗ Ocurrió un error o la contraseña actual es incorrecta.'; $msg_accion_type = 'error'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servi-Job — Panel de Servicios</title>
    <link href="fonts/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="style_backend.css">
    <style>
        :root {
            --navy: #0f2057; --blue: #1a3a8f; --blue-mid: #2d5be3; --blue-light: #3d7af5;
            --orange: #f5820d; --orange-dark: #d96a00; --white: #ffffff; --off-white: #f4f6fc;
            --text-muted: #8898bb; --border: rgba(255,255,255,0.08); --card-bg: rgba(255,255,255,0.04);
            --radius: 12px; --bg: #0c1840;
        }
        body { margin:0; padding:0; box-sizing:border-box; background: var(--bg); color: var(--white); min-height: 100vh; display: flex; font-family: 'DM Sans', sans-serif; }
        a { text-decoration: none; color: inherit; }

        /* SIDEBAR */
        .sidebar { width: 260px; background: var(--navy); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 24px 0; flex-shrink: 0; }
        .logo { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; padding: 0 24px 32px; border-bottom: 1px solid var(--border); margin-bottom: 24px; display:flex; align-items:center; gap:8px;}
        .logo .logo-text { color: white; }
        .logo .logo-text span { color: var(--orange); }
        .nav-item { padding: 14px 24px; color: var(--text-muted); font-size: 15px; font-weight: 500; display: flex; align-items: center; gap: 12px; transition: all 0.2s; cursor: pointer; }
        .nav-item:hover, .nav-item.active { background: rgba(61,122,245,0.1); color: var(--blue-light); border-right: 3px solid var(--blue-light); }
        .nav-badge { background: var(--orange); color: white; font-size: 11px; padding: 2px 6px; border-radius: 10px; margin-left: auto; }
        
        .sidebar-bottom { margin-top: auto; padding: 24px; border-top: 1px solid var(--border); }
        .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--blue-mid), var(--blue-light)); display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .btn-logout { display: block; width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,100,100,0.3); background: rgba(255,100,100,0.1); color: #ff8a8a; text-align: center; font-size: 14px; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(255,100,100,0.2); }

        /* MAIN CONTENT */
        .main-content { flex: 1; overflow-y: auto; height: 100vh; display: flex; flex-direction: column; }
        .tab-content { display: none; flex: 1; padding: 40px; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* BARRA STICKY BÚSQUEDA + FILTROS */
        .explorar-sticky {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(12, 24, 64, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 14px 40px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        .explorar-sticky .search-bar {
            flex: 1;
            min-width: 200px;
            max-width: 420px;
        }
        .explorar-sticky .filters-inline {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .explorar-sticky .filters-inline select {
            padding: 9px 14px;
            border-radius: 8px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
            font-size: 13px;
            outline: none;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: border-color 0.2s;
        }
        .explorar-sticky .filters-inline select:focus { border-color: var(--blue-light); }
        .explorar-sticky .filters-inline select option { background: #1a3a8f; color: #fff; }
        .explorar-sticky .filters-inline .btn-filter {
            padding: 9px 18px;
            border-radius: 8px;
            background: var(--orange);
            border: none;
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: background 0.2s;
        }
        .explorar-sticky .filters-inline .btn-filter:hover { background: var(--orange-dark); }

        /* FAB: Volver arriba */
        #scrollTopBtn {
            display: none;
            position: fixed;
            bottom: 100px;
            right: 28px;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(15, 32, 87, 0.9);
            backdrop-filter: blur(10px);
            color: var(--text-muted);
            font-size: 18px;
            cursor: pointer;
            z-index: 498;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            transition: all 0.2s;
        }
        #scrollTopBtn.visible { display: flex; animation: fadeIn 0.2s ease; }
        #scrollTopBtn:hover { background: rgba(45,91,227,0.4); border-color: var(--blue-light); color: white; transform: translateY(-2px); }

        /* FORM, TOAST, EXTRA STYLES FOR PROFILE/CHATS */
        .toast { padding:14px 20px; border-radius:10px; margin-bottom:24px; font-size:14px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .toast.success { background:rgba(74,222,128,0.1); border:1px solid rgba(74,222,128,0.3); color:#4ade80; }
        .toast.error   { background:rgba(239,68,68,0.1);  border:1px solid rgba(239,68,68,0.3);  color:#f87171; }
        .field { margin-bottom:18px; }
        .field label { display:block; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); margin-bottom:8px; }
        .field input { width:100%; max-width:400px; padding:11px 14px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:9px; color:var(--white); font-size:14px; outline:none; font-family:'DM Sans',sans-serif; transition: border-color 0.2s; }
        .field input:focus { border-color: var(--blue-light); }
        .btn-primary { padding:11px 22px; border-radius:8px; border:none; font-size:14px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; background:linear-gradient(135deg, var(--blue-mid), var(--blue-light)); color:white; box-shadow:0 0 20px rgba(45,91,227,0.3); transition:all 0.2s;}
        .btn-primary:hover { transform: translateY(-2px); box-shadow:0 0 30px rgba(45,91,227,0.5); }

        /* CHATS LIST STYLES */
        .conv-list { display:flex; flex-direction:column; gap:10px; max-width: 800px; }
        .conv-item { background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:16px; cursor:pointer; transition:all .2s; }
        .conv-item:hover { border-color:rgba(61,122,245,0.4); background:rgba(61,122,245,0.05); transform:translateX(3px); }
        .conv-item.unread { border-color:rgba(245,130,13,0.35); }
        .conv-avatar { width:44px; height:44px; border-radius:50%; flex-shrink:0; background:linear-gradient(135deg,#2d5be3,#14a87a); display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; color:#fff; }
        .conv-info { flex:1; min-width:0; }
        .conv-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:3px; }
        .conv-name { font-weight:700; font-size:14px; color:#fff; }
        .conv-time { font-size:11px; color:var(--text-muted); }
        .conv-service { font-size:12px; color:var(--blue-light); margin-bottom:4px; }
        .conv-preview { font-size:13px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .conv-unread-badge { background:var(--orange); color:#fff; font-size:11px; font-weight:700; min-width:20px; height:20px; border-radius:10px; display:flex; align-items:center; justify-content:center; padding:0 5px; flex-shrink:0; }
        .conv-item.archivado { opacity: 0.6; }
        .conv-item.archivado .conv-name { color: var(--text-muted); }
        .conv-arch-btn {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-muted);
            font-size: 11px; font-weight: 600;
            padding: 4px 10px; border-radius: 6px;
            cursor: pointer; flex-shrink: 0;
            transition: all .2s; white-space: nowrap;
        }
        .conv-arch-btn:hover { background: rgba(245,130,13,0.15); border-color: rgba(245,130,13,0.4); color: var(--orange); }
        .conv-arch-btn.desarchivar { background: rgba(61,122,245,0.1); border-color: rgba(61,122,245,0.3); color: var(--blue-light); }
        .conv-arch-btn.desarchivar:hover { background: rgba(61,122,245,0.2); }
        /* Tabs de filtro activo/archivado */
        .chat-tabs {
            display: flex; gap: 8px; margin-bottom: 20px;
        }
        .chat-tab-btn {
            padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600;
            border: 1px solid var(--border); background: var(--card-bg);
            color: var(--text-muted); cursor: pointer; transition: all .2s;
            font-family: 'DM Sans', sans-serif; display: flex; align-items: center; gap: 6px;
        }
        .chat-tab-btn.active { background: rgba(61,122,245,0.15); border-color: rgba(61,122,245,0.4); color: var(--blue-light); }
        .chat-tab-count { background: rgba(255,255,255,0.1); font-size:10px; padding:1px 6px; border-radius:8px; }

        .card-img { width:100%; height:160px; overflow:hidden; border-radius:10px 10px 0 0; }
        .card-img img { width:100%; height:100%; object-fit:cover; }

        /* ── Modal servicio ── */
        #modalServicio {
            display:none; position:fixed; inset:0; z-index:200;
            background:rgba(10,20,60,0.88); backdrop-filter:blur(10px);
            align-items:center; justify-content:center; padding:20px;
        }
        #modalServicio.open { display:flex; }
        .srv-modal-box {
            background:#0d1e4a; border:1px solid rgba(255,255,255,0.1);
            border-radius:18px; width:100%; max-width:850px;
            overflow:hidden; position:relative;
            display: flex; flex-direction: row; align-items: stretch;
            max-height: 90vh;
            animation:slideUp .3s ease;
            box-shadow:0 30px 80px rgba(0,0,0,0.5);
        }
        @media(max-width:768px) {
            .srv-modal-box { flex-direction: column; overflow-y: auto; }
        }
        .srv-modal-left { flex: 1.2; overflow-y: auto; border-right: 1px solid rgba(255,255,255,0.1); display:flex; flex-direction:column; }
        .srv-modal-right { flex: 1; padding:24px; display:flex; flex-direction:column; background:rgba(255,255,255,0.02); }
        @media(max-width:768px) {
            .srv-modal-left { border-right: none; overflow-y: visible; }
            .srv-modal-right { border-top: 1px solid rgba(255,255,255,0.1); padding: 20px; }
        }
        @keyframes slideUp { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }
        .srv-modal-img { width:100%; height:240px; object-fit:cover; display:block; }
        .srv-modal-no-img {
            width:100%; height:160px;
            background:linear-gradient(135deg,#1a3a8f,#0f2057);
            display:flex; align-items:center; justify-content:center; font-size:52px;
        }
        .srv-modal-body { padding:28px; }
        .srv-modal-badge {
            display:inline-block; padding:4px 12px; border-radius:20px;
            background:rgba(61,122,245,0.15); border:1px solid rgba(61,122,245,0.3);
            color:#3d7af5; font-size:12px; font-weight:600; margin-bottom:12px;
        }
        .srv-modal-badge.dest {
            background:rgba(245,130,13,0.15); border-color:rgba(245,130,13,0.35); color:#f5820d;
        }
        .srv-modal-title { font-family:'Rajdhani',sans-serif; font-size:26px; font-weight:700; color:#fff; margin-bottom:8px; }
        .srv-modal-desc { color:#8898bb; font-size:14px; line-height:1.6; margin-bottom:18px; white-space:pre-line; }
        .srv-modal-meta { display:flex; gap:16px; margin-bottom:22px; flex-wrap:wrap; }
        .srv-meta-item { display:flex; align-items:center; gap:6px; font-size:13px; color:rgba(255,255,255,0.6); }
        .srv-modal-price { font-family:'Rajdhani',sans-serif; font-size:32px; font-weight:700; color:#fff; margin-bottom:22px; }
        .srv-modal-price small { font-size:16px; color:#8898bb; font-family:'DM Sans',sans-serif; }
        .srv-modal-close {
            position:absolute; top:14px; right:16px;
            background:rgba(0,0,0,0.4); border:none; color:#fff;
            width:34px; height:34px; border-radius:50%;
            font-size:16px; cursor:pointer; z-index:10;
            display:flex; align-items:center; justify-content:center;
        }
        .btn-llamar {
            width:100%; padding:14px;
            background:linear-gradient(135deg,#2d5be3,#3d7af5);
            border:none; border-radius:10px; color:#fff;
            font-size:15px; font-weight:600; cursor:pointer;
            font-family:'DM Sans',sans-serif; transition:all .2s;
            margin-bottom: 10px;
        }
        .btn-llamar:hover { transform:translateY(-2px); box-shadow:0 0 30px rgba(61,122,245,0.5); }

        /* ── Botón Chat en modal servicio ── */
        .btn-abrir-chat {
            width:100%; padding:13px;
            background:linear-gradient(135deg,#0f7c5a,#14a87a);
            border:none; border-radius:10px; color:#fff;
            font-size:15px; font-weight:600; cursor:pointer;
            font-family:'DM Sans',sans-serif; transition:all .2s;
        }
        .btn-abrir-chat:hover { transform:translateY(-2px); box-shadow:0 0 30px rgba(20,168,122,0.5); }

        /* ── Sección de reseñas en modal ── */
        .reviews-heading {
            font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700;
            color:#fff; margin-bottom:12px; display:flex; align-items:center; gap:8px;
        }
        #srvReviewsList::-webkit-scrollbar { width:4px; }
        #srvReviewsList::-webkit-scrollbar-track { background:rgba(255,255,255,0.03); border-radius:2px; }
        #srvReviewsList::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.15); border-radius:2px; }
        #srvReviewsList::-webkit-scrollbar-thumb:hover { background:rgba(255,255,255,0.3); }

        /* ── Filtros en fila ── */
        .filters {
            display:flex; align-items:center; gap:20px;
            flex-wrap:wrap; margin-bottom:28px;
        }
        .filters label {
            font-size:12px; font-weight:700; text-transform:uppercase;
            letter-spacing:.6px; color:#8898bb; white-space:nowrap;
        }
        .filters select {
            padding:9px 16px; border-radius:8px;
            background:#1a3a8f; border:1px solid rgba(255,255,255,0.2);
            color:#fff; font-size:14px; outline:none; cursor:pointer;
            font-family:'DM Sans',sans-serif;
        }
        .filters select option { background:#1a3a8f; color:#fff; }

        /* ── Paginación ── */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
            margin-bottom: 20px;
        }
        .btn-page {
            padding: 10px 16px;
            border-radius: 8px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--white);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-page:hover {
            border-color: var(--blue-light);
            background: rgba(61,122,245,0.1);
        }
        .btn-page.active {
            background: var(--blue-mid);
            border-color: var(--blue-light);
            font-weight: 600;
        }
        .btn-page.disabled {
            opacity: 0.4;
            pointer-events: none;
        }
        .btn-filter {
            padding: 9px 20px;
            border-radius: 8px;
            background: var(--orange);
            border: none;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-filter:hover { background: var(--orange-dark); }

        /* ══════════════════════════════════════════════════════
           MÓDULO DE CHAT
        ══════════════════════════════════════════════════════ */

        /* Ventana flotante del chat */
        #chatWindow {
            display: none;
            position: fixed;
            bottom: 28px; right: 28px;
            width: 370px; height: 520px;
            background: #0a1640;
            border: 1px solid rgba(61,122,245,0.35);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 0 0 1px rgba(61,122,245,0.1);
            z-index: 500;
            flex-direction: column;
            overflow: hidden;
            animation: chatEntrar .25s ease;
        }
        #chatWindow.open { display: flex; }
        @keyframes chatEntrar {
            from { opacity:0; transform: translateY(20px) scale(.97); }
            to   { opacity:1; transform: translateY(0) scale(1); }
        }

        /* Header del chat */
        .chat-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 18px;
            background: linear-gradient(135deg, #1a3a8f, #0f2057);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }
        .chat-avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg,#2d5be3,#14a87a);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .chat-header-info { flex: 1; min-width: 0; }
        .chat-header-name {
            font-weight: 700; font-size: 14px;
            color: #fff;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .chat-header-sub {
            font-size: 11px; color: #8898bb; margin-top: 1px;
        }
        .chat-dot-online {
            width: 8px; height: 8px; border-radius: 50%;
            background: #14a87a;
            box-shadow: 0 0 6px #14a87a;
            flex-shrink: 0;
        }
        .chat-close-btn {
            background: rgba(255,255,255,0.07);
            border: none; color: #8898bb;
            width: 30px; height: 30px; border-radius: 8px;
            font-size: 14px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all .2s; flex-shrink: 0;
        }
        .chat-close-btn:hover { background: rgba(255,80,80,0.2); color: #ff6b6b; }

        /* Cuerpo del chat (mensajes) */
        #chatBody {
            flex: 1;
            overflow-y: auto;
            padding: 16px 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            scroll-behavior: smooth;
        }
        #chatBody::-webkit-scrollbar { width: 4px; }
        #chatBody::-webkit-scrollbar-track { background: transparent; }
        #chatBody::-webkit-scrollbar-thumb { background: rgba(61,122,245,0.3); border-radius: 2px; }

        /* Burbujas */
        .chat-msg {
            display: flex;
            flex-direction: column;
            max-width: 80%;
        }
        .chat-msg.mio  { align-self: flex-end; align-items: flex-end; }
        .chat-msg.otro { align-self: flex-start; align-items: flex-start; }

        .chat-bubble {
            padding: 10px 14px;
            border-radius: 16px;
            font-size: 13.5px;
            line-height: 1.5;
            word-break: break-word;
        }
        .chat-msg.mio  .chat-bubble {
            background: linear-gradient(135deg,#2d5be3,#3d7af5);
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .chat-msg.otro .chat-bubble {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            color: #d8e0f0;
            border-bottom-left-radius: 4px;
        }
        .chat-time {
            font-size: 10px;
            color: #6a7a9b;
            margin-top: 3px;
            padding: 0 4px;
        }
        .chat-tick { font-size: 10px; color: #3d7af5; margin-left: 4px; }

        /* Empty state */
        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #4a5a7a;
            font-size: 13px;
            text-align: center;
            gap: 10px;
        }
        .chat-empty-icon { font-size: 38px; opacity: .6; }

        /* Typing indicator */
        .chat-typing {
            display: none;
            align-self: flex-start;
            padding: 8px 14px;
            background: rgba(255,255,255,0.07);
            border-radius: 14px;
            font-size: 12px;
            color: #6a7a9b;
        }
        .chat-typing span { animation: blink 1.2s infinite; display:inline-block; }
        .chat-typing span:nth-child(2) { animation-delay:.2s; }
        .chat-typing span:nth-child(3) { animation-delay:.4s; }
        @keyframes blink { 0%,80%,100%{opacity:.2} 40%{opacity:1} }

        /* Footer del chat */
        .chat-footer {
            padding: 12px 14px;
            border-top: 1px solid rgba(255,255,255,0.07);
            display: flex;
            align-items: flex-end;
            gap: 8px;
            background: #0d1e4a;
            flex-shrink: 0;
        }
        #chatInput {
            flex: 1;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            padding: 10px 14px;
            color: #fff;
            font-size: 13.5px;
            font-family: 'DM Sans',sans-serif;
            resize: none;
            max-height: 100px;
            outline: none;
            transition: border-color .2s;
            line-height: 1.4;
        }
        #chatInput::placeholder { color: #4a5a7a; }
        #chatInput:focus { border-color: rgba(61,122,245,0.5); }
        #chatSendBtn {
            width: 40px; height: 40px;
            background: linear-gradient(135deg,#2d5be3,#3d7af5);
            border: none; border-radius: 12px;
            color: #fff; font-size: 17px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all .2s; flex-shrink: 0;
        }
        #chatSendBtn:hover { transform: scale(1.08); box-shadow: 0 0 16px rgba(61,122,245,0.5); }
        #chatSendBtn:disabled { opacity: .4; cursor: default; transform: none; }

        /* Notificación (badge) en botón flotante */
        #chatFab {
            display: none;
            position: fixed; bottom: 28px; right: 28px;
            width: 56px; height: 56px;
            background: linear-gradient(135deg,#2d5be3,#3d7af5);
            border: none; border-radius: 50%;
            color: #fff; font-size: 24px; cursor: pointer;
            box-shadow: 0 8px 28px rgba(45,91,227,0.5);
            z-index: 499;
            align-items: center; justify-content: center;
            transition: transform .2s;
        }
        #chatFab:hover { transform: scale(1.1); }
        #chatFab .fab-badge {
            position: absolute; top: -4px; right: -4px;
            background: #f5820d; color: #fff;
            font-size: 10px; font-weight: 700;
            min-width: 18px; height: 18px;
            border-radius: 9px; padding: 0 4px;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #0a1640;
        }
        /* ── RESPONSIVE MOBILE ── */
        .mobile-header { display:none; }
        .sidebar-overlay { display:none; }
        @media(max-width: 900px) {
            body { flex-direction: column !important; }
            .sidebar { 
                position: fixed; top: 0; left: -260px; bottom: 0; 
                z-index: 1000; transition: left 0.3s ease;
                width: 260px;
            }
            .sidebar.open { left: 0; }
            .sidebar-overlay {
                display: none; position: fixed; inset: 0;
                background: rgba(10,20,60,0.8); z-index: 999;
                backdrop-filter: blur(4px);
            }
            .sidebar-overlay.open { display: block; }
            .mobile-header {
                display: flex; align-items: center; justify-content: space-between;
                padding: 14px 20px; background: var(--navy); border-bottom: 1px solid var(--border);
                position: sticky; top: 0; z-index: 998;
                width: 100%; flex-shrink: 0;
            }
            .mobile-header-title { font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; display:flex; align-items:center; gap:8px; }
            .mobile-header-title span { color: var(--orange); }
            .mobile-menu-btn { background: none; border: none; color: white; font-size: 26px; cursor: pointer; line-height: 1; }

            .main-content { width: 100% !important; overflow-x: hidden; padding-top: 0; }
            .tab-content { padding: 20px 16px; }
            .explorar-sticky { padding: 14px 16px; }
            .filters-inline select { width: 100%; margin-bottom: 8px; }
            .filters-inline button { width: 100%; }
            /* Chat pantalla completa en móvil */
            #chatWindow { width: 100% !important; height: 100% !important; bottom: 0; right: 0; left: 0; top: 0; border-radius: 0; z-index: 1100; }
            /* Botón de cierre más grande */
            .chat-close-btn {
                width: 38px !important; height: 38px !important;
                font-size: 20px !important;
                background: rgba(255,80,80,0.15) !important;
                color: #ff6b6b !important;
                border-radius: 10px !important;
            }
            /* Stats-bar wrapper scroll horizontal */
            .stats-bar-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 28px; }
            .stats-bar-wrap .stats-bar { min-width: 520px; margin-bottom: 0; }
            .srv-modal-box { padding: 16px; margin: 10px; width: 100%; max-height: 90vh; border-radius: 10px; }
            .page-header h1 { font-size: 28px; }
            .grid-services { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

    <script>
        history.pushState(null, null, location.href);
        window.addEventListener('popstate', function() {
            history.pushState(null, null, location.href);
        });
    </script>

<!-- MOBILE HEADER & OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="mobile-header">
    <div class="mobile-header-title">
        <div style="width:28px; height:28px; background:linear-gradient(135deg, var(--blue-mid), var(--orange)); border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:14px; color:white;">⚙</div>
        <span style="color:white;">SERVI-<span style="color:var(--orange);">JOB</span></span>
    </div>
    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>
</div>

    <div class="sidebar">
        <a href="index.php" class="logo" style="text-decoration:none;">
            <div style="width:36px; height:36px; background:linear-gradient(135deg, var(--blue-mid), var(--orange)); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; box-shadow:0 0 14px rgba(45,91,227,0.5); color:white;">⚙</div>
            <span style="color:white;">SERVI-<span style="color:var(--orange);">JOB</span></span>
        </a>
        
        <a href="?tab=explorar" class="nav-item <?= $tab == 'explorar' ? 'active' : '' ?>">
            🔍 Explorar Servicios
        </a>
        <a href="?tab=chats" class="nav-item <?= $tab == 'chats' ? 'active' : '' ?>">
            💬 Mis Chats
            <?php if($total_no_leidos > 0): ?><span class="nav-badge"><?= $total_no_leidos ?></span><?php endif; ?>
        </a>
        <a href="?tab=contratos" class="nav-item <?= $tab == 'contratos' ? 'active' : '' ?>">
            🤝 Mis Contrataciones
            <?php if($total_mis_contratos_pendientes > 0): ?><span class="nav-badge" style="background:#f59e0b"><?= $total_mis_contratos_pendientes ?></span><?php endif; ?>
        </a>
        <a href="?tab=perfil" class="nav-item <?= $tab == 'perfil' ? 'active' : '' ?>">
            👤 Mi Perfil
        </a>
        <div style="flex:1"></div>
        <?php if(($_SESSION['user_role'] ?? '') === 'proveedor'): ?>
        <a href="proveedor_panel.php" class="nav-item" style="border-top: 1px solid rgba(255,255,255,0.05); color: #a5b4fc;">
            ⚙️ Volver a Modo Proveedor
        </a>
        <?php endif; ?>
        <div class="sidebar-bottom">
            <div class="user-info">
                <div class="user-avatar"><?= substr($mi_user_name, 0, 1) ?></div>
                <div>
                    <div style="font-size: 14px; font-weight: 600;"><?= $mi_user_name ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);">Cliente</div>
                </div>
            </div>
            <button class="btn-primary" style="width:100%; font-size:13px; margin-bottom:14px; padding:9px;" onclick="openModal()">＋ Postular Verificación</button>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <div class="main-content">

        <!-- ── TAB: EXPLORAR ── -->
        <div id="tab-explorar" class="tab-content <?= $tab == 'explorar' ? 'active' : '' ?>" style="padding:0;">

            <!-- BARRA STICKY: Búsqueda + Filtros unificados -->
            <form action="index.php" method="GET" class="explorar-sticky">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view_f) ?>">
                <input type="hidden" name="tab" value="explorar">

                <!-- Búsqueda -->
                <div class="search-bar" style="flex:1; min-width:200px; max-width:420px;">
                    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Buscar servicios, emprendimientos...">
                    <button type="submit">🔍</button>
                </div>

                <!-- Filtros en línea -->
                <div class="filters-inline">
                    <select name="municipio">
                        <option value="todos" <?= ($municipio_f == 'todos' ? 'selected' : '') ?>>📍 Todos los Municipios</option>
                        <?php foreach ($all_municipios as $m):
                            $slug = slugify($m['nombre']); ?>
                            <option value="<?= $slug ?>" <?= ($municipio_f == $slug ? 'selected' : '') ?>><?= htmlspecialchars($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="categoria">
                        <option value="todas" <?= ($categoria_f == 'todas' ? 'selected' : '') ?>>🏷 Todas las Categorías</option>
                        <?php foreach ($all_categorias as $cat):
                            $slug = slugify($cat['nombre']); ?>
                            <option value="<?= $slug ?>" <?= ($categoria_f == $slug ? 'selected' : '') ?>><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-filter">Filtrar</button>
                </div>
            </form>

            <!-- CONTENIDO DEL CATÁLOGO -->
            <div style="padding: 32px 40px;">
                <section class="results">
                    <h2>Servicios Disponibles</h2>
                    <div class="grid-services" id="servicesGrid">
                        <?php include __DIR__ . '/logic.php'; ?>
                    </div>

                    <!-- ── PAGINACIÓN ── -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        $base_url = "index.php?tab=explorar&view=$view_f&municipio=$municipio_f&categoria=$categoria_f&q=" . urlencode($search_f);
                        ?>
                        <a href="<?= $base_url ?>&p=<?= max(1, $page - 1) ?>" class="btn-page <?= ($page <= 1 ? 'disabled' : '') ?>">« Anterior</a>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="<?= $base_url ?>&p=<?= $i ?>" class="btn-page <?= ($page == $i ? 'active' : '') ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <a href="<?= $base_url ?>&p=<?= min($total_pages, $page + 1) ?>" class="btn-page <?= ($page >= $total_pages ? 'disabled' : '') ?>">Siguiente »</a>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </div> <!-- Fin tab-explorar -->

        <!-- ── TAB: MIS CHATS ── -->
        <div id="tab-chats" class="tab-content <?= $tab == 'chats' ? 'active' : '' ?>">
            <div style="margin-bottom:24px;">
                <h1 style="font-family:'Rajdhani',sans-serif; font-size:32px; font-weight:700;">Mis <span style="color:var(--orange);">Chats</span></h1>
                <p style="color:var(--text-muted); font-size:15px; margin-top:4px;">Conversaciones con proveedores de servicios</p>
            </div>

            <!-- Filtro activos / archivados -->
            <div class="chat-tabs">
                <a href="?tab=chats&chats_tab=activos" class="chat-tab-btn <?= ($chat_filtro_cliente == 0 ? 'active' : '') ?>">
                    💬 Activos
                </a>
                <a href="?tab=chats&chats_tab=archivados" class="chat-tab-btn <?= ($chat_filtro_cliente == 1 ? 'active' : '') ?>">
                    📦 Archivados
                    <?php if ($total_archivados_cliente > 0): ?>
                        <span class="chat-tab-count"><?= $total_archivados_cliente ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <?php if (empty($mis_chats)): ?>
                <div style="text-align:center; padding:48px 24px; color:var(--text-muted);">
                    <div style="font-size:48px; margin-bottom:12px; opacity:0.4;"><?= $chat_filtro_cliente ? '📦' : '💬' ?></div>
                    <p><?= $chat_filtro_cliente
                        ? 'No tienes conversaciones archivadas.'
                        : '¡Explora servicios y contacta a un proveedor!' ?></p>
                </div>
            <?php else: ?>
                <div class="conv-list">
                    <?php foreach ($mis_chats as $c):
                        $iniciales = mb_strtoupper(mb_substr($c['proveedor_nombre'], 0, 1) . mb_substr($c['proveedor_apellido'], 0, 1));
                        $nombre_completo = htmlspecialchars($c['proveedor_nombre'] . ' ' . $c['proveedor_apellido']);
                        $preview = htmlspecialchars(mb_substr($c['ultimo_msg'], 0, 80));
                        $no_leidos = (int)$c['no_leidos'];
                        $hora = date('d/m H:i', strtotime($c['ultimo_at']));
                        $esta_archivado = (int)$c['esta_archivado'];
                        $data_conv = htmlspecialchars(json_encode([
                            'servicio_id'  => (int)$c['servicio_id'],
                            'cliente_id'   => (int)$c['cliente_id'],
                            'proveedor_id' => (int)$c['proveedor_id'],
                            'titulo'       => $c['servicio_titulo'],
                            'nombre'       => $c['proveedor_nombre'] . ' ' . $c['proveedor_apellido'],
                        ]), ENT_QUOTES);
                        $data_arch = htmlspecialchars(json_encode([
                            'servicio_id'  => (int)$c['servicio_id'],
                            'cliente_id'   => (int)$c['cliente_id'],
                            'proveedor_id' => (int)$c['proveedor_id'],
                        ]), ENT_QUOTES);
                    ?>
                        <div class="conv-item <?= $no_leidos > 0 ? 'unread' : '' ?> <?= $esta_archivado ? 'archivado' : '' ?>"
                             onclick='abrirChatDesdeLista(<?= $data_conv ?>)'>
                            <div class="conv-avatar"><?= $iniciales ?></div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <div class="conv-name"><?= $nombre_completo ?></div>
                                    <div class="conv-time"><?= $hora ?></div>
                                </div>
                                <div class="conv-service">📋 <?= htmlspecialchars($c['servicio_titulo']) ?></div>
                                <div class="conv-preview"><?= $preview ?></div>
                            </div>
                            <?php if ($no_leidos > 0): ?>
                                <div class="conv-unread-badge"><?= $no_leidos ?></div>
                            <?php endif; ?>
                            <!-- Botón archivar / desarchivar -->
                            <button class="conv-arch-btn <?= $esta_archivado ? 'desarchivar' : '' ?>"
                                    onclick="event.stopPropagation(); archivarChat(<?= $data_arch ?>, <?= $esta_archivado ? 0 : 1 ?>, this)">
                                <?= $esta_archivado ? '↩ Mover a activos' : '📦 Archivar' ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div> <!-- Fin tab-chats -->

        <!-- ── TAB: MIS CONTRATACIONES ── -->
        <div id="tab-contratos" class="tab-content <?= $tab == 'contratos' ? 'active' : '' ?>">
            <div style="margin-bottom:32px;">
                <h1 style="font-family:'Rajdhani',sans-serif; font-size:32px; font-weight:700;">Mis <span style="color:var(--orange);">Contrataciones</span></h1>
                <p style="color:var(--text-muted); font-size:15px; margin-top:4px;">Sigue el estado de los servicios que has solicitado</p>
            </div>

            <?php if (empty($mis_contratos)): ?>
                <div style="text-align:center; padding:48px 24px; color:var(--text-muted);">
                    <div style="font-size:48px; margin-bottom:12px; opacity:0.4;">🤝</div>
                    <p>Aún no has solicitado ningún servicio.<br>Explora el catálogo y contacta a un proveedor.</p>
                </div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:16px; max-width:800px;">
                <?php foreach ($mis_contratos as $c): ?>
                    <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:20px 24px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                            <div>
                                <h3 style="font-family:'Rajdhani',sans-serif; font-size:19px; font-weight:700; margin-bottom:4px;">
                                    <?= htmlspecialchars($c['servicio_titulo']) ?>
                                </h3>
                                <div style="font-size:13px; color:var(--text-muted); margin-bottom:8px;">
                                    Proveedor: <strong style="color:#fff"><?= htmlspecialchars($c['proveedor_nombre'] . ' ' . $c['proveedor_apellido']) ?></strong>
                                    <span style="margin:0 8px;">|</span>
                                    <?= date('d/m/Y', strtotime($c['created_at'])) ?>
                                </div>
                                <?php
                                $estado_color = match($c['estado']) {
                                    'pendiente'  => '#f59e0b',
                                    'aceptado'   => 'var(--blue-light)',
                                    'rechazado', 'cancelado' => '#ef4444',
                                    'completado' => '#4ade80',
                                    default => 'var(--text-muted)'
                                };
                                $estado_icon = match($c['estado']) {
                                    'pendiente'  => '⏳',
                                    'aceptado'   => '✅',
                                    'rechazado'  => '❌',
                                    'cancelado'  => '🚫',
                                    'completado' => '⭐',
                                    default => '•'
                                };
                                ?>
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <span style="font-size:15px;"><?= $estado_icon ?></span>
                                    <strong style="color:<?= $estado_color ?>; text-transform:uppercase; font-size:12px; letter-spacing:.5px;"><?= $c['estado'] ?></strong>
                                </div>
                                <?php if($c['motivo']): ?>
                                    <div style="font-size:12px; color:var(--text-muted); margin-top:6px; font-style:italic; background:rgba(255,255,255,0.03); border-left:2px solid rgba(255,255,255,0.1); padding:6px 10px; border-radius:4px;">
                                        Motivo: <?= htmlspecialchars($c['motivo']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($c['valoracion_id']): ?>
                                    <div style="margin-top:10px; padding:10px 14px; background:rgba(74,222,128,0.05); border:1px solid rgba(74,222,128,0.2); border-radius:8px;">
                                        <div style="color:#f59e0b; font-size:14px; margin-bottom:4px;">
                                            <?= str_repeat('★', $c['puntuacion']) ?><?= str_repeat('☆', 5 - $c['puntuacion']) ?> 
                                            <span style="color:#4ade80; font-size:12px; margin-left:6px; font-weight:600;">(Valoraste con <?= $c['puntuacion'] ?>/5)</span>
                                        </div>
                                        <?php if (!empty($c['comentario'])): ?>
                                            <div style="font-size:13px; color:var(--text-muted); font-style:italic; line-height:1.4;">
                                                "<?= htmlspecialchars($c['comentario']) ?>"
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
                                <?php
                                $chat_ctx = json_encode([
                                    'servicio_id'  => (int)$c['servicio_id'],
                                    'cliente_id'   => (int)$c['cliente_id'],
                                    'proveedor_id' => (int)$c['proveedor_id'],
                                    'titulo'       => $c['servicio_titulo'],
                                    'nombre'       => $c['proveedor_nombre'] . ' ' . $c['proveedor_apellido']
                                ]);
                                ?>
                                <button onclick='abrirChatDesdeLista(<?= htmlspecialchars($chat_ctx, ENT_QUOTES) ?>)'
                                    style="padding:7px 14px; border-radius:8px; border:none; background:linear-gradient(135deg,#0f7c5a,#14a87a); color:#fff; font-size:12px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .2s; margin-bottom:4px;"
                                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 0 10px rgba(20,168,122,0.4)'"
                                    onmouseout="this.style.transform='none'; this.style.boxShadow='none'">
                                    💬 Abrir Chat
                                </button>

                                <?php if ($c['estado'] === 'pendiente' || $c['estado'] === 'aceptado'): ?>
                                    <button onclick="cancelarContrato(<?= $c['id'] ?>)"
                                        style="padding:7px 14px; border-radius:8px; border:1px solid rgba(239,68,68,0.4); background:rgba(239,68,68,0.1); color:#f87171; font-size:12px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .2s;"
                                        onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'">
                                        🚫 Cancelar
                                    </button>
                                <?php endif; ?>

                                <?php if ($c['estado'] === 'completado' && !$c['valoracion_id']): ?>
                                    <button onclick="abrirModalValorar(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['servicio_titulo'])) ?>')"
                                        style="padding:7px 14px; border-radius:8px; border:none; background:linear-gradient(135deg,#f59e0b,#f5820d); color:#fff; font-size:12px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .2s;">
                                        ⭐ Valorar Servicio
                                    </button>
                                <?php elseif ($c['estado'] === 'completado' && $c['valoracion_id']): ?>
                                    <!-- La valoración ya se muestra en el bloque de la izquierda -->
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div> <!-- Fin tab-contratos -->

        <!-- ── TAB: MI PERFIL E HISTORIAL ── -->
        <div id="tab-perfil" class="tab-content <?= $tab == 'perfil' ? 'active' : '' ?>">
            <div style="margin-bottom:32px;">
                <h1 style="font-family:'Rajdhani',sans-serif; font-size:32px; font-weight:700;">Mi <span style="color:var(--orange);">Perfil</span></h1>
                <p style="color:var(--text-muted); font-size:15px; margin-top:4px;">Gestiona tu cuenta e historial</p>
            </div>

            <?php if ($msg_accion): ?>
                <div class="toast <?= $msg_accion_type ?>"><?= $msg_accion ?></div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:24px;">
                
                <!-- Seguridad -->
                <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius); padding:24px;">
                    <h2 style="font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:10px;">🔒 Seguridad</h2>
                    <form action="cliente_actions.php" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="field">
                            <label>Contraseña Actual</label>
                            <input type="password" name="current_password" required>
                        </div>
                        <div class="field">
                            <label>Nueva Contraseña</label>
                            <input type="password" name="new_password" required>
                        </div>
                        <button type="submit" class="btn-primary">Actualizar Contraseña</button>
                    </form>
                </div>

                <!-- Verificación e Historial -->
                <div style="display:flex; flex-direction:column; gap:24px;">
                    <!-- Verificación -->
                    <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius); padding:24px;">
                        <h2 style="font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:10px;">🛡️ Mi Verificación</h2>
                        <?php if ($estado_verif == 'aprobado'): ?>
                            <div style="color:#4ade80; font-weight:600; display:flex; align-items:center; gap:8px;">✔ Eres un Proveedor Verificado</div>
                            <p style="color:var(--text-muted); font-size:13px; margin-top:8px;">Tus servicios destacarán por tener el sello de confianza.</p>
                        <?php elseif ($estado_verif == 'pendiente'): ?>
                            <div style="color:var(--orange); font-weight:600; display:flex; align-items:center; gap:8px;">⏳ Solicitud en proceso</div>
                            <p style="color:var(--text-muted); font-size:13px; margin-top:8px;">El administrador está revisando tu documento.</p>
                        <?php else: ?>
                            <p style="color:var(--text-muted); font-size:13px; margin-bottom:16px;">Aún no has solicitado la verificación. Si ofreces servicios, ser verificado genera más confianza a tus clientes.</p>
                            <button class="btn-primary" onclick="openModal()">Postularme Ahora</button>
                        <?php endif; ?>
                    </div>

                    <!-- Historial -->
                    <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius); padding:24px;">
                        <h2 style="font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:10px;">🕰️ Proveedores Contactados</h2>
                        <?php if (empty($historial_proveedores)): ?>
                            <p style="color:var(--text-muted); font-size:13px;">Aún no has contactado a ningún proveedor.</p>
                        <?php else: ?>
                            <ul style="list-style:none; padding:0; margin:0;">
                                <?php foreach($historial_proveedores as $hp): ?>
                                    <li style="padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.04); display:flex; align-items:center; gap:10px;">
                                        <div style="width:32px; height:32px; border-radius:50%; background:var(--border); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700;"><?= mb_strtoupper(mb_substr($hp['nombre'], 0, 1)) ?></div>
                                        <div>
                                            <div style="font-size:14px; font-weight:600;"><?= htmlspecialchars($hp['nombre'] . ' ' . $hp['apellido']) ?></div>
                                            <div style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($hp['email']) ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div> <!-- Fin tab-perfil -->

    </div> <!-- Fin main-content -->

    <!-- ── MODAL VERIFICACIÓN ── -->
    <div id="modalVerify" style="display:none;position:fixed;inset:0;z-index:100;background:rgba(10,20,60,0.8);backdrop-filter:blur(8px);align-items:center;justify-content:center;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&#x2715;</span>
            <h2>Postular para Verificación</h2>
            <p>Sube tu documento de identidad para que el Administrador te valide:</p>
            <form action="verify.php" method="POST" enctype="multipart/form-data">
                <input type="text" name="nombre" placeholder="Nombre del Negocio" required>
                <input type="file" name="id_doc" required>
                <button type="submit" class="btn-submit">Enviar al Administrador</button>
            </form>
        </div>
    </div>

    <!-- ── MODAL VISTA PREVIA SERVICIO ── -->
    <div id="modalServicio">
        <div class="srv-modal-box">
            <button class="srv-modal-close" onclick="cerrarServicio()">✕</button>
            
            <div class="srv-modal-left">
                <div id="srvImgWrap"></div>
                <div class="srv-modal-body">
                    <div id="srvBadgeWrap"></div>
                    <div class="srv-modal-title" id="srvTitulo"></div>
                    <div class="srv-modal-desc"  id="srvDesc"></div>
                    <div class="srv-modal-meta">
                        <div class="srv-meta-item"><span>📍</span><span id="srvMunicipio"></span></div>
                        <div class="srv-meta-item"><span>🏷</span><span id="srvCategoria"></span></div>
                    </div>
                    <div class="srv-modal-price">
                        <small>USD </small><span id="srvPrecio"></span>
                    </div>
                    
                    <!-- Botón de chat (reemplaza el "Contactar" anterior) -->
                    <button class="btn-abrir-chat" id="btnAbrirChat" onclick="abrirChatDesdeModal()" style="margin-bottom:10px;">
                        💬 Contactar por Chat
                    </button>
                    <button id="btnContratar" onclick="solicitarContratacion()"
                        style="width:100%; padding:13px; background:linear-gradient(135deg,#14a87a,#059669); border:none; border-radius:10px; color:#fff; font-size:15px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .2s;">
                        🤝 Solicitar Contratación
                    </button>
                </div>
            </div>

            <div class="srv-modal-right">
                <!-- ── SECCIÓN DE RESEÑAS ─────────────────────── -->
                <div id="srvReviewsSection" style="flex:1; display:flex; flex-direction:column;">
                    <div class="reviews-heading">⭐ Opiniones de clientes</div>
                    <div id="srvReviewsLoading" style="text-align:center; padding:12px 0; color:var(--text-muted); font-size:13px;">Cargando opiniones...</div>
                    <div id="srvReviewsContent" style="display:none; flex:1; display:flex; flex-direction:column; overflow:hidden;">
                        <!-- Resumen de puntuación -->
                        <div id="srvReviewsSummary" style="display:flex; align-items:center; gap:14px; margin-bottom:14px;"></div>
                        <!-- Lista de reseñas -->
                        <div id="srvReviewsList" style="flex:1; display:flex; flex-direction:column; gap:10px; overflow-y:auto; padding-right:4px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════
         VENTANA DE CHAT FLOTANTE
    ══════════════════════════════════════════════════════ -->
    <div id="chatWindow">
        <div class="chat-header">
            <div class="chat-avatar">💬</div>
            <div class="chat-header-info">
                <div class="chat-header-name" id="chatNombreServicio">Servicio</div>
                <div class="chat-header-sub" id="chatSubInfo">Chat con el proveedor</div>
            </div>
            <div class="chat-dot-online" title="En línea"></div>
            <button class="chat-close-btn" onclick="cerrarChat()" title="Cerrar chat">✕</button>
        </div>

        <div id="chatBody">
            <div class="chat-empty" id="chatEmpty">
                <div class="chat-empty-icon">💬</div>
                <div>Inicia la conversación con el proveedor.<br>Responde en minutos.</div>
            </div>
            <!-- Los mensajes se insertan aquí dinámicamente -->
            <div class="chat-typing" id="chatTyping">
                <span>●</span><span>●</span><span>●</span>
            </div>
        </div>

        <div class="chat-footer">
            <textarea
                id="chatInput"
                rows="1"
                placeholder="Escribe un mensaje..."
                maxlength="1000"
            ></textarea>
            <button id="chatSendBtn" onclick="enviarMensaje()" title="Enviar">➤</button>
        </div>
    </div>

    <!-- FAB (botón flotante para reabrir el chat) -->
    <button id="chatFab" onclick="reabrirChat()" title="Abrir chat">
        💬
        <span class="fab-badge" id="fabBadge" style="display:none">1</span>
    </button>

    <!-- FAB: Volver arriba -->
    <button id="scrollTopBtn" title="Volver al inicio" onclick="volverArriba()">↑</button>


    <!-- Datos de sesión para JS -->
    <script>
        const MI_USER_ID   = <?= $mi_user_id ?>;
        const MI_USER_NAME = "<?= $mi_user_name ?>";
    </script>

    <script>
        // ── MODAL VERIFICACIÓN ────────────────────────────────
        function openModal() { document.getElementById('modalVerify').style.display = 'flex'; }
        function closeModal() { document.getElementById('modalVerify').style.display = 'none'; }
        document.getElementById('modalVerify').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // ── MODAL VISTA PREVIA ────────────────────────────────
        // Estado del servicio actualmente seleccionado
        let chatCtx = null; // { servicio_id, proveedor_id, cliente_id, titulo }

        function verServicio(data) {
            var imgWrap = document.getElementById('srvImgWrap');
            imgWrap.innerHTML = data.imagen
                ? '<img class="srv-modal-img" src="' + data.imagen + '" alt="' + data.titulo + '">'
                : '<div class="srv-modal-no-img">🔧</div>';

            var badges = '<span class="srv-modal-badge">' + (data.categoria || 'Servicio') + '</span>';
            if (data.destacado) badges += ' <span class="srv-modal-badge dest">⭐ Destacado</span>';

            document.getElementById('srvBadgeWrap').innerHTML   = badges;
            document.getElementById('srvTitulo').textContent    = data.titulo;
            document.getElementById('srvDesc').textContent      = data.descripcion || 'Sin descripción disponible.';
            document.getElementById('srvMunicipio').textContent = data.municipio;
            document.getElementById('srvCategoria').textContent = data.categoria;
            document.getElementById('srvPrecio').textContent    = parseFloat(data.precio).toFixed(2);

            // Guardar contexto para el chat
            chatCtx = {
                servicio_id  : data.servicio_id,
                proveedor_id : data.proveedor_id,
                cliente_id   : MI_USER_ID,
                titulo       : data.titulo,
            };

            // Si el usuario ES el proveedor de este servicio, ocultar botón de chat
            const btnChat = document.getElementById('btnAbrirChat');
            const btnContratar = document.getElementById('btnContratar');
            if (MI_USER_ID === data.proveedor_id) {
                btnChat.style.display = 'none';
                btnContratar.style.display = 'none';
            } else {
                btnChat.style.display = 'block';
                btnContratar.style.display = 'block';
            }

            // Cargar reseñas
            cargarReseñas(data.servicio_id);

            document.getElementById('modalServicio').classList.add('open');
        }

        function cerrarServicio() {
            document.getElementById('modalServicio').classList.remove('open');
        }
        document.getElementById('modalServicio').addEventListener('click', function(e) {
            if (e.target === this) cerrarServicio();
        });

        // ── CARGA DE RESEÑAS ─────────────────────────────────
        async function cargarReseñas(servicioId) {
            const loading = document.getElementById('srvReviewsLoading');
            const content = document.getElementById('srvReviewsContent');
            const summary = document.getElementById('srvReviewsSummary');
            const list    = document.getElementById('srvReviewsList');

            loading.style.display = 'block';
            content.style.display = 'none';
            list.innerHTML = '';
            summary.innerHTML = '';

            try {
                const res  = await fetch('get_reviews.php?servicio_id=' + servicioId);
                const data = await res.json();

                loading.style.display = 'none';

                if (!data.ok || data.total === 0) {
                    loading.style.display = 'block';
                    loading.innerHTML = '<span style="font-size:20px;">💬</span><br>Aún no hay opiniones para este servicio.';
                    return;
                }

                // Resumen
                const estrellas = '★'.repeat(Math.round(data.promedio)) + '☆'.repeat(5 - Math.round(data.promedio));
                summary.innerHTML = `
                    <div style="font-size:38px; font-weight:800; color:#f59e0b; line-height:1;">${data.promedio}</div>
                    <div>
                        <div style="color:#f59e0b; font-size:18px; letter-spacing:2px;">${estrellas}</div>
                        <div style="color:var(--text-muted); font-size:12px; margin-top:2px;">${data.total} opinión${data.total !== 1 ? 'es' : ''}</div>
                    </div>`;

                // Reseñas individuales
                data.reviews.forEach(r => {
                    const stars = '★'.repeat(r.puntuacion) + '☆'.repeat(5 - r.puntuacion);
                    const badge = r.verificado
                        ? `<span style="background:rgba(74,222,128,0.12); border:1px solid rgba(74,222,128,0.3); color:#4ade80; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; letter-spacing:0.4px;">✔ Verificado</span>`
                        : '';
                    const comentario = r.comentario
                        ? `<p style="font-size:13px; color:rgba(255,255,255,0.7); margin:6px 0 0; font-style:italic; line-height:1.5;">"${r.comentario}"</p>`
                        : '';

                    list.innerHTML += `
                        <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:10px; padding:12px 14px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; flex-wrap:wrap; gap:6px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg,#2d5be3,#3d7af5); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; color:#fff; flex-shrink:0;">
                                        ${r.nombre.charAt(0).toUpperCase()}
                                    </div>
                                    <div>
                                        <div style="font-size:13px; font-weight:600; color:#fff;">${r.nombre} ${badge}</div>
                                        <div style="font-size:11px; color:var(--text-muted);">${r.fecha}</div>
                                    </div>
                                </div>
                                <div style="color:#f59e0b; font-size:13px; letter-spacing:1px;">${stars}</div>
                            </div>
                            ${comentario}
                        </div>`;
                });

                content.style.display = 'block';
            } catch(e) {
                loading.innerHTML = '⚠ No se pudieron cargar las opiniones.';
            }
        }

        // ══════════════════════════════════════════════════════
        //  LÓGICA DEL CHAT
        // ══════════════════════════════════════════════════════
        let pollInterval   = null;
        let ultimoMsgId    = 0;
        let chatAbierto    = false;

        function abrirChatDesdeLista(ctx) {
            chatCtx = ctx;
            document.getElementById('chatNombreServicio').textContent = ctx.titulo;
            document.getElementById('chatSubInfo').textContent = 'Chat con ' + ctx.nombre;
            
            document.getElementById('chatWindow').classList.add('open');
            document.getElementById('chatFab').style.display = 'none';
            document.getElementById('fabBadge').style.display = 'none';
            
            chatAbierto = true;
            ultimoMsgId = 0;
            document.getElementById('chatBody').innerHTML = ''; // Limpiar chat
            
            cargarMensajes();
            
            if (pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(cargarMensajes, 3000);
        }

        function abrirChatDesdeModal() {
            if (!chatCtx) return;
            cerrarServicio();
            abrirChat(chatCtx);
        }

        function abrirChat(ctx) {
            chatCtx    = ctx;
            chatAbierto = true;
            ultimoMsgId = 0;

            // Actualizar header
            document.getElementById('chatNombreServicio').textContent = ctx.titulo;
            document.getElementById('chatSubInfo').textContent        = 'Chat con el proveedor';

            // Limpiar body
            const body = document.getElementById('chatBody');
            body.innerHTML = `
                <div class="chat-empty" id="chatEmpty">
                    <div class="chat-empty-icon">💬</div>
                    <div>Inicia la conversación con el proveedor.<br>Responde en minutos.</div>
                </div>
                <div class="chat-typing" id="chatTyping" style="display:none">
                    <span>●</span><span>●</span><span>●</span>
                </div>`;

            document.getElementById('chatWindow').classList.add('open');
            document.getElementById('chatFab').style.display = 'none';
            document.getElementById('chatInput').focus();

            // Cargar historial y arrancar polling
            cargarMensajes(true);
            clearInterval(pollInterval);
            pollInterval = setInterval(() => cargarMensajes(false), 4000);
        }

        function cerrarChat() {
            document.getElementById('chatWindow').classList.remove('open');
            document.getElementById('chatFab').style.display = 'flex';
            clearInterval(pollInterval);
            chatAbierto = false;
        }

        function reabrirChat() {
            if (!chatCtx) return;
            document.getElementById('chatWindow').classList.add('open');
            document.getElementById('chatFab').style.display = 'none';
            document.getElementById('fabBadge').style.display = 'none';
            chatAbierto = true;
            cargarMensajes(true);
            clearInterval(pollInterval);
            pollInterval = setInterval(() => cargarMensajes(false), 4000);
        }

        async function cargarMensajes(inicial) {
            if (!chatCtx) return;
            const params = new URLSearchParams({
                servicio_id  : chatCtx.servicio_id,
                proveedor_id : chatCtx.proveedor_id,
                cliente_id   : chatCtx.cliente_id,
                desde_id     : inicial ? 0 : ultimoMsgId,
            });

            try {
                const res  = await fetch('chat_get.php?' + params);
                const data = await res.json();
                if (!data.ok) return;

                if (data.mensajes.length === 0) return;

                const body = document.getElementById('chatBody');

                // Quitar empty state si hay mensajes
                const empty = document.getElementById('chatEmpty');
                if (empty) empty.remove();

                data.mensajes.forEach(msg => {
                    if (msg.id > ultimoMsgId) ultimoMsgId = msg.id;
                    agregarBurbuja(msg, body);
                });

                // Scroll al final
                body.scrollTop = body.scrollHeight;

                // Badge FAB si el chat está cerrado y llegó mensaje del otro
                if (!chatAbierto) {
                    const badge = document.getElementById('fabBadge');
                    badge.style.display = 'flex';
                }

            } catch (e) {
                console.warn('Chat polling error:', e);
            }
        }

        function agregarBurbuja(msg, body) {
            // Evitar duplicados
            if (document.getElementById('cmsg-' + msg.id)) return;

            const typing = document.getElementById('chatTyping');
            const div    = document.createElement('div');
            div.className = 'chat-msg ' + (msg.es_mio ? 'mio' : 'otro');
            div.id = 'cmsg-' + msg.id;

            const hora = new Date(msg.created_at).toLocaleTimeString('es-VE', {hour:'2-digit', minute:'2-digit'});
            const tick = msg.es_mio ? (msg.leido ? '<span class="chat-tick">✔✔</span>' : '<span class="chat-tick" style="color:#6a7a9b">✔</span>') : '';

            div.innerHTML = `
                <div class="chat-bubble">${msg.mensaje}</div>
                <div class="chat-time">${hora}${tick}</div>`;

            // Insertar antes del indicador de typing
            if (typing && typing.parentNode === body) {
                body.insertBefore(div, typing);
            } else {
                body.appendChild(div);
            }
        }

        async function enviarMensaje() {
            if (!chatCtx) return;
            const input = document.getElementById('chatInput');
            const texto = input.value.trim();
            if (!texto) return;

            const btn = document.getElementById('chatSendBtn');
            btn.disabled = true;
            input.value  = '';
            autoResizeTextarea(input);

            // Mostrar typing brevemente
            const typing = document.getElementById('chatTyping');
            if (typing) typing.style.display = 'flex';

            const body = new FormData();
            body.append('servicio_id',  chatCtx.servicio_id);
            body.append('proveedor_id', chatCtx.proveedor_id);
            body.append('cliente_id',   chatCtx.cliente_id);
            body.append('mensaje',      texto);

            try {
                const res  = await fetch('chat_send.php', { method:'POST', body });
                const data = await res.json();

                if (typing) typing.style.display = 'none';

                if (data.ok) {
                    // Quitar empty state
                    const empty = document.getElementById('chatEmpty');
                    if (empty) empty.remove();

                    const chatBody = document.getElementById('chatBody');
                    const fakeMsg  = {
                        id         : data.id,
                        es_mio     : true,
                        mensaje    : data.mensaje,
                        leido      : false,
                        created_at : data.created_at,
                    };
                    if (data.id > ultimoMsgId) ultimoMsgId = data.id;
                    agregarBurbuja(fakeMsg, chatBody);
                    chatBody.scrollTop = chatBody.scrollHeight;
                } else {
                    alert('Error: ' + (data.error || 'No se pudo enviar'));
                }
            } catch(e) {
                if (typing) typing.style.display = 'none';
                console.error(e);
                alert('Error de conexión');
            }

            btn.disabled = false;
            input.focus();
        }

        // Auto-resize del textarea
        function autoResizeTextarea(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 100) + 'px';
        }

        document.getElementById('chatInput').addEventListener('input', function() {
            autoResizeTextarea(this);
        });

        // Enter para enviar (Shift+Enter = nueva línea)
        document.getElementById('chatInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                enviarMensaje();
            }
        });

        // ══════════════════════════════════════════════════════
        //  CONTRATACIONES — CLIENTE
        // ══════════════════════════════════════════════════════
        async function solicitarContratacion() {
            if (!chatCtx) return;
            if (!confirm('¿Deseas solicitar la contratación de "' + chatCtx.titulo + '"?\nEl proveedor recibirá tu solicitud y la aceptará o rechazará.')) return;

            const btnContratar = document.getElementById('btnContratar');
            btnContratar.disabled = true;
            btnContratar.textContent = 'Enviando...';

            const fd = new FormData();
            fd.append('action', 'solicitar');
            fd.append('servicio_id', chatCtx.servicio_id);

            try {
                const res  = await fetch('contratacion_actions.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) {
                    cerrarServicio();
                    // Redirigir a la pestaña de contrataciones
                    window.location.href = '?tab=contratos';
                } else {
                    alert('⚠️ ' + (data.error || 'No se pudo enviar la solicitud.'));
                    btnContratar.disabled = false;
                    btnContratar.textContent = '🤝 Solicitar Contratación';
                }
            } catch(e) {
                alert('Error de conexión. Intenta de nuevo.');
                btnContratar.disabled = false;
                btnContratar.textContent = '🤝 Solicitar Contratación';
            }
        }

        async function cancelarContrato(id) {
            const motivo = prompt('¿Por qué deseas cancelar esta contratación?\n(Es requerido para garantizar transparencia)');
            if (motivo === null || motivo.trim() === '') {
                alert('Debes indicar un motivo para cancelar.');
                return;
            }
            const fd = new FormData();
            fd.append('action', 'cancelar');
            fd.append('contratacion_id', id);
            fd.append('motivo', motivo);
            try {
                const res  = await fetch('contratacion_actions.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) { window.location.reload(); }
                else { alert('Error: ' + data.error); }
            } catch(e) { alert('Error de conexión'); }
        }

        // ── Modal Valorar ─────────────────────────────────────
        let _valorarId = null;
        function abrirModalValorar(id, titulo) {
            _valorarId = id;
            document.getElementById('valorar-titulo').textContent = titulo;
            document.querySelectorAll('.star-btn').forEach(s => {
                s.classList.remove('selected');
                s.style.color = '#6a7a9b';
            });
            document.getElementById('valorar-comentario').value = '';
            document.getElementById('modalValorar').classList.add('open');
        }
        function cerrarModalValorar() {
            document.getElementById('modalValorar').classList.remove('open');
        }
        function seleccionarEstrella(n) {
            document.getElementById('valorar-puntuacion').value = n;
            document.querySelectorAll('.star-btn').forEach((s, i) => {
                s.style.color = i < n ? '#f5820d' : '#6a7a9b';
            });
        }
    </script>

    <script>
        // ── Bloquear botón atrás ──────────────────────────────
        history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function() {
            history.pushState(null, null, window.location.href);
        });
    </script>

    <script>
        // ── Archivar / Desarchivar chat ───────────────────────
        async function archivarChat(ctx, archivar, btn) {
            btn.disabled = true;
            const fd = new FormData();
            fd.append('servicio_id',  ctx.servicio_id);
            fd.append('cliente_id',   ctx.cliente_id);
            fd.append('proveedor_id', ctx.proveedor_id);
            fd.append('archivar',     archivar);
            try {
                const res  = await fetch('chat_archivar.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) {
                    // Quitar el item de la lista actual (se mueve al otro filtro)
                    const item = btn.closest('.conv-item');
                    item.style.transition = 'all .3s ease';
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(20px)';
                    setTimeout(() => item.remove(), 300);
                } else {
                    alert('Error: ' + (data.error || 'No se pudo archivar'));
                    btn.disabled = false;
                }
            } catch(e) {
                alert('Error de conexión');
                btn.disabled = false;
            }
        }

    </script>

    <script>
        // ── Botón volver arriba ───────────────────────────────
        const mainContent = document.querySelector('.main-content');
        const scrollTopBtn = document.getElementById('scrollTopBtn');

        if (mainContent && scrollTopBtn) {
            mainContent.addEventListener('scroll', function() {
                if (mainContent.scrollTop > 300) {
                    scrollTopBtn.classList.add('visible');
                } else {
                    scrollTopBtn.classList.remove('visible');
                }
            });
        }

        function volverArriba() {
            if (mainContent) {
                mainContent.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }
    </script>

    <!-- ══ MODAL VALORAR SERVICIO ══════════════════════════════ -->
    <div id="modalValorar" style="display:none; position:fixed; inset:0; z-index:600; background:rgba(10,20,60,0.88); backdrop-filter:blur(10px); align-items:center; justify-content:center; padding:20px;">
        <div style="background:#0d1e4a; border:1px solid rgba(255,255,255,0.1); border-radius:18px; width:100%; max-width:460px; padding:32px; position:relative; animation:slideUp .3s ease;">
            <button onclick="cerrarModalValorar()" style="position:absolute; top:14px; right:16px; background:rgba(255,255,255,0.07); border:none; color:#8898bb; width:32px; height:32px; border-radius:50%; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center;">✕</button>
            <h2 style="font-family:'Rajdhani',sans-serif; font-size:24px; font-weight:700; margin-bottom:6px;">⭐ Valorar Servicio</h2>
            <p id="valorar-titulo" style="color:var(--text-muted); font-size:14px; margin-bottom:24px;"></p>
            <form id="formValorar">
                <input type="hidden" id="valorar-puntuacion" value="0">
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:12px;">Puntuación</label>
                    <div style="display:flex; gap:10px;">
                        <?php for($i=1; $i<=5; $i++): ?>
                        <button type="button" class="star-btn" onclick="seleccionarEstrella(<?= $i ?>)"
                            style="background:none; border:none; font-size:34px; cursor:pointer; color:#6a7a9b; transition:color .15s, transform .15s; padding:2px;"
                            onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">★</button>
                        <?php endfor; ?>
                    </div>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); margin-bottom:8px;">Comentario (opcional)</label>
                    <textarea id="valorar-comentario" rows="3" placeholder="Cuéntanos tu experiencia con el servicio..."
                        style="width:100%; padding:11px 14px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:9px; color:#fff; font-size:14px; outline:none; font-family:'DM Sans',sans-serif; resize:vertical; min-height:80px;"></textarea>
                </div>
                <button type="submit"
                    style="width:100%; padding:13px; background:linear-gradient(135deg,#f59e0b,#f5820d); border:none; border-radius:10px; color:#fff; font-size:15px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif;">
                    Enviar Valoración
                </button>
            </form>
        </div>
    </div>
    <script>
        // Hacer que el modal valorar sea display:flex cuando tiene clase open
        const mV = document.getElementById('modalValorar');
        const obs = new MutationObserver(() => {
            mV.style.display = mV.classList.contains('open') ? 'flex' : 'none';
        });
        obs.observe(mV, { attributes: true, attributeFilter: ['class'] });
        mV.addEventListener('click', e => { if(e.target === mV) cerrarModalValorar(); });

        document.getElementById('formValorar').addEventListener('submit', async function(e) {
            e.preventDefault();
            const puntuacion = document.getElementById('valorar-puntuacion').value;
            if (!puntuacion || puntuacion < 1) { alert('Por favor selecciona una puntuación.'); return; }
            const comentario = document.getElementById('valorar-comentario').value;
            const fd = new FormData();
            fd.append('action', 'valorar');
            fd.append('contratacion_id', _valorarId);
            fd.append('puntuacion', puntuacion);
            fd.append('comentario', comentario);
            const btn = this.querySelector('button[type=submit]');
            btn.disabled = true; btn.textContent = 'Enviando...';
            try {
                const res  = await fetch('contratacion_actions.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) { window.location.reload(); }
                else { alert('Error: ' + data.error); btn.disabled = false; btn.textContent = 'Enviar Valoración'; }
            } catch(e) { alert('Error de conexión'); btn.disabled = false; btn.textContent = 'Enviar Valoración'; }
        });

        // ── Menú Móvil ─────────────────────────────────────────────
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }
    </script>
</body>
</html>