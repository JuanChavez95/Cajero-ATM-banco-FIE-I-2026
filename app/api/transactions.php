<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$usuario_id = requiereAutenticacion();
$pdo  = getDB();

// Trae los últimos 10 movimientos de TODAS las cuentas del usuario
$stmt = $pdo->prepare(
    "SELECT m.tipo, m.monto, m.saldo_anterior, m.saldo_posterior,
            m.descripcion, m.fecha,
            c.tipo AS cuenta_tipo, c.numero_cuenta
     FROM movimientos m
     INNER JOIN cuentas c ON c.id = m.cuenta_id
     WHERE c.usuario_id = ?
     ORDER BY m.fecha DESC
     LIMIT 10"
);
$stmt->execute([$usuario_id]);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['movimientos' => $movimientos]);