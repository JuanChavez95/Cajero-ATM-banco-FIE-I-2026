<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$usuario_id = requiereAutenticacion();
$data       = json_decode(file_get_contents('php://input'), true);

$cuenta_origen_id  = (int)($data['cuenta_origen_id']   ?? 0);
$numero_destino    = trim($data['numero_cuenta_destino'] ?? '');
$monto             = (float)($data['monto']              ?? 0);

if ($monto <= 0 || $cuenta_origen_id <= 0 || empty($numero_destino)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit();
}

$pdo = getDB();
$pdo->beginTransaction();

try {
    // Verificar cuenta origen pertenece al usuario
    $stmt = $pdo->prepare(
        "SELECT id, saldo, tipo FROM cuentas
         WHERE id = ? AND usuario_id = ? AND estado = 'activa' FOR UPDATE"
    );
    $stmt->execute([$cuenta_origen_id, $usuario_id]);
    $origen = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$origen) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['error' => 'Cuenta origen no encontrada']);
        exit();
    }

    // Buscar cuenta destino por número (no debe ser del mismo usuario)
    $stmt2 = $pdo->prepare(
        "SELECT c.id, c.saldo, c.tipo, c.usuario_id,
                u.nombre, u.apellido
         FROM cuentas c
         INNER JOIN usuarios u ON u.id = c.usuario_id
         WHERE c.numero_cuenta = ? AND c.estado = 'activa' FOR UPDATE"
    );
    $stmt2->execute([$numero_destino]);
    $destino = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$destino) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Cuenta destino no encontrada o inactiva']);
        exit();
    }

    if ($destino['usuario_id'] == $usuario_id) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Use "Transferencia entre mis cuentas" para transferir a sus propias cuentas']);
        exit();
    }

    $saldo_origen_ant  = (float)$origen['saldo'];

    if ($monto > $saldo_origen_ant) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Saldo insuficiente en la cuenta origen']);
        exit();
    }

    $saldo_origen_nuevo  = $saldo_origen_ant - $monto;
    $saldo_destino_ant   = (float)$destino['saldo'];
    $saldo_destino_nuevo = $saldo_destino_ant + $monto;

    // Actualizar saldos
    $pdo->prepare("UPDATE cuentas SET saldo = ? WHERE id = ?")
        ->execute([$saldo_origen_nuevo, $cuenta_origen_id]);

    $pdo->prepare("UPDATE cuentas SET saldo = ? WHERE id = ?")
        ->execute([$saldo_destino_nuevo, $destino['id']]);

    // Movimiento en cuenta origen
    $pdo->prepare(
        "INSERT INTO movimientos
         (cuenta_id, cuenta_destino_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion)
         VALUES (?, ?, 'transferencia', ?, ?, ?, ?)"
    )->execute([
        $cuenta_origen_id,
        $destino['id'],
        $monto,
        $saldo_origen_ant,
        $saldo_origen_nuevo,
        'Transferencia a ' . $destino['nombre'] . ' ' . $destino['apellido']
    ]);

    // Movimiento en cuenta destino
    $pdo->prepare(
        "INSERT INTO movimientos
         (cuenta_id, cuenta_destino_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion)
         VALUES (?, ?, 'transferencia', ?, ?, ?, ?)"
    )->execute([
        $destino['id'],
        $cuenta_origen_id,
        $monto,
        $saldo_destino_ant,
        $saldo_destino_nuevo,
        'Transferencia recibida de ' . $_SESSION['nombre']
    ]);

    $pdo->commit();

    echo json_encode([
        'success'            => true,
        'mensaje'            => 'Transferencia realizada con éxito',
        'nuevo_saldo_origen' => $saldo_origen_nuevo,
        'destinatario'       => $destino['nombre'] . ' ' . $destino['apellido'],
        'cuenta_origen_tipo' => $origen['tipo']
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error al procesar la transferencia']);
}