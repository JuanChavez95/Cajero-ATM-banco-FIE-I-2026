<?php
// logout.php — MODIFICADO para módulo Markov
// CAMBIO: Antes de destruir la sesión, se registra la transición → FIN_SESION
// INTACTO: cierre de sesión ATM, session_destroy(), respuesta JSON.

require_once '../config/config.php';

// ── [MARKOV] Registrar transición → FIN_SESION ───────────────
// Solo si hay sesión activa y estado Markov inicializado.
if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['estado_markov'])) {
    try {
        $pdo = getDB();

        $estado_actual = $_SESSION['estado_markov'];

        // Solo registrar si el estado actual no es ya FIN_SESION
        if ($estado_actual !== 'FIN_SESION') {
            $pdo->prepare(
                "INSERT INTO transiciones_markov
                    (usuario_id, operacion_actual, operacion_siguiente, fecha)
                 VALUES (?, ?, 'FIN_SESION', NOW())"
            )->execute([
                (int) $_SESSION['usuario_id'],
                $estado_actual
            ]);
        }
    } catch (PDOException $e) {
        // Error silencioso: no interrumpir el logout por Markov
    }
}

// Limpiar estado Markov de la sesión
unset($_SESSION['estado_markov']);
// ─────────────────────────────────────────────────────────────

// ── Cerrar sesión ATM si existe (original intacto) ────────────
if (!empty($_SESSION['sesion_atm_id'])) {
    $pdo = getDB();
    $pdo->prepare(
        "UPDATE sesiones_atm
         SET hora_fin          = NOW(),
             duracion_minutos  = TIMESTAMPDIFF(SECOND, hora_inicio, NOW()) / 60.0,
             estado            = 'finalizada'
         WHERE id = ? AND estado = 'activa'"
    )->execute([$_SESSION['sesion_atm_id']]);
}

session_destroy();
echo json_encode(['success' => true, 'mensaje' => 'Sesión cerrada']);
?>