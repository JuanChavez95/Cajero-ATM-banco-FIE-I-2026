<?php
session_start();

// ── Configuración BD ──────────────────────────────────────────
$ADMIN_PASS = 'admin123';
$host = 'localhost'; $db = 'atm_db'; $user = 'root'; $pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE        => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) { die("Error BD: " . $e->getMessage()); }

// ── Login Admin ───────────────────────────────────────────────
if (isset($_POST['login'])) {
    if ($_POST['admin_password'] === $ADMIN_PASS) $_SESSION['admin_auth'] = true;
    else $login_error = "Contraseña incorrecta";
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: cola_stats.php"); exit; }

if (!isset($_SESSION['admin_auth'])): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cola Stats — ATM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #080c14;
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh;
            font-family: 'JetBrains Mono', monospace;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: fixed; inset: 0;
            background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(250,170,0,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .login-card {
            background: #0f1624;
            border: 1px solid rgba(250,170,0,0.2);
            border-radius: 16px;
            padding: 48px 40px;
            width: 340px;
            text-align: center;
            box-shadow: 0 0 60px rgba(250,170,0,0.06), 0 30px 60px rgba(0,0,0,0.6);
            animation: fadeIn .5s ease;
        }
        @keyframes fadeIn { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:none; } }
        .login-logo {
            font-family: 'Syne', sans-serif;
            font-size: 11px; letter-spacing: 4px; text-transform: uppercase;
            color: #faa500; margin-bottom: 6px;
        }
        .login-title {
            font-family: 'Syne', sans-serif;
            font-size: 24px; font-weight: 800;
            color: #f0f4f8; margin-bottom: 32px;
        }
        .login-title span { color: #faa500; }
        input[type="password"] {
            width: 100%; padding: 14px 16px;
            background: #080c14; border: 1px solid #1e2d45;
            border-radius: 8px; color: #f0f4f8;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px; margin-bottom: 14px;
            transition: border-color .2s;
        }
        input[type="password"]:focus { outline: none; border-color: #faa500; }
        .btn-login {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #faa500, #e07b00);
            border: none; border-radius: 8px;
            font-family: 'Syne', sans-serif; font-weight: 700;
            font-size: 13px; letter-spacing: 2px;
            color: #080c14; cursor: pointer;
            transition: transform .15s, box-shadow .15s;
        }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(250,170,0,0.3); }
        .err { color: #f87171; font-size: 12px; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">ATM System</div>
        <div class="login-title">Cola <span>M/M/1</span></div>
        <form method="POST">
            <input type="password" name="admin_password" placeholder="PIN Maestro" autofocus required>
            <button type="submit" name="login" class="btn-login">ACCEDER</button>
        </form>
        <?php if (isset($login_error)) echo "<p class='err'>✖ $login_error</p>"; ?>
    </div>
</body>
</html>
<?php exit; endif;

// ── Cálculos de Teoría de Colas M/M/1 ────────────────────────

// λ — llegadas por hora en la última hora
$stmt_lambda = $pdo->query(
    "SELECT COUNT(*) FROM sesiones_atm
     WHERE hora_inicio >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$lambda = (float) $stmt_lambda->fetchColumn(); // usuarios/hora

// μ — tasa de servicio (sesiones finalizadas, promedio en minutos → convertir a por hora)
$stmt_mu = $pdo->query(
    "SELECT AVG(duracion_minutos) FROM sesiones_atm
     WHERE estado = 'finalizada' AND duracion_minutos IS NOT NULL AND duracion_minutos > 0"
);
$avg_dur_min = (float) $stmt_mu->fetchColumn(); // minutos
$mu = ($avg_dur_min > 0) ? (60.0 / $avg_dur_min) : 0; // sesiones/hora

// Métricas de resumen
$atendidos_hoy = (int) $pdo->query(
    "SELECT COUNT(*) FROM sesiones_atm WHERE DATE(hora_inicio) = CURDATE()"
)->fetchColumn();

$activos_ahora = (int) $pdo->query(
    "SELECT COUNT(*) FROM sesiones_atm WHERE estado = 'activa'"
)->fetchColumn();

$avg_display = $avg_dur_min > 0 ? round($avg_dur_min, 2) : null;

// ── Fórmulas M/M/1 (solo si sistema estable: ρ < 1) ──────────
$sistema_invalido = false;
$rho = $Lq = $Ls = $Wq = $Ws = null;

if ($mu > 0 && $lambda > 0) {
    $rho = $lambda / $mu;
    if ($rho < 1) {
        $diff = $mu - $lambda;
        $Lq   = ($lambda ** 2) / ($mu * $diff);     // clientes en cola
        $Ls   = $lambda / $diff;                     // clientes en sistema
        $Wq   = $lambda / ($mu * $diff);             // tiempo espera en cola (horas)
        $Ws   = 1.0  / $diff;                        // tiempo en sistema (horas)
    } else {
        $sistema_invalido = true;
    }
} elseif ($lambda == 0 && $mu == 0) {
    // Sin datos aún
}

function fmt($v, $dec = 4) {
    return $v !== null ? number_format($v, $dec) : '—';
}
function fmt2($v) { return fmt($v, 2); }

$rho_pct = ($rho !== null) ? min(100, round($rho * 100, 1)) : null;
$estado_color = '#22c55e';
$estado_texto = 'ESTABLE';
if ($rho_pct !== null) {
    if ($rho_pct >= 90) { $estado_color = '#ef4444'; $estado_texto = 'CRÍTICO'; }
    elseif ($rho_pct >= 70) { $estado_color = '#f59e0b'; $estado_texto = 'MODERADO'; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cola M/M/1 — ATM Stats</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #080c14;
            --surface:  #0f1624;
            --border:   #1a2540;
            --orange:   #faa500;
            --orange2:  #e07b00;
            --green:    #22c55e;
            --yellow:   #f59e0b;
            --red:      #ef4444;
            --blue:     #3b82f6;
            --cyan:     #06b6d4;
            --purple:   #a78bfa;
            --text:     #f0f4f8;
            --muted:    #4a6080;
            --card-h:   #131d2e;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'JetBrains Mono', monospace;
            min-height: 100vh;
            padding: 32px 24px 60px;
        }
        body::before {
            content: '';
            position: fixed; inset: 0; pointer-events: none;
            background:
                radial-gradient(ellipse 70% 40% at 50% 0%, rgba(250,165,0,0.07) 0%, transparent 60%),
                radial-gradient(ellipse 40% 30% at 80% 80%, rgba(59,130,246,0.05) 0%, transparent 60%);
        }

        /* ── Header ── */
        .header {
            max-width: 1100px; margin: 0 auto 36px;
            display: flex; justify-content: space-between; align-items: flex-end;
            border-bottom: 1px solid var(--border); padding-bottom: 20px;
        }
        .header-left { }
        .header-label {
            font-size: 10px; letter-spacing: 4px; color: var(--orange);
            text-transform: uppercase; margin-bottom: 4px;
        }
        .header-title {
            font-family: 'Syne', sans-serif;
            font-size: 30px; font-weight: 800; color: var(--text);
            line-height: 1;
        }
        .header-title span { color: var(--orange); }
        .header-right {
            display: flex; align-items: center; gap: 16px;
        }
        .badge-estado {
            padding: 6px 14px; border-radius: 20px;
            font-size: 11px; font-weight: 700; letter-spacing: 2px;
            border: 1px solid currentColor;
        }
        .btn-logout {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px; color: var(--muted);
            background: none; border: 1px solid var(--border);
            border-radius: 6px; padding: 7px 14px;
            cursor: pointer; text-decoration: none;
            transition: color .2s, border-color .2s;
        }
        .btn-logout:hover { color: var(--text); border-color: var(--muted); }

        .wrap { max-width: 1100px; margin: 0 auto; }

        /* ── Summary row ── */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px; margin-bottom: 20px;
        }
        .sum-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px; padding: 22px 24px;
            position: relative; overflow: hidden;
            animation: fadeUp .4s ease both;
        }
        .sum-card::before {
            content: ''; position: absolute;
            top: 0; left: 0; right: 0; height: 2px;
            background: var(--accent, var(--orange));
        }
        .sum-card:nth-child(1) { --accent: var(--cyan);   animation-delay: .05s; }
        .sum-card:nth-child(2) { --accent: var(--green);  animation-delay: .10s; }
        .sum-card:nth-child(3) { --accent: var(--purple); animation-delay: .15s; }

        .sum-label { font-size: 10px; letter-spacing: 2px; color: var(--muted); text-transform: uppercase; margin-bottom: 10px; }
        .sum-value { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 700; color: var(--accent, var(--orange)); line-height: 1; }
        .sum-sub   { font-size: 11px; color: var(--muted); margin-top: 6px; }

        /* ── Utilización bar ── */
        .util-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px; padding: 24px 28px;
            margin-bottom: 20px;
            animation: fadeUp .4s .2s ease both;
        }
        .util-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .util-title { font-size: 12px; letter-spacing: 2px; color: var(--muted); text-transform: uppercase; }
        .util-pct { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 700; }
        .bar-track {
            height: 12px; background: var(--border); border-radius: 99px; overflow: hidden;
        }
        .bar-fill {
            height: 100%; border-radius: 99px;
            transition: width 1s cubic-bezier(.4,0,.2,1);
            background: linear-gradient(90deg, var(--fill-a), var(--fill-b));
        }
        .bar-ticks { display: flex; justify-content: space-between; margin-top: 8px; }
        .bar-ticks span { font-size: 10px; color: var(--muted); }

        /* ── Formula cards ── */
        .formula-section { margin-bottom: 20px; }
        .section-title {
            font-size: 10px; letter-spacing: 3px; color: var(--muted);
            text-transform: uppercase; margin-bottom: 12px;
            display: flex; align-items: center; gap: 10px;
        }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .formula-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        .formula-grid.two-col { grid-template-columns: repeat(2, 1fr); }

        .f-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px; padding: 20px 18px;
            position: relative; overflow: hidden;
            animation: fadeUp .4s ease both;
        }
        .f-card:hover { border-color: var(--f-color, var(--orange)); background: var(--card-h); }
        .f-card::after {
            content: var(--f-sym);
            position: absolute; bottom: -10px; right: 6px;
            font-family: 'Syne', sans-serif; font-size: 56px; font-weight: 800;
            color: rgba(255,255,255,0.03); pointer-events: none; line-height: 1;
        }
        .f-sym-top {
            font-family: 'Syne', sans-serif;
            font-size: 13px; font-weight: 700;
            color: var(--f-color, var(--orange));
            margin-bottom: 4px;
        }
        .f-name { font-size: 10px; color: var(--muted); letter-spacing: 1px; margin-bottom: 12px; }
        .f-formula { font-size: 10px; color: #2a3d58; margin-bottom: 10px; font-style: italic; }
        .f-value {
            font-family: 'Syne', sans-serif;
            font-size: 22px; font-weight: 700;
            color: var(--text);
        }
        .f-unit { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .f-equiv { font-size: 13px; font-weight: 400; color: var(--muted); margin-left: 6px; }

        /* Color variants */
        .c-orange { --f-color: #faa500; }
        .c-cyan   { --f-color: #06b6d4; }
        .c-green  { --f-color: #22c55e; }
        .c-purple { --f-color: #a78bfa; }
        .c-blue   { --f-color: #3b82f6; }
        .c-yellow { --f-color: #f59e0b; }
        .c-red    { --f-color: #f87171; }

        /* ── Warning ── */
        .warn-box {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 12px; padding: 20px 24px;
            margin-bottom: 20px;
            animation: fadeUp .4s .2s ease both;
        }
        .warn-box h3 { color: #f87171; font-family: 'Syne', sans-serif; margin-bottom: 6px; }
        .warn-box p { font-size: 12px; color: #94a3b8; line-height: 1.7; }

        /* ── No data ── */
        .nodata-box {
            background: rgba(250,165,0,0.06);
            border: 1px dashed rgba(250,165,0,0.25);
            border-radius: 12px; padding: 24px;
            text-align: center; margin-bottom: 20px;
            animation: fadeUp .4s ease both;
        }
        .nodata-box p { color: var(--muted); font-size: 13px; }

        /* ── Footer / Teoría ── */
        .theory-box {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 24px 28px;
            animation: fadeUp .4s .35s ease both;
        }
        .theory-box h4 {
            font-family: 'Syne', sans-serif; font-size: 14px;
            color: var(--orange); margin-bottom: 10px;
        }
        .theory-box p { font-size: 12px; color: #64748b; line-height: 1.8; }
        .theory-box .eq { color: #94a3b8; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: none; }
        }

        @media (max-width: 768px) {
            .summary-grid    { grid-template-columns: 1fr; }
            .formula-grid    { grid-template-columns: repeat(2, 1fr); }
            .formula-grid.two-col { grid-template-columns: 1fr; }
            .header          { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
    </style>
</head>
<body>

<div class="wrap">

    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <div class="header-label">ATM System · Teoría de Colas</div>
            <div class="header-title">Cola <span>M/M/1</span></div>
        </div>
        <div class="header-right">
            <?php if ($rho_pct !== null): ?>
            <div class="badge-estado" style="color:<?= $estado_color ?>; border-color:<?= $estado_color ?>;">
                ● <?= $estado_texto ?>
            </div>
            <?php endif; ?>
            <a href="?logout" class="btn-logout">Cerrar sesión</a>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="summary-grid">
        <div class="sum-card">
            <div class="sum-label">Atendidos hoy</div>
            <div class="sum-value"><?= $atendidos_hoy ?></div>
            <div class="sum-sub">sesiones iniciadas</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Duración promedio</div>
            <div class="sum-value"><?= $avg_display !== null ? $avg_display : '—' ?></div>
            <div class="sum-sub">minutos por sesión</div>
        </div>
        <div class="sum-card">
            <div class="sum-label">Usuarios activos</div>
            <div class="sum-value"><?= $activos_ahora ?></div>
            <div class="sum-sub">sesiones en curso ahora</div>
        </div>
    </div>

    <!-- Barra de utilización -->
    <?php if ($rho !== null): ?>
    <div class="util-card">
        <div class="util-header">
            <div class="util-title">Utilización del sistema (ρ = λ/μ)</div>
            <div class="util-pct" style="color:<?= $estado_color ?>"><?= $rho_pct ?>%</div>
        </div>
        <?php
            if ($rho_pct >= 90) { $fa='#ef4444'; $fb='#dc2626'; }
            elseif ($rho_pct >= 70) { $fa='#f59e0b'; $fb='#d97706'; }
            else { $fa='#22c55e'; $fb='#16a34a'; }
        ?>
        <div class="bar-track">
            <div class="bar-fill" style="width:<?= $rho_pct ?>%; --fill-a:<?= $fa ?>; --fill-b:<?= $fb ?>;"></div>
        </div>
        <div class="bar-ticks">
            <span>0%</span>
            <span style="color:#22c55e">70% estable</span>
            <span style="color:#f59e0b">90% moderado</span>
            <span style="color:#ef4444">100% crítico</span>
        </div>
    </div>

    <?php if ($sistema_invalido): ?>
    <!-- Sistema inestable -->
    <div class="warn-box">
        <h3>⚠ Sistema inestable — ρ ≥ 1</h3>
        <p>
            La tasa de llegadas (λ = <?= fmt2($lambda) ?> usuarios/hora) supera o iguala la tasa de servicio
            (μ = <?= fmt2($mu) ?> sesiones/hora). En este estado la cola crece indefinidamente
            y los indicadores M/M/1 clásicos no son válidos.<br>
            <strong style="color:#fca5a5">Acción sugerida:</strong> aumentar la capacidad del sistema (más cajeros) o reducir el flujo de clientes.
        </p>
    </div>
    <?php else: ?>

    <!-- Parámetros base -->
    <div class="formula-section">
        <div class="section-title">Parámetros de entrada</div>
        <div class="formula-grid">
            <div class="f-card c-orange" style="--f-sym:'λ'; animation-delay:.05s">
                <div class="f-sym-top">λ</div>
                <div class="f-name">Tasa de llegadas</div>
                <div class="f-formula">usuarios en última hora</div>
                <div class="f-value"><?= fmt2($lambda) ?></div>
                <div class="f-unit">usuarios / hora</div>
            </div>
            <div class="f-card c-cyan" style="--f-sym:'μ'; animation-delay:.10s">
                <div class="f-sym-top">μ</div>
                <div class="f-name">Tasa de servicio</div>
                <div class="f-formula">60 / duración promedio</div>
                <div class="f-value"><?= fmt2($mu) ?></div>
                <div class="f-unit">sesiones / hora</div>
            </div>
            <div class="f-card c-purple" style="--f-sym:'ρ'; animation-delay:.15s">
                <div class="f-sym-top">ρ</div>
                <div class="f-name">Factor de utilización</div>
                <div class="f-formula">λ / μ</div>
                <div class="f-value"><?= fmt($rho) ?></div>
                <div class="f-unit" style="color:<?= $estado_color ?>"><?= $rho_pct ?>% — <?= $estado_texto ?></div>
            </div>
            <div class="f-card c-blue" style="--f-sym:'1/μ'; animation-delay:.20s">
                <div class="f-sym-top">1/μ</div>
                <div class="f-name">Tiempo de servicio</div>
                <div class="f-formula">1 / μ × 60</div>
                <div class="f-value"><?= $avg_display !== null ? $avg_display : '—' ?></div>
                <div class="f-unit">minutos / sesión</div>
            </div>
        </div>
    </div>

    <!-- Indicadores en cola -->
    <div class="formula-section">
        <div class="section-title">Clientes en el sistema</div>
        <div class="formula-grid two-col">
            <div class="f-card c-yellow" style="--f-sym:'Lq'; animation-delay:.25s">
                <div class="f-sym-top">Lq</div>
                <div class="f-name">Clientes promedio en cola</div>
                <div class="f-formula">λ² / (μ × (μ − λ))</div>
                <div class="f-value"><?= fmt($Lq) ?></div>
                <div class="f-unit">clientes esperando</div>
            </div>
            <div class="f-card c-green" style="--f-sym:'Ls'; animation-delay:.30s">
                <div class="f-sym-top">Ls</div>
                <div class="f-name">Clientes promedio en sistema</div>
                <div class="f-formula">λ / (μ − λ)</div>
                <div class="f-value"><?= fmt($Ls) ?></div>
                <div class="f-unit">clientes (cola + servicio)</div>
            </div>
        </div>
    </div>

    <!-- Tiempos de espera -->
    <div class="formula-section">
        <div class="section-title">Tiempos de espera</div>
        <div class="formula-grid two-col">
            <div class="f-card c-red" style="--f-sym:'Wq'; animation-delay:.35s">
                <div class="f-sym-top">Wq</div>
                <div class="f-name">Tiempo promedio en cola</div>
                <div class="f-formula">λ / (μ × (μ − λ))</div>
                <div class="f-value"><?= fmt(($Wq !== null ? $Wq * 60 : null)) ?> <span class="f-equiv">/ <?= $Wq !== null ? number_format($Wq * 3600, 2) : '—' ?> seg</span></div>
                <div class="f-unit">minutos esperando en cola</div>
            </div>
            <div class="f-card c-cyan" style="--f-sym:'Ws'; animation-delay:.40s">
                <div class="f-sym-top">Ws</div>
                <div class="f-name">Tiempo promedio en sistema</div>
                <div class="f-formula">1 / (μ − λ)</div>
                <div class="f-value"><?= fmt(($Ws !== null ? $Ws * 60 : null)) ?> <span class="f-equiv">/ <?= $Ws !== null ? number_format($Ws * 3600, 2) : '—' ?> seg</span></div>
                <div class="f-unit">minutos en sistema (espera + servicio)</div>
            </div>
        </div>
    </div>

    <?php endif; // sistema valido ?>
    <?php else: // sin datos suficientes ?>

    <div class="nodata-box">
        <p>⚠ Sin suficientes datos para calcular el modelo M/M/1.<br>
        Se necesita al menos una sesión finalizada con duración registrada y una llegada en la última hora.</p>
    </div>

    <?php endif; ?>

    <!-- Teoría -->
    <div class="theory-box">
        <h4>Modelo M/M/1 — Teoría de Colas</h4>
        <p>
            Este panel aplica el modelo <strong style="color:#94a3b8">M/M/1</strong> de teoría de colas sobre sesiones reales del cajero ATM.
            Las <span class="eq">llegadas siguen una distribución de Poisson</span> con tasa λ (usuarios por hora en la última hora),
            y los <span class="eq">tiempos de servicio siguen una distribución exponencial</span> con tasa μ (calculada como 60 / duración promedio de sesión).
            El sistema es un <span class="eq">único servidor (un cajero)</span>.<br><br>
            El modelo es válido únicamente cuando <strong style="color:#faa500">ρ = λ/μ &lt; 1</strong>.
            Si ρ ≥ 1 el sistema es inestable: la cola crece sin límite y las fórmulas clásicas no aplican.
            <br><br>
            <span class="eq">Lq = λ² / (μ(μ−λ)) · Ls = λ/(μ−λ) · Wq = λ/(μ(μ−λ)) · Ws = 1/(μ−λ)</span>
        </p>
    </div>

</div><!-- /wrap -->
</body>
</html>