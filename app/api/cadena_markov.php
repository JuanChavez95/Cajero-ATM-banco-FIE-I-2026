<?php
// =============================================
// api/cadena_markov.php
// Calcula la matriz de transición Markov
// a partir de las frecuencias registradas.
//
// DEVUELVE JSON con:
//   - total_transiciones
//   - matriz: { estado_actual: { estado_siguiente: probabilidad } }
//   - frecuencias: [ { operacion_actual, operacion_siguiente, conteo, probabilidad } ]
//   - ranking_estados: [ { estado, apariciones } ]
//   - top_transiciones: [ { operacion_actual, operacion_siguiente, conteo } ]
//   - cierre_sesion: { estado_previo_mas_comun, total_fin_sesion }
// =============================================

require_once '../config/config.php';

header('Content-Type: application/json');

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Autenticación admin simple (misma lógica que crear_usuario.php)
if (empty($_SESSION['admin_auth'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit();
}

try {
    $pdo = getDB();

    // ── 1. Total de transiciones registradas ──────────────────
    $total = (int) $pdo->query(
        "SELECT COUNT(*) FROM transiciones_markov"
    )->fetchColumn();

    // ── 2. Contar frecuencias de cada par (A → B) ─────────────
    $frecuencias_raw = $pdo->query(
        "SELECT
            operacion_actual,
            operacion_siguiente,
            COUNT(*) AS conteo
         FROM transiciones_markov
         GROUP BY operacion_actual, operacion_siguiente
         ORDER BY operacion_actual, conteo DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    // ── 3. Contar totales por estado origen (para calcular P) ──
    $totales_origen = [];
    foreach ($frecuencias_raw as $row) {
        $a = $row['operacion_actual'];
        if (!isset($totales_origen[$a])) $totales_origen[$a] = 0;
        $totales_origen[$a] += (int) $row['conteo'];
    }

    // ── 4. Construir matriz y array de frecuencias con P ──────
    $matriz      = [];   // matriz[A][B] = probabilidad
    $frecuencias = [];   // array plano para la tabla del panel

    foreach ($frecuencias_raw as $row) {
        $a      = $row['operacion_actual'];
        $b      = $row['operacion_siguiente'];
        $conteo = (int) $row['conteo'];
        $total_a = $totales_origen[$a];

        // P(B|A) = conteo(A→B) / total_salidas(A)
        $prob = ($total_a > 0) ? round($conteo / $total_a, 4) : 0;

        $matriz[$a][$b] = $prob;

        $frecuencias[] = [
            'operacion_actual'    => $a,
            'operacion_siguiente' => $b,
            'conteo'              => $conteo,
            'total_desde_a'       => $total_a,
            'probabilidad'        => $prob,
            'porcentaje'          => round($prob * 100, 2)
        ];
    }

    // ── 5. Ranking de estados más usados (como origen) ────────
    $ranking_estados = $pdo->query(
        "SELECT
            operacion_actual AS estado,
            COUNT(*) AS apariciones
         FROM transiciones_markov
         GROUP BY operacion_actual
         ORDER BY apariciones DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    // ── 6. Top transiciones más frecuentes ───────────────────
    $top_transiciones = $pdo->query(
        "SELECT
            operacion_actual,
            operacion_siguiente,
            COUNT(*) AS conteo
         FROM transiciones_markov
         GROUP BY operacion_actual, operacion_siguiente
         ORDER BY conteo DESC
         LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

    // ── 7. Estado más frecuente antes de FIN_SESION ──────────
    $antes_fin = $pdo->query(
        "SELECT
            operacion_actual,
            COUNT(*) AS conteo
         FROM transiciones_markov
         WHERE operacion_siguiente = 'FIN_SESION'
         GROUP BY operacion_actual
         ORDER BY conteo DESC
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    $total_fin = (int) $pdo->query(
        "SELECT COUNT(*) FROM transiciones_markov
         WHERE operacion_siguiente = 'FIN_SESION'"
    )->fetchColumn();

    $cierre_sesion = [
        'estado_previo_mas_comun' => $antes_fin['operacion_actual'] ?? null,
        'conteo_previo'           => $antes_fin['conteo']           ?? 0,
        'total_fin_sesion'        => $total_fin
    ];

    // ── Respuesta final ───────────────────────────────────────
    echo json_encode([
        'success'          => true,
        'total_transiciones' => $total,
        'matriz'           => $matriz,
        'frecuencias'      => $frecuencias,
        'ranking_estados'  => $ranking_estados,
        'top_transiciones' => $top_transiciones,
        'cierre_sesion'    => $cierre_sesion
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al calcular Markov']);
}
?>