<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$usuario_id = requiereAutenticacion();
$pdo  = getDB();
$stmt = $pdo->prepare(
    "SELECT id, numero_cuenta, tipo, saldo
     FROM cuentas
     WHERE usuario_id = ? AND estado = 'activa'
     ORDER BY tipo ASC"
);
$stmt->execute([$usuario_id]);
$cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['cuentas' => $cuentas]);