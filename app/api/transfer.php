<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$usuario_id = requiereAutenticacion();
$data       = json_decode(file_get_contents('php://input'), true);

$cuenta_origen_id  = (int)($data['cuenta_origen_id']  ?? 0);
$cuenta_destino_id = (int)($data['cuenta_destino_id'] ?? 0);
$monto             = (float)($data['monto']            ?? 0);

if ($monto <= 0 || $cuenta_origen_id <= 0 || $cuenta_destino_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit();
}

if ($cuenta_origen_id === $cuenta_destino_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Las cuentas de origen y destino no pueden ser iguales']);
    exit();
}

$pdo = getDB();
$pdo->beginTransaction();

try {
    // Verificar cuenta origen (debe pertenecer al usuario)
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

    // Verificar cuenta destino (debe pertenecer al mismo usuario)
    $stmt2 = $pdo->prepare(
        "SELECT id, saldo, tipo FROM cuentas
         WHERE id = ? AND usuario_id = ? AND estado = 'activa' FOR UPDATE"
    );
    $stmt2->execute([$cuenta_destino_id, $usuario_id]);
    $destino = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$destino) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['error' => 'Cuenta destino no encontrada']);
        exit();
    }

    $saldo_origen_ant = (float)$origen['saldo'];

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
        ->execute([$saldo_destino_nuevo, $cuenta_destino_id]);

    // Registrar movimiento en cuenta origen
    $pdo->prepare(
        "INSERT INTO movimientos
         (cuenta_id, cuenta_destino_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion)
         VALUES (?, ?, 'transferencia', ?, ?, ?, 'Transferencia entre cuentas')"
    )->execute([$cuenta_origen_id, $cuenta_destino_id, $monto, $saldo_origen_ant, $saldo_origen_nuevo]);

    // Registrar movimiento en cuenta destino (como depósito por transferencia)
    $pdo->prepare(
        "INSERT INTO movimientos
         (cuenta_id, cuenta_destino_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion)
         VALUES (?, ?, 'transferencia', ?, ?, ?, 'Transferencia recibida')"
    )->execute([$cuenta_destino_id, $cuenta_origen_id, $monto, $saldo_destino_ant, $saldo_destino_nuevo]);

    $pdo->commit();

    echo json_encode([
        'success'              => true,
        'mensaje'              => 'Transferencia realizada con éxito',
        'nuevo_saldo_origen'   => $saldo_origen_nuevo,
        'nuevo_saldo_destino'  => $saldo_destino_nuevo,
        'cuenta_origen_tipo'   => $origen['tipo'],
        'cuenta_destino_tipo'  => $destino['tipo']
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error al procesar la transferencia']);
}