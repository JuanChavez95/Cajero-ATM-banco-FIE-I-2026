<?php
// =============================================
// api/registrar_markov.php
// Registra una transición de estado Markov
// en la tabla transiciones_markov.
//
// RECIBE (JSON POST):
//   estado_siguiente: string  (ej. "RETIRO")
//
// USA la sesión activa para leer:
//   $_SESSION['usuario_id']
//   $_SESSION['estado_markov']
//
// ACTUALIZA $_SESSION['estado_markov']
// con el nuevo estado.
// =============================================

require_once '../config/config.php';

header('Content-Type: application/json');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Verificar sesión activa
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sin sesión activa']);
    exit();
}

// Leer body JSON
$data = json_decode(file_get_contents('php://input'), true);
$estado_siguiente = trim($data['estado_siguiente'] ?? '');

// Validar estado recibido contra lista permitida
$estados_validos = [
    'MENU',
    'CONSULTA',
    'RETIRO',
    'DEPOSITO',
    'TRANSFERENCIA',
    'TRANSFERENCIA_TERCEROS',
    'CAMBIO_PIN',
    'FIN_SESION'
];

if (!in_array($estado_siguiente, $estados_validos)) {
    http_response_code(400);
    echo json_encode(['error' => 'Estado no válido: ' . $estado_siguiente]);
    exit();
}

// Estado actual desde la sesión (default: MENU)
$estado_actual = $_SESSION['estado_markov'] ?? 'MENU';

// No registrar si es la misma operación (sin cambio de estado)
if ($estado_actual === $estado_siguiente) {
    echo json_encode(['success' => true, 'info' => 'Sin cambio de estado']);
    exit();
}

try {
    $pdo = getDB();

    // Insertar la transición en la BD
    $stmt = $pdo->prepare(
        "INSERT INTO transiciones_markov
            (usuario_id, operacion_actual, operacion_siguiente, fecha)
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->execute([
        (int) $_SESSION['usuario_id'],
        $estado_actual,
        $estado_siguiente
    ]);

    // Actualizar estado Markov en sesión
    $_SESSION['estado_markov'] = $estado_siguiente;

    echo json_encode([
        'success'    => true,
        'transicion' => $estado_actual . ' → ' . $estado_siguiente
    ]);

} catch (PDOException $e) {
    // Error silencioso: no romper la navegación del ATM
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al registrar transición']);
}
?>