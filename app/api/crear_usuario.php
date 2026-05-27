<?php
session_start();
// CONFIGURACIÓN DE BD
$ADMIN_PASS = 'admin123';
$host = 'localhost'; $db = 'atm_db'; $user = 'root'; $pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

// --- LOGIN ADMIN ---
if (isset($_POST['login'])) {
    if ($_POST['admin_password'] === $ADMIN_PASS) $_SESSION['admin_auth'] = true;
    else $error = "Contraseña incorrecta";
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: crear_usuario.php"); exit; }
if (!isset($_SESSION['admin_auth'])): ?>
    <body style="background:#0f172a; display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif;">
        <div style="background:#1e293b; padding:40px; border-radius:12px; width:300px; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.5);">
            <h2 style="color:white;">ATM ADMIN</h2>
            <form method="POST">
                <input type="password" name="admin_password" placeholder="PIN Maestro" style="width:100%; padding:10px; margin-bottom:15px; border-radius:5px; border:none;" required>
                <button type="submit" name="login" style="width:100%; padding:10px; background:#ffa500; border:none; font-weight:bold; cursor:pointer;">ENTRAR</button>
            </form>
            <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        </div>
    </body>
<?php exit; endif;

// --- PROCESOS ---
$msg_ok = ""; $msg_err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. REGISTRAR USUARIO
    if (isset($_POST['action']) && $_POST['action'] == 'create_user') {
        try {
            $pdo->beginTransaction();
            $pin_hash = password_hash($_POST['pin'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, ci, numero_tarjeta, pin_hash, telefono, email, estado) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$_POST['nombre'], $_POST['apellido'], $_POST['ci'], $_POST['tarjeta'], $pin_hash, $_POST['telefono'], $_POST['email'], 'activo']);
            $nuevo_id = (int) $pdo->lastInsertId();
            $seq = $pdo->query("SELECT COUNT(*) FROM cuentas")->fetchColumn() + 1;
            $numero_cuenta = str_pad($nuevo_id, 4, '0', STR_PAD_LEFT) . str_pad($seq, 8, '0', STR_PAD_LEFT);
            $tipo_cuenta = in_array($_POST['tipo_cuenta'] ?? '', ['ahorros','corriente']) ? $_POST['tipo_cuenta'] : 'ahorros';
            $saldo_inicial = max(0, floatval($_POST['saldo_inicial'] ?? 0));
            $pdo->prepare("INSERT INTO cuentas (usuario_id, numero_cuenta, tipo, saldo, estado) VALUES (?,?,?,?,'activa')")
                ->execute([$nuevo_id, $numero_cuenta, $tipo_cuenta, $saldo_inicial]);
            if ($saldo_inicial > 0) {
                $cuenta_id = (int) $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO movimientos (cuenta_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion) VALUES (?,?,?,?,?,?)")
                    ->execute([$cuenta_id, 'deposito', $saldo_inicial, 0.00, $saldo_inicial, 'Depósito inicial al crear cuenta']);
            }
            $pdo->commit();
            $msg_ok = "Usuario registrado correctamente. Cuenta: $numero_cuenta";
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg_err = "Error: El CI o Tarjeta ya existen.";
        }
    }

    // 2. EDITAR DATOS DEL USUARIO
    if (isset($_POST['action']) && $_POST['action'] == 'update_user') {
        try {
            $uid = (int) $_POST['usuario_id'];
            if (!empty($_POST['pin_nuevo'])) {
                $pin_hash = password_hash($_POST['pin_nuevo'], PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE usuarios SET nombre=?, apellido=?, ci=?, numero_tarjeta=?, telefono=?, email=?, pin_hash=? WHERE id=?")
                    ->execute([$_POST['nombre'], $_POST['apellido'], $_POST['ci'], $_POST['tarjeta'], $_POST['telefono'], $_POST['email'], $pin_hash, $uid]);
            } else {
                $pdo->prepare("UPDATE usuarios SET nombre=?, apellido=?, ci=?, numero_tarjeta=?, telefono=?, email=? WHERE id=?")
                    ->execute([$_POST['nombre'], $_POST['apellido'], $_POST['ci'], $_POST['tarjeta'], $_POST['telefono'], $_POST['email'], $uid]);
            }
            $msg_ok = "Datos del usuario actualizados correctamente.";
        } catch (Exception $e) {
            $msg_err = "Error: CI o Tarjeta ya pertenecen a otro usuario.";
        }
    }

    // 3. AGREGAR NUEVA CUENTA AL USUARIO
    if (isset($_POST['action']) && $_POST['action'] == 'add_cuenta') {
        try {
            $uid = (int) $_POST['usuario_id'];
            $tipo_nueva = in_array($_POST['tipo_cuenta'] ?? '', ['ahorros','corriente']) ? $_POST['tipo_cuenta'] : 'ahorros';
            $saldo_inicial = max(0, floatval($_POST['saldo_inicial'] ?? 0));
            $existe = $pdo->prepare("SELECT id FROM cuentas WHERE usuario_id = ? AND tipo = ? LIMIT 1");
            $existe->execute([$uid, $tipo_nueva]);
            if ($existe->fetch()) {
                $msg_err = "El usuario ya tiene una cuenta de tipo $tipo_nueva.";
            } else {
                $pdo->beginTransaction();
                $seq = $pdo->query("SELECT COUNT(*) FROM cuentas")->fetchColumn() + 1;
                $numero_cuenta = str_pad($uid, 4, '0', STR_PAD_LEFT) . str_pad($seq, 8, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO cuentas (usuario_id, numero_cuenta, tipo, saldo, estado) VALUES (?,?,?,?,'activa')")
                    ->execute([$uid, $numero_cuenta, $tipo_nueva, $saldo_inicial]);
                if ($saldo_inicial > 0) {
                    $cuenta_id = (int) $pdo->lastInsertId();
                    $pdo->prepare("INSERT INTO movimientos (cuenta_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion) VALUES (?,?,?,?,?,?)")
                        ->execute([$cuenta_id, 'deposito', $saldo_inicial, 0.00, $saldo_inicial, 'Depósito inicial al crear cuenta']);
                }
                $pdo->commit();
                $msg_ok = "Cuenta $tipo_nueva agregada. Nº: $numero_cuenta";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg_err = "Error al agregar la cuenta.";
        }
    }

    // 4. DEPÓSITO / RETIRO
    if (isset($_POST['action']) && ($_POST['action'] == 'deposito' || $_POST['action'] == 'retiro')) {
        $monto = floatval($_POST['monto']);
        $cuenta_id = $_POST['cuenta_id'];
        $res = $pdo->prepare("SELECT saldo FROM cuentas WHERE id = ?"); $res->execute([$cuenta_id]);
        $saldo_ant = $res->fetchColumn();
        if ($_POST['action'] == 'retiro' && $saldo_ant < $monto) {
            $msg_err = "Saldo insuficiente.";
        } else {
            $nuevo_saldo = ($_POST['action'] == 'deposito') ? $saldo_ant + $monto : $saldo_ant - $monto;
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE cuentas SET saldo = ? WHERE id = ?")->execute([$nuevo_saldo, $cuenta_id]);
            $pdo->prepare("INSERT INTO movimientos (cuenta_id, tipo, monto, saldo_anterior, saldo_posterior, descripcion) VALUES (?,?,?,?,?,?)")
                ->execute([$cuenta_id, $_POST['action'], $monto, $saldo_ant, $nuevo_saldo, 'Operación Administrativa']);
            $pdo->commit();
            $msg_ok = "Transacción exitosa.";
        }
    }
}

// Búsqueda
$q = $_GET['q'] ?? '';
$users = $pdo->prepare("SELECT * FROM usuarios WHERE nombre LIKE ? OR ci LIKE ? ORDER BY id DESC");
$users->execute(["%$q%", "%$q%"]);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ATM Admin Pro</title>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --orange: #ffa500; --green: #22c55e; --blue: #3b82f6; --text: #f8fafc; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .container { max-width: 1000px; margin: auto; }
        .acc-item { background: var(--card); border-radius: 8px; margin-bottom: 10px; border: 1px solid #334155; overflow: hidden; }
        .acc-header { padding: 15px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .acc-header:hover { background: #2d3a4f; }
        .acc-content { display: none; padding: 20px; border-top: 1px solid #334155; background: #161e2d; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        input, select { background: #0f172a; border: 1px solid #334155; color: white; padding: 8px; border-radius: 4px; width: 100%; margin-bottom: 10px; }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 11px; }
        .btn-orange { background: var(--orange); color: #000; }
        .btn-blue  { background: var(--blue);   color: #fff; }
        .btn-green { background: var(--green);  color: #fff; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { text-align: left; color: var(--orange); padding-bottom: 5px; }
        td { border-bottom: 1px solid #334155; padding: 5px 0; }
        /* Tabs */
        .tabs { display: flex; gap: 5px; margin-bottom: 15px; }
        .tab-btn { padding: 7px 14px; border: none; border-radius: 4px 4px 0 0; cursor: pointer; font-size: 11px; font-weight: bold;
                   background: #334155; color: #94a3b8; }
        .tab-btn.active { background: var(--orange); color: #000; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>ATM <span style="color:var(--orange)">DASHBOARD</span></h1>
        <a href="?logout" style="color:white; text-decoration:none;">Cerrar Sesión</a>
    </div>

    <?php if($msg_ok) echo "<p style='color:var(--green)'>✔ $msg_ok</p>"; ?>
    <?php if($msg_err) echo "<p style='color:red'>✖ $msg_err</p>"; ?>

    <!-- REGISTRAR NUEVO USUARIO -->
    <div class="acc-item" style="border-color: var(--green);">
        <div class="acc-header" style="color:var(--green)" onclick="toggle('nuevo')">+ REGISTRAR NUEVO USUARIO ▼</div>
        <div class="acc-content" id="u-nuevo">
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="grid">
                    <div>
                        <label>Nombre</label><input type="text" name="nombre" required>
                        <label>Apellido</label><input type="text" name="apellido" required>
                        <label>CI</label><input type="text" name="ci" required>
                        <label>Teléfono</label><input type="text" name="telefono">
                    </div>
                    <div>
                        <label>Tarjeta (16 dígitos)</label><input type="text" name="tarjeta" maxlength="16" required>
                        <label>PIN (4-6 dígitos)</label><input type="password" name="pin" required>
                        <label>Email</label><input type="email" name="email">
                        <label>Tipo de Cuenta</label>
                        <select name="tipo_cuenta">
                            <option value="ahorros">Ahorros</option>
                            <option value="corriente">Corriente</option>
                        </select>
                        <label>Saldo Inicial (Bs.)</label>
                        <input type="number" name="saldo_inicial" min="0" step="0.01" placeholder="0.00" value="0">
                    </div>
                </div>
                <button type="submit" class="btn btn-orange" style="width:200px; display:block;">GUARDAR EN BD</button>
            </form>
        </div>
    </div>

    <form method="GET"><input type="text" name="q" placeholder="Buscar por Nombre o CI..." style="padding:12px;" value="<?= htmlspecialchars($q) ?>"></form>

    <?php foreach($users->fetchAll() as $u):
        // Obtener cuentas del usuario
        $accs_stmt = $pdo->prepare("SELECT * FROM cuentas WHERE usuario_id = ?");
        $accs_stmt->execute([$u['id']]);
        $cuentas = $accs_stmt->fetchAll();
        $tipos_existentes = array_column($cuentas, 'tipo');
    ?>
    <div class="acc-item">
        <div class="acc-header" onclick="toggle(<?= $u['id'] ?>)">
            <span>
                <strong><?= htmlspecialchars($u['nombre']) ?> <?= htmlspecialchars($u['apellido']) ?></strong>
                <small style="margin-left:20px; color:#94a3b8;">CI: <?= htmlspecialchars($u['ci']) ?></small>
            </span>
            <span>ID: <?= $u['id'] ?> ▼</span>
        </div>
        <div class="acc-content" id="u-<?= $u['id'] ?>">

            <!-- TABS -->
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab(<?= $u['id'] ?>, 'cuentas', this)">💳 Cuentas y Movimientos</button>
                <button class="tab-btn"        onclick="showTab(<?= $u['id'] ?>, 'editar',  this)">✏️ Editar Datos</button>
                <?php if (!in_array('ahorros', $tipos_existentes) || !in_array('corriente', $tipos_existentes)): ?>
                <button class="tab-btn"        onclick="showTab(<?= $u['id'] ?>, 'addcta',  this)">➕ Agregar Cuenta</button>
                <?php endif; ?>
            </div>

            <!-- TAB: CUENTAS Y MOVIMIENTOS -->
            <div class="tab-panel active" id="tab-<?= $u['id'] ?>-cuentas">
                <div class="grid">
                    <div>
                        <h4 style="color:var(--orange)">Cuentas del Usuario</h4>
                        <?php foreach($cuentas as $c): ?>
                            <div style="background:#0f172a; padding:15px; border-radius:8px; margin-bottom:10px;">
                                <div style="display:flex; justify-content:space-between;">
                                    <strong><?= strtoupper($c['tipo']) ?></strong>
                                    <span style="color:var(--orange)"><?= $c['numero_cuenta'] ?></span>
                                </div>
                                <h2 style="margin:10px 0;">Bs. <?= number_format($c['saldo'], 2) ?></h2>
                                <form method="POST" style="display:flex; gap:5px;">
                                    <input type="hidden" name="cuenta_id" value="<?= $c['id'] ?>">
                                    <input type="number" name="monto" step="0.01" placeholder="Monto" style="margin:0;">
                                    <button type="submit" name="action" value="deposito" class="btn" style="background:var(--green); color:white;">+</button>
                                    <button type="submit" name="action" value="retiro"   class="btn" style="background:red; color:white;">-</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <h4 style="color:var(--orange)">Últimos Movimientos</h4>
                        <div style="max-height:250px; overflow-y:auto;">
                            <table>
                                <thead><tr><th>Fecha</th><th>Tipo</th><th>Monto</th><th>Final</th></tr></thead>
                                <tbody>
                                    <?php
                                    $movs = $pdo->prepare("SELECT m.* FROM movimientos m JOIN cuentas c ON m.cuenta_id = c.id WHERE c.usuario_id = ? ORDER BY m.fecha DESC LIMIT 10");
                                    $movs->execute([$u['id']]);
                                    foreach($movs->fetchAll() as $m): ?>
                                        <tr>
                                            <td><?= date('d/m H:i', strtotime($m['fecha'])) ?></td>
                                            <td style="color:<?= $m['tipo']=='deposito'?'#22c55e':'red' ?>"><?= strtoupper($m['tipo']) ?></td>
                                            <td>Bs.<?= $m['monto'] ?></td>
                                            <td><b>Bs.<?= $m['saldo_posterior'] ?></b></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: EDITAR DATOS -->
            <div class="tab-panel" id="tab-<?= $u['id'] ?>-editar">
                <form method="POST">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                    <div class="grid">
                        <div>
                            <label>Nombre</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($u['nombre']) ?>" required>
                            <label>Apellido</label>
                            <input type="text" name="apellido" value="<?= htmlspecialchars($u['apellido']) ?>" required>
                            <label>CI</label>
                            <input type="text" name="ci" value="<?= htmlspecialchars($u['ci']) ?>" required>
                            <label>Teléfono</label>
                            <input type="text" name="telefono" value="<?= htmlspecialchars($u['telefono']) ?>">
                        </div>
                        <div>
                            <label>Nº Tarjeta (16 dígitos)</label>
                            <input type="text" name="tarjeta" value="<?= htmlspecialchars($u['numero_tarjeta']) ?>" maxlength="16" required>
                            <label>Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>">
                            <label>Nuevo PIN <small style="color:#94a3b8;">(dejar vacío para no cambiar)</small></label>
                            <input type="password" name="pin_nuevo" placeholder="Nuevo PIN (4-6 dígitos)">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-blue" style="width:200px; display:block;">GUARDAR CAMBIOS</button>
                </form>
            </div>

            <!-- TAB: AGREGAR CUENTA (solo si le falta algún tipo) -->
            <?php if (!in_array('ahorros', $tipos_existentes) || !in_array('corriente', $tipos_existentes)): ?>
            <div class="tab-panel" id="tab-<?= $u['id'] ?>-addcta">
                <form method="POST">
                    <input type="hidden" name="action" value="add_cuenta">
                    <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                    <div class="grid">
                        <div>
                            <label>Tipo de Cuenta a Agregar</label>
                            <select name="tipo_cuenta">
                                <?php if (!in_array('ahorros',   $tipos_existentes)): ?>
                                    <option value="ahorros">Ahorros</option>
                                <?php endif; ?>
                                <?php if (!in_array('corriente', $tipos_existentes)): ?>
                                    <option value="corriente">Corriente</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label>Saldo Inicial (Bs.)</label>
                            <input type="number" name="saldo_inicial" min="0" step="0.01" placeholder="0.00" value="0">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-green" style="width:200px; display:block;">AGREGAR CUENTA</button>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function toggle(id) {
    const el = document.getElementById('u-' + id);
    const isVisible = el.style.display === 'block';
    document.querySelectorAll('.acc-content').forEach(c => c.style.display = 'none');
    el.style.display = isVisible ? 'none' : 'block';
}

function showTab(userId, tabName, btn) {
    // Ocultar todos los panels de este usuario
    document.querySelectorAll('#u-' + userId + ' .tab-panel').forEach(p => p.classList.remove('active'));
    // Desactivar todos los botones de tab de este usuario
    document.querySelectorAll('#u-' + userId + ' .tab-btn').forEach(b => b.classList.remove('active'));
    // Mostrar el panel seleccionado
    document.getElementById('tab-' + userId + '-' + tabName).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>