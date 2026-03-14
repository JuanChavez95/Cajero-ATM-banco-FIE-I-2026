<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$data    = json_decode(file_get_contents('php://input'), true);
$tarjeta = trim($data['tarjeta'] ?? '');
$pin     = trim($data['pin'] ?? '');

if (empty($tarjeta) || empty($pin)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tarjeta y PIN requeridos']);
    exit();
}

if (!isset($_SESSION['intentos'])) $_SESSION['intentos'] = 0;

if ($_SESSION['intentos'] >= 3) {
    http_response_code(403);
    echo json_encode(['error' => 'Sesión bloqueada por demasiados intentos. Reinicia el navegador.']);
    exit();
}

$pdo  = getDB();
$stmt = $pdo->prepare(
    "SELECT u.id, u.nombre, u.apellido, u.pin_hash, u.estado,
            c.saldo
     FROM usuarios u
     LEFT JOIN cuentas c ON c.usuario_id = u.id AND c.tipo = 'ahorros'
     WHERE u.numero_tarjeta = ? LIMIT 1"
);
$stmt->execute([$tarjeta]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario || $usuario['estado'] === 'bloqueado') {
    $_SESSION['intentos']++;
    http_response_code(401);
    echo json_encode([
        'error'              => 'Tarjeta no encontrada o cuenta bloqueada',
        'intentos_restantes' => 3 - $_SESSION['intentos']
    ]);
    exit();
}

if (!password_verify($pin, $usuario['pin_hash'])) {
    $_SESSION['intentos']++;

    $pdo->prepare("UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = ?")
        ->execute([$usuario['id']]);

    http_response_code(401);
    echo json_encode([
        'error'              => 'PIN incorrecto',
        'intentos_restantes' => 3 - $_SESSION['intentos']
    ]);
    exit();
}

// Login exitoso
$_SESSION['intentos']   = 0;
$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['nombre']     = $usuario['nombre'];

$pdo->prepare("UPDATE usuarios SET intentos_fallidos = 0 WHERE id = ?")
    ->execute([$usuario['id']]);

echo json_encode([
    'success' => true,
    'usuario' => [
        'id'     => $usuario['id'],
        'nombre' => $usuario['nombre'] . ' ' . $usuario['apellido'],
        'saldo'  => (float)$usuario['saldo']
    ]
]);
?>