<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// ANTIGUO $usuario_id = requiereAutenticacion();
// ANTIGUO $data        = json_decode(file_get_contents('php://input'), true);

$data        = json_decode(file_get_contents('php://input'), true);
$usuario_id  = requiereAutenticacion() ?? (int)($data['usuario_id'] ?? 0);

$pin_actual  = trim($data['pin_actual']  ?? '');
$pin_nuevo   = trim($data['pin_nuevo']   ?? '');
$pin_confirm = trim($data['pin_confirm'] ?? '');

if (empty($pin_actual) || empty($pin_nuevo) || empty($pin_confirm)) {
    http_response_code(400);
    echo json_encode(['error' => 'Todos los campos son requeridos']);
    exit();
}

if (strlen($pin_nuevo) !== 4 || !ctype_digit($pin_nuevo)) {
    http_response_code(400);
    echo json_encode(['error' => 'El PIN debe tener exactamente 4 dígitos']);
    exit();
}

if ($pin_nuevo !== $pin_confirm) {
    http_response_code(400);
    echo json_encode(['error' => 'Los PINs nuevos no coinciden']);
    exit();
}

if ($pin_actual === $pin_nuevo) {
    http_response_code(400);
    echo json_encode(['error' => 'El PIN nuevo debe ser diferente al actual']);
    exit();
}

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT pin_hash FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    http_response_code(404);
    echo json_encode(['error' => 'Usuario no encontrado']);
    exit();
}

// Verificar PIN — soporta tanto bcrypt como texto plano
$pin_valido = password_verify($pin_actual, $usuario['pin_hash'])
           || $pin_actual === $usuario['pin_hash'];

if (!$pin_valido) {
    http_response_code(401);
    echo json_encode(['error' => 'El PIN actual es incorrecto']);
    exit();
}

// Guardar nuevo PIN siempre como bcrypt
$nuevo_hash = password_hash($pin_nuevo, PASSWORD_BCRYPT);
$pdo->prepare("UPDATE usuarios SET pin_hash = ? WHERE id = ?")
    ->execute([$nuevo_hash, $usuario_id]);

echo json_encode([
    'success' => true,
    'mensaje' => 'PIN actualizado correctamente'
]);