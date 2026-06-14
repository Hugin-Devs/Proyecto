<?php
require_once __DIR__ . '/auth_guard.php';
requireAdmin();

include __DIR__ . '/db.php';
include __DIR__ . '/get_lists.php';

// 1. CONFIGURACIÓN DE PAGINACIÓN
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$tab = $_GET['tab'] ?? 'dashboard';

// --- QUERIES PARA MÉTRICAS ---
$q_users = mysqli_query($conn, "SELECT COUNT(*) as c FROM usuarios WHERE deleted_at IS NULL AND role != 'admin'");
$total_users = mysqli_fetch_assoc($q_users)['c'] ?? 0;

$q_serv_activos = mysqli_query($conn, "SELECT COUNT(*) as c FROM servicios WHERE deleted_at IS NULL");
$total_serv_activos = mysqli_fetch_assoc($q_serv_activos)['c'] ?? 0;

$q_verif_pendientes = mysqli_query($conn, "SELECT COUNT(*) as c FROM verificaciones WHERE estado = 'pendiente'");
$total_pendientes = mysqli_fetch_assoc($q_verif_pendientes)['c'] ?? 0;

$q_serv_verif = mysqli_query($conn, "SELECT COUNT(*) as c FROM servicios WHERE deleted_at IS NULL AND verificado = 1");
$total_verificados = mysqli_fetch_assoc($q_serv_verif)['c'] ?? 0;

// --- DATOS PARA LAS PESTAÑAS (Con Paginación) ---
$verificaciones = mysqli_query($conn, "SELECT * FROM verificaciones ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Servicios
$total_pages_serv = ceil($total_serv_activos / $limit);
$servicios = mysqli_query($conn, "SELECT s.*, u.nombre as proveedor_nombre FROM servicios s LEFT JOIN usuarios u ON s.usuario_id = u.id WHERE s.deleted_at IS NULL ORDER BY s.created_at DESC LIMIT $limit OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

// Usuarios
$total_pages_user = ceil($total_users / $limit);
$usuarios = mysqli_query($conn, "SELECT * FROM usuarios WHERE deleted_at IS NULL AND role != 'admin' ORDER BY created_at DESC LIMIT $limit OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

// Ordenar categorías alfabéticamente
$categorias = mysqli_query($conn, "SELECT * FROM categorias ORDER BY nombre ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servi-Job — Panel de Administración</title>
    <link href="fonts/fonts.css" rel="stylesheet">
    <style>
        :root {
            --navy: #0f2057; --blue: #1a3a8f; --blue-mid: #2d5be3; --blue-light: #3d7af5;
            --orange: #f5820d; --orange-dark: #d96a00; --white: #ffffff; --off-white: #f4f6fc;
            --text-muted: #8898bb; --border: rgba(255,255,255,0.08); --card-bg: rgba(255,255,255,0.04);
            --radius: 12px; --bg: #0c1840;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--white); min-height: 100vh; display: flex; }
        a { text-decoration: none; color: inherit; }
        
        /* SIDEBAR */
        .sidebar { width: 260px; background: var(--navy); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 24px 0; }
        .logo { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; padding: 0 24px 32px; border-bottom: 1px solid var(--border); margin-bottom: 24px; }
        .logo span { color: var(--orange); }
        .nav-item { padding: 14px 24px; color: var(--text-muted); font-size: 15px; font-weight: 500; display: flex; align-items: center; gap: 12px; transition: all 0.2s; cursor: pointer; }
        .nav-item:hover, .nav-item.active { background: rgba(61,122,245,0.1); color: var(--blue-light); border-right: 3px solid var(--blue-light); }
        .nav-badge { background: var(--orange); color: white; font-size: 11px; padding: 2px 6px; border-radius: 10px; margin-left: auto; }
        
        .sidebar-bottom { margin-top: auto; padding: 24px; border-top: 1px solid var(--border); }
        .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--blue-mid), var(--blue-light)); display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .btn-logout { display: block; width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,100,100,0.3); background: rgba(255,100,100,0.1); color: #ff8a8a; text-align: center; font-size: 14px; transition: all 0.2s; }
        .btn-logout:hover { background: rgba(255,100,100,0.2); }

        /* MAIN CONTENT */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        h1 { font-family: 'Rajdhani', sans-serif; font-size: 32px; font-weight: 700; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* CARDS / DASHBOARD */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .metric-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; }
        .metric-val { font-family: 'Rajdhani', sans-serif; font-size: 36px; font-weight: 700; color: var(--white); margin-bottom: 4px; }
        .metric-label { font-size: 14px; color: var(--text-muted); }

        /* TABLAS */
        .table-wrap { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px 20px; text-align: left; font-size: 14px; }
        th { background: rgba(255,255,255,0.02); color: var(--text-muted); font-weight: 500; border-bottom: 1px solid var(--border); }
        td { border-bottom: 1px solid rgba(255,255,255,0.04); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .b-pend { background: rgba(245,130,13,0.15); color: var(--orange); }
        .b-aprov { background: rgba(74,222,128,0.15); color: #4ade80; }
        .b-rech { background: rgba(248,113,113,0.15); color: #f87171; }
        .b-cliente { background: rgba(61,122,245,0.15); color: var(--blue-light); }
        .b-proveedor { background: rgba(167,139,250,0.15); color: #a78bfa; }

        .btn-sm { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
        .btn-aprov { background: #4ade80; color: #000; }
        .btn-rech { background: #f87171; color: #fff; }
        .btn-doc { background: var(--blue-mid); color: #fff; }
        .btn-del { background: rgba(255,100,100,0.1); color: #ff8a8a; border: 1px solid rgba(255,100,100,0.3); }
        .btn-del:hover { background: rgba(255,100,100,0.2); }
        .btn-toggle { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
        .btn-toggle.on { background: rgba(245,130,13,0.15); border-color: var(--orange); color: var(--orange); }

        .form-group { margin-bottom: 16px; display: flex; gap: 10px; }
        .form-group input { flex: 1; padding: 12px 14px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: white; outline: none; }
        .form-submit { padding: 12px 24px; background: var(--orange); border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; }

        /* PAGINACIÓN ADMIN */
        .admin-pagination { display: flex; gap: 8px; padding: 16px 20px; background: rgba(255,255,255,0.02); border-top: 1px solid var(--border); justify-content: flex-end; }
        .btn-page { padding: 6px 12px; border-radius: 6px; background: rgba(255,255,255,0.05); color: var(--text-muted); font-size: 13px; text-decoration: none; border: 1px solid var(--border); }
        .btn-page:hover { background: rgba(61,122,245,0.1); color: var(--blue-light); }
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
            .mobile-header-title {
                font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700;
                display: flex; align-items: center; gap: 8px;
            }
            .mobile-header-title span { color: var(--orange); }
            .mobile-menu-btn {
                background: none; border: none; color: white;
                font-size: 26px; cursor: pointer; line-height: 1;
            }

            .main-content { width: 100% !important; overflow-x: hidden; padding: 20px; }
            .header { flex-direction: column; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
            .metrics-grid { grid-template-columns: 1fr; }
            .table-wrap { overflow-x: auto; }
            table { min-width: 600px; }
            .form-group { flex-direction: column; }
            .form-submit { width: 100%; }
        }
    </style>
    <!-- Librerías para exportar a PDF (Offline) -->
    <script src="libs/jspdf.umd.min.js"></script>
    <script src="libs/jspdf.plugin.autotable.min.js"></script>
</head>
<body>

<!-- MOBILE HEADER & OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="mobile-header">
    <div class="mobile-header-title"><span style="color:white;">SERVI-<span>JOB</span></span> ADMIN</div>
    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>
</div>

<div class="sidebar">
    <div class="logo">Admin<span>Job</span></div>
    
    <a href="?tab=dashboard" class="nav-item <?= $tab == 'dashboard' ? 'active' : '' ?>">
        📊 Dashboard
    </a>
    <a href="?tab=verificaciones" class="nav-item <?= $tab == 'verificaciones' ? 'active' : '' ?>">
        🛡️ Verificaciones 
        <?php if($total_pendientes > 0): ?><span class="nav-badge"><?= $total_pendientes ?></span><?php endif; ?>
    </a>
    <a href="?tab=servicios" class="nav-item <?= $tab == 'servicios' ? 'active' : '' ?>">
        🔧 Servicios
    </a>
    <a href="?tab=usuarios" class="nav-item <?= $tab == 'usuarios' ? 'active' : '' ?>">
        👥 Usuarios
    </a>
    <a href="?tab=categorias" class="nav-item <?= $tab == 'categorias' ? 'active' : '' ?>">
        📁 Categorías
    </a>
    <a href="?tab=auditoria" class="nav-item <?= $tab == 'auditoria' ? 'active' : '' ?>">
        🔍 Auditoría
    </a>
    
    <div class="sidebar-bottom">
        <div class="user-info">
            <div class="user-avatar"><?= substr(nombreUsuario(), 0, 1) ?></div>
            <div>
                <div style="font-size: 14px; font-weight: 600;"><?= nombreUsuario() ?></div>
                <div style="font-size: 12px; color: var(--text-muted);">Administrador</div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
    </div>
</div>

<div class="main-content">
    
    <!-- DASHBOARD -->
    <div id="tab-dashboard" class="tab-content <?= $tab == 'dashboard' ? 'active' : '' ?>">
        <div class="header">
            <h1>Panel de Resumen</h1>
            <a href="index.php?view=public" target="_blank" class="btn-sm btn-doc" style="text-decoration:none; padding:10px 20px;">Ver Sitio Público ↗</a>
        </div>
        
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-val"><?= $total_users ?></div>
                <div class="metric-label">Usuarios Activos</div>
            </div>
            <div class="metric-card">
                <div class="metric-val"><?= $total_serv_activos ?></div>
                <div class="metric-label">Servicios Publicados</div>
            </div>
            <div class="metric-card" style="<?= $total_pendientes > 0 ? 'border-color: var(--orange);' : '' ?>">
                <div class="metric-val" style="color: var(--orange);"><?= $total_pendientes ?></div>
                <div class="metric-label">Verificaciones Pendientes</div>
            </div>
            <div class="metric-card">
                <div class="metric-val" style="color: #4ade80;"><?= $total_verificados ?></div>
                <div class="metric-label">Servicios Verificados</div>
            </div>
        </div>
    </div>

    <!-- VERIFICACIONES -->
    <div id="tab-verificaciones" class="tab-content <?= $tab == 'verificaciones' ? 'active' : '' ?>">
        <div class="header">
            <h1>Solicitudes de Verificación</h1>
        </div>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Negocio</th>
                        <th>Municipio</th>
                        <th>Fecha</th>
                        <th>Documento</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($verificaciones as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['nombre']) ?></td>
                        <td><?= htmlspecialchars($v['municipio']) ?></td>
                        <td><?= date('d/m/Y', strtotime($v['created_at'])) ?></td>
                        <td>
                            <a href="ver_doc.php?id=<?= $v['id'] ?>" target="_blank" class="btn-sm btn-doc" style="text-decoration:none;">📄 Ver Documento</a>
                        </td>
                        <td>
                            <?php 
                                $class = 'b-pend';
                                if($v['estado'] == 'aprobado') $class = 'b-aprov';
                                if($v['estado'] == 'rechazado') $class = 'b-rech';
                            ?>
                            <span class="badge <?= $class ?>"><?= $v['estado'] ?></span>
                        </td>
                        <td>
                            <?php if($v['estado'] == 'pendiente'): ?>
                                <form action="admin_actions.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="verify_approve">
                                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn-sm btn-aprov">✔ Aprobar</button>
                                </form>
                                <form action="admin_actions.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="verify_reject">
                                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn-sm btn-rech">✖ Rechazar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($verificaciones)): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No hay solicitudes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SERVICIOS -->
    <div id="tab-servicios" class="tab-content <?= $tab == 'servicios' ? 'active' : '' ?>">
        <div class="header">
            <h1>Gestión de Servicios</h1>
        </div>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th>Proveedor</th>
                        <th>Categoría</th>
                        <th>Verificado</th>
                        <th>Destacado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($servicios as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['titulo']) ?></strong><br><small style="color:var(--text-muted);">$<?= $s['precio'] ?></small></td>
                        <td><?= htmlspecialchars($s['proveedor_nombre']) ?></td>
                        <td><?= htmlspecialchars($s['categoria']) ?></td>
                        <td>
                            <form action="admin_actions.php" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_verificado">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn-sm <?= $s['verificado'] ? 'btn-aprov' : 'btn-toggle' ?>" style="font-size:12px; padding: 4px 8px; cursor:pointer;">
                                    <?= $s['verificado'] ? '✔ Verificado' : '✖ Sin verificar' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <form action="admin_actions.php" method="POST">
                                <input type="hidden" name="action" value="toggle_destacado">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn-sm btn-toggle <?= $s['es_destacado'] ? 'on' : '' ?>">
                                    <?= $s['es_destacado'] ? '★ Destacado' : '☆ Normal' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <form action="admin_actions.php" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este servicio?');">
                                <input type="hidden" name="action" value="delete_service">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn-sm btn-del">🗑️ Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($total_pages_serv > 1): ?>
                <div class="admin-pagination">
                    <?php for ($i = 1; $i <= $total_pages_serv; $i++): ?>
                        <a href="?tab=servicios&p=<?= $i ?>" class="btn-page <?= $page == $i ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- USUARIOS -->
    <div id="tab-usuarios" class="tab-content <?= $tab == 'usuarios' ? 'active' : '' ?>">
        <div class="header">
            <h1>Gestión de Usuarios</h1>
        </div>

        <?php if (isset($_GET['warn']) && $_GET['warn'] === 'contratos_activos'): ?>
            <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; padding: 16px; border-radius: var(--radius); margin-bottom: 24px; display: flex; flex-direction: column; gap: 12px;">
                <h4 style="color: #f87171; font-size: 16px; font-weight:600;">⚠️ Advertencia de Seguridad: El usuario tiene <?= (int)$_GET['n'] ?> contratos activos</h4>
                <p style="font-size: 14px; color:#e2e8f0;">Eliminar a este usuario afectará contratos en curso. ¿Deseas forzar la eliminación de todas formas?</p>
                <div style="display: flex; gap: 12px;">
                    <form action="admin_actions.php" method="POST">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="id" value="<?= (int)$_GET['uid'] ?>">
                        <input type="hidden" name="confirmar_forzar" value="1">
                        <button type="submit" class="btn-sm btn-del" style="background: #ef4444; color: #fff; border-color: #ef4444; cursor:pointer;">Sí, forzar eliminación</button>
                    </form>
                    <a href="admin_panel.php?tab=usuarios" class="btn-sm btn-doc" style="text-decoration: none; padding: 6px 12px; line-height:1.5;">Cancelar</a>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios as $u): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] == 'cliente' ? 'b-cliente' : 'b-proveedor' ?>">
                                <?= $u['role'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['suspendido_at'] !== null): ?>
                                <span class="badge" style="background: rgba(245, 130, 13, 0.2); color: var(--orange);">Suspendido</span>
                            <?php else: ?>
                                <span class="badge b-aprov">Activo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <button type="button" class="btn-sm btn-doc" onclick="verPerfilUsuario(<?= $u['id'] ?>)" style="cursor:pointer;">👤 Ver Perfil</button>
                                
                                <?php if ($u['suspendido_at'] === null): ?>
                                    <form action="admin_actions.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas suspender a este usuario? Perderá acceso temporalmente.');">
                                        <input type="hidden" name="action" value="suspender_usuario">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-sm" style="background: rgba(245, 130, 13, 0.15); color: var(--orange); border: 1px solid rgba(245, 130, 13, 0.3); cursor:pointer;">⏸ Suspender</button>
                                    </form>
                                <?php else: ?>
                                    <form action="admin_actions.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="reactivar_usuario">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-sm btn-aprov" style="cursor:pointer;">▶ Reactivar</button>
                                    </form>
                                <?php endif; ?>

                                <form action="admin_actions.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar usuario? Perderá acceso y sus servicios serán ocultados.');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-sm btn-del" style="cursor:pointer;">🗑️ Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($total_pages_user > 1): ?>
                <div class="admin-pagination">
                    <?php for ($i = 1; $i <= $total_pages_user; $i++): ?>
                        <a href="?tab=usuarios&p=<?= $i ?>" class="btn-page <?= $page == $i ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CATEGORIAS -->
    <div id="tab-categorias" class="tab-content <?= $tab == 'categorias' ? 'active' : '' ?>">
        <div class="header">
            <h1>Gestión de Categorías</h1>
        </div>
        
        <div style="background: var(--card-bg); padding: 24px; border-radius: var(--radius); border: 1px solid var(--border); margin-bottom: 24px; max-width: 500px;">
            <h3 style="margin-bottom: 16px;">Agregar Nueva Categoría</h3>
            <form action="admin_actions.php" method="POST" class="form-group">
                <input type="hidden" name="action" value="add_categoria">
                <input type="text" name="nombre" placeholder="Nombre de la categoría..." required>
                <button type="submit" class="form-submit">Agregar</button>
            </form>
        </div>

        <div class="table-wrap" style="max-width: 500px;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre de Categoría</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($categorias as $c): ?>
                    <tr>
                        <td style="color:var(--text-muted);">#<?= $c['id'] ?></td>
                        <td><?= htmlspecialchars($c['nombre']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- AUDITORÍA -->
    <div id="tab-auditoria" class="tab-content <?= $tab == 'auditoria' ? 'active' : '' ?>">
        <div class="header">
            <h1>Módulo de Auditoría y Supervisión</h1>
            <div class="audit-subtabs" style="display:flex; gap:10px;">
                <button class="btn-sm btn-doc subtab-btn active" onclick="switchAuditSubtab('actividad')">📋 Actividad del Sistema</button>
                <button class="btn-sm btn-doc subtab-btn" onclick="switchAuditSubtab('chats')">💬 Monitor de Chats</button>
                <button class="btn-sm btn-doc subtab-btn" onclick="switchAuditSubtab('perfiles')">👤 Perfiles de Usuario</button>
            </div>
        </div>

        <!-- SUBTAB 1: ACTIVIDAD -->
        <div id="audit-subtab-actividad" class="audit-subtab-content" style="display:block;">
            <div class="filters-bar" style="background: var(--card-bg); border: 1px solid var(--border); padding: 20px; border-radius: var(--radius); margin-bottom: 24px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:15px; align-items:end;">
                <div>
                    <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px;">Tipo de Evento</label>
                    <select id="filt-tipo" style="width:100%; padding:10px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:8px; color:white; outline:none;">
                        <option value="">Todos los eventos</option>
                        <option value="login">login (Exitoso)</option>
                        <option value="login_fallido">login_fallido (Fallo)</option>
                        <option value="logout">logout</option>
                        <option value="register">register</option>
                        <option value="password_cambiada">password_cambiada</option>
                        <option value="create_service">create_service</option>
                        <option value="update_service">update_service</option>
                        <option value="delete_service">delete_service</option>
                        <option value="contratacion_creada">contratacion_creada</option>
                        <option value="contratacion_aceptada">contratacion_aceptada</option>
                        <option value="contratacion_rechazada">contratacion_rechazada</option>
                        <option value="contratacion_cancelada">contratacion_cancelada</option>
                        <option value="contratacion_completada">contratacion_completada</option>
                        <option value="valoracion_enviada">valoracion_enviada</option>
                        <option value="verificacion_solicitada">verificacion_solicitada</option>
                        <option value="verificacion_aprobada">verificacion_aprobada</option>
                        <option value="verificacion_rechazada">verificacion_rechazada</option>
                        <option value="admin_delete_user">admin_delete_user</option>
                        <option value="admin_suspendio_usuario">admin_suspendio_usuario</option>
                        <option value="admin_reactivo_usuario">admin_reactivo_usuario</option>
                        <option value="admin_toggle_verificado">admin_toggle_verificado</option>
                        <option value="chat_archivado">chat_archivado</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px;">ID Usuario</label>
                    <input type="number" id="filt-uid" placeholder="Ej. 5" style="width:100%; padding:10px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:8px; color:white; outline:none;">
                </div>
                <div>
                    <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px;">Desde</label>
                    <input type="date" id="filt-desde" style="width:100%; padding:10px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:8px; color:white; outline:none;">
                </div>
                <div>
                    <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px;">Hasta</label>
                    <input type="date" id="filt-hasta" style="width:100%; padding:10px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:8px; color:white; outline:none;">
                </div>
                <div>
                    <button class="form-submit" onclick="cargarAuditLogs(1)" style="width:100%; padding:11px; cursor:pointer;">🔍 Filtrar</button>
                </div>
                <div>
                    <button class="btn-sm btn-doc" onclick="exportarAuditoriaPDF()" style="width:100%; padding:11px; cursor:pointer; background: #2d5be3; color: white; border: none; border-radius: 8px; font-weight: 600; font-family: 'DM Sans', sans-serif; font-size: 13px;">📄 Exportar PDF</button>
                </div>
            </div>

            <div class="table-wrap">
                <table id="audit-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Usuario / Email</th>
                            <th>Tabla / ID</th>
                            <th>Descripción</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody id="audit-table-body">
                        <!-- Carga dinámica por AJAX -->
                    </tbody>
                </table>
                <div class="admin-pagination" id="audit-pagination" style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:13px; color:var(--text-muted);" id="audit-info">Mostrando registros...</div>
                    <div style="display:flex; gap:8px;" id="audit-page-buttons"></div>
                </div>
            </div>
        </div>

        <!-- SUBTAB 2: MONITOR CHATS -->
        <div id="audit-subtab-chats" class="audit-subtab-content" style="display:none;">
            <!-- Barra de filtros del Monitor de Chats -->
            <div style="background: var(--card-bg); border: 1px solid var(--border); padding: 16px 20px; border-radius: var(--radius); margin-bottom: 20px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                <div style="flex:1; min-width:180px;">
                    <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px;">Buscar servicio, cliente o proveedor</label>
                    <input type="text" id="chat-filt-q" placeholder="Ej: Juan, Diseño web..." style="width:100%; padding:10px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:8px; color:white; outline:none;" oninput="filtrarChatsEnVivo()">
                </div>
                <div style="min-width:150px;">
                    <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px;">Mín. mensajes</label>
                    <input type="number" id="chat-filt-min" placeholder="Cualquiera" min="0" style="width:100%; padding:10px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:8px; color:white; outline:none;" oninput="filtrarChatsEnVivo()">
                </div>
                <button class="form-submit" onclick="cargarChatThreads()" style="padding:10px 20px; cursor:pointer;">🔄 Recargar</button>
            </div>
            <!-- Vista lista de hilos -->
            <div id="chats-list-view">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th>Cliente</th>
                                <th>Proveedor</th>
                                <th>Total Mensajes</th>
                                <th>Última Actividad</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="chats-threads-body">
                            <!-- Carga dinámica -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Vista conversación del hilo (Detalle) -->
            <div id="chats-detail-view" style="display:none; background: var(--card-bg); border:1px solid var(--border); border-radius: var(--radius); overflow:hidden;">
                <div style="padding:16px 20px; background:rgba(255,255,255,0.02); border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3 id="chat-detail-title" style="font-family:'Rajdhani',sans-serif; font-size:18px;">Conversación</h3>
                        <p id="chat-detail-sub" style="font-size:13px; color:var(--text-muted);">Cargando participantes...</p>
                    </div>
                    <button class="btn-sm btn-doc" onclick="cerrarDetalleChat()" style="cursor:pointer;">⬅ Volver a la Lista</button>
                </div>
                <div style="background: rgba(245,130,13,0.1); border-bottom:1px solid rgba(245,130,13,0.2); padding:10px 20px; font-size:12px; color:var(--orange); font-weight:600; display:flex; align-items:center; gap:8px;">
                    🔒 MODO AUDITORÍA — SOLO LECTURA (ADMINISTRADOR NO PUEDE ENVIAR MENSAJES)
                </div>
                <!-- Burbujas de chat -->
                <div id="chat-messages-container" style="height:400px; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:16px; background: rgba(0,0,0,0.2);">
                    <!-- Mensajes cargados dinámicamente -->
                </div>
            </div>
        </div>

        <!-- SUBTAB 3: PERFILES DE USUARIO -->
        <div id="audit-subtab-perfiles" class="audit-subtab-content" style="display:none;">
            <div style="background: var(--card-bg); border: 1px solid var(--border); padding: 20px; border-radius: var(--radius); margin-bottom: 16px; display:flex; gap:15px; align-items:flex-end;">
                <div style="flex:1;">
                    <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px;">Buscar Usuario (ID, nombre o email)</label>
                    <input type="text" id="perfil-search-input" placeholder="Ej: 12, Juan López, juan@email.com..." style="width:100%; padding:10px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:8px; color:white; outline:none;" onkeypress="if(event.key === 'Enter') buscarPerfilLive()">
                </div>
                <button class="form-submit" onclick="buscarPerfilLive()" style="padding:11px 24px; cursor:pointer;">🔍 Buscar</button>
            </div>
            <!-- Lista de resultados de búsqueda (aparece cuando la query no es un ID exacto) -->
            <div id="perfil-search-results" style="display:none; background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 20px; overflow:hidden;"></div>

            <!-- Ficha detallada -->
            <div id="perfil-card-view" style="display:none; display:grid; grid-template-columns: 300px 1fr; gap:24px;">
                <!-- Columna Izquierda: Datos Personales -->
                <div style="background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding:24px; display:flex; flex-direction:column; gap:20px; height:fit-content;">
                    <div style="text-align:center;">
                        <div id="prof-avatar" style="width:72px; height:72px; border-radius:50%; background:linear-gradient(135deg, var(--blue-mid), var(--blue-light)); display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:700; margin:0 auto 12px;">U</div>
                        <h3 id="prof-nombre" style="font-family:'Rajdhani',sans-serif; font-size:20px; font-weight:700;">Nombre Completo</h3>
                        <span id="prof-role-badge" class="badge b-cliente" style="margin-top:6px; display:inline-block;">Cliente</span>
                    </div>

                    <div style="border-top: 1px solid var(--border); padding-top:16px; display:flex; flex-direction:column; gap:12px; font-size:14px;">
                        <div>
                            <span style="color:var(--text-muted); display:block; font-size:12px;">ID Usuario</span>
                            <strong id="prof-id">#0</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-muted); display:block; font-size:12px;">Email</span>
                            <strong id="prof-email">correo@ejemplo.com</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-muted); display:block; font-size:12px;">Teléfono</span>
                            <strong id="prof-tel">--</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-muted); display:block; font-size:12px;">Fecha Registro</span>
                            <strong id="prof-registro">--</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-muted); display:block; font-size:12px;">Último Login</span>
                            <strong id="prof-lastlogin">--</strong>
                        </div>
                        <div>
                            <span style="color:var(--text-muted); display:block; font-size:12px;">Estado</span>
                            <div id="prof-estado" style="margin-top:4px;">Activo</div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Relaciones y Actividad -->
                <div style="display:flex; flex-direction:column; gap:24px;">
                    <!-- Verificación -->
                    <div id="prof-sec-verif" style="background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding:20px;">
                        <h4 style="font-family:'Rajdhani',sans-serif; font-size:16px; margin-bottom:12px; display:flex; align-items:center; gap:8px;">🛡️ Verificación de Identidad</h4>
                        <div id="prof-verif-content" style="font-size:14px; color:var(--text-muted);">Sin datos de verificación solicitada.</div>
                    </div>

                    <!-- Servicios Publicados -->
                    <div style="background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding:20px;">
                        <h4 style="font-family:'Rajdhani',sans-serif; font-size:16px; margin-bottom:12px;">🔧 Servicios Publicados (Proveedores)</h4>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Servicio</th>
                                        <th>Categoría</th>
                                        <th>Municipio</th>
                                        <th>Precio</th>
                                        <th>Verificado</th>
                                        <th>Total Contratos</th>
                                    </tr>
                                </thead>
                                <tbody id="prof-servicios-body">
                                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Sin servicios publicados.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Historial Contrataciones -->
                    <div style="background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding:20px;">
                        <h4 style="font-family:'Rajdhani',sans-serif; font-size:16px; margin-bottom:12px;">🤝 Historial de Contrataciones</h4>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Servicio</th>
                                        <th>Cliente</th>
                                        <th>Proveedor</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody id="prof-contrataciones-body">
                                    <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">Sin contrataciones registradas.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Chats Activos -->
                    <div style="background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding:20px;">
                        <h4 style="font-family:'Rajdhani',sans-serif; font-size:16px; margin-bottom:12px;">💬 Hilos de Chat Activos</h4>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Servicio</th>
                                        <th>Participante</th>
                                        <th>Último Mensaje</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="prof-chats-body">
                                    <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">Sin chats activos.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="perfil-card-empty" style="text-align:center; padding:40px; border:1px dashed var(--border); border-radius:var(--radius); color:var(--text-muted);">
                Usa el buscador o haz clic en "Ver Perfil" de cualquier usuario para cargar su auditoría completa.
            </div>
        </div>
    </div>

</div>

<script>
// --- MANEJO DE PESTAÑAS PRINCIPALES (FALLBACK / ROUTING INTERNO) ---
document.addEventListener('DOMContentLoaded', () => {
    // Si la URL pide ?tab=auditoria, cargar de inmediato
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam === 'auditoria') {
        cargarAuditLogs(1);
    }
});

// --- SUBPESTAÑAS DE AUDITORÍA ---
function switchAuditSubtab(subtab) {
    // Esconder todos los contenidos
    document.querySelectorAll('.audit-subtab-content').forEach(el => {
        el.style.display = 'none';
    });
    
    // Quitar clase activa a todos los botones
    document.querySelectorAll('.subtab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.style.background = 'var(--blue-mid)';
        btn.style.color = 'white';
    });

    // Mostrar el seleccionado
    const selectedContent = document.getElementById(`audit-subtab-${subtab}`);
    if (selectedContent) {
        selectedContent.style.display = 'block';
    }

    // Activar botón correspondiente
    document.querySelectorAll('.subtab-btn').forEach(btn => {
        if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(`'${subtab}'`)) {
            btn.classList.add('active');
            btn.style.background = 'var(--orange)';
            btn.style.color = 'white';
        }
    });

    // Cargas iniciales según la subpestaña
    if (subtab === 'actividad') {
        cargarAuditLogs(1);
    } else if (subtab === 'chats') {
        cargarChatThreads();
    }
}

// --- SUBTAB 1: LOGS DE ACTIVIDAD ---
function cargarAuditLogs(page) {
    const tipo = document.getElementById('filt-tipo').value;
    const uid = document.getElementById('filt-uid').value;
    const desde = document.getElementById('filt-desde').value;
    const hasta = document.getElementById('filt-hasta').value;

    const tbody = document.getElementById('audit-table-body');
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">⌛ Cargando logs de auditoría...</td></tr>`;

    fetch(`admin_actions.php?action=get_audit_log&tipo=${tipo}&uid=${uid}&desde=${desde}&hasta=${hasta}&p=${page}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok || res.logs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">📭 No se encontraron registros de auditoría que coincidan con los filtros.</td></tr>`;
                document.getElementById('audit-info').textContent = 'Mostrando 0 registros';
                document.getElementById('audit-page-buttons').innerHTML = '';
                return;
            }

            tbody.innerHTML = '';
            res.logs.forEach(log => {
                const tr = document.createElement('tr');
                
                // Formatear fecha
                const fecha = formatDateSafe(log.created_at, true);

                // Determinar badge para tipo de evento
                let badgeClass = 'badge';
                if (log.tipo.includes('fallido') || log.tipo.includes('delete') || log.tipo.includes('rechazado') || log.tipo.includes('suspendio')) {
                    badgeClass += ' b-rech';
                } else if (log.tipo.includes('creada') || log.tipo.includes('aprobada') || log.tipo.includes('exito') || log.tipo.includes('reactivo') || log.tipo.includes('completada') || log.tipo.includes('register') || log.tipo === 'login') {
                    badgeClass += ' b-aprov';
                } else if (log.tipo.includes('update') || log.tipo.includes('edit') || log.tipo.includes('toggle')) {
                    badgeClass += ' b-pend';
                } else {
                    badgeClass += ' b-cliente';
                }

                // Usuario info
                const userCell = log.usuario_id 
                    ? `<strong>${escapeHtml(log.nombre || '')}</strong><br><small style="color:var(--text-muted);">#${log.usuario_id} — ${escapeHtml(log.email || '')}</small>`
                    : `<span style="color:var(--text-muted);">Sistema / Anónimo</span>`;

                tr.innerHTML = `
                    <td style="white-space:nowrap; font-size:13px;">${fecha}</td>
                    <td><span class="${badgeClass}" style="font-size:11px;">${log.tipo}</span></td>
                    <td>${userCell}</td>
                    <td><small style="color:var(--text-muted);">${log.entidad || '--'}<br>ID: ${log.entidad_id || '--'}</small></td>
                    <td><div style="max-width:320px; word-wrap:break-word; font-size:13px;">${escapeHtml(log.descripcion)}</div></td>
                    <td style="font-family:monospace; font-size:12px; color:var(--text-muted);">${log.ip || '--'}</td>
                `;
                tbody.appendChild(tr);
            });

            // Paginación
            document.getElementById('audit-info').textContent = `Mostrando logs (${res.logs.length} de ${res.total} en total)`;
            
            let pagHtml = '';
            for (let i = 1; i <= res.pages; i++) {
                pagHtml += `<button class="btn-page ${res.page === i ? 'active' : ''}" onclick="cargarAuditLogs(${i})" style="cursor:pointer; padding:4px 10px; border-radius:6px; border:1px solid var(--border); margin:0 2px;">${i}</button>`;
            }
            document.getElementById('audit-page-buttons').innerHTML = pagHtml;
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:#f87171;">❌ Error de conexión al cargar los logs.</td></tr>`;
        });
}

function exportarAuditoriaPDF() {
    if (!window.jspdf || !window.jspdf.jsPDF) {
        alert("La librería para generar PDF aún se está cargando. Intenta en un momento.");
        return;
    }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape');
    
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(16);
    doc.text("Reporte de Auditoria - ServiJob", 14, 15);
    
    const tipo = document.getElementById('filt-tipo').options[document.getElementById('filt-tipo').selectedIndex].text;
    const uid = document.getElementById('filt-uid').value || 'Todos';
    const desde = document.getElementById('filt-desde').value || 'Inicio';
    const hasta = document.getElementById('filt-hasta').value || 'Hoy';
    
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text(`Filtros aplicados - Tipo: ${tipo} | Usuario: ${uid} | Desde: ${desde} | Hasta: ${hasta}`, 14, 22);

    doc.autoTable({
        html: '#audit-table',
        startY: 28,
        theme: 'grid',
        styles: { fontSize: 8, cellPadding: 3 },
        headStyles: { fillColor: [45, 91, 227] },
        columnStyles: {
            0: { cellWidth: 35 },
            1: { cellWidth: 45 },
            2: { cellWidth: 50 },
            3: { cellWidth: 35 },
            4: { cellWidth: 'auto' },
            5: { cellWidth: 30 }
        }
    });

    doc.save('auditoria_servijob.pdf');
}

// --- SUBTAB 2: MONITOR DE CHATS ---
function cargarChatThreads() {
    const tbody = document.getElementById('chats-threads-body');
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">⌛ Cargando hilos de conversación...</td></tr>`;

    fetch('admin_actions.php?action=get_chat_hilos')
        .then(r => r.json())
        .then(res => {
            if (!res.ok || res.hilos.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">📭 No hay hilos de chat registrados en el sistema.</td></tr>`;
                return;
            }

            tbody.innerHTML = '';
            res.hilos.forEach(thread => {
                const tr = document.createElement('tr');

                const fecha = formatDateSafe(thread.ultimo_mensaje, false);

                tr.innerHTML = `
                    <td><strong>${escapeHtml(thread.servicio_titulo)}</strong><br><small style="color:var(--text-muted);">ID Servicio: #${thread.servicio_id}</small></td>
                    <td><strong>${escapeHtml(thread.cliente_nombre)}</strong><br><small style="color:var(--text-muted);">${escapeHtml(thread.cliente_email)}</small></td>
                    <td><strong>${escapeHtml(thread.proveedor_nombre)}</strong><br><small style="color:var(--text-muted);">${escapeHtml(thread.proveedor_email)}</small></td>
                    <td style="text-align:center;"><span class="badge b-cliente">${thread.total_mensajes} msg</span></td>
                    <td style="font-size:13px; color:var(--text-muted);">${fecha}</td>
                    <td>
                        <button class="btn-sm btn-doc" onclick="verChatDetalle(${thread.servicio_id}, ${thread.cliente_id}, '${escapeJs(thread.servicio_titulo)}', '${escapeJs(thread.cliente_nombre)}', '${escapeJs(thread.proveedor_nombre)}')" style="cursor:pointer;">👁️ Inspeccionar Chat</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:#f87171;">❌ Error de conexión al cargar hilos de chat.</td></tr>`;
        });
}

// Filtrado en vivo (client-side) del monitor de chats
function filtrarChatsEnVivo() {
    const q = (document.getElementById('chat-filt-q').value || '').toLowerCase().trim();
    const minMsg = parseInt(document.getElementById('chat-filt-min').value) || 0;

    document.querySelectorAll('#chats-threads-body tr').forEach(tr => {
        const text = tr.textContent.toLowerCase();
        const totalMsgCell = tr.querySelectorAll('td')[3]; // columna "Total Mensajes"
        const totalMsg = totalMsgCell ? parseInt(totalMsgCell.textContent) || 0 : 0;

        const matchQ = !q || text.includes(q);
        const matchMin = totalMsg >= minMsg;
        tr.style.display = (matchQ && matchMin) ? '' : 'none';
    });
}

function verChatDetalle(servicio_id, cliente_id, titulo, cliente_nombre, proveedor_nombre) {
    document.getElementById('chats-list-view').style.display = 'none';
    const detailView = document.getElementById('chats-detail-view');
    detailView.style.display = 'block';

    document.getElementById('chat-detail-title').textContent = `Inspección: ${titulo}`;
    document.getElementById('chat-detail-sub').innerHTML = `Cliente: <strong>${cliente_nombre}</strong> ── Proveedor: <strong>${proveedor_nombre}</strong>`;

    const container = document.getElementById('chat-messages-container');
    container.innerHTML = `<div style="text-align:center; color:var(--text-muted); padding:40px;">⌛ Cargando mensajes del hilo...</div>`;

    fetch(`admin_actions.php?action=get_chat_mensajes_hilo&servicio_id=${servicio_id}&cliente_id=${cliente_id}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok || res.mensajes.length === 0) {
                container.innerHTML = `<div style="text-align:center; color:var(--text-muted); padding:40px;">📭 Conversación vacía o sin mensajes.</div>`;
                return;
            }

            container.innerHTML = '';
            res.mensajes.forEach(msg => {
                const bubble = document.createElement('div');
                
                // Formatear fecha
                const fecha = formatDateSafe(msg.created_at, false).split(', ')[1] || formatDateSafe(msg.created_at, false);

                // Lado de la burbuja (Emisor es el cliente vs el proveedor)
                const isCliente = msg.emisor_role === 'cliente';
                const colorBg = isCliente ? 'rgba(61, 122, 245, 0.2)' : 'rgba(167, 139, 250, 0.2)';
                const borderColor = isCliente ? 'rgba(61, 122, 245, 0.4)' : 'rgba(167, 139, 250, 0.4)';
                const align = isCliente ? 'flex-start' : 'flex-end';
                const labelColor = isCliente ? 'var(--blue-light)' : '#a78bfa';

                bubble.style.cssText = `
                    max-width: 60%;
                    background: ${colorBg};
                    border: 1px solid ${borderColor};
                    border-radius: 12px;
                    padding: 12px 16px;
                    align-self: ${align};
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                    animation: fadeIn 0.2s ease;
                `;

                bubble.innerHTML = `
                    <div style="font-size:11px; font-weight:600; color:${labelColor}; text-transform:uppercase;">
                        ${escapeHtml(msg.emisor_nombre)} (${msg.emisor_role})
                    </div>
                    <div style="font-size:14px; color:white; word-break:break-word; line-height:1.4;">
                        ${escapeHtml(msg.mensaje)}
                    </div>
                    <div style="font-size:10px; color:var(--text-muted); text-align:right; margin-top:2px;">
                        ${fecha}
                    </div>
                `;
                container.appendChild(bubble);
            });
            
            // Auto-scroll al final del chat
            container.scrollTop = container.scrollHeight;
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = `<div style="text-align:center; color:#f87171; padding:40px;">❌ Error de conexión al cargar mensajes del hilo.</div>`;
        });
}

function cerrarDetalleChat() {
    document.getElementById('chats-detail-view').style.display = 'none';
    document.getElementById('chats-list-view').style.display = 'block';
}

// --- SUBTAB 3: PERFILES DE USUARIO ---
function buscarPerfilLive() {
    const query = document.getElementById('perfil-search-input').value.trim();
    if (!query) return;
    
    // Si la query es numérica, cargar el perfil directamente por ID
    const uidDirecto = parseInt(query);
    if (!isNaN(uidDirecto) && String(uidDirecto) === query) {
        document.getElementById('perfil-search-results').style.display = 'none';
        verPerfilUsuario(uidDirecto);
        return;
    }

    // Búsqueda por texto (nombre o email)
    const resultsBox = document.getElementById('perfil-search-results');
    resultsBox.innerHTML = `<div style="padding:20px; color:var(--text-muted);">⌛ Buscando...</div>`;
    resultsBox.style.display = 'block';
    document.getElementById('perfil-card-view').style.display = 'none';
    document.getElementById('perfil-card-empty').style.display = 'none';

    fetch(`admin_actions.php?action=search_users&q=${encodeURIComponent(query)}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok || res.users.length === 0) {
                resultsBox.innerHTML = `<div style="padding:20px; color:var(--text-muted); text-align:center;">📭 No se encontraron usuarios con "${escapeHtml(query)}".</div>`;
                return;
            }

            let html = `<table style="width:100%; border-collapse:collapse;"><thead><tr>
                <th style="padding:12px 16px; text-align:left; font-size:13px; color:var(--text-muted); border-bottom:1px solid var(--border);">ID</th>
                <th style="padding:12px 16px; text-align:left; font-size:13px; color:var(--text-muted); border-bottom:1px solid var(--border);">Nombre</th>
                <th style="padding:12px 16px; text-align:left; font-size:13px; color:var(--text-muted); border-bottom:1px solid var(--border);">Email</th>
                <th style="padding:12px 16px; text-align:left; font-size:13px; color:var(--text-muted); border-bottom:1px solid var(--border);">Rol</th>
                <th style="padding:12px 16px; border-bottom:1px solid var(--border);"></th>
            </tr></thead><tbody>`;

            res.users.forEach(u => {
                const badgeClass = u.role === 'cliente' ? 'b-cliente' : 'b-proveedor';
                const estadoBadge = u.suspendido_at ? '<span class="badge" style="background:rgba(245,130,13,0.2);color:var(--orange);font-size:10px;">Suspendido</span>' : '<span class="badge b-aprov" style="font-size:10px;">Activo</span>';
                html += `<tr style="border-bottom:1px solid rgba(255,255,255,0.04); cursor:pointer;" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background=''">
                    <td style="padding:12px 16px; font-size:13px; color:var(--text-muted);">#${u.id}</td>
                    <td style="padding:12px 16px; font-weight:600;">${escapeHtml(u.nombre)} ${escapeHtml(u.apellido || '')}</td>
                    <td style="padding:12px 16px; font-size:13px; color:var(--text-muted);">${escapeHtml(u.email)}</td>
                    <td style="padding:12px 16px;"><span class="badge ${badgeClass}">${u.role}</span> ${estadoBadge}</td>
                    <td style="padding:12px 16px; text-align:right;"><button class="btn-sm btn-doc" onclick="seleccionarPerfilBusqueda(${u.id})" style="cursor:pointer; font-size:12px;">👤 Ver Perfil</button></td>
                </tr>`;
            });

            html += `</tbody></table>`;
            resultsBox.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            resultsBox.innerHTML = `<div style="padding:20px; color:#f87171; text-align:center;">❌ Error al buscar usuarios.</div>`;
        });
}

function seleccionarPerfilBusqueda(uid) {
    document.getElementById('perfil-search-results').style.display = 'none';
    verPerfilUsuario(uid);
}

function verPerfilUsuario(uid) {
    // Si viene desde otra pestaña (ej: Tab Usuarios)
    // Cambiar a la pestaña Auditoría
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-auditoria').classList.add('active');

    // Cambiar el enlace activo en la sidebar
    document.querySelectorAll('.nav-item').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(btn => {
        if (btn.getAttribute('href') && btn.getAttribute('href').includes('auditoria')) {
            btn.classList.add('active');
        }
    });

    // Activar sub-pestaña de perfiles
    const perfilesBtn = document.querySelectorAll('.subtab-btn')[2];
    if (perfilesBtn) {
        // Ocultar todos los subtab contents
        document.querySelectorAll('.audit-subtab-content').forEach(el => el.style.display = 'none');
        document.getElementById('audit-subtab-perfiles').style.display = 'block';
        
        // Activar el botón estéticamente
        document.querySelectorAll('.subtab-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.style.background = 'var(--blue-mid)';
        });
        perfilesBtn.classList.add('active');
        perfilesBtn.style.background = 'var(--orange)';
    }

    // Rellenar buscador con el ID por claridad
    document.getElementById('perfil-search-input').value = uid;

    const cardView = document.getElementById('perfil-card-view');
    const emptyView = document.getElementById('perfil-card-empty');
    
    emptyView.style.display = 'block';
    emptyView.innerHTML = `⌛ Cargando ficha de auditoría y relaciones para el usuario #${uid}...`;
    cardView.style.display = 'none';

    fetch(`admin_actions.php?action=get_user_profile&uid=${uid}`)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) {
                emptyView.style.display = 'block';
                emptyView.innerHTML = `<span style="color:#f87171;">❌ Error: ${escapeHtml(res.error || 'Usuario no encontrado.')}</span>`;
                return;
            }

            emptyView.style.display = 'none';
            cardView.style.display = 'grid';

            const user = res.user;

            // Datos principales
            document.getElementById('prof-avatar').textContent = user.nombre.substring(0, 1).toUpperCase();
            document.getElementById('prof-nombre').textContent = `${user.nombre} ${user.apellido || ''}`;
            document.getElementById('prof-id').textContent = `#${user.id}`;
            document.getElementById('prof-email').textContent = user.email;
            document.getElementById('prof-tel').textContent = user.telefono || 'Sin registrar';
            
            // Fechas
            document.getElementById('prof-registro').textContent = new Date(user.created_at).toLocaleDateString('es-ES');
            document.getElementById('prof-lastlogin').textContent = user.last_login 
                ? new Date(user.last_login).toLocaleString('es-ES') 
                : 'Nunca registrado';

            // Role Badge
            const roleBadge = document.getElementById('prof-role-badge');
            roleBadge.textContent = user.role;
            roleBadge.className = `badge ${user.role === 'cliente' ? 'b-cliente' : 'b-proveedor'}`;

            // Estado
            const estadoDiv = document.getElementById('prof-estado');
            if (user.deleted_at) {
                estadoDiv.innerHTML = `<span class="badge b-rech">Eliminado (Baja)</span>`;
            } else if (user.suspendido_at) {
                estadoDiv.innerHTML = `<span class="badge" style="background:rgba(245,130,13,0.2); color:var(--orange);">Suspendido (${new Date(user.suspendido_at).toLocaleDateString('es-ES')})</span>`;
            } else {
                estadoDiv.innerHTML = `<span class="badge b-aprov">Activo / Autorizado</span>`;
            }

            // Sección 1: Verificación de Identidad
            const verifDiv = document.getElementById('prof-verif-content');
            if (res.verificacion) {
                const v = res.verificacion;
                const vDate = new Date(v.created_at).toLocaleDateString('es-ES');
                let stBadge = 'b-pend';
                if (v.estado === 'aprobado') stBadge = 'b-aprov';
                if (v.estado === 'rechazado') stBadge = 'b-rech';

                verifDiv.innerHTML = `
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <div>Estado de Solicitud: <span class="badge ${stBadge}">${v.estado}</span></div>
                        <div>Fecha: <strong>${vDate}</strong></div>
                        <div>Negocio registrado: <strong>${escapeHtml(v.nombre)}</strong> (Municipio: ${escapeHtml(v.municipio)})</div>
                        <div style="margin-top:4px;">
                            <a href="ver_doc.php?id=${v.id}" target="_blank" class="btn-sm btn-doc" style="display:inline-block; text-decoration:none;">📄 Ver Documento de Respaldo</a>
                        </div>
                    </div>
                `;
            } else {
                verifDiv.innerHTML = `<div style="color:var(--text-muted); font-style:italic;">No ha iniciado procesos de verificación de identidad.</div>`;
            }

            // Sección 2: Servicios Publicados (Si es proveedor)
            const srvBody = document.getElementById('prof-servicios-body');
            srvBody.innerHTML = '';
            if (res.servicios.length > 0) {
                res.servicios.forEach(s => {
                    const tr = document.createElement('tr');
                    const avgStars = s.avg_valoracion ? parseFloat(s.avg_valoracion).toFixed(1) + ' ★' : 'Sin reviews';
                    tr.innerHTML = `
                        <td><strong>${escapeHtml(s.titulo)}</strong></td>
                        <td>${escapeHtml(s.categoria)}</td>
                        <td>${escapeHtml(s.municipio)}</td>
                        <td>$${s.precio}</td>
                        <td>
                            <form action="admin_actions.php" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_verificado">
                                <input type="hidden" name="id" value="${s.id}">
                                <button type="submit" class="btn-sm ${s.verificado ? 'btn-aprov' : 'btn-toggle'}" style="font-size:11px; padding:2px 6px; cursor:pointer;">
                                    ${s.verificado ? '✔ verificado' : '✖ sin verificar'}
                                </button>
                            </form>
                        </td>
                        <td style="text-align:center;"><strong>${s.total_contrataciones}</strong> (${avgStars})</td>
                    `;
                    srvBody.appendChild(tr);
                });
            } else {
                srvBody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--text-muted); font-style:italic;">Sin servicios activos.</td></tr>`;
            }

            // Sección 3: Historial de Contrataciones
            const cntBody = document.getElementById('prof-contrataciones-body');
            cntBody.innerHTML = '';
            if (res.contrataciones.length > 0) {
                res.contrataciones.forEach(c => {
                    const tr = document.createElement('tr');
                    const cDate = formatDateSafe(c.created_at, false).split(', ')[0] || formatDateSafe(c.created_at, false);
                    
                    let cntBadge = 'b-pend';
                    if (c.estado === 'completado') cntBadge = 'b-aprov';
                    if (c.estado === 'rechazado' || c.estado === 'cancelado') cntBadge = 'b-rech';

                    tr.innerHTML = `
                        <td><strong>${escapeHtml(c.servicio_titulo)}</strong></td>
                        <td>${escapeHtml(c.cliente_nombre)} <span style="color:var(--text-muted); font-size:11px;">#${c.cliente_id}</span></td>
                        <td>${escapeHtml(c.proveedor_nombre)} <span style="color:var(--text-muted); font-size:11px;">#${c.proveedor_id}</span></td>
                        <td><span class="badge ${cntBadge}">${c.estado}</span></td>
                        <td style="font-size:13px; color:var(--text-muted);">${cDate}</td>
                    `;
                    cntBody.appendChild(tr);
                });
            } else {
                cntBody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:var(--text-muted); font-style:italic;">Sin contrataciones registradas.</td></tr>`;
            }

            // Sección 4: Chats Activos
            const chatBody = document.getElementById('prof-chats-body');
            chatBody.innerHTML = '';
            if (res.chats.length > 0) {
                res.chats.forEach(ch => {
                    const tr = document.createElement('tr');
                    const cDate = formatDateSafe(ch.ultimo_mensaje, false);
                    
                    // El participante es el opuesto a este usuario
                    const participante = (uid === ch.cliente_id) ? ch.proveedor_nombre : ch.cliente_nombre;

                    tr.innerHTML = `
                        <td><strong>${escapeHtml(ch.servicio_titulo)}</strong></td>
                        <td>${escapeHtml(participante)}</td>
                        <td style="font-size:13px; color:var(--text-muted);">${cDate}</td>
                        <td>
                            <button class="btn-sm btn-doc" onclick="inspectFromProfile(${ch.servicio_id}, ${ch.cliente_id}, '${escapeJs(ch.servicio_titulo)}', '${escapeJs(ch.cliente_nombre)}', '${escapeJs(ch.proveedor_nombre)}')" style="font-size:12px; padding:4px 8px; cursor:pointer;">👁️ Inspeccionar</button>
                        </td>
                    `;
                    chatBody.appendChild(tr);
                });
            } else {
                chatBody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:var(--text-muted); font-style:italic;">Sin chats activos.</td></tr>`;
            }

        })
        .catch(err => {
            console.error(err);
            emptyView.style.display = 'block';
            emptyView.innerHTML = `<span style="color:#f87171;">❌ Error de conexión al cargar la ficha de perfil.</span>`;
        });
}

function inspectFromProfile(servicio_id, cliente_id, titulo, cliente_nombre, proveedor_nombre) {
    // Cambiar a la pestaña de monitor de chats
    switchAuditSubtab('chats');
    // Cargar el detalle
    verChatDetalle(servicio_id, cliente_id, titulo, cliente_nombre, proveedor_nombre);
}

// --- HELPERS AUXILIARES ---
function formatDateSafe(dateStr, includeSeconds = true) {
    if (!dateStr) return '--';
    // Reemplaza guiones por barras para evitar fallos en motores tipo Safari/iOS
    const normalized = dateStr.toString().replace(/-/g, '/');
    const d = new Date(normalized);
    if (isNaN(d.getTime())) return dateStr; // Fallback al string original si falla
    
    const options = {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    };
    if (includeSeconds) {
        options.second = '2-digit';
    }
    return d.toLocaleString('es-ES', options);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function escapeJs(str) {
    if (!str) return '';
    return str.toString()
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"');
}

// ── Menú Móvil ─────────────────────────────────────────────
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
</script>
</body>
</html>
