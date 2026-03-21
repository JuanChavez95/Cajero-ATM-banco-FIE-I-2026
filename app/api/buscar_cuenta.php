<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$usuario_id = requiereAutenticacion();
$numero     = trim($_GET['numero'] ?? '');

if (strlen($numero) !== 12 || !ctype_digit($numero)) {
    http_response_code(400);
    echo json_encode(['error' => 'Número de cuenta inválido']);
    exit();
}

$pdo  = getDB();
$stmt = $pdo->prepare(
    "SELECT c.id, c.tipo, c.numero_cuenta,
            u.nombre, u.apellido, u.id AS usuario_id
     FROM cuentas c
     INNER JOIN usuarios u ON u.id = c.usuario_id
     WHERE c.numero_cuenta = ? AND c.estado = 'activa'
     LIMIT 1"
);
$stmt->execute([$numero]);
$cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cuenta) {
    http_response_code(404);
    echo json_encode(['error' => 'Cuenta no encontrada o inactiva']);
    exit();
}

if ($cuenta['usuario_id'] == $usuario_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Esa es su propia cuenta. Use "Transferencia entre mis cuentas"']);
    exit();
}

echo json_encode([
    'nombre' => $cuenta['nombre'] . ' ' . $cuenta['apellido'],
    'tipo'   => $cuenta['tipo'],
    'numero' => $cuenta['numero_cuenta']
]);