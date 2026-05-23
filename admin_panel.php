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
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
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
        .btn-page.active { background: var(--blue-mid); color: white; border-color: var(--blue-light); }
    </style>
</head>
<body>

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
                            <?php if($s['verificado']): ?>
                                <span class="badge b-aprov">Sí</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(255,255,255,0.1);">No</span>
                            <?php endif; ?>
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
        
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] == 'cliente' ? 'b-cliente' : 'b-proveedor' ?>">
                                <?= $u['role'] ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <form action="admin_actions.php" method="POST" onsubmit="return confirm('¿Eliminar usuario? Perderá acceso y sus servicios serán ocultados.');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn-sm btn-del">🗑️ Eliminar</button>
                            </form>
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

</div>

<script>
</script>
</body>
</html>
