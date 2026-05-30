# 🔍 Plan de Implementación — Módulo de Auditoría + Correcciones ServiJob v2.5

> **Base:** `analisis_sistema_servijob.md` v2.4 · Stack: PHP 8+ vanilla, MySQL/MariaDB, JS sin framework
> **Resuelve:** DT-0 · DT-3 · 8 hallazgos críticos del análisis de gaps

---

## Mapa general de fases

```
Fase 0 — Correcciones críticas (bugs y riesgos reales antes de auditar)
Fase 1 — Base de datos (audit_log + campo suspendido_at)
Fase 2 — Helper audit() en db.php + todas las llamadas
Fase 3 — Endpoints nuevos en admin_actions.php
Fase 4 — UI en admin_panel.php (pestaña Auditoría + mejoras a tabs existentes)
Fase 5 — Actualizar documentación a v2.5
```

> [!IMPORTANT]
> La **Fase 0** debe ejecutarse primero porque corrige bugs de integridad de datos activos independientemente del módulo de auditoría.

---

## ⚡ Fase 0 — Correcciones Críticas (pre-auditoría)

### 0.A — `proveedor_actions.php`: Cambiar DELETE físico a soft-delete

**Problema:** El proveedor borra servicios con `DELETE FROM servicios`, dejando contrataciones y valoraciones huérfanas.

**Archivo:** `proveedor_actions.php` · Acción `delete`

```php
// ANTES (línea 107):
$stmt = mysqli_prepare($conn, "DELETE FROM servicios WHERE id=? AND usuario_id=?");

// DESPUÉS — soft delete consistente con admin:
$stmt = mysqli_prepare($conn, "UPDATE servicios SET deleted_at = NOW() WHERE id=? AND usuario_id=?");
```

> El borrado de la imagen del disco (`unlink`) se mantiene igual — solo el registro en BD cambia a soft-delete.

---

### 0.B — `chat_archivar.php`: Corregir identificador de hilo (DT-2)

**Problema:** El archivo filtra por `(servicio_id, cliente_id, proveedor_id)` pero DT-2 actualizó el identificador de hilo a `(servicio_id, cliente_id)`.

**Archivo:** `chat_archivar.php`

```php
// ANTES (líneas 36-40):
$stmt = mysqli_prepare($conn,
    "UPDATE chat_mensajes
     SET $columna = ?
     WHERE servicio_id = ? AND cliente_id = ? AND proveedor_id = ?"
);
mysqli_stmt_bind_param($stmt, 'iiii', $valor, $servicio_id, $cliente_id, $proveedor_id);

// DESPUÉS — solo 2 campos identificadores del hilo:
$stmt = mysqli_prepare($conn,
    "UPDATE chat_mensajes
     SET $columna = ?
     WHERE servicio_id = ? AND cliente_id = ?"
);
mysqli_stmt_bind_param($stmt, 'iii', $valor, $servicio_id, $cliente_id);
```

> El parámetro `$proveedor_id` puede seguir llegando por POST pero ya no se usa en el WHERE.

---

### 0.C — `admin_actions.php`: Guardar snapshot antes de eliminar usuario

**Problema:** El soft-delete no guarda quién era el usuario ni cuántos servicios se afectaron.

**Archivo:** `admin_actions.php` · Acción `delete_user`

Antes de ejecutar el soft-delete, recuperar y guardar en el log (cuando esté disponible la función `audit()` de Fase 2):
```php
// Recuperar datos del usuario ANTES de borrarlo
$stmt_info = mysqli_prepare($conn, "SELECT nombre, email, role FROM usuarios WHERE id = ?");
// ... fetch $info ...

// Contar servicios activos del usuario
$stmt_cnt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM servicios WHERE usuario_id = ? AND deleted_at IS NULL");
// ... fetch $count ...

// Luego proceder con el soft-delete existente...

// Al final, registrar:
audit('admin_delete_user', idUsuario(), 'usuarios', $id,
    "Eliminó usuario: {$info['nombre']} ({$info['email']}) rol:{$info['role']} — {$count} servicios ocultados");
```

---

### 0.D — `admin_actions.php`: Verificar contratos activos antes de `delete_user`

**Problema:** Eliminar un proveedor puede dejar contrataciones en estado `pendiente`/`aceptado` sin atención.

**Archivo:** `admin_actions.php` · Acción `delete_user`

Agregar verificación y redirección con aviso:
```php
$stmt_contratos = mysqli_prepare($conn,
    "SELECT COUNT(*) as c FROM contrataciones WHERE (proveedor_id = ? OR cliente_id = ?) AND estado IN ('pendiente','aceptado')"
);
mysqli_stmt_bind_param($stmt_contratos, 'ii', $id, $id);
mysqli_stmt_execute($stmt_contratos);
$contratos_activos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_contratos))['c'] ?? 0;

if ($contratos_activos > 0 && !isset($_POST['confirmar_forzar'])) {
    // Redirigir al panel con advertencia para mostrar modal de confirmación
    header("Location: admin_panel.php?tab=usuarios&warn=contratos_activos&uid=$id&n=$contratos_activos");
    exit;
}
```

La UI en `admin_panel.php` detecta `?warn=contratos_activos` y muestra un modal de confirmación con el campo `confirmar_forzar=1`.

---

## 📦 Fase 1 — Base de Datos

### 1.A — `audit_log_migration.sql` *(archivo nuevo)*

```sql
-- ============================================================
--  SERVI-JOB — Migración v2.5: Tabla de Auditoría + Suspensión
--  Ejecutar UNA SOLA VEZ sobre la BD service_libre
-- ============================================================

-- Tabla de log de auditoría
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED      NULL COMMENT 'NULL si es acción del sistema',
  `tipo`        VARCHAR(60)   NOT NULL,
  `entidad`     VARCHAR(40)       NULL,
  `entidad_id`  INT UNSIGNED      NULL,
  `descripcion` TEXT              NULL,
  `ip`          VARCHAR(45)       NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tipo`    (`tipo`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_creado`  (`created_at`),
  CONSTRAINT `fk_audit_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campo suspensión reversible en usuarios
ALTER TABLE `usuarios`
  ADD COLUMN `suspendido_at` DATETIME NULL DEFAULT NULL
    COMMENT 'NULL = activo, fecha = suspendido desde esa fecha'
  AFTER `deleted_at`;
```

### 1.B — Tabla de tipos de evento definidos

| Tipo | Disparado desde |
|---|---|
| `login` | `auth_login.php` — exitoso |
| `login_fallido` | `auth_login.php` — credenciales inválidas |
| `logout` | `logout.php` |
| `register` | `auth_register.php` |
| `password_cambiada` | `proveedor_actions.php` / `cliente_actions.php` |
| `create_service` | `proveedor_actions.php` |
| `update_service` | `proveedor_actions.php` |
| `delete_service` | `proveedor_actions.php` (soft-delete tras Fase 0.A) |
| `contratacion_creada` | `contratacion_actions.php` |
| `contratacion_aceptada` | `contratacion_actions.php` |
| `contratacion_rechazada` | `contratacion_actions.php` |
| `contratacion_completada` | `contratacion_actions.php` |
| `contratacion_cancelada` | `contratacion_actions.php` |
| `valoracion_enviada` | `contratacion_actions.php` |
| `verificacion_solicitada` | `verify.php` |
| `verificacion_aprobada` | `admin_actions.php` |
| `verificacion_rechazada` | `admin_actions.php` |
| `admin_delete_user` | `admin_actions.php` |
| `admin_delete_service` | `admin_actions.php` |
| `admin_suspendio_usuario` | `admin_actions.php` *(nuevo)* |
| `admin_reactivo_usuario` | `admin_actions.php` *(nuevo)* |
| `admin_toggle_destacado` | `admin_actions.php` |
| `admin_toggle_verificado` | `admin_actions.php` *(nuevo)* |
| `admin_add_categoria` | `admin_actions.php` |
| `chat_archivado` | `chat_archivar.php` |
| `chat_desarchivado` | `chat_archivar.php` |

---

## 📦 Fase 2 — Helper `audit()` en `db.php` + Llamadas

### 2.A — Función a agregar al final de `db.php`

```php
// --- Al final de db.php ---
function audit(string $tipo, ?int $usuario_id = null, ?string $entidad = null, ?int $entidad_id = null, ?string $desc = null): void {
    global $conn;
    if (!$conn) return;
    try {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $conn->prepare(
            "INSERT INTO audit_log (usuario_id, tipo, entidad, entidad_id, descripcion, ip) VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param('ississ', $usuario_id, $tipo, $entidad, $entidad_id, $desc, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Silenciosa — nunca interrumpe el flujo del usuario
    }
}
```

> [!NOTE]
> Se usa `try/catch` para que un fallo del log nunca corte la operación principal del usuario.

### 2.B — Llamadas a insertar por archivo

**`auth_login.php`** — reemplaza el comentario `// Registrar último acceso (opcional)`:
```php
// Login exitoso
audit('login', $usuario['id'], 'usuarios', $usuario['id'], "Login: {$usuario['email']}");

// Credenciales inválidas (añadir ANTES de la redirección de error):
audit('login_fallido', null, 'usuarios', null, "Intento fallido: $email");
header('Location: login.php?error=credenciales_invalidas');
```

**`auth_register.php`** — tras INSERT exitoso:
```php
audit('register', $last_id, 'usuarios', $last_id, "Registro: $email como $role");
```

**`logout.php`** — antes de `session_unset()`:
```php
audit('logout', $_SESSION['user_id'] ?? null, 'usuarios', $_SESSION['user_id'] ?? null, "Logout");
```

**`proveedor_actions.php`** — en cada acción exitosa:
```php
// create:
audit('create_service', $uid, 'servicios', mysqli_insert_id($conn), "Creó servicio: $titulo");
// update:
audit('update_service', $uid, 'servicios', $id, "Editó servicio #$id: $titulo");
// delete (ahora soft):
audit('delete_service', $uid, 'servicios', $id, "Eliminó (soft) servicio #$id");
// change_password:
audit('password_cambiada', $uid, 'usuarios', $uid, "Cambió su contraseña (proveedor)");
```

**`cliente_actions.php`** — en `change_password` exitoso:
```php
audit('password_cambiada', $uid, 'usuarios', $uid, "Cambió su contraseña (cliente)");
```

**`contratacion_actions.php`** — en cada acción exitosa:
```php
audit("contratacion_{$action}", $mi_id, 'contrataciones', $contratacion_id,
    "Estado → $action en contratación #$contratacion_id");
// Para 'solicitar':
audit('contratacion_creada', $mi_id, 'contrataciones', mysqli_insert_id($conn),
    "Solicitó servicio #$servicio_id");
```

**`verify.php`** — tras INSERT exitoso:
```php
audit('verificacion_solicitada', $uid, 'verificaciones', mysqli_insert_id($conn),
    "Solicitud de verificación, doc: $nombre_archivo");
```

**`admin_actions.php`** — en cada acción:
```php
// verify_approve:
audit('verificacion_aprobada', idUsuario(), 'verificaciones', $id, "Admin aprobó verificación #$id del usuario #$uid");
// verify_reject:
audit('verificacion_rechazada', idUsuario(), 'verificaciones', $id, "Admin rechazó verificación #$id");
// toggle_destacado:
audit('admin_toggle_destacado', idUsuario(), 'servicios', $id, "Toggle destacado en servicio #$id");
// delete_service:
audit('admin_delete_service', idUsuario(), 'servicios', $id, "Admin eliminó (soft) servicio #$id");
// add_categoria:
audit('admin_add_categoria', idUsuario(), 'categorias', mysqli_insert_id($conn), "Agregó categoría: $nombre");
```

**`chat_archivar.php`** — tras UPDATE exitoso:
```php
$tipo_evento = $archivar ? 'chat_archivado' : 'chat_desarchivado';
audit($tipo_evento, $mi_id, 'chat_mensajes', null,
    "Servicio #$servicio_id | Cliente #$cliente_id | Columna: $columna");
```

---

## 📦 Fase 3 — Nuevas Acciones en `admin_actions.php`

### 3.A — Acciones de gestión (POST, modifican estado)

```php
// ── SUSPENDER USUARIO ────────────────────────────────────────
case 'suspender_usuario':
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $id !== idUsuario()) {
        $stmt = mysqli_prepare($conn,
            "UPDATE usuarios SET suspendido_at = NOW() WHERE id = ? AND role != 'admin' AND deleted_at IS NULL"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            audit('admin_suspendio_usuario', idUsuario(), 'usuarios', $id, "Admin suspendió usuario #$id");
        }
    }
    header('Location: admin_panel.php?tab=usuarios&msg=suspendido');
    exit;

// ── REACTIVAR USUARIO ────────────────────────────────────────
case 'reactivar_usuario':
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn,
            "UPDATE usuarios SET suspendido_at = NULL WHERE id = ? AND role != 'admin'"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            audit('admin_reactivo_usuario', idUsuario(), 'usuarios', $id, "Admin reactivó usuario #$id");
        }
    }
    header('Location: admin_panel.php?tab=usuarios&msg=reactivado');
    exit;

// ── VERIFICAR/DESVERIFICAR SERVICIO INDIVIDUAL ───────────────
case 'toggle_verificado':
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn,
            "UPDATE servicios SET verificado = NOT verificado WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        audit('admin_toggle_verificado', idUsuario(), 'servicios', $id, "Toggle verificado en servicio #$id");
    }
    header('Location: admin_panel.php?tab=servicios');
    exit;
```

### 3.B — Endpoints de consulta (GET, solo lectura para la UI de auditoría)

```php
// ── LOG DE ACTIVIDAD (paginado y filtrable) ──────────────────
case 'get_audit_log':
    requireAdmin();
    $tipo  = $_GET['tipo']  ?? '';
    $uid   = (int)($_GET['uid'] ?? 0);
    $desde = $_GET['desde'] ?? '';
    $hasta = $_GET['hasta'] ?? '';
    $page  = max(1, (int)($_GET['p'] ?? 1));
    $limit = 25; $offset = ($page - 1) * $limit;
    // WHERE dinámico + JOIN usuarios + json_encode
    break;

// ── HILOS DE CHAT (Monitor DT-0) ────────────────────────────
case 'get_chat_hilos':
    requireAdmin();
    // GROUP BY servicio_id, cliente_id — datos del hilo + conteo
    break;

// ── MENSAJES DE UN HILO (Monitor DT-0) ──────────────────────
case 'get_chat_mensajes_hilo':
    requireAdmin();
    $sid = (int)$_GET['servicio_id'];
    $cid = (int)$_GET['cliente_id'];
    // Filtro por (servicio_id, cliente_id) — alineado con DT-2
    break;

// ── PERFIL COMPLETO DE USUARIO (DT-3) ───────────────────────
case 'get_user_profile':
    requireAdmin();
    $uid = (int)$_GET['uid'];
    // Datos personales + servicios + contratos + chats + verificación
    break;
```

---

## 📦 Fase 4 — UI en `admin_panel.php`

### 4.A — Mejoras a tabs existentes

**Tab Usuarios** — agregar columnas y acciones:
- Nueva columna `Estado` con badge: `Activo` (verde) / `Suspendido` (naranja) / `Eliminado` (rojo)
- Botón `Ver Perfil` → abre sub-vista del módulo de auditoría con la ficha del usuario
- Botón `Suspender` / `Reactivar` según estado actual
- Modal de advertencia cuando `?warn=contratos_activos` está en URL

**Tab Servicios** — agregar acción:
- Botón `toggle_verificado` por fila (similar al `toggle_destacado` existente)
- Badge de `Verificado` / `No verificado` ya existe, solo agregar el botón de acción

**Tab Verificaciones** — mejorar flujo de aprobación:
- Antes de aprobar: mostrar los servicios que quedarán verificados en cascada
- Mantener el botón existente, solo enriquecer la información visible

**Tab Dashboard** — enriquecer con actividad reciente:
- Tabla de "Últimas 5 altas de usuario" (query sobre `usuarios.created_at`)
- Contador de contrataciones creadas en últimas 24h
- Verificaciones pendientes con días de espera

### 4.B — Nueva pestaña `?tab=auditoria`

```
Sidebar nuevo ítem: 🔍 Auditoría
```

Tres sub-vistas con navegación JS interna (sin recarga):

```
[📋 Actividad]  [💬 Monitor Chats]  [👤 Perfil de Usuario]
```

**Sub-vista Actividad:**
- Tabla paginada de `audit_log` con: Fecha | Usuario | Tipo | Entidad | Descripción | IP
- Filtros: `<select>` tipo, input usuario, datepicker desde/hasta
- Badges por categoría: accesos (azul), creates (verde), deletes (rojo), admin (naranja), fallos (amarillo)

**Sub-vista Monitor de Chats (DT-0):**
- Lista de todos los hilos: Servicio | Cliente | Proveedor | Mensajes | Último mensaje
- Click en hilo → vista de conversación completa en burbujas (solo lectura)
- Banner `"🔒 Modo solo lectura"` siempre visible
- Usa identificador `(servicio_id, cliente_id)` — alineado con DT-2

**Sub-vista Perfil de Usuario (DT-3):**
- Buscador por nombre/email
- Ficha con secciones: datos personales, estado, servicios publicados, historial de contrataciones, chats activos, verificaciones, últimos eventos del log

### 4.C — `auth_login.php`: Bloqueo por intentos fallidos (mejora de seguridad)

Se suma al log de `login_fallido` un contador sobre `audit_log`:

```php
// Contar intentos fallidos en los últimos 15 minutos desde esta IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$stmt_intentos = $conn->prepare(
    "SELECT COUNT(*) as c FROM audit_log
     WHERE tipo = 'login_fallido' AND ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
);
// Si c >= 5 → mostrar error de bloqueo temporal sin procesar el login
```

---

## 📦 Fase 5 — Actualizar Documentación

Al finalizar la implementación, actualizar `analisis_sistema_servijob.md`:

1. Versión → **v2.5**
2. Fecha → actualizar a la fecha de implementación
3. Descripción de versión → "Módulo de Auditoría completo, correcciones de integridad (soft-delete proveedor, chat_archivar), suspensión de usuarios, verificado por servicio individual"
4. Tabla de archivos → agregar `audit_log_migration.sql`
5. Tabla BD → documentar `audit_log` y campo `suspendido_at` en `usuarios`
6. Observaciones → marcar **DT-0** y **DT-3** como `✅ COMPLETADO`
7. Ejecutar `export_html.py` para regenerar el HTML

---

## 🗓️ Orden de ejecución recomendado

```
[Fase 0]
  0.A → proveedor_actions.php (soft-delete)
  0.B → chat_archivar.php (fix identificador hilo)
  0.C/D → admin_actions.php (snapshot + verificación contratos activos)

[Fase 1]
  → Ejecutar audit_log_migration.sql en phpMyAdmin

[Fase 2]
  → db.php (agregar función audit())
  → auth_login.php (login + login_fallido + bloqueo)
  → auth_register.php
  → logout.php
  → proveedor_actions.php (audit calls)
  → cliente_actions.php (audit calls)
  → contratacion_actions.php (audit calls)
  → verify.php (audit calls)
  → admin_actions.php (audit calls en acciones existentes)

[Fase 3]
  → admin_actions.php (suspender/reactivar, toggle_verificado, endpoints GET)

[Fase 4]
  → admin_panel.php (mejoras tabs existentes + nueva pestaña auditoría)

[Fase 5]
  → analisis_sistema_servijob.md + regenerar HTML
```

---

## ⚠️ Restricciones a respetar en toda la implementación

> [!IMPORTANT]
> - `audit()` es **siempre silenciosa** — el `try/catch` garantiza que nunca corta el flujo del usuario.
> - El admin **nunca escribe** en chats desde el monitor — solo lectura absoluta.
> - Los registros de `audit_log` son **inmutables** desde el panel — no hay acción `delete` sobre ellos.
> - La suspensión de usuario **no es lo mismo** que el soft-delete — un usuario suspendido conserva todos sus datos visibles para el admin pero no puede hacer login.
> - Todos los endpoints GET nuevos en `admin_actions.php` llaman `requireAdmin()` como primera instrucción.
> - El fix del soft-delete en `proveedor_actions.php` (Fase 0.A) **no elimina** la lógica de borrado del archivo de imagen del disco — esa parte se conserva igual.
