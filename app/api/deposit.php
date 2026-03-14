<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$usuario_id = requiereAutenticacion();
$data       = json_decode(file_get_contents('php://input'), true);
$monto      = (float)($data['monto']    ?? 0);
$cuenta_id  = (int)($data['cuenta_id']  ?? 0);

if ($monto <= 0 || $cuenta_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit();
}

$pdo = getDB();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        "SELECT id, saldo FROM cuentas
         WHERE id = ? AND usuario_id = ? AND estado = 'activa' FOR UPDATE"
    );
    $stmt->execute([$cuenta_id, $usuario_id]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cuenta) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['error' => 'Cuenta no encontrada']);
        exit();
    }

    $saldo_anterior = (float)$cuenta['saldo'];
    $saldo_nuevo    = $saldo_anterior + $monto;

    $pdo->prepare("UPDATE cuentas SET saldo = ? WHERE id = ?")
        ->execute([$saldo_nuevo, $cuenta_id]);

    $pdo->prepare(
        "INSERT INTO movimientos
         (cuenta_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion)
         VALUES (?, 'deposito', ?, ?, ?, 'Depósito en cajero')"
    )->execute([$cuenta_id, $monto, $saldo_anterior, $saldo_nuevo]);

    $pdo->commit();

    echo json_encode([
        'success'     => true,
        'mensaje'     => 'Depósito realizado con éxito',
        'nuevo_saldo' => $saldo_nuevo
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error al procesar el depósito']);
}