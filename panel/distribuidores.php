<?php
session_start();

/*
|--------------------------------------------------------------------------
| PRO-FIRMA - DISTRIBUIDORES
|--------------------------------------------------------------------------
| Este archivo administra únicamente el módulo de distribuidores.
| No modifica el flujo de clientes ni solicitudes existentes.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| CONEXIÓN A POSTGRESQL
|--------------------------------------------------------------------------
| Usa las mismas variables de entorno que ya utiliza el proyecto.
|--------------------------------------------------------------------------
*/

$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    die('No se encontró DATABASE_URL.');
}

$db = parse_url($databaseUrl);

$host = $db['host'] ?? '';
$port = $db['port'] ?? 5432;
$user = $db['user'] ?? '';
$pass = $db['pass'] ?? '';
$name = isset($db['path']) ? ltrim($db['path'], '/') : '';

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$name}",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('Error de conexión con la base de datos.');
}


/*
|--------------------------------------------------------------------------
| PROTECCIÓN DEL PANEL
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| ACCIONES
|--------------------------------------------------------------------------
*/

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | CREAR DISTRIBUIDOR
    |--------------------------------------------------------------------------
    */

    if ($accion === 'crear') {

        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $numero_documento = trim($_POST['numero_documento'] ?? '');
        $empresa = trim($_POST['empresa'] ?? '');
        $ruc = trim($_POST['ruc'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $tipo_membresia = $_POST['tipo_membresia'] ?? '';

        if (
            $nombres === '' ||
            $apellidos === '' ||
            $numero_documento === '' ||
            $email === '' ||
            $password === '' ||
            !in_array($tipo_membresia, ['MENSUAL', 'ANUAL'], true)
        ) {
            $error = 'Completa todos los campos obligatorios.';
        } else {

            try {

                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $fechaInicio = new DateTime();

                $fechaVencimiento = clone $fechaInicio;

                if ($tipo_membresia === 'MENSUAL') {
                    $fechaVencimiento->modify('+1 month');
                } else {
                    $fechaVencimiento->modify('+1 year');
                }

                $sql = "
                    INSERT INTO distribuidores (
                        nombres,
                        apellidos,
                        numero_documento,
                        empresa,
                        ruc,
                        telefono,
                        whatsapp,
                        email,
                        password_hash,
                        estado,
                        tipo_membresia,
                        fecha_inicio_membresia,
                        fecha_vencimiento_membresia,
                        saldo
                    )
                    VALUES (
                        :nombres,
                        :apellidos,
                        :numero_documento,
                        :empresa,
                        :ruc,
                        :telefono,
                        :whatsapp,
                        :email,
                        :password_hash,
                        'ACTIVO',
                        :tipo_membresia,
                        :fecha_inicio,
                        :fecha_vencimiento,
                        0
                    )
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':nombres' => $nombres,
                    ':apellidos' => $apellidos,
                    ':numero_documento' => $numero_documento,
                    ':empresa' => $empresa ?: null,
                    ':ruc' => $ruc ?: null,
                    ':telefono' => $telefono ?: null,
                    ':whatsapp' => $whatsapp ?: null,
                    ':email' => $email,
                    ':password_hash' => $password_hash,
                    ':tipo_membresia' => $tipo_membresia,
                    ':fecha_inicio' => $fechaInicio->format('Y-m-d H:i:s'),
                    ':fecha_vencimiento' => $fechaVencimiento->format('Y-m-d H:i:s')
                ]);

                $mensaje = 'Distribuidor creado correctamente.';

            } catch (PDOException $e) {

                if ($e->getCode() === '23505') {
                    $error = 'La cédula o el correo electrónico ya están registrados.';
                } else {
                    $error = 'No se pudo crear el distribuidor.';
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | BLOQUEAR / ACTIVAR
    |--------------------------------------------------------------------------
    */

    if ($accion === 'cambiar_estado') {

        $id = (int)($_POST['id'] ?? 0);
        $nuevoEstado = $_POST['nuevo_estado'] ?? '';

        if (
            $id > 0 &&
            in_array($nuevoEstado, ['ACTIVO', 'BLOQUEADO', 'INACTIVO'], true)
        ) {

            $stmt = $pdo->prepare("
                UPDATE distribuidores
                SET estado = :estado,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $stmt->execute([
                ':estado' => $nuevoEstado,
                ':id' => $id
            ]);

            $mensaje = 'Estado actualizado correctamente.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| LISTAR DISTRIBUIDORES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        nombres,
        apellidos,
        numero_documento,
        empresa,
        email,
        whatsapp,
        tipo_membresia,
        fecha_vencimiento_membresia,
        saldo,
        estado,
        created_at
    FROM distribuidores
    ORDER BY created_at DESC
");

$distribuidores = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

$totalDistribuidores = count($distribuidores);

$activos = 0;
$bloqueados = 0;
$saldoTotal = 0;

foreach ($distribuidores as $d) {

    if ($d['estado'] === 'ACTIVO') {
        $activos++;
    }

    if ($d['estado'] === 'BLOQUEADO') {
        $bloqueados++;
    }

    $saldoTotal += (float)$d['saldo'];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Distribuidores | PRO-FIRMA</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #17365d;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 270px;
    background: #073b68;
    color: white;
    padding: 25px 18px;
}

.logo {
    background: white;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    margin-bottom: 25px;
}

.logo img {
    max-width: 150px;
    max-height: 80px;
}

.logo-text {
    color: #073b68;
    font-weight: bold;
    font-size: 22px;
}

.admin-title {
    text-align: center;
    margin-bottom: 25px;
    font-size: 14px;
    opacity: .85;
}

.menu a {
    display: block;
    padding: 15px 18px;
    margin-bottom: 7px;
    border-radius: 10px;
    color: white;
    text-decoration: none;
    font-weight: 600;
}

.menu a:hover,
.menu a.active {
    background: rgba(255,255,255,.14);
}

.main {
    margin-left: 270px;
    padding: 35px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0;
    font-size: 28px;
}

.header p {
    margin-top: 7px;
    color: #66788a;
}

.btn {
    border: 0;
    border-radius: 8px;
    padding: 12px 18px;
    cursor: pointer;
    font-weight: bold;
}

.btn-primary {
    background: #073b68;
    color: white;
}

.btn-danger {
    background: #b42318;
    color: white;
}

.btn-success {
    background: #147d4d;
    color: white;
}

.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.card {
    background: white;
    border-radius: 14px;
    padding: 22px;
    box-shadow: 0 5px 20px rgba(0,0,0,.06);
}

.card-title {
    color: #66788a;
    font-size: 14px;
    margin-bottom: 10px;
}

.card-value {
    font-size: 28px;
    font-weight: bold;
}

.alert {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.alert-success {
    background: #e8f7ef;
    color: #17663f;
}

.alert-error {
    background: #fdecec;
    color: #9b1c1c;
}

.panel {
    background: white;
    border-radius: 14px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,.06);
    margin-bottom: 25px;
}

.panel h2 {
    margin-top: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.field {
    display: flex;
    flex-direction: column;
}

.field.full {
    grid-column: 1 / -1;
}

.field label {
    font-weight: bold;
    margin-bottom: 7px;
    font-size: 14px;
}

.field input,
.field select {
    border: 1px solid #d5dde7;
    border-radius: 8px;
    padding: 12px;
    font-size: 15px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    text-align: left;
    background: #f1f5f9;
    padding: 14px;
    font-size: 13px;
}

td {
    padding: 14px;
    border-bottom: 1px solid #edf1f5;
    font-size: 14px;
}

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.badge-active {
    background: #e7f6ed;
    color: #137443;
}

.badge-blocked {
    background: #fdeaea;
    color: #a61b1b;
}

.badge-inactive {
    background: #edf0f3;
    color: #596675;
}

.actions {
    display: flex;
    gap: 7px;
}

.actions form {
    margin: 0;
}

.small-btn {
    padding: 7px 10px;
    border: 0;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
}

@media (max-width: 900px) {

    .sidebar {
        width: 220px;
    }

    .main {
        margin-left: 220px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<aside class="sidebar">

    <div class="logo">

        <?php if (file_exists(__DIR__ . '/logo.jpeg')): ?>

            <img src="logo.jpeg" alt="PRO-FIRMA">

        <?php else: ?>

            <div class="logo-text">
                PRO-FIRMA
            </div>

        <?php endif; ?>

    </div>

    <div class="admin-title">
        Administración
    </div>

    <nav class="menu">

        <a href="index.php">
            🏠 Inicio
        </a>

        <a href="ventas.php">
            📊 Ventas
        </a>

        <a href="solicitudes.php">
            📝 Solicitudes
        </a>

        <a href="distribuidores.php" class="active">
            👥 Distribuidores
        </a>

        <a href="reportes.php">
            📈 Reportes
        </a>

        <a href="logout.php">
            🚪 Cerrar sesión
        </a>

    </nav>

</aside>


<main class="main">

    <div class="header">

        <div>
            <h1>Distribuidores</h1>

            <p>
                Administración de compradores mayoristas de PRO-FIRMA
            </p>
        </div>

    </div>


    <?php if ($mensaje): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($mensaje) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- ESTADÍSTICAS -->

    <section class="cards">

        <div class="card">

            <div class="card-title">
                Total de distribuidores
            </div>

            <div class="card-value">
                <?= $totalDistribuidores ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Distribuidores activos
            </div>

            <div class="card-value">
                <?= $activos ?>
            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Saldo total disponible
            </div>

            <div class="card-value">
                $<?= number_format($saldoTotal, 2) ?>
            </div>

        </div>

    </section>


    <!-- CREAR DISTRIBUIDOR -->

    <section class="panel">

        <h2>Registrar distribuidor</h2>

        <form method="POST">

            <input
                type="hidden"
                name="accion"
                value="crear"
            >

            <div class="form-grid">

                <div class="field">

                    <label>
                        Nombres *
                    </label>

                    <input
                        type="text"
                        name="nombres"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        Apellidos *
                    </label>

                    <input
                        type="text"
                        name="apellidos"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        Cédula *
                    </label>

                    <input
                        type="text"
                        name="numero_documento"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        Empresa
                    </label>

                    <input
                        type="text"
                        name="empresa"
                    >

                </div>


                <div class="field">

                    <label>
                        RUC
                    </label>

                    <input
                        type="text"
                        name="ruc"
                    >

                </div>


                <div class="field">

                    <label>
                        Teléfono
                    </label>

                    <input
                        type="text"
                        name="telefono"
                    >

                </div>


                <div class="field">

                    <label>
                        WhatsApp
                    </label>

                    <input
                        type="text"
                        name="whatsapp"
                    >

                </div>


                <div class="field">

                    <label>
                        Correo electrónico *
                    </label>

                    <input
                        type="email"
                        name="email"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        Contraseña *
                    </label>

                    <input
                        type="password"
                        name="password"
                        required
                    >

                </div>


                <div class="field">

                    <label>
                        Membresía *
                    </label>

                    <select
                        name="tipo_membresia"
                        required
                    >

                        <option value="">
                            Seleccionar
                        </option>

                        <option value="MENSUAL">
                            Mensual
                        </option>

                        <option value="ANUAL">
                            Anual
                        </option>

                    </select>

                </div>

            </div>


            <br>

            <button
                type="submit"
                class="btn btn-primary"
            >
                + Registrar distribuidor
            </button>

        </form>

    </section>


    <!-- LISTADO -->

    <section class="panel">

        <h2>Distribuidores registrados</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Distribuidor
                        </th>

                        <th>
                            Documento
                        </th>

                        <th>
                            Empresa
                        </th>

                        <th>
                            Membresía
                        </th>

                        <th>
                            Vencimiento
                        </th>

                        <th>
                            Saldo
                        </th>

                        <th>
                            Estado
                        </th>

                        <th>
                            Acción
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!$distribuidores): ?>

                    <tr>

                        <td colspan="8" style="text-align:center; padding:35px;">

                            Todavía no hay distribuidores registrados.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($distribuidores as $d): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $d['nombres'] . ' ' . $d['apellidos']
                                    ) ?>
                                </strong>

                                <br>

                                <small>
                                    <?= htmlspecialchars($d['email']) ?>
                                </small>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $d['numero_documento']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $d['empresa'] ?: '—'
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $d['tipo_membresia'] ?: '—'
                                ) ?>

                            </td>


                            <td>

                                <?php

                                if ($d['fecha_vencimiento_membresia']) {

                                    echo date(
                                        'd/m/Y',
                                        strtotime(
                                            $d['fecha_vencimiento_membresia']
                                        )
                                    );

                                } else {

                                    echo '—';

                                }

                                ?>

                            </td>


                            <td>

                                <strong>
                                    $<?= number_format(
                                        (float)$d['saldo'],
                                        2
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <?php if ($d['estado'] === 'ACTIVO'): ?>

                                    <span class="badge badge-active">
                                        ACTIVO
                                    </span>

                                <?php elseif ($d['estado'] === 'BLOQUEADO'): ?>

                                    <span class="badge badge-blocked">
                                        BLOQUEADO
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-inactive">
                                        INACTIVO
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="actions">

                                    <?php if ($d['estado'] === 'ACTIVO'): ?>

                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="cambiar_estado"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$d['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="nuevo_estado"
                                                value="BLOQUEADO"
                                            >

                                            <button
                                                type="submit"
                                                class="small-btn btn-danger"
                                            >
                                                Bloquear
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="cambiar_estado"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$d['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="nuevo_estado"
                                                value="ACTIVO"
                                            >

                                            <button
                                                type="submit"
                                                class="small-btn btn-success"
                                            >
                                                Activar
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>
</html>
