<?php
require_once __DIR__ . '/auth_guard.php';

// Solo proveedores
if (($_SESSION['user_role'] ?? '') !== 'proveedor') {
    header('Location: index.php');
    exit;
}

include __DIR__ . '/db.php';
include __DIR__ . '/get_lists.php';
$all_municipios = getMunicipios($conn);
$all_categorias = getCategorias($conn);

$uid = idUsuario();

// ── Traer servicios del proveedor ─────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT * FROM servicios WHERE usuario_id = ? AND deleted_at IS NULL ORDER BY created_at DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$servicios = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$msg = '';
$msg_type = '';
if (isset($_GET['ok']))    { $msg = '✔ Cambios guardados correctamente.'; $msg_type = 'success'; }
if (isset($_GET['added'])) { $msg = '✔ Nuevo servicio añadido.';           $msg_type = 'success'; }
if (isset($_GET['del']))   { $msg = '✔ Servicio eliminado.';               $msg_type = 'warning'; }
if (isset($_GET['err']))   { $msg = '✗ Ocurrió un error. Intenta de nuevo.'; $msg_type = 'error'; }

// ── Traer conversaciones de chat del proveedor ────────────────
$chat_filtro_prov = isset($_GET['chats_tab']) && $_GET['chats_tab'] === 'archivados' ? 1 : 0;

$stmt_chats = mysqli_prepare($conn,
    "SELECT
        cm.servicio_id,
        cm.cliente_id,
        cm.proveedor_id,
        s.titulo        AS servicio_titulo,
        u.nombre        AS cliente_nombre,
        u.apellido      AS cliente_apellido,
        MAX(cm.created_at)  AS ultimo_at,
        SUBSTRING_INDEX(GROUP_CONCAT(cm.mensaje ORDER BY cm.created_at DESC SEPARATOR '|||'), '|||', 1) AS ultimo_msg,
        SUM(cm.leido = 0 AND cm.emisor_id != ?)   AS no_leidos,
        MAX(cm.archivado_proveedor)               AS esta_archivado
     FROM chat_mensajes cm
     JOIN servicios s ON s.id = cm.servicio_id
     JOIN usuarios  u ON u.id = cm.cliente_id
     WHERE cm.proveedor_id = ?
     GROUP BY cm.servicio_id, cm.cliente_id, cm.proveedor_id
     HAVING MAX(cm.archivado_proveedor) = ?
     ORDER BY ultimo_at DESC"
);
mysqli_stmt_bind_param($stmt_chats, 'iii', $uid, $uid, $chat_filtro_prov);
mysqli_stmt_execute($stmt_chats);
$chats = mysqli_stmt_get_result($stmt_chats)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt_chats);

// No leídos solo en activos (para el stat card)
$stmt_nl_prov = mysqli_prepare($conn,
    "SELECT SUM(cm.leido = 0 AND cm.emisor_id != ?) AS total_nl
     FROM chat_mensajes cm
     WHERE cm.proveedor_id = ? AND cm.archivado_proveedor = 0"
);
mysqli_stmt_bind_param($stmt_nl_prov, 'ii', $uid, $uid);
mysqli_stmt_execute($stmt_nl_prov);
$total_no_leidos = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_nl_prov))['total_nl'] ?? 0);
mysqli_stmt_close($stmt_nl_prov);

// Total archivados del proveedor (para el contador del filtro)
$stmt_arch_prov = mysqli_prepare($conn,
    "SELECT COUNT(DISTINCT CONCAT(servicio_id,'-',cliente_id,'-',proveedor_id)) AS total_arch
     FROM chat_mensajes
     WHERE proveedor_id = ? AND archivado_proveedor = 1"
);
mysqli_stmt_bind_param($stmt_arch_prov, 'i', $uid);
mysqli_stmt_execute($stmt_arch_prov);
$total_archivados_prov = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_arch_prov))['total_arch'] ?? 0);
mysqli_stmt_close($stmt_arch_prov);

// ── Traer estado de verificación del proveedor ────────────────
$stmt_verif = mysqli_prepare($conn, "SELECT * FROM verificaciones WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 1");
mysqli_stmt_bind_param($stmt_verif, 'i', $uid);
mysqli_stmt_execute($stmt_verif);
$verificacion_estado = mysqli_stmt_get_result($stmt_verif)->fetch_assoc();
mysqli_stmt_close($stmt_verif);

// ── Traer contrataciones del proveedor ─────────────────────────
$stmt_contratos = mysqli_prepare($conn,
    "SELECT c.*, s.titulo AS servicio_titulo, u.nombre AS cliente_nombre, u.apellido AS cliente_apellido,
            v.puntuacion, v.comentario
     FROM contrataciones c
     JOIN servicios s ON s.id = c.servicio_id
     JOIN usuarios u ON u.id = c.cliente_id
     LEFT JOIN valoraciones v ON v.contratacion_id = c.id
     WHERE c.proveedor_id = ?
     ORDER BY c.created_at DESC"
);
mysqli_stmt_bind_param($stmt_contratos, 'i', $uid);
mysqli_stmt_execute($stmt_contratos);
$contrataciones = mysqli_stmt_get_result($stmt_contratos)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt_contratos);

$total_contratos_pendientes = 0;
$total_contratos_completados = 0;
// Agrupar contrataciones por servicio y calcular métricas
$contratos_por_servicio = [];
foreach ($contrataciones as $c) {
    if ($c['estado'] === 'pendiente') $total_contratos_pendientes++;
    if ($c['estado'] === 'completado') $total_contratos_completados++;
    $contratos_por_servicio[$c['servicio_id']]['contratos'][] = $c;
}

foreach ($contratos_por_servicio as $sid => &$data) {
    $pendientes = 0;
    $sum_punt = 0;
    $count_punt = 0;
    foreach ($data['contratos'] as $ct) {
        if ($ct['estado'] === 'pendiente') $pendientes++;
        if ($ct['puntuacion']) {
            $sum_punt += $ct['puntuacion'];
            $count_punt++;
        }
    }
    $data['pendientes'] = $pendientes;
    $data['promedio'] = $count_punt > 0 ? round($sum_punt / $count_punt, 1) : 0;
}
unset($data);

$stmt_val = mysqli_prepare($conn, "SELECT AVG(puntuacion) as prom, COUNT(*) as total FROM valoraciones WHERE proveedor_id = ?");
mysqli_stmt_bind_param($stmt_val, 'i', $uid);
mysqli_stmt_execute($stmt_val);
$row_val = mysqli_stmt_get_result($stmt_val)->fetch_assoc();
$promedio_puntuacion = $row_val['prom'] ? round($row_val['prom'], 1) : 0;
$total_valoraciones = $row_val['total'] ? $row_val['total'] : 0;

$tab = $_GET['tab'] ?? 'servicios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servi-Job — Mi Panel</title>
    <link href="fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --navy: #0f2057;
            --blue: #1a3a8f;
            --blue-mid: #2d5be3;
            --blue-light: #3d7af5;
            --orange: #f5820d;
            --orange-dark: #d96a00;
            --white: #ffffff;
            --off-white: #f4f6fc;
            --text-muted: #8898bb;
            --border: rgba(255,255,255,0.08);
            --card-bg: rgba(255,255,255,0.04);
            --radius: 12px;
            --bg: #0c1840;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--white);
            min-height: 100vh;
        }
        body::before {
            content:''; position:fixed; inset:0; pointer-events:none;
            background:
                radial-gradient(ellipse 70% 50% at 5% 0%, rgba(45,91,227,0.2) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 95% 100%, rgba(245,130,13,0.1) 0%, transparent 55%);
        }

        /* ── SIDEBAR & LAYOUT ── */
        body { display: flex; }
        .sidebar { width: 260px; background: var(--navy); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 24px 0; flex-shrink: 0; }
        .logo { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; padding: 0 24px 32px; border-bottom: 1px solid var(--border); margin-bottom: 24px; display:flex; align-items:center; gap:8px; text-decoration:none; color:white; }
        .logo .logo-text { color: white; }
        .logo .logo-text span { color: var(--orange); }
        .nav-item { padding: 14px 24px; color: var(--text-muted); font-size: 15px; font-weight: 500; display: flex; align-items: center; gap: 12px; transition: all 0.2s; cursor: pointer; text-decoration:none; }
        .nav-item:hover, .nav-item.active { background: rgba(61,122,245,0.1); color: var(--blue-light); border-right: 3px solid var(--blue-light); }
        .nav-badge { background: var(--orange); color: white; font-size: 11px; padding: 2px 6px; border-radius: 10px; margin-left: auto; }
        
        .sidebar-bottom { margin-top: auto; padding: 24px; border-top: 1px solid var(--border); }
        .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--blue-mid), var(--blue-light)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size:14px; }
        .btn-logout { display: block; width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,100,100,0.3); background: rgba(255,100,100,0.1); color: #ff8a8a; text-align: center; font-size: 14px; transition: all 0.2s; text-decoration:none; }
        .btn-logout:hover { background: rgba(255,100,100,0.2); }

        .main-content { flex: 1; overflow-y: auto; height: 100vh; display: flex; flex-direction: column; }
        .tab-content { display: none; flex: 1; padding: 40px; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .wrapper { max-width:1280px; margin:0 auto; width:100%; }

        /* ── PAGE TITLE ── */
        .page-header { margin-bottom:32px; }
        .page-header h1 { font-family:'Rajdhani',sans-serif; font-size:34px; font-weight:700; }
        .page-header h1 span { color:var(--orange); }
        .page-header p { color:var(--text-muted); font-size:15px; margin-top:4px; }

        /* ── TOAST ── */
        .toast {
            padding:14px 20px; border-radius:10px; margin-bottom:24px;
            font-size:14px; font-weight:500; display:flex; align-items:center; gap:10px;
        }
        .toast.success { background:rgba(74,222,128,0.1); border:1px solid rgba(74,222,128,0.3); color:#4ade80; }
        .toast.warning { background:rgba(245,130,13,0.1); border:1px solid rgba(245,130,13,0.3); color:var(--orange); }
        .toast.error   { background:rgba(239,68,68,0.1);  border:1px solid rgba(239,68,68,0.3);  color:#f87171; }

        /* ── GRID ── */
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        @media(max-width:900px) { .grid { grid-template-columns:1fr; } .wrapper { padding:24px 20px; } header { padding:0 20px; } }

        /* ── CARD ── */
        .panel-card {
            background:var(--card-bg);
            border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
        }
        .panel-card.full { grid-column:1/-1; }
        .card-head {
            padding:20px 24px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between;
        }
        .card-head h2 {
            font-family:'Rajdhani',sans-serif; font-size:20px; font-weight:700;
            display:flex; align-items:center; gap:8px;
        }
        .card-body { padding:24px; }

        /* ── FORM FIELDS ── */
        .field { margin-bottom:18px; }
        .field label {
            display:block; font-size:12px; font-weight:600;
            text-transform:uppercase; letter-spacing:0.5px;
            color:var(--text-muted); margin-bottom:8px;
        }
        .field input, .field textarea, .field select {
            width:100%; padding:11px 14px;
            background:rgba(255,255,255,0.06);
            border:1px solid rgba(255,255,255,0.1);
            border-radius:9px; color:var(--white);
            font-size:14px; outline:none;
            font-family:'DM Sans',sans-serif;
            transition:border-color 0.2s, box-shadow 0.2s;
        }
        .field input:focus, .field textarea:focus, .field select:focus {
            border-color:var(--blue-light);
            box-shadow:0 0 0 3px rgba(61,122,245,0.15);
        }
        .field input::placeholder, .field textarea::placeholder { color:rgba(255,255,255,0.2); }
        .field textarea { resize:vertical; min-height:90px; }
        .field select option { background:#1a3a8f; color:#fff; }
        .field-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

        /* ── IMAGE UPLOAD ── */
        .img-preview {
            width:100%; height:200px; border-radius:10px; overflow:hidden;
            background:rgba(255,255,255,0.04);
            border:2px dashed rgba(255,255,255,0.12);
            display:flex; align-items:center; justify-content:center;
            flex-direction:column; gap:8px; cursor:pointer;
            transition:all 0.2s; margin-bottom:12px; position:relative;
        }
        .img-preview:hover { border-color:var(--orange); background:rgba(245,130,13,0.05); }
        .img-preview img {
            width:100%; height:100%; object-fit:cover; position:absolute; inset:0;
        }
        .img-preview .upload-hint { color:var(--text-muted); font-size:13px; text-align:center; z-index:1; }
        .img-preview .upload-icon { font-size:32px; }
        input[type="file"] { display:none; }

        /* ── BUTTONS ── */
        .btn {
            padding:11px 22px; border-radius:8px; border:none;
            font-size:14px; font-weight:600; cursor:pointer;
            font-family:'DM Sans',sans-serif; transition:all 0.2s;
        }
        .btn-primary {
            background:linear-gradient(135deg, var(--blue-mid), var(--blue-light));
            color:white; box-shadow:0 0 20px rgba(45,91,227,0.3);
        }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 0 30px rgba(45,91,227,0.5); }
        .btn-orange {
            background:var(--orange); color:white;
            box-shadow:0 0 20px rgba(245,130,13,0.3);
        }
        .btn-orange:hover { background:var(--orange-dark); transform:translateY(-1px); }
        .btn-ghost {
            background:rgba(255,255,255,0.06);
            border:1px solid var(--border); color:var(--text-muted);
        }
        .btn-ghost:hover { color:var(--white); border-color:rgba(255,255,255,0.2); }
        .btn-danger {
            background:rgba(239,68,68,0.12);
            border:1px solid rgba(239,68,68,0.3);
            color:#f87171;
        }
        .btn-danger:hover { background:rgba(239,68,68,0.25); }
        .btn-sm { padding:7px 14px; font-size:12px; }
        .btn-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:4px; }

        /* ── SERVICE CARDS ── */
        .servicio-card {
            background:rgba(255,255,255,0.03);
            border:1px solid var(--border);
            border-radius:10px; overflow:hidden;
            transition:all 0.2s;
        }
        .servicio-card:hover { border-color:rgba(61,122,245,0.3); transform:translateY(-2px); }
        .servicio-card.destacado { border-color:rgba(245,130,13,0.35); }
        .servicio-img {
            width:100%; height:140px;
            background:linear-gradient(135deg, rgba(26,58,143,0.5), rgba(245,130,13,0.15));
            display:flex; align-items:center; justify-content:center;
            font-size:40px; overflow:hidden; position:relative;
        }
        .servicio-img img { width:100%; height:100%; object-fit:cover; position:absolute; inset:0; }
        .servicio-body { padding:16px; }
        .servicio-nombre { font-family:'Rajdhani',sans-serif; font-size:18px; font-weight:700; margin-bottom:4px; }
        .servicio-desc { font-size:13px; color:var(--text-muted); margin-bottom:10px; line-height:1.5; }
        .servicio-meta { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
        .tag {
            padding:3px 10px; border-radius:4px; font-size:11px; font-weight:600;
            text-transform:uppercase; letter-spacing:0.4px;
        }
        .tag-blue { background:rgba(61,122,245,0.15); color:var(--blue-light); }
        .tag-orange { background:rgba(245,130,13,0.15); color:var(--orange); }
        .tag-green { background:rgba(74,222,128,0.12); color:#4ade80; }
        .servicio-precio {
            font-family:'Rajdhani',sans-serif; font-size:26px; font-weight:700;
            color:var(--white); margin-bottom:12px;
        }
        .servicio-precio sup { font-size:14px; color:var(--text-muted); }
        .servicio-acciones { display:flex; gap:8px; }

        /* ── STATS BAR ── */
        .stats-bar {
            display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:28px;
        }
        /* Wrapper para stats-bar con scroll horizontal en móvil */
        .stats-bar-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom:28px; padding-bottom: 4px; }
        .stats-bar-wrap .stats-bar { margin-bottom:0; min-width: 600px; }
        .stat-box {
            background:var(--card-bg); border:1px solid var(--border);
            border-radius:10px; padding:18px 20px;
            display:flex; align-items:center; gap:14px;
        }
        .stat-icon {
            width:42px; height:42px; border-radius:9px;
            display:flex; align-items:center; justify-content:center; font-size:20px;
            flex-shrink:0;
        }
        .stat-icon.blue  { background:rgba(45,91,227,0.15); }
        .stat-icon.orange{ background:rgba(245,130,13,0.15); }
        .stat-icon.green { background:rgba(74,222,128,0.12); }
        .stat-num { font-family:'Rajdhani',sans-serif; font-size:28px; font-weight:700; }
        .stat-label { font-size:12px; color:var(--text-muted); }

        /* ── MODAL ── */
        .modal-overlay {
            display:none; position:fixed; inset:0; z-index:200;
            background:rgba(10,20,60,0.85); backdrop-filter:blur(8px);
            align-items:center; justify-content:center;
        }
        .modal-overlay.open { display:flex; }
        .modal-box {
            background:#0d1e4a; border:1px solid var(--border);
            border-radius:16px; padding:36px; width:100%; max-width:560px;
            position:relative; animation:slideUp 0.3s ease;
            max-height:90vh; overflow-y:auto;
        }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        .modal-close {
            position:absolute; top:14px; right:18px;
            background:none; border:none; color:var(--text-muted);
            font-size:20px; cursor:pointer;
        }
        .modal-title {
            font-family:'Rajdhani',sans-serif; font-size:24px; font-weight:700;
            margin-bottom:20px;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align:center; padding:48px 24px; color:var(--text-muted);
        }
        .empty-state .empty-icon { font-size:48px; margin-bottom:12px; opacity:0.4; }
        .empty-state p { font-size:14px; }

        /* ══ SECCIÓN CHAT ══════════════════════════════════════ */
        .chat-section { margin-top: 48px; }
        .chat-section-title {
            font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700;
            margin-bottom: 20px; display:flex; align-items:center; gap:10px;
        }
        .chat-badge-total {
            background: var(--orange); color:#fff;
            font-size:12px; font-weight:700;
            padding:2px 9px; border-radius:10px;
            font-family:'DM Sans',sans-serif;
        }
        .conv-list { display:flex; flex-direction:column; gap:10px; }
        .conv-item {
            background:var(--card-bg); border:1px solid var(--border);
            border-radius:12px; padding:16px 20px;
            display:flex; align-items:center; gap:16px;
            cursor:pointer; transition:all .2s;
        }
        .conv-item:hover { border-color:rgba(61,122,245,0.4); background:rgba(61,122,245,0.05); transform:translateX(3px); }
        .conv-item.unread { border-color:rgba(245,130,13,0.35); }
        .conv-avatar {
            width:44px; height:44px; border-radius:50%; flex-shrink:0;
            background:linear-gradient(135deg,#2d5be3,#14a87a);
            display:flex; align-items:center; justify-content:center;
            font-size:18px; font-weight:700; color:#fff;
        }
        .conv-info { flex:1; min-width:0; }
        .conv-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:3px; }
        .conv-name { font-weight:700; font-size:14px; color:#fff; }
        .conv-time { font-size:11px; color:var(--text-muted); }
        .conv-service { font-size:12px; color:var(--blue-light); margin-bottom:4px; }
        .conv-preview {
            font-size:13px; color:var(--text-muted);
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .conv-unread-badge {
            background:var(--orange); color:#fff;
            font-size:11px; font-weight:700;
            min-width:20px; height:20px; border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            padding:0 5px; flex-shrink:0;
        }
        .conv-item.archivado { opacity: 0.6; }
        .conv-item.archivado .conv-name { color: var(--text-muted); }
        .conv-arch-btn {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-muted); font-size: 11px; font-weight: 600;
            padding: 4px 10px; border-radius: 6px; cursor: pointer;
            flex-shrink: 0; transition: all .2s; white-space: nowrap;
        }
        .conv-arch-btn:hover { background:rgba(245,130,13,0.15); border-color:rgba(245,130,13,0.4); color:var(--orange); }
        .conv-arch-btn.desarchivar { background:rgba(61,122,245,0.1); border-color:rgba(61,122,245,0.3); color:var(--blue-light); }
        .conv-arch-btn.desarchivar:hover { background:rgba(61,122,245,0.2); }
        .chat-tabs { display:flex; gap:8px; margin-bottom:20px; }
        .chat-tab-btn {
            padding:8px 18px; border-radius:8px; font-size:13px; font-weight:600;
            border:1px solid var(--border); background:var(--card-bg);
            color:var(--text-muted); transition:all .2s;
            font-family:'DM Sans',sans-serif; display:flex; align-items:center; gap:6px;
            text-decoration:none; cursor:pointer;
        }
        .chat-tab-btn.active { background:rgba(61,122,245,0.15); border-color:rgba(61,122,245,0.4); color:var(--blue-light); }
        .chat-tab-count { background:rgba(255,255,255,0.1); font-size:10px; padding:1px 6px; border-radius:8px; }
        #chatWindow {
            display:none; position:fixed;
            bottom:28px; right:28px;
            width:370px; height:520px;
            background:#0a1640;
            border:1px solid rgba(61,122,245,0.35);
            border-radius:20px;
            box-shadow:0 20px 60px rgba(0,0,0,0.6);
            z-index:500; flex-direction:column; overflow:hidden;
            animation:chatEntrar .25s ease;
        }
        #chatWindow.open { display:flex; }
        @keyframes chatEntrar {
            from{opacity:0;transform:translateY(20px) scale(.97)}
            to  {opacity:1;transform:translateY(0) scale(1)}
        }
        .chat-header {
            display:flex; align-items:center; gap:10px;
            padding:16px 18px;
            background:linear-gradient(135deg,#1a3a8f,#0f2057);
            border-bottom:1px solid rgba(255,255,255,0.08); flex-shrink:0;
        }
        .chat-avatar-sm {
            width:38px; height:38px; border-radius:50%; flex-shrink:0;
            background:linear-gradient(135deg,#2d5be3,#14a87a);
            display:flex; align-items:center; justify-content:center; font-size:16px;
        }
        .chat-header-info { flex:1; min-width:0; }
        .chat-header-name { font-weight:700; font-size:14px; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .chat-header-sub  { font-size:11px; color:#8898bb; margin-top:1px; }
        .chat-close-btn {
            background:rgba(255,255,255,0.07); border:none; color:#8898bb;
            width:30px; height:30px; border-radius:8px; font-size:14px; cursor:pointer;
            display:flex; align-items:center; justify-content:center; transition:all .2s; flex-shrink:0;
        }
        .chat-close-btn:hover { background:rgba(255,80,80,0.2); color:#ff6b6b; }
        #chatBody {
            flex:1; overflow-y:auto; padding:16px 14px;
            display:flex; flex-direction:column; gap:10px; scroll-behavior:smooth;
        }
        #chatBody::-webkit-scrollbar { width:4px; }
        #chatBody::-webkit-scrollbar-thumb { background:rgba(61,122,245,0.3); border-radius:2px; }
        .chat-msg { display:flex; flex-direction:column; max-width:80%; }
        .chat-msg.mio  { align-self:flex-end; align-items:flex-end; }
        .chat-msg.otro { align-self:flex-start; align-items:flex-start; }
        .chat-bubble { padding:10px 14px; border-radius:16px; font-size:13.5px; line-height:1.5; word-break:break-word; }
        .chat-msg.mio  .chat-bubble { background:linear-gradient(135deg,#2d5be3,#3d7af5); color:#fff; border-bottom-right-radius:4px; }
        .chat-msg.otro .chat-bubble { background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.1); color:#d8e0f0; border-bottom-left-radius:4px; }
        .chat-time { font-size:10px; color:#6a7a9b; margin-top:3px; padding:0 4px; }
        .chat-tick { font-size:10px; color:#3d7af5; margin-left:4px; }
        .chat-empty {
            flex:1; display:flex; flex-direction:column;
            align-items:center; justify-content:center;
            color:#4a5a7a; font-size:13px; text-align:center; gap:10px;
        }
        .chat-empty-icon { font-size:38px; opacity:.6; }
        .chat-footer {
            padding:12px 14px; border-top:1px solid rgba(255,255,255,0.07);
            display:flex; align-items:flex-end; gap:8px;
            background:#0d1e4a; flex-shrink:0;
        }
        #chatInput {
            flex:1; background:rgba(255,255,255,0.06);
            border:1px solid rgba(255,255,255,0.12); border-radius:12px;
            padding:10px 14px; color:#fff; font-size:13.5px;
            font-family:'DM Sans',sans-serif; resize:none; max-height:100px;
            outline:none; transition:border-color .2s; line-height:1.4;
        }
        #chatInput::placeholder { color:#4a5a7a; }
        #chatInput:focus { border-color:rgba(61,122,245,0.5); }
        #chatSendBtn {
            width:40px; height:40px;
            background:linear-gradient(135deg,#2d5be3,#3d7af5);
            border:none; border-radius:12px; color:#fff; font-size:17px;
            cursor:pointer; display:flex; align-items:center; justify-content:center;
            transition:all .2s; flex-shrink:0;
        }
        #chatSendBtn:hover { transform:scale(1.08); box-shadow:0 0 16px rgba(61,122,245,0.5); }
        /* ── RESPONSIVE MOBILE ── */
        .mobile-header { display:none; }
        .sidebar-overlay { display:none; }
        @media(max-width: 900px) {
            /* El body pasa a columna: mobile-header arriba, luego el área de contenido */
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

            /* El header pegado al tope, ocupando el 100% */
            .mobile-header {
                display: flex; align-items: center; justify-content: space-between;
                padding: 14px 20px;
                background: var(--navy); border-bottom: 1px solid var(--border);
                position: sticky; top: 0; z-index: 998;
                width: 100%; flex-shrink: 0;
            }
            .mobile-header-title {
                font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700;
                display: flex; align-items: center; gap: 8px;
            }
            .mobile-header-title span { color: var(--orange); }
            .mobile-menu-btn {
                background: none; border: none; color: white;
                font-size: 26px; cursor: pointer; line-height: 1;
            }

            /* El main-content ocupa todo el ancho restante */
            .main-content {
                width: 100% !important;
                flex: 1;
                overflow-x: hidden;
                padding-top: 0;
            }
            .tab-content { padding: 20px 16px; }
            .grid { grid-template-columns: 1fr !important; }
            .stats-bar { grid-template-columns: repeat(2, 1fr); min-width: unset; }
            .stats-bar-wrap .stats-bar { min-width: 520px; grid-template-columns: repeat(3, 1fr); }
            /* Chat a pantalla completa en móvil */
            #chatWindow { width: 100% !important; height: 100% !important; bottom: 0; right: 0; left: 0; top: 0; border-radius: 0; z-index: 1100; }
            /* Botón de cierre del chat más grande y visible */
            .chat-close-btn {
                width: 38px !important; height: 38px !important;
                font-size: 20px !important;
                background: rgba(255,80,80,0.15) !important;
                color: #ff6b6b !important;
                border-radius: 10px !important;
            }
            .modal-box { padding: 24px 16px; margin: 10px; width: 100%; max-height: 90vh; }
            .page-header h1 { font-size: 28px; }
            .btn-row { flex-direction: column; }
            .btn-row .btn { width: 100%; margin-bottom: 8px; }
            .servicio-acciones { flex-direction: column; }
            .servicio-acciones button { width: 100%; margin-bottom: 8px; }
        }
    </style>
</head>
<body>

<!-- MOBILE HEADER & OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="mobile-header">
    <div class="mobile-header-title"><span style="color:white;">SERVI-<span>JOB</span></span></div>
    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>
</div>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="proveedor_panel.php" class="logo">
        <div class="logo-icon">⚙</div>
        <span class="logo-text">SERVI-<span>JOB</span></span>
    </a>

    <a href="?tab=servicios" class="nav-item <?= $tab == 'servicios' ? 'active' : '' ?>">
        📋 Mis Servicios
    </a>
    <a href="?tab=chats" class="nav-item <?= $tab == 'chats' ? 'active' : '' ?>">
        💬 Mis Chats
        <?php if($total_no_leidos > 0): ?><span class="nav-badge"><?= $total_no_leidos ?></span><?php endif; ?>
    </a>
    <a href="?tab=contratos" class="nav-item <?= $tab == 'contratos' ? 'active' : '' ?>">
        🤝 Contrataciones
        <?php if($total_contratos_pendientes > 0): ?><span class="nav-badge" style="background:#4ade80"><?= $total_contratos_pendientes ?></span><?php endif; ?>
    </a>
    <a href="?tab=perfil" class="nav-item <?= $tab == 'perfil' ? 'active' : '' ?>">
        👤 Mi Perfil
    </a>

    <div style="flex:1"></div>
    <a href="index.php" class="nav-item" style="border-top: 1px solid rgba(255,255,255,0.05); color: #a5b4fc;">
        🔍 Cambiar a Modo Cliente
    </a>

    <div class="sidebar-bottom">
        <div class="user-info">
            <div class="user-avatar"><?= substr(nombreUsuario(), 0, 1) ?></div>
            <div>
                <div style="font-size: 14px; font-weight: 600;"><?= nombreUsuario() ?></div>
                <div style="font-size: 12px; color: var(--text-muted);">Proveedor</div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</div>

<div class="main-content">
    
    <!-- TOAST GENERAL -->
    <?php if ($msg): ?>
        <div class="toast <?= $msg_type ?>" style="margin: 20px 40px 0;"><?= $msg ?></div>
    <?php endif; ?>

    <!-- ── TAB: SERVICIOS ── -->
    <div id="tab-servicios" class="tab-content <?= $tab == 'servicios' ? 'active' : '' ?>">
        <div class="page-header">
            <h1>Mis <span>Servicios</span></h1>
            <p>Gestiona tus servicios, sube imágenes y edita tu información</p>
        </div>



    <!-- STATS -->
    <div class="stats-bar-wrap">
    <div class="stats-bar">
        <div class="stat-box">
            <div class="stat-icon blue">📋</div>
            <div>
                <div class="stat-num"><?= count($servicios) ?></div>
                <div class="stat-label">Servicios activos</div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon orange">⭐</div>
            <div>
                <div class="stat-num"><?= count(array_filter($servicios, fn($s) => $s['es_destacado'])) ?></div>
                <div class="stat-label">Destacados</div>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon green">✔</div>
            <div>
                <div class="stat-num"><?= count(array_filter($servicios, fn($s) => $s['verificado'])) ?></div>
                <div class="stat-label">Verificados</div>
            </div>
        </div>
        <div class="stat-box" style="cursor:pointer" onclick="document.getElementById('seccionChat').scrollIntoView({behavior:'smooth'})">
            <div class="stat-icon" style="background:rgba(245,130,13,0.15)">💬</div>
            <div>
                <div class="stat-num" style="<?= $total_no_leidos > 0 ? 'color:var(--orange)' : '' ?>">
                    <?= $total_no_leidos ?>
                </div>
                <div class="stat-label">Mensajes sin leer</div>
            </div>
        </div>
    </div>
    </div>

    <!-- HEADER DE SERVICIOS -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h2 style="font-family:'Rajdhani',sans-serif;font-size:22px;font-weight:700;">
            Mis Servicios
        </h2>
        <button class="btn btn-orange" onclick="abrirModalNuevo()">＋ Añadir Servicio</button>
    </div>

    <!-- LISTA DE SERVICIOS -->
    <?php if (empty($servicios)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔧</div>
            <p>Aún no tienes servicios. ¡Añade tu primer servicio!</p>
        </div>
    <?php else: ?>
        <div class="grid">
        <?php foreach ($servicios as $s): ?>
            <div class="servicio-card <?= $s['es_destacado'] ? 'destacado' : '' ?>">
                <!-- IMAGEN -->
                <div class="servicio-img" id="preview-<?= $s['id'] ?>">
                    <?php if (!empty($s['imagen'])): ?>
                        <img src="uploads/<?= htmlspecialchars($s['imagen']) ?>" alt="Imagen servicio">
                    <?php else: ?>
                        <?= match(strtolower($s['categoria'])) {
                            'plomería','plomeria' => '🔧',
                            'electricidad' => '⚡',
                            'comida' => '🍕',
                            'belleza' => '💈',
                            'remodelación','remodelacion' => '🏠',
                            'tecnología','tecnologia' => '💻',
                            'delivery' => '📦',
                            default => '🔧'
                        } ?>
                    <?php endif; ?>
                </div>

                <div class="servicio-body">
                    <div class="servicio-nombre"><?= htmlspecialchars($s['titulo']) ?></div>
                    <div class="servicio-meta">
                        <span class="tag tag-blue"><?= htmlspecialchars($s['categoria']) ?></span>
                        <span class="tag tag-blue">📍 <?= htmlspecialchars($s['municipio']) ?></span>
                        <?php if ($s['es_destacado']): ?><span class="tag tag-orange">⭐ Destacado</span><?php endif; ?>
                        <?php if ($s['verificado']):   ?><span class="tag tag-green">✔ Verificado</span><?php endif; ?>
                    </div>
                    <?php if (!empty($s['descripcion'])): ?>
                        <div class="servicio-desc"><?= htmlspecialchars(substr($s['descripcion'], 0, 100)) ?>...</div>
                    <?php endif; ?>
                    <div class="servicio-precio" style="margin-bottom:12px;"><sup>$</sup><?= number_format($s['precio'], 2) ?></div>
                    
                    <?php 
                        $mis_mets = $contratos_por_servicio[$s['id']] ?? ['contratos' => [], 'pendientes' => 0, 'promedio' => 0];
                        $tot_contratos = count($mis_mets['contratos']);
                    ?>
                    <div style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.05); border-radius:12px; padding:12px; margin-bottom:16px; display:flex; flex-direction:column; gap:8px;">
                        <!-- Total contrataciones -->
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <div style="display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:12px;">
                                <span style="background:rgba(61,122,245,0.15); border-radius:6px; width:22px; height:22px; display:flex; align-items:center; justify-content:center; font-size:11px;">🤝</span>
                                Total contrataciones
                            </div>
                            <span style="font-weight:700; font-size:14px; color:#fff;"><?= $tot_contratos ?></span>
                        </div>
                        <!-- Pendientes -->
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <div style="display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:12px;">
                                <span style="background:<?= $mis_mets['pendientes'] > 0 ? 'rgba(245,130,13,0.15)' : 'rgba(255,255,255,0.05)' ?>; border-radius:6px; width:22px; height:22px; display:flex; align-items:center; justify-content:center; font-size:11px;">⏳</span>
                                Solicitudes pendientes
                            </div>
                            <span style="font-weight:700; font-size:14px; color:<?= $mis_mets['pendientes'] > 0 ? 'var(--orange)' : 'var(--text-muted)' ?>;">
                                <?= $mis_mets['pendientes'] ?>
                            </span>
                        </div>
                        <!-- Valoración -->
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <div style="display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:12px;">
                                <span style="background:rgba(245,158,11,0.12); border-radius:6px; width:22px; height:22px; display:flex; align-items:center; justify-content:center; font-size:11px;">⭐</span>
                                Valoración media
                            </div>
                            <?php if ($mis_mets['promedio'] > 0): ?>
                                <div style="display:flex; align-items:center; gap:3px;">
                                    <span style="font-weight:700; font-size:14px; color:#f59e0b;"><?= $mis_mets['promedio'] ?></span>
                                    <span style="color:#f59e0b; font-size:12px;">★</span>
                                    <span style="color:var(--text-muted); font-size:11px;">/ 5</span>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:12px;">N/A</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="servicio-acciones">
                        <button class="btn btn-primary btn-sm" onclick='abrirModalEditar(<?= json_encode($s) ?>)'>✏ Editar</button>
                        <form method="POST" action="proveedor_actions.php" style="display:inline"
                              onsubmit="return confirm('¿Eliminar este servicio?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑 Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    
    </div> <!-- fin tab-servicios -->

    <!-- ── TAB: CHATS ── -->
    <div id="tab-chats" class="tab-content <?= $tab == 'chats' ? 'active' : '' ?>">
        <div class="page-header">
            <h1>Mensajes <span>de Clientes</span></h1>
            <p>Gestiona tus conversaciones con los clientes</p>
        </div>

        <!-- Filtro activos / archivados -->
        <div class="chat-tabs">
            <a href="?tab=chats&chats_tab=activos" class="chat-tab-btn <?= ($chat_filtro_prov == 0 ? 'active' : '') ?>">
                💬 Activos
            </a>
            <a href="?tab=chats&chats_tab=archivados" class="chat-tab-btn <?= ($chat_filtro_prov == 1 ? 'active' : '') ?>">
                📦 Archivados
                <?php if ($total_archivados_prov > 0): ?>
                    <span class="chat-tab-count"><?= $total_archivados_prov ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if (empty($chats)): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= $chat_filtro_prov ? '📦' : '💬' ?></div>
                <p><?= $chat_filtro_prov
                    ? 'No tienes conversaciones archivadas.'
                    : 'Aún no tienes mensajes de clientes.<br>Cuando alguien te contacte, aparecerá aquí.' ?></p>
            </div>
        <?php else: ?>
            <div class="conv-list">
                <?php foreach ($chats as $c):
                    $iniciales = mb_strtoupper(mb_substr($c['cliente_nombre'], 0, 1) . mb_substr($c['cliente_apellido'], 0, 1));
                    $nombre_completo = htmlspecialchars($c['cliente_nombre'] . ' ' . $c['cliente_apellido']);
                    $preview = htmlspecialchars(mb_substr($c['ultimo_msg'], 0, 80));
                    $no_leidos = (int)$c['no_leidos'];
                    $hora = date('d/m H:i', strtotime($c['ultimo_at']));
                    $esta_archivado = (int)$c['esta_archivado'];
                    $data_conv = htmlspecialchars(json_encode([
                        'servicio_id'  => (int)$c['servicio_id'],
                        'cliente_id'   => (int)$c['cliente_id'],
                        'proveedor_id' => (int)$c['proveedor_id'],
                        'titulo'       => $c['servicio_titulo'],
                        'nombre'       => $c['cliente_nombre'] . ' ' . $c['cliente_apellido'],
                    ]), ENT_QUOTES);
                    $data_arch = htmlspecialchars(json_encode([
                        'servicio_id'  => (int)$c['servicio_id'],
                        'cliente_id'   => (int)$c['cliente_id'],
                        'proveedor_id' => (int)$c['proveedor_id'],
                    ]), ENT_QUOTES);
                ?>
                    <div class="conv-item <?= $no_leidos > 0 ? 'unread' : '' ?> <?= $esta_archivado ? 'archivado' : '' ?>"
                         onclick='abrirConversacion(<?= $data_conv ?>)'>
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
                        <button class="conv-arch-btn <?= $esta_archivado ? 'desarchivar' : '' ?>"
                                onclick="event.stopPropagation(); archivarChat(<?= $data_arch ?>, <?= $esta_archivado ? 0 : 1 ?>, this)">
                            <?= $esta_archivado ? '↩ Mover a activos' : '📦 Archivar' ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div> <!-- fin tab-chats -->

    <!-- ── TAB: CONTRATOS ── -->
    <div id="tab-contratos" class="tab-content <?= $tab == 'contratos' ? 'active' : '' ?>">
        <div class="page-header">
            <h1>Mis <span>Contrataciones</span></h1>
            <p>Gestiona los servicios que te han solicitado y visualiza tus métricas</p>
        </div>

        <!-- MÉTTRICAS DE CONTRATACIONES -->
        <div class="stats-bar-wrap">
        <div class="stats-bar" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat-box">
                <div class="stat-icon blue">🤝</div>
                <div>
                    <div class="stat-num"><?= $total_contratos_completados ?></div>
                    <div class="stat-label">Servicios Completados</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon orange">⭐</div>
                <div>
                    <div class="stat-num"><?= $promedio_puntuacion ?> <span style="font-size:16px;color:var(--text-muted)">/ 5</span></div>
                    <div class="stat-label">Puntuación (<?= $total_valoraciones ?> votos)</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon green">🕒</div>
                <div>
                    <div class="stat-num"><?= $total_contratos_pendientes ?></div>
                    <div class="stat-label">Solicitudes Pendientes</div>
                </div>
            </div>
        </div>
        </div>

        <!-- Las métricas por servicio se calculan ahora al inicio del archivo -->

        <?php if (empty($contrataciones)): ?>
            <div class="empty-state">
                <div class="empty-icon">🤝</div>
                <p>No tienes contrataciones registradas por el momento.</p>
            </div>
        <?php else: ?>
            <!-- VISTA MAESTRO: TARJETAS DE SERVICIOS -->
            <div id="vista-maestro-contratos" class="grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:24px;">
            <?php foreach ($servicios as $s): ?>
                <?php if (!isset($contratos_por_servicio[$s['id']])) continue; ?>
                <?php $metricas = $contratos_por_servicio[$s['id']]; ?>
                <?php $tiene_pendientes = $metricas['pendientes'] > 0; ?>
                <div onclick="mostrarDetalleContrato(<?= $s['id'] ?>)"
                    style="background:var(--card-bg); border:1px solid <?= $tiene_pendientes ? 'rgba(245,130,13,0.35)' : 'var(--border)' ?>; border-radius:16px; overflow:hidden; cursor:pointer; transition:all 0.25s ease; position:relative; display:flex; flex-direction:column;"
                    onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='rgba(61,122,245,0.5)'; this.style.boxShadow='0 12px 32px rgba(0,0,0,0.3)'"
                    onmouseout="this.style.transform='none'; this.style.borderColor='<?= $tiene_pendientes ? 'rgba(245,130,13,0.35)' : 'var(--border)' ?>'; this.style.boxShadow='none'">
                    
                    <?php if ($tiene_pendientes): ?>
                        <div style="position:absolute; top:14px; right:14px; background:var(--orange); color:#fff; font-size:11px; font-weight:700; padding:3px 9px; border-radius:20px; letter-spacing:0.5px; z-index:1;">
                            <?= $metricas['pendientes'] ?> PENDIENTE<?= $metricas['pendientes'] > 1 ? 'S' : '' ?>
                        </div>
                    <?php endif; ?>

                    <!-- Encabezado con gradiente -->
                    <div style="padding:20px 20px 16px; background:linear-gradient(135deg, rgba(26,58,143,0.6) 0%, rgba(45,91,227,0.2) 100%); border-bottom:1px solid rgba(255,255,255,0.07);">
                        <div style="font-size:28px; margin-bottom:8px;">📋</div>
                        <h3 style="font-family:'Rajdhani',sans-serif; font-size:19px; font-weight:700; color:#fff; line-height:1.2; margin-right:<?= $tiene_pendientes ? '90px' : '0' ?>;">
                            <?= htmlspecialchars($s['titulo']) ?>
                        </h3>
                    </div>

                    <!-- Métricas -->
                    <div style="padding:16px 20px; display:flex; flex-direction:column; gap:10px; flex:1;">
                        <!-- Total contrataciones -->
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <div style="display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:13px;">
                                <span style="background:rgba(61,122,245,0.15); border-radius:6px; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-size:13px;">🤝</span>
                                Total contrataciones
                            </div>
                            <span style="font-weight:700; font-size:16px; color:#fff;"><?= count($metricas['contratos']) ?></span>
                        </div>

                        <!-- Pendientes -->
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <div style="display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:13px;">
                                <span style="background:<?= $tiene_pendientes ? 'rgba(245,130,13,0.15)' : 'rgba(255,255,255,0.05)' ?>; border-radius:6px; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-size:13px;">⏳</span>
                                Solicitudes pendientes
                            </div>
                            <span style="font-weight:700; font-size:16px; color:<?= $tiene_pendientes ? 'var(--orange)' : 'var(--text-muted)' ?>;">
                                <?= $metricas['pendientes'] ?>
                            </span>
                        </div>

                        <!-- Valoración -->
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <div style="display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:13px;">
                                <span style="background:rgba(245,158,11,0.12); border-radius:6px; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-size:13px;">⭐</span>
                                Valoración media
                            </div>
                            <?php if ($metricas['promedio'] > 0): ?>
                                <div style="display:flex; align-items:center; gap:4px;">
                                    <span style="font-weight:700; font-size:16px; color:#f59e0b;"><?= $metricas['promedio'] ?></span>
                                    <span style="color:#f59e0b; font-size:14px;">★</span>
                                    <span style="color:var(--text-muted); font-size:12px;">/ 5</span>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:13px; font-style:italic;">Sin valoraciones</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pie de tarjeta -->
                    <div style="padding:12px 20px; background:rgba(255,255,255,0.02); border-top:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:space-between;">
                        <span style="font-size:12px; color:var(--text-muted);">Ver contrataciones</span>
                        <span style="color:var(--blue-light); font-size:13px; font-weight:600;">Ver detalle →</span>
                    </div>
                </div>

            <?php endforeach; ?>
            </div>

            <!-- VISTAS DETALLE (OCULTAS POR DEFECTO) -->
            <?php foreach ($servicios as $s): ?>
                <?php if (!isset($contratos_por_servicio[$s['id']])) continue; ?>
                <div id="detalle-servicio-<?= $s['id'] ?>" class="detalle-servicio-container" style="display:none; animation:fadeIn 0.3s ease;">
                    <button onclick="ocultarDetalleContrato()" style="margin-bottom:20px; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text-muted); padding:8px 16px; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s; font-family:'DM Sans',sans-serif; font-size:14px;" onmouseover="this.style.background='rgba(255,255,255,0.1)'; this.style.color='#fff'" onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.color='var(--text-muted)'">
                        ⬅ Volver al listado de servicios
                    </button>
                    
                    <div class="panel-card" style="padding:0; overflow:hidden;">
                        <div style="background:rgba(255,255,255,0.02); padding:16px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                            <h2 style="font-family:'Rajdhani',sans-serif; font-size:20px; font-weight:700;">
                                📋 <?= htmlspecialchars($s['titulo']) ?>
                            </h2>
                            <span style="font-size:13px; color:var(--text-muted); background:rgba(255,255,255,0.05); padding:4px 10px; border-radius:12px;">
                                <?= count($contratos_por_servicio[$s['id']]['contratos']) ?> contrataciones
                            </span>
                        </div>
                        <div style="padding:16px 24px; display:flex; flex-direction:column; gap:16px;">
                            <?php foreach ($contratos_por_servicio[$s['id']]['contratos'] as $c): ?>
                                <div style="background:var(--bg); border:1px solid var(--border); border-radius:12px; padding:16px; display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
                                    <div style="flex:1;">
                                        <div style="font-size:15px; margin-bottom:8px;">
                                            Cliente: <strong style="color:#fff"><?= htmlspecialchars($c['cliente_nombre'] . ' ' . $c['cliente_apellido']) ?></strong>
                                            <span style="margin:0 8px; color:var(--text-muted);">|</span>
                                            <span style="color:var(--text-muted); font-size:13px;">Fecha: <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></span>
                                        </div>
                                        <div>
                                            <?php
                                            $estado_color = match($c['estado']) {
                                                'pendiente' => 'var(--orange)',
                                                'aceptado' => 'var(--blue-light)',
                                                'rechazado', 'cancelado' => '#ef4444',
                                                'completado' => '#4ade80',
                                                default => 'var(--text-muted)'
                                            };
                                            ?>
                                            Estado: <strong style="color:<?= $estado_color ?>; text-transform:uppercase; font-size:13px; letter-spacing:0.5px;"><?= $c['estado'] ?></strong>
                                            <?php if($c['motivo']): ?>
                                                <div style="font-size:13px; color:var(--text-muted); margin-top:6px; font-style:italic; border-left:2px solid rgba(255,255,255,0.1); padding-left:8px;">
                                                    Motivo: <?= htmlspecialchars($c['motivo']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($c['puntuacion']): ?>
                                            <div style="margin-top:12px; padding:12px; background:rgba(74,222,128,0.05); border:1px solid rgba(74,222,128,0.15); border-radius:8px;">
                                                <div style="color:#f59e0b; font-size:16px; margin-bottom:4px;">
                                                    <?= str_repeat('★', $c['puntuacion']) ?><?= str_repeat('☆', 5 - $c['puntuacion']) ?> 
                                                    <span style="color:#4ade80; font-size:13px; margin-left:8px;">(<?= $c['puntuacion'] ?>/5)</span>
                                                </div>
                                                <?php if ($c['comentario']): ?>
                                                    <div style="font-size:14px; color:var(--text-muted); font-style:italic;">
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
                                            'nombre'       => $c['cliente_nombre'] . ' ' . $c['cliente_apellido']
                                        ]);
                                        ?>
                                        <button onclick='abrirConversacion(<?= htmlspecialchars($chat_ctx, ENT_QUOTES) ?>)'
                                            style="padding:7px 14px; border-radius:8px; border:none; background:linear-gradient(135deg,#0f7c5a,#14a87a); color:#fff; font-size:12px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; transition:all .2s; margin-bottom:4px;"
                                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 0 10px rgba(20,168,122,0.4)'"
                                            onmouseout="this.style.transform='none'; this.style.boxShadow='none'">
                                            💬 Abrir Chat
                                        </button>
                                        
                                        <div style="display:flex; gap:8px;">
                                            <?php if ($c['estado'] === 'pendiente'): ?>
                                                <button class="btn btn-primary btn-sm" onclick="accionarContrato('aceptar', <?= $c['id'] ?>)">✅ Aceptar</button>
                                                <button class="btn btn-danger btn-sm" onclick="accionarContrato('rechazar', <?= $c['id'] ?>)">❌ Rechazar</button>
                                            <?php elseif ($c['estado'] === 'aceptado'): ?>
                                                <button class="btn btn-primary btn-sm" style="background:#14a87a" onclick="accionarContrato('completar', <?= $c['id'] ?>)">✔ Marcar como Completado</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <script>
                function mostrarDetalleContrato(servicioId) {
                    document.getElementById('vista-maestro-contratos').style.display = 'none';
                    document.querySelectorAll('.detalle-servicio-container').forEach(el => el.style.display = 'none');
                    document.getElementById('detalle-servicio-' + servicioId).style.display = 'block';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
                function ocultarDetalleContrato() {
                    document.querySelectorAll('.detalle-servicio-container').forEach(el => el.style.display = 'none');
                    document.getElementById('vista-maestro-contratos').style.display = 'grid';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            </script>
        <?php endif; ?>
    </div> <!-- fin tab-contratos -->

    <!-- ── TAB: PERFIL ── -->
    <div id="tab-perfil" class="tab-content <?= $tab == 'perfil' ? 'active' : '' ?>">
        <div class="page-header">
            <h1>Mi <span>Perfil</span></h1>
            <p>Gestiona tu cuenta y seguridad</p>
        </div>

        <?php if (!empty($_GET['ok']) && $_GET['tab'] == 'perfil'): ?>
            <div class="toast success">✔ Contraseña actualizada correctamente.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['err']) && $_GET['tab'] == 'perfil'): ?>
            <div class="toast error">✗ La contraseña actual es incorrecta o hubo un error.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['verify_ok'])): ?>
            <div class="toast success" style="margin-bottom:24px;">✔ Tu solicitud de verificación ha sido enviada. El administrador la revisará pronto.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['verify_err'])): ?>
            <div class="toast error" style="margin-bottom:24px;">
                ✗ Error al enviar verificación: 
                <?= match($_GET['verify_err']) {
                    'campos_vacios' => 'Faltan campos obligatorios.',
                    'sin_documento' => 'Debes adjuntar un documento.',
                    'formato_invalido' => 'El documento debe ser PDF, JPG o PNG.',
                    'archivo_muy_grande' => 'El archivo supera el límite de 5MB.',
                    'error_subida' => 'Error técnico al subir el archivo.',
                    'db_error' => 'Error de base de datos. Intenta más tarde.',
                    default => 'Error desconocido.'
                } ?>
            </div>
        <?php endif; ?>

        <div class="grid" style="align-items: start;">
            <!-- Panel Izquierdo: Contraseña -->
            <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:24px;">
                <h3 style="margin-bottom:20px; font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700;">Cambiar Contraseña</h3>
                <form action="proveedor_actions.php" method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="field">
                        <label>Contraseña Actual</label>
                        <input type="password" name="current_password" required placeholder="Ingresa tu contraseña actual">
                    </div>
                    
                    <div class="field">
                        <label>Nueva Contraseña</label>
                        <input type="password" name="new_password" required placeholder="Mínimo 6 caracteres">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Actualizar Contraseña</button>
                </form>
            </div>

            <!-- Panel Derecho: Verificación -->
            <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:24px;">
                <h3 style="margin-bottom:20px; font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; display:flex; align-items:center; gap:8px;">
                    🛡️ Verificación de Identidad
                </h3>
                
                <?php if ($verificacion_estado && $verificacion_estado['estado'] === 'aprobado'): ?>
                    <div style="background:rgba(74,222,128,0.1); border:1px solid rgba(74,222,128,0.3); padding:16px; border-radius:8px; text-align:center;">
                        <div style="font-size:32px; margin-bottom:8px;">✅</div>
                        <h4 style="color:#4ade80; font-size:16px; margin-bottom:4px;">¡Perfil Verificado!</h4>
                        <p style="font-size:13px; color:var(--text-muted);">Tu identidad ha sido comprobada por un administrador.</p>
                    </div>
                <?php elseif ($verificacion_estado && $verificacion_estado['estado'] === 'pendiente'): ?>
                    <div style="background:rgba(245,130,13,0.1); border:1px solid rgba(245,130,13,0.3); padding:16px; border-radius:8px; text-align:center;">
                        <div style="font-size:32px; margin-bottom:8px;">⏳</div>
                        <h4 style="color:var(--orange); font-size:16px; margin-bottom:4px;">Revisión en Proceso</h4>
                        <p style="font-size:13px; color:var(--text-muted);">Tu documento está siendo revisado por un administrador. Esto puede tardar hasta 48 horas.</p>
                    </div>
                <?php else: ?>
                    <?php if ($verificacion_estado && $verificacion_estado['estado'] === 'rechazado'): ?>
                        <div style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); padding:16px; border-radius:8px; margin-bottom:20px;">
                            <h4 style="color:#f87171; font-size:14px; margin-bottom:4px;">❌ Solicitud Anterior Rechazada</h4>
                            <p style="font-size:12px; color:var(--text-muted);">Por favor revisa que el documento sea legible y tus datos coincidan e intenta de nuevo.</p>
                        </div>
                    <?php endif; ?>
                    
                    <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px; line-height:1.5;">
                        Sube una foto legible de tu cédula de identidad o RIF. Los proveedores verificados inspiran más confianza y consiguen más clientes.
                    </p>
                    
                    <form action="verify.php" method="POST" enctype="multipart/form-data">
                        <div class="field">
                            <label>Nombre Completo / Razón Social</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars(nombreUsuario()) ?>" required>
                        </div>
                        
                        <div class="field">
                            <label>Municipio Principal</label>
                            <select name="municipio" required>
                                <option value="">Selecciona tu municipio...</option>
                                <?php foreach ($all_municipios as $m): ?>
                                    <option value="<?= htmlspecialchars($m['nombre']) ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="field">
                            <label>Documento (Cédula o RIF)</label>
                            <input type="file" name="id_doc" accept=".pdf,image/png,image/jpeg" required style="display:block; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:10px; width:100%; color:var(--text-muted); font-family:'DM Sans',sans-serif; cursor:pointer;">
                            <small style="display:block; margin-top:6px; color:var(--text-muted); font-size:11px;">Formato PDF, JPG o PNG. Máx. 5MB.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-orange" style="width:100%; margin-top:10px;">Enviar para Revisión</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div> <!-- fin tab-perfil -->

    <!-- BOTON FLOTANTE IR ARRIBA -->
    <button id="fabBtn" onclick="document.querySelector('.main-content').scrollTo({top:0, behavior:'smooth'})" 
            style="display:none; position:fixed; bottom:28px; right:28px; width:56px; height:56px; background:linear-gradient(135deg,var(--blue-mid),var(--blue-light)); border:none; border-radius:50%; color:#fff; font-size:24px; cursor:pointer; box-shadow:0 8px 28px rgba(45,91,227,0.5); z-index:499; align-items:center; justify-content:center; transition:transform .2s;">
        ↑
    </button>
</div> <!-- fin main-content -->

<!-- ══ VENTANA DE CHAT FLOTANTE ════════════════════════════ -->
<div id="chatWindow">
    <div class="chat-header">
        <div class="chat-avatar-sm">💬</div>
        <div class="chat-header-info">
            <div class="chat-header-name" id="chatNombreCliente">Cliente</div>
            <div class="chat-header-sub"  id="chatSubServicio">Servicio</div>
        </div>
        <button class="chat-close-btn" onclick="cerrarChat()">✕</button>
    </div>
    <div id="chatBody">
        <div class="chat-empty" id="chatEmpty">
            <div class="chat-empty-icon">💬</div>
            <div>Cargando conversación...</div>
        </div>
    </div>
    <div class="chat-footer">
        <textarea id="chatInput" rows="1" placeholder="Escribe tu respuesta..." maxlength="1000"></textarea>
        <button id="chatSendBtn" onclick="enviarMensaje()">➤</button>
    </div>
</div>

<!-- ID del proveedor para JS -->
<script>
    const MI_USER_ID = <?= $uid ?>;
</script>

<!-- ══ MODAL EDITAR SERVICIO ══════════════════════════════ -->
<div class="modal-overlay" id="modalEditar">
    <div class="modal-box">
        <button class="modal-close" onclick="cerrarModal('modalEditar')">✕</button>
        <div class="modal-title">✏ Editar Servicio</div>

        <form action="proveedor_actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <!-- IMAGEN -->
            <div class="field">
                <label>Imagen del negocio</label>
                <div class="img-preview" id="editImgPreview" onclick="document.getElementById('edit_imagen').click()">
                    <span class="upload-icon">📷</span>
                    <span class="upload-hint">Click para subir imagen<br><small>JPG, PNG — máx 2MB</small></span>
                </div>
                <input type="file" id="edit_imagen" name="imagen" accept="image/*"
                       onchange="previsualizarImagen(this, 'editImgPreview')">
            </div>

            <div class="field">
                <label>Nombre del servicio</label>
                <input type="text" name="titulo" id="edit_titulo" placeholder="Ej: Plomería Express" required>
            </div>

            <div class="field">
                <label>Descripción</label>
                <textarea name="descripcion" id="edit_descripcion"
                    placeholder="Describe tu servicio, qué ofreces, horarios, etc."></textarea>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Categoría</label>
                    <select name="categoria" id="edit_categoria">
                        <?php foreach ($all_categorias as $cat): ?>
                            <option><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Municipio</label>
                    <select name="municipio" id="edit_municipio">
                        <?php foreach ($all_municipios as $m): ?>
                            <option value="<?= htmlspecialchars($m['nombre']) ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Precio (USD)</label>
                <input type="number" name="precio" id="edit_precio" step="0.01" min="0" placeholder="0.00">
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalEditar')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL NUEVO SERVICIO ═══════════════════════════════ -->
<div class="modal-overlay" id="modalNuevo">
    <div class="modal-box">
        <button class="modal-close" onclick="cerrarModal('modalNuevo')">✕</button>
        <div class="modal-title">＋ Nuevo Servicio</div>

        <form action="proveedor_actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <!-- IMAGEN -->
            <div class="field">
                <label>Imagen del negocio</label>
                <div class="img-preview" id="nuevoImgPreview" onclick="document.getElementById('nuevo_imagen').click()">
                    <span class="upload-icon">📷</span>
                    <span class="upload-hint">Click para subir imagen<br><small>JPG, PNG — máx 2MB</small></span>
                </div>
                <input type="file" id="nuevo_imagen" name="imagen" accept="image/*"
                       onchange="previsualizarImagen(this, 'nuevoImgPreview')">
            </div>

            <div class="field">
                <label>Nombre del servicio</label>
                <input type="text" name="titulo" placeholder="Ej: Electricidad Rápida" required>
            </div>

            <div class="field">
                <label>Descripción</label>
                <textarea name="descripcion" placeholder="Describe tu servicio..."></textarea>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Categoría</label>
                    <select name="categoria">
                        <?php foreach ($all_categorias as $cat): ?>
                            <option><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Municipio</label>
                    <select name="municipio">
                        <?php foreach ($all_municipios as $m): ?>
                            <option value="<?= htmlspecialchars($m['nombre']) ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Precio (USD)</label>
                <input type="number" name="precio" step="0.01" min="0" placeholder="0.00" required>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn btn-orange">＋ Añadir Servicio</button>
                <button type="button" class="btn btn-ghost" onclick="cerrarModal('modalNuevo')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Menú Móvil ─────────────────────────────────────────────
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}

// ── Modales ───────────────────────────────────────────────
function cerrarModal(id) {
    document.getElementById(id).classList.remove('open');
}
function abrirModalNuevo() {
    document.getElementById('modalNuevo').classList.add('open');
}
function abrirModalEditar(s) {
    document.getElementById('edit_id').value        = s.id;
    document.getElementById('edit_titulo').value    = s.titulo;
    document.getElementById('edit_descripcion').value = s.descripcion || '';
    document.getElementById('edit_precio').value    = s.precio;

    // Select categoría y municipio
    setSelect('edit_categoria', s.categoria);
    setSelect('edit_municipio', s.municipio);

    // Imagen actual si existe
    var prev = document.getElementById('editImgPreview');
    prev.innerHTML = '';
    if (s.imagen) {
        var img = document.createElement('img');
        img.src = 'uploads/' + s.imagen;
        prev.appendChild(img);
    } else {
        prev.innerHTML = '<span class="upload-icon">📷</span><span class="upload-hint">Click para subir imagen</span>';
    }

    document.getElementById('modalEditar').classList.add('open');
}
function setSelect(id, val) {
    var sel = document.getElementById(id);
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value.toLowerCase() === (val||'').toLowerCase() ||
            sel.options[i].text.toLowerCase()  === (val||'').toLowerCase()) {
            sel.selectedIndex = i; break;
        }
    }
}

// ── Preview de imagen ─────────────────────────────────────
function previsualizarImagen(input, previewId) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    if (file.size > 2 * 1024 * 1024) {
        alert('La imagen supera 2MB. Elige una más pequeña.');
        input.value = ''; return;
    }
    var reader = new FileReader();
    reader.onload = function(e) {
        var prev = document.getElementById(previewId);
        prev.innerHTML = '';
        var img = document.createElement('img');
        img.src = e.target.result;
        prev.appendChild(img);
    };
    reader.readAsDataURL(file);
}

// ── Cerrar modal clickando fuera ──────────────────────────
['modalEditar','modalNuevo'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) cerrarModal(id);
    });
});

// ── Botón volver arriba ───────────────────────────────
const mainContent = document.querySelector('.main-content');
const fabBtn = document.getElementById('fabBtn');

if (mainContent && fabBtn) {
    mainContent.addEventListener('scroll', () => {
        if (mainContent.scrollTop > 300) {
            fabBtn.style.display = 'flex';
        } else {
            fabBtn.style.display = 'none';
        }
    });
}
</script>

<script>
// ══════════════════════════════════════════════════════════
//  CHAT DEL PROVEEDOR
// ══════════════════════════════════════════════════════════
let chatCtx      = null;
let pollInterval = null;
let ultimoMsgId  = 0;

function abrirConversacion(ctx) {
    chatCtx     = ctx;
    ultimoMsgId = 0;

    document.getElementById('chatNombreCliente').textContent = ctx.nombre;
    document.getElementById('chatSubServicio').textContent   = ctx.titulo;

    // Limpiar y mostrar ventana
    document.getElementById('chatBody').innerHTML = `
        <div class="chat-empty" id="chatEmpty">
            <div class="chat-empty-icon">💬</div>
            <div>Cargando...</div>
        </div>`;
    document.getElementById('chatWindow').classList.add('open');
    document.getElementById('chatInput').focus();

    cargarMensajes(true);
    clearInterval(pollInterval);
    pollInterval = setInterval(() => cargarMensajes(false), 4000);

    // Quitar badge visual de la fila clickeada
    // (se actualizará en el próximo poll del backend)
    document.querySelectorAll('.conv-item.unread').forEach(el => {
        if (el.onclick && el.getAttribute('onclick')?.includes(ctx.cliente_id)) {
            el.classList.remove('unread');
            const badge = el.querySelector('.conv-unread-badge');
            if (badge) badge.remove();
        }
    });
}

function cerrarChat() {
    document.getElementById('chatWindow').classList.remove('open');
    clearInterval(pollInterval);
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
        if (!data.ok || data.mensajes.length === 0) return;

        const body  = document.getElementById('chatBody');
        const empty = document.getElementById('chatEmpty');
        if (empty) empty.remove();

        data.mensajes.forEach(msg => {
            if (msg.id > ultimoMsgId) ultimoMsgId = msg.id;
            agregarBurbuja(msg, body);
        });
        body.scrollTop = body.scrollHeight;

        // Actualizar preview en la lista si hay mensaje nuevo
        actualizarPreviewLista(chatCtx.cliente_id, data.mensajes[data.mensajes.length - 1].mensaje);
    } catch(e) {
        console.warn('Chat poll error:', e);
    }
}

function agregarBurbuja(msg, body) {
    if (document.getElementById('cmsg-' + msg.id)) return;
    const div  = document.createElement('div');
    div.className = 'chat-msg ' + (msg.es_mio ? 'mio' : 'otro');
    div.id = 'cmsg-' + msg.id;
    const hora = new Date(msg.created_at).toLocaleTimeString('es-VE', {hour:'2-digit', minute:'2-digit'});
    const tick = msg.es_mio ? (msg.leido ? '<span class="chat-tick">✔✔</span>' : '<span class="chat-tick" style="color:#6a7a9b">✔</span>') : '';
    div.innerHTML = `
        <div class="chat-bubble">${msg.mensaje}</div>
        <div class="chat-time">${hora}${tick}</div>`;
    body.appendChild(div);
}

async function enviarMensaje() {
    if (!chatCtx) return;
    const input = document.getElementById('chatInput');
    const texto = input.value.trim();
    if (!texto) return;

    const btn = document.getElementById('chatSendBtn');
    btn.disabled = true;
    input.value  = '';
    autoResize(input);

    const fd = new FormData();
    fd.append('servicio_id',  chatCtx.servicio_id);
    fd.append('proveedor_id', chatCtx.proveedor_id);
    fd.append('cliente_id',   chatCtx.cliente_id);
    fd.append('mensaje',      texto);

    try {
        const res  = await fetch('chat_send.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.ok) {
            const empty = document.getElementById('chatEmpty');
            if (empty) empty.remove();
            const body = document.getElementById('chatBody');
            const fakeMsg = { id:data.id, es_mio:true, mensaje:data.mensaje, leido:false, created_at:data.created_at };
            if (data.id > ultimoMsgId) ultimoMsgId = data.id;
            agregarBurbuja(fakeMsg, body);
            body.scrollTop = body.scrollHeight;
            actualizarPreviewLista(chatCtx.cliente_id, data.mensaje);
        } else {
            alert('Error: ' + (data.error || 'No se pudo enviar'));
        }
    } catch(e) {
        alert('Error de conexión');
    }
    btn.disabled = false;
    input.focus();
}

function actualizarPreviewLista(cliente_id, ultimo_msg) {
    document.querySelectorAll('.conv-item').forEach(el => {
        const onclick = el.getAttribute('onclick') || '';
        if (onclick.includes('"cliente_id":' + cliente_id) || onclick.includes('"cliente_id": ' + cliente_id)) {
            const prev = el.querySelector('.conv-preview');
            if (prev) prev.textContent = ultimo_msg.substring(0, 80);
        }
    });
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}

document.getElementById('chatInput').addEventListener('input', function() { autoResize(this); });
document.getElementById('chatInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviarMensaje(); }
});

// ── Archivar / Desarchivar chat ───────────────────────────
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

// ══════════════════════════════════════════════════════════
//  ACCIONES DE CONTRATACIÓN
// ══════════════════════════════════════════════════════════
async function accionarContrato(accion, id) {
    let motivo = '';
    if (accion === 'rechazar') {
        motivo = prompt('Por favor, indica el motivo del rechazo:');
        if (motivo === null || motivo.trim() === '') {
            alert('Debes indicar un motivo para rechazar.');
            return;
        }
    } else if (accion === 'aceptar') {
        if(!confirm('¿Estás seguro de aceptar esta contratación?')) return;
    } else if (accion === 'completar') {
        if(!confirm('¿Confirmas que el servicio fue realizado y está completado?')) return;
    }
    
    const fd = new FormData();
    fd.append('action', accion);
    fd.append('contratacion_id', id);
    if (motivo) fd.append('motivo', motivo);
    
    try {
        const res = await fetch('contratacion_actions.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.ok) {
            window.location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    } catch(e) {
        alert('Error de conexión');
    }
}
</script>
</body>
</html>