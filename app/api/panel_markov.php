<?php
// =============================================
// panel_markov.php
// Panel Académico — Cadenas de Markov ATM
//
// Misma arquitectura y estilo que crear_usuario.php
// Autenticación admin idéntica.
// Sin frameworks. PHP puro + HTML + CSS + JS simple.
// =============================================

session_start();

// ── CONFIG (igual que crear_usuario.php) ─────────────────────
$ADMIN_PASS = 'admin123';
$host = 'localhost'; $db = 'atm_db'; $user = 'root'; $pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die("Error BD: " . $e->getMessage()); }

// ── LOGIN ADMIN (idéntico a crear_usuario.php) ────────────────
if (isset($_POST['login'])) {
    if ($_POST['admin_password'] === $ADMIN_PASS) $_SESSION['admin_auth'] = true;
    else $error = "Contraseña incorrecta";
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: panel_markov.php");
    exit;
}

// Mostrar pantalla de login si no está autenticado
if (!isset($_SESSION['admin_auth'])): ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Markov Admin — Login</title>
</head>
<body style="background:#0f172a; display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif;">
  <div style="background:#1e293b; padding:40px; border-radius:12px; width:300px; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.5);">
    <h2 style="color:white; margin-bottom:5px;">ATM ADMIN</h2>
    <p style="color:#94a3b8; font-size:12px; margin-bottom:20px;">Módulo Cadenas de Markov</p>
    <form method="POST">
      <input type="password" name="admin_password" placeholder="PIN Maestro"
             style="width:100%; padding:10px; margin-bottom:15px; border-radius:5px; border:none; box-sizing:border-box;" required>
      <button type="submit" name="login"
              style="width:100%; padding:10px; background:#ffa500; border:none; font-weight:bold; cursor:pointer; border-radius:5px;">
        ENTRAR
      </button>
    </form>
    <?php if (isset($error)) echo "<p style='color:red; margin-top:10px;'>$error</p>"; ?>
  </div>
</body>
</html>
<?php exit; endif;

// ── LÓGICA: Leer datos Markov desde BD ───────────────────────

// Total de transiciones
$total_transiciones = (int) $pdo->query(
    "SELECT COUNT(*) FROM transiciones_markov"
)->fetchColumn();

// Frecuencias y probabilidades: P(B|A) = conteo(A→B) / total_salidas(A)
$frecuencias_raw = $pdo->query(
    "SELECT
        operacion_actual,
        operacion_siguiente,
        COUNT(*) AS conteo
     FROM transiciones_markov
     GROUP BY operacion_actual, operacion_siguiente
     ORDER BY operacion_actual, conteo DESC"
)->fetchAll();

// Totales por estado origen
$totales_origen = [];
foreach ($frecuencias_raw as $row) {
    $a = $row['operacion_actual'];
    if (!isset($totales_origen[$a])) $totales_origen[$a] = 0;
    $totales_origen[$a] += (int) $row['conteo'];
}

// Armar frecuencias con probabilidades calculadas
$frecuencias = [];
foreach ($frecuencias_raw as $row) {
    $a       = $row['operacion_actual'];
    $b       = $row['operacion_siguiente'];
    $conteo  = (int) $row['conteo'];
    $total_a = $totales_origen[$a] ?? 1;
    $prob    = round($conteo / $total_a, 4);
    $frecuencias[] = [
        'operacion_actual'    => $a,
        'operacion_siguiente' => $b,
        'conteo'              => $conteo,
        'total_desde_a'       => $total_a,
        'probabilidad'        => $prob,
        'porcentaje'          => round($prob * 100, 2)
    ];
}

// Ranking de estados más usados
$ranking_estados = $pdo->query(
    "SELECT operacion_actual AS estado, COUNT(*) AS apariciones
     FROM transiciones_markov
     GROUP BY operacion_actual
     ORDER BY apariciones DESC"
)->fetchAll();

// Top 5 transiciones más frecuentes
$top_transiciones = $pdo->query(
    "SELECT operacion_actual, operacion_siguiente, COUNT(*) AS conteo
     FROM transiciones_markov
     GROUP BY operacion_actual, operacion_siguiente
     ORDER BY conteo DESC LIMIT 5"
)->fetchAll();

// Estado más común antes de FIN_SESION
$antes_fin = $pdo->query(
    "SELECT operacion_actual, COUNT(*) AS conteo
     FROM transiciones_markov
     WHERE operacion_siguiente = 'FIN_SESION'
     GROUP BY operacion_actual
     ORDER BY conteo DESC LIMIT 1"
)->fetch();

$total_fin = (int) $pdo->query(
    "SELECT COUNT(*) FROM transiciones_markov WHERE operacion_siguiente = 'FIN_SESION'"
)->fetchColumn();

// Últimas 20 transiciones registradas
$ultimas = $pdo->query(
    "SELECT t.id, t.operacion_actual, t.operacion_siguiente, t.fecha,
            u.nombre, u.apellido
     FROM transiciones_markov t
     LEFT JOIN usuarios u ON u.id = t.usuario_id
     ORDER BY t.fecha DESC LIMIT 20"
)->fetchAll();

// Total de usuarios distintos con transiciones
$usuarios_con_datos = (int) $pdo->query(
    "SELECT COUNT(DISTINCT usuario_id) FROM transiciones_markov"
)->fetchColumn();

// Construir lista de todos los estados que aparecen (para la matriz)
$todos_estados = [];
foreach ($frecuencias_raw as $row) {
    $todos_estados[$row['operacion_actual']]    = true;
    $todos_estados[$row['operacion_siguiente']] = true;
}
$todos_estados = array_keys($todos_estados);
sort($todos_estados);

// Construir la matriz como array PHP [A][B] = probabilidad
$matriz = [];
foreach ($frecuencias as $f) {
    $matriz[$f['operacion_actual']][$f['operacion_siguiente']] = $f['probabilidad'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ATM Markov — Panel Académico</title>
  <style>
    /* ── Variables (idéntico a crear_usuario.php) ── */
    :root {
      --bg:     #0f172a;
      --card:   #1e293b;
      --orange: #ffa500;
      --green:  #22c55e;
      --blue:   #3b82f6;
      --purple: #a855f7;
      --red:    #ef4444;
      --text:   #f8fafc;
      --muted:  #94a3b8;
      --border: #334155;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Segoe UI', sans-serif;
      padding: 20px;
    }

    /* ── Layout principal ── */
    .container { max-width: 1100px; margin: auto; }

    /* ── Header ── */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    .header h1 { font-size: 24px; }
    .header a  { color: var(--muted); text-decoration: none; font-size: 13px; }
    .header a:hover { color: var(--text); }

    /* ── Tarjetas de estadísticas ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }
    .stat-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 18px 20px;
    }
    .stat-card .stat-label {
      font-size: 11px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
    }
    .stat-card .stat-value {
      font-size: 28px;
      font-weight: bold;
    }
    .stat-card .stat-sub {
      font-size: 11px;
      color: var(--muted);
      margin-top: 4px;
    }

    /* ── Acordeón (igual que crear_usuario.php) ── */
    .acc-item {
      background: var(--card);
      border-radius: 8px;
      margin-bottom: 12px;
      border: 1px solid var(--border);
      overflow: hidden;
    }
    .acc-header {
      padding: 15px 20px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-weight: bold;
      font-size: 13px;
    }
    .acc-header:hover { background: #2d3a4f; }
    .acc-content {
      display: none;
      padding: 20px;
      border-top: 1px solid var(--border);
      background: #161e2d;
    }

    /* ── Tabs (idéntico a crear_usuario.php) ── */
    .tabs { display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap; }
    .tab-btn {
      padding: 7px 14px;
      border: none;
      border-radius: 4px 4px 0 0;
      cursor: pointer;
      font-size: 11px;
      font-weight: bold;
      background: #334155;
      color: var(--muted);
    }
    .tab-btn.active { background: var(--orange); color: #000; }
    .tab-panel      { display: none; }
    .tab-panel.active { display: block; }

    /* ── Tablas ── */
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th {
      text-align: left;
      color: var(--orange);
      padding: 8px 10px;
      border-bottom: 1px solid var(--border);
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }
    td { border-bottom: 1px solid var(--border); padding: 8px 10px; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.03); }

    /* ── Barra de probabilidad ── */
    .prob-bar-wrap {
      background: #0f172a;
      border-radius: 4px;
      height: 8px;
      width: 120px;
      display: inline-block;
      vertical-align: middle;
      overflow: hidden;
    }
    .prob-bar-fill {
      height: 100%;
      border-radius: 4px;
      background: var(--orange);
      transition: width 0.4s ease;
    }

    /* ── Badges de estado ── */
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 10px;
      font-weight: bold;
      letter-spacing: 0.3px;
    }
    .badge-consulta    { background: #1e3a5f; color: #60a5fa; }
    .badge-retiro      { background: #3b1d1d; color: #f87171; }
    .badge-deposito    { background: #14532d; color: #4ade80; }
    .badge-transfer    { background: #3b2a0e; color: #fbbf24; }
    .badge-cambio      { background: #2d1b4e; color: #c084fc; }
    .badge-fin         { background: #1f2937; color: #9ca3af; }
    .badge-menu        { background: #164e63; color: #67e8f9; }

    /* ── Matriz de transición ── */
    .matrix-wrap { overflow-x: auto; }
    .matrix-table { font-size: 11px; min-width: 500px; }
    .matrix-table th { text-align: center; }
    .matrix-table td { text-align: center; }
    .matrix-cell-high   { color: #22c55e; font-weight: bold; }
    .matrix-cell-mid    { color: #fbbf24; }
    .matrix-cell-low    { color: #64748b; }
    .matrix-cell-zero   { color: #1e293b; }
    .matrix-origin      { color: var(--orange); font-weight: bold; text-align: left !important; }

    /* ── Grid de 2 columnas ── */
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 650px) { .grid-2 { grid-template-columns: 1fr; } }

    /* ── Scroll en contenedores de tabla ── */
    .scroll-box { max-height: 320px; overflow-y: auto; }

    /* ── Top transiciones ── */
    .top-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid var(--border);
    }
    .top-item:last-child { border-bottom: none; }
    .top-arrow { color: var(--orange); margin: 0 8px; }

    /* ── Aviso sin datos ── */
    .no-data {
      text-align: center;
      color: var(--muted);
      padding: 30px;
      font-size: 13px;
    }

    /* ── Botón limpiar datos (admin) ── */
    .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 11px; }
    .btn-danger { background: var(--red); color: white; }
  </style>
</head>
<body>

<div class="container">

  <!-- ── HEADER ── -->
  <div class="header">
    <h1>ATM <span style="color:var(--orange)">MARKOV</span>
      <span style="font-size:13px; color:var(--muted); font-weight:normal; margin-left:10px;">
        Panel Académico — Cadenas de Markov
      </span>
    </h1>
    <a href="?logout">Cerrar Sesión</a>
  </div>

  <!-- ── TARJETAS DE RESUMEN ── -->
  <div class="stats-grid">

    <div class="stat-card">
      <div class="stat-label">Total Transiciones</div>
      <div class="stat-value" style="color:var(--orange)"><?= number_format($total_transiciones) ?></div>
      <div class="stat-sub">Registradas en BD</div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Estados Únicos</div>
      <div class="stat-value" style="color:var(--blue)"><?= count($todos_estados) ?></div>
      <div class="stat-sub">Con actividad registrada</div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Usuarios Analizados</div>
      <div class="stat-value" style="color:var(--green)"><?= $usuarios_con_datos ?></div>
      <div class="stat-sub">Con transiciones</div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Cierres de Sesión</div>
      <div class="stat-value" style="color:var(--muted)"><?= $total_fin ?></div>
      <div class="stat-sub">
        <?php if ($antes_fin): ?>
          Previo más común: <strong style="color:var(--text)"><?= htmlspecialchars($antes_fin['operacion_actual']) ?></strong>
        <?php else: ?>
          Sin datos aún
        <?php endif; ?>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Pares de Estados</div>
      <div class="stat-value" style="color:var(--purple)"><?= count($frecuencias) ?></div>
      <div class="stat-sub">Transiciones distintas</div>
    </div>

  </div>

  <?php if ($total_transiciones === 0): ?>
    <!-- Sin datos -->
    <div class="acc-item">
      <div class="no-data">
        <p style="font-size:32px; margin-bottom:10px;">📊</p>
        <p style="color:var(--text); font-weight:bold; margin-bottom:6px;">Sin transiciones registradas aún</p>
        <p>El módulo Markov comenzará a recopilar datos cuando los usuarios naveguen por el menú ATM.</p>
        <p style="margin-top:8px; font-size:11px;">Las transiciones se registran automáticamente al seleccionar operaciones.</p>
      </div>
    </div>

  <?php else: ?>

  <!-- ══════════════════════════════════════════════════
       SECCIÓN 1 — TABLA DE PROBABILIDADES
  ══════════════════════════════════════════════════ -->
  <div class="acc-item" style="border-color: var(--orange);">
    <div class="acc-header" style="color:var(--orange)" onclick="toggle('probs')">
      📊 TABLA DE PROBABILIDADES — P(B|A)
      <span id="arrow-probs">▼</span>
    </div>
    <div class="acc-content" id="s-probs" style="display:block;">
      <p style="font-size:11px; color:var(--muted); margin-bottom:15px;">
        P(B|A) = Probabilidad de ir al estado B dado que el usuario está en el estado A.
        Calculada como: conteo(A→B) / total_salidas(A)
      </p>
      <div class="scroll-box">
        <table>
          <thead>
            <tr>
              <th>Estado Actual (A)</th>
              <th>Estado Siguiente (B)</th>
              <th>Frecuencia</th>
              <th>Total desde A</th>
              <th>P(B|A)</th>
              <th>Probabilidad</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($frecuencias as $f): ?>
            <tr>
              <td><?= badgeEstado($f['operacion_actual']) ?></td>
              <td><?= badgeEstado($f['operacion_siguiente']) ?></td>
              <td style="font-weight:bold; color:var(--text)"><?= $f['conteo'] ?></td>
              <td style="color:var(--muted)"><?= $f['total_desde_a'] ?></td>
              <td style="font-family:monospace; color:var(--orange)"><?= number_format($f['probabilidad'], 4) ?></td>
              <td>
                <div class="prob-bar-wrap">
                  <div class="prob-bar-fill" style="width:<?= $f['porcentaje'] ?>%"></div>
                </div>
                <span style="margin-left:6px; font-size:11px; color:var(--muted)"><?= $f['porcentaje'] ?>%</span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════
       SECCIÓN 2 — MATRIZ DE TRANSICIÓN
  ══════════════════════════════════════════════════ -->
  <div class="acc-item" style="border-color: var(--blue);">
    <div class="acc-header" style="color:var(--blue)" onclick="toggle('matriz')">
      🔢 MATRIZ DE TRANSICIÓN MARKOV
      <span id="arrow-matriz">▼</span>
    </div>
    <div class="acc-content" id="s-matriz">
      <p style="font-size:11px; color:var(--muted); margin-bottom:15px;">
        Cada celda representa P(columna | fila). La suma de cada fila = 1.0
        <br>
        <span style="color:#22c55e">■</span> ≥ 0.5 alta probabilidad &nbsp;
        <span style="color:#fbbf24">■</span> 0.2–0.49 media &nbsp;
        <span style="color:#64748b">■</span> &lt; 0.2 baja &nbsp;
        <span style="color:#1e293b">■</span> 0 sin transición
      </p>
      <div class="matrix-wrap">
        <table class="matrix-table">
          <thead>
            <tr>
              <th style="text-align:left; color:var(--muted)">Origen ↓ / Destino →</th>
              <?php foreach ($todos_estados as $col): ?>
                <th style="color:var(--blue)"><?= htmlspecialchars($col) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($todos_estados as $fila): ?>
            <tr>
              <td class="matrix-origin"><?= htmlspecialchars($fila) ?></td>
              <?php foreach ($todos_estados as $col):
                $p = $matriz[$fila][$col] ?? 0;
                if ($p === 0) {
                    $cls = 'matrix-cell-zero';
                    $txt = '–';
                } elseif ($p >= 0.5) {
                    $cls = 'matrix-cell-high';
                    $txt = number_format($p, 2);
                } elseif ($p >= 0.2) {
                    $cls = 'matrix-cell-mid';
                    $txt = number_format($p, 2);
                } else {
                    $cls = 'matrix-cell-low';
                    $txt = number_format($p, 2);
                }
              ?>
                <td class="<?= $cls ?>" title="P(<?= $col ?>|<?= $fila ?>) = <?= number_format($p,4) ?>">
                  <?= $txt ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════
       SECCIÓN 3 — ESTADÍSTICAS Y ÚLTIMAS TRANSICIONES
  ══════════════════════════════════════════════════ -->
  <div class="acc-item" style="border-color: var(--green);">
    <div class="acc-header" style="color:var(--green)" onclick="toggle('stats')">
      📈 ESTADÍSTICAS Y REGISTRO RECIENTE
      <span id="arrow-stats">▼</span>
    </div>
    <div class="acc-content" id="s-stats">

      <!-- Tabs dentro de la sección -->
      <div class="tabs">
        <button class="tab-btn active" onclick="showTab('stats', 'ranking', this)">🏆 Estados más usados</button>
        <button class="tab-btn"        onclick="showTab('stats', 'top',     this)">⚡ Top transiciones</button>
        <button class="tab-btn"        onclick="showTab('stats', 'reciente',this)">🕒 Registro reciente</button>
      </div>

      <!-- TAB: Ranking estados -->
      <div class="tab-panel active" id="tab-stats-ranking">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Estado</th>
              <th>Veces como origen</th>
              <th>% del total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ranking_estados as $i => $r): ?>
            <tr>
              <td style="color:var(--muted)"><?= $i + 1 ?></td>
              <td><?= badgeEstado($r['estado']) ?></td>
              <td style="font-weight:bold"><?= $r['apariciones'] ?></td>
              <td style="color:var(--muted)">
                <?= $total_transiciones > 0 ? number_format(($r['apariciones'] / $total_transiciones) * 100, 1) . '%' : '–' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- TAB: Top transiciones -->
      <div class="tab-panel" id="tab-stats-top">
        <p style="font-size:11px; color:var(--muted); margin-bottom:12px;">
          Las 5 transiciones más frecuentes registradas en el sistema.
        </p>
        <?php foreach ($top_transiciones as $i => $tr): ?>
        <div class="top-item">
          <span style="color:var(--muted); width:20px;"><?= $i + 1 ?>.</span>
          <?= badgeEstado($tr['operacion_actual']) ?>
          <span class="top-arrow">→</span>
          <?= badgeEstado($tr['operacion_siguiente']) ?>
          <span style="margin-left:auto; color:var(--orange); font-weight:bold; font-size:13px;">
            <?= $tr['conteo'] ?> <span style="font-size:11px; color:var(--muted); font-weight:normal;">veces</span>
          </span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- TAB: Registro reciente -->
      <div class="tab-panel" id="tab-stats-reciente">
        <div class="scroll-box">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Usuario</th>
                <th>Estado Actual</th>
                <th>Estado Siguiente</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ultimas as $u): ?>
              <tr>
                <td style="color:var(--muted)"><?= $u['id'] ?></td>
                <td>
                  <?php if ($u['nombre']): ?>
                    <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?>
                  <?php else: ?>
                    <span style="color:var(--muted)">ID <?= $u['usuario_id'] ?></span>
                  <?php endif; ?>
                </td>
                <td><?= badgeEstado($u['operacion_actual']) ?></td>
                <td><?= badgeEstado($u['operacion_siguiente']) ?></td>
                <td style="color:var(--muted); font-size:11px; white-space:nowrap;">
                  <?= date('d/m/Y H:i', strtotime($u['fecha'])) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <?php endif; /* fin if total_transiciones > 0 */ ?>

  <!-- ── Info matemática al pie ── -->
  <div style="margin-top:20px; padding:15px; background:var(--card); border-radius:8px; border:1px solid var(--border); font-size:11px; color:var(--muted);">
    <strong style="color:var(--text)">Fórmula utilizada:</strong>
    P(B|A) = frecuencia(A→B) / Σ frecuencia(A→*) &nbsp;·&nbsp;
    Solo frecuencias reales. Sin IA, sin ML, sin librerías externas.
    &nbsp;|&nbsp; <strong style="color:var(--text)">Tabla:</strong> transiciones_markov
    &nbsp;|&nbsp; <a href="crear_usuario.php" style="color:var(--orange); text-decoration:none;">← Volver al Panel Principal</a>
  </div>

</div><!-- /container -->

<script>
// ── Acordeón (igual que crear_usuario.php) ───────────────────
function toggle(id) {
  const el    = document.getElementById('s-' + id);
  const arrow = document.getElementById('arrow-' + id);
  const visible = el.style.display === 'block';
  el.style.display = visible ? 'none' : 'block';
  if (arrow) arrow.textContent = visible ? '▼' : '▲';
}

// ── Tabs genérico (igual que crear_usuario.php) ───────────────
function showTab(section, tabName, btn) {
  // Ocultar todos los tab-panel dentro del mismo acc-content padre del botón
  const parent = btn.closest('.acc-content');
  parent.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  parent.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + section + '-' + tabName).classList.add('active');
  btn.classList.add('active');
}
</script>

</body>
</html>

<?php
// ── Helper: badge visual por estado ──────────────────────────
function badgeEstado($estado) {
    $mapa = [
        'CONSULTA'              => 'badge-consulta',
        'RETIRO'                => 'badge-retiro',
        'DEPOSITO'              => 'badge-deposito',
        'TRANSFERENCIA'         => 'badge-transfer',
        'TRANSFERENCIA_TERCEROS'=> 'badge-transfer',
        'CAMBIO_PIN'            => 'badge-cambio',
        'FIN_SESION'            => 'badge-fin',
        'MENU'                  => 'badge-menu',
    ];
    $clase = $mapa[$estado] ?? 'badge-fin';
    return '<span class="badge ' . $clase . '">' . htmlspecialchars($estado) . '</span>';
}
?>