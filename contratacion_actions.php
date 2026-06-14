<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$action = $_POST['action'] ?? '';
$mi_id = (int)$_SESSION['user_id'];
$mi_role = $_SESSION['user_role'] ?? '';

if ($action === 'solicitar') {
    if ($mi_role !== 'cliente') {
        echo json_encode(['ok' => false, 'error' => 'Solo los clientes pueden solicitar servicios.']);
        exit;
    }
    
    $servicio_id = (int)($_POST['servicio_id'] ?? 0);
    
    // Verificar que el servicio existe y obtener el proveedor
    $stmt = mysqli_prepare($conn, "SELECT usuario_id FROM servicios WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $servicio_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $proveedor_id = (int)$row['usuario_id'];
        
        if ($proveedor_id === $mi_id) {
            echo json_encode(['ok' => false, 'error' => 'No puedes contratar tu propio servicio.']);
            exit;
        }
        
        // Verificar que no haya ya una solicitud pendiente o aceptada para este servicio y cliente
        $check = mysqli_prepare($conn, "SELECT id FROM contrataciones WHERE servicio_id = ? AND cliente_id = ? AND estado IN ('pendiente', 'aceptado')");
        mysqli_stmt_bind_param($check, 'ii', $servicio_id, $mi_id);
        mysqli_stmt_execute($check);
        if (mysqli_num_rows(mysqli_stmt_get_result($check)) > 0) {
            echo json_encode(['ok' => false, 'error' => 'Ya tienes una contratación en proceso para este servicio.']);
            exit;
        }
        
        $insert = mysqli_prepare($conn, "INSERT INTO contrataciones (servicio_id, cliente_id, proveedor_id) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($insert, 'iii', $servicio_id, $mi_id, $proveedor_id);
        if (mysqli_stmt_execute($insert)) {
            $contratacion_id = mysqli_insert_id($conn);
            audit('contratacion_creada', $mi_id, 'contrataciones', $contratacion_id, "Solicitó servicio #$servicio_id al proveedor #$proveedor_id");
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Error al crear la solicitud.']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Servicio no encontrado.']);
    }
    exit;
}

if ($action === 'aceptar') {
    if ($mi_role !== 'proveedor') {
        echo json_encode(['ok' => false, 'error' => 'Solo los proveedores pueden aceptar solicitudes.']);
        exit;
    }
    
    $contratacion_id = (int)($_POST['contratacion_id'] ?? 0);
    
    $upd = mysqli_prepare($conn, "UPDATE contrataciones SET estado = 'aceptado' WHERE id = ? AND proveedor_id = ? AND estado = 'pendiente'");
    mysqli_stmt_bind_param($upd, 'ii', $contratacion_id, $mi_id);
    if (mysqli_stmt_execute($upd) && mysqli_stmt_affected_rows($upd) > 0) {
        audit('contratacion_aceptada', $mi_id, 'contrataciones', $contratacion_id, "Aceptó la contratación #$contratacion_id");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No se pudo aceptar la solicitud.']);
    }
    exit;
}

if ($action === 'rechazar') {
    if ($mi_role !== 'proveedor') {
        echo json_encode(['ok' => false, 'error' => 'Solo los proveedores pueden rechazar solicitudes.']);
        exit;
    }
    
    $contratacion_id = (int)($_POST['contratacion_id'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');
    
    if (empty($motivo)) {
        echo json_encode(['ok' => false, 'error' => 'Debes proporcionar un motivo de rechazo.']);
        exit;
    }
    
    $upd = mysqli_prepare($conn, "UPDATE contrataciones SET estado = 'rechazado', motivo = ? WHERE id = ? AND proveedor_id = ? AND estado = 'pendiente'");
    mysqli_stmt_bind_param($upd, 'sii', $motivo, $contratacion_id, $mi_id);
    if (mysqli_stmt_execute($upd) && mysqli_stmt_affected_rows($upd) > 0) {
        audit('contratacion_rechazada', $mi_id, 'contrataciones', $contratacion_id, "Rechazó la contratación #$contratacion_id. Motivo: $motivo");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No se pudo rechazar la solicitud.']);
    }
    exit;
}

if ($action === 'cancelar') {
    if ($mi_role !== 'cliente') {
        echo json_encode(['ok' => false, 'error' => 'Solo los clientes pueden cancelar contrataciones.']);
        exit;
    }
    
    $contratacion_id = (int)($_POST['contratacion_id'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');
    
    if (empty($motivo)) {
        echo json_encode(['ok' => false, 'error' => 'Debes proporcionar un motivo de cancelación.']);
        exit;
    }
    
    $upd = mysqli_prepare($conn, "UPDATE contrataciones SET estado = 'cancelado', motivo = ? WHERE id = ? AND cliente_id = ? AND estado IN ('pendiente', 'aceptado')");
    mysqli_stmt_bind_param($upd, 'sii', $motivo, $contratacion_id, $mi_id);
    if (mysqli_stmt_execute($upd) && mysqli_stmt_affected_rows($upd) > 0) {
        audit('contratacion_cancelada', $mi_id, 'contrataciones', $contratacion_id, "Canceló la contratación #$contratacion_id. Motivo: $motivo");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No se pudo cancelar la contratación.']);
    }
    exit;
}

if ($action === 'completar') {
    if ($mi_role !== 'proveedor') {
        echo json_encode(['ok' => false, 'error' => 'Solo los proveedores pueden marcar como completado.']);
        exit;
    }
    
    $contratacion_id = (int)($_POST['contratacion_id'] ?? 0);
    
    $upd = mysqli_prepare($conn, "UPDATE contrataciones SET estado = 'completado' WHERE id = ? AND proveedor_id = ? AND estado = 'aceptado'");
    mysqli_stmt_bind_param($upd, 'ii', $contratacion_id, $mi_id);
    if (mysqli_stmt_execute($upd) && mysqli_stmt_affected_rows($upd) > 0) {
        audit('contratacion_completada', $mi_id, 'contrataciones', $contratacion_id, "Marcó como completada la contratación #$contratacion_id");
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No se pudo completar la contratación.']);
    }
    exit;
}

if ($action === 'valorar') {
    if ($mi_role !== 'cliente') {
        echo json_encode(['ok' => false, 'error' => 'Solo los clientes pueden valorar.']);
        exit;
    }
    
    $contratacion_id = (int)($_POST['contratacion_id'] ?? 0);
    $puntuacion = (int)($_POST['puntuacion'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');
    
    if ($puntuacion < 1 || $puntuacion > 5) {
        echo json_encode(['ok' => false, 'error' => 'La puntuación debe ser entre 1 y 5.']);
        exit;
    }
    
    // Verificar que la contratación sea del cliente y esté completada
    $stmt = mysqli_prepare($conn, "SELECT proveedor_id FROM contrataciones WHERE id = ? AND cliente_id = ? AND estado = 'completado'");
    mysqli_stmt_bind_param($stmt, 'ii', $contratacion_id, $mi_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $proveedor_id = (int)$row['proveedor_id'];
        
        // Verificar que no se haya valorado ya
        $check = mysqli_prepare($conn, "SELECT id FROM valoraciones WHERE contratacion_id = ?");
        mysqli_stmt_bind_param($check, 'i', $contratacion_id);
        mysqli_stmt_execute($check);
        if (mysqli_num_rows(mysqli_stmt_get_result($check)) > 0) {
            echo json_encode(['ok' => false, 'error' => 'Ya has valorado este servicio.']);
            exit;
        }
        
        $insert = mysqli_prepare($conn, "INSERT INTO valoraciones (contratacion_id, cliente_id, proveedor_id, puntuacion, comentario) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($insert, 'iiiis', $contratacion_id, $mi_id, $proveedor_id, $puntuacion, $comentario);
        if (mysqli_stmt_execute($insert)) {
            $val_id = mysqli_insert_id($conn);
            audit('valoracion_enviada', $mi_id, 'valoraciones', $val_id, "Valoró contratación #$contratacion_id con $puntuacion estrellas");
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar la valoración.']);
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Contratación no válida o no completada.']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
exit;
