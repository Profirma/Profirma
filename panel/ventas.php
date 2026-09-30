<?php

session_start();

if (empty($_SESSION['profirma_admin'])) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('America/Guayaquil');

$ventas = [];
$totalVentas = 0;
$errorBaseDatos = '';

$databaseUrl = trim((string) getenv('DATABASE_URL'));

if ($databaseUrl === '') {

    $errorBaseDatos = 'DATABASE_URL no está configurada.';

} else {

    try {

        $db = parse_url($databaseUrl);

        if (
            $db === false ||
            !isset($db['host'], $db['path'])
        ) {
            throw new RuntimeException('DATABASE_URL inválida.');
        }

        $dbHost = (string) $db['host'];
        $dbPort = isset($db['port']) ? (int) $db['port'] : 5432;
        $dbName = ltrim((string) $db['path'], '/');
        $dbUser = isset($db['user']) ? urldecode((string) $db['user']) : '';
        $dbPass = isset($db['pass']) ? urldecode((string) $db['pass']) : '';

        $pdo = new PDO(
            "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | TOTAL DE VENTAS APROBADAS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT
                COALESCE(SUM(monto_centavos), 0) AS total
            FROM solicitudes
            WHERE payphone_status = 'Approved'
        ");

        $resultado = $stmt->fetch();

        $totalVentas = (int) ($resultado['total'] ?? 0);


        /*
        |--------------------------------------------------------------------------
        | LISTADO DE VENTAS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT
                id,
                client_transaction_id,
                estado,
                monto_centavos,
                datos_solicitud,
                payphone_id,
                payphone_status,
                enext_procesado,
                created_at
            FROM solicitudes
            WHERE payphone_status = 'Approved'
            ORDER BY id DESC
        ");

        $ventas = $stmt->fetchAll();

    } catch (Throwable $e) {

        error_log(
            'PROFIRMA / Panel ventas: ' .
            $e->getMessage()
        );

        $errorBaseDatos =
            'No fue posible cargar las ventas.';
    }
}


/*
|--------------------------------------------------------------------------
| FUNCIONES
|--------------------------------------------------------------------------
*/

function e(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


function datosSolicitud(mixed $datos): array
{
    if (is_array($datos)) {
        return $datos;
    }

    if ($datos === null || $datos === '') {
        return [];
    }

    $resultado = json_decode(
        (string) $datos,
        true
    );

    return is_array($resultado)
        ? $resultado
        : [];
}


function dinero(int $centavos): string
{
    return '$' . number_format(
        $centavos / 100,
        2,
        '.',
        ','
    );
}


function fechaVenta(mixed $fecha): string
{
    if (
        $fecha === null ||
        trim((string) $fecha) === ''
    ) {
        return '-';
    }

    try {

        return (new DateTime(
            (string) $fecha
        ))->format('d/m/Y');

    } catch (Throwable $e) {

        return (string) $fecha;
    }
}


function nombreCliente(array $datos): string
{
    $nombres = trim(
        (string) (
            $datos['nombres']
            ?? $datos['nombre']
            ?? ''
        )
    );

    $apellidos = trim(
        (string) (
            $datos['apellidos']
            ?? $datos['apellido']
            ?? ''
        )
    );

    $nombre = trim(
        $nombres . ' ' . $apellidos
    );

    return $nombre !== ''
        ? $nombre
        : 'Sin nombre';
}


function correoCliente(array $datos): string
{
    return trim(
        (string) (
            $datos['correo']
            ?? $datos['email']
            ?? ''
        )
    );
}


function documentoCliente(array $datos): string
{
    return trim(
        (string) (
            $datos['cedula']
            ?? $datos['ruc']
            ?? $datos['documento']
            ?? ''
        )
    );
}


function planCliente(array $datos): string
{
    return trim(
        (string) (
            $datos['vigencia']
            ?? $datos['plan']
            ?? ''
        )
    );
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Ventas | PROFIRMA</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --azul: #07396b;
            --azul-oscuro: #052b52;
            --fondo: #f5f8fb;
            --blanco: #ffffff;
            --texto: #172033;
            --texto-suave: #718096;
            --borde: #e4eaf0;
            --verde: #22a06b;
            --amarillo: #e8a317;
            --rojo: #d64545;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--fondo);
            color: var(--texto);
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;

            background: linear-gradient(
                180deg,
                var(--azul) 0%,
                var(--azul-oscuro) 100%
            );

            color: white;
            padding: 28px 18px;
            z-index: 100;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 28px;
            border-bottom: 1px solid rgba(255,255,255,.14);
        }

        .logo-area img {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            object-fit: cover;
            background: white;
        }

        .logo-text h2 {
            font-size: 21px;
            letter-spacing: .5px;
        }

        .logo-text span {
            font-size: 12px;
            color: rgba(255,255,255,.70);
        }

        .menu-title {
            margin: 28px 12px 12px;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1.2px;
            color: rgba(255,255,255,.55);
        }

        .menu {
            list-style: none;
        }

        .menu li {
            margin-bottom: 7px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 15px;
            border-radius: 10px;
            text-decoration: none;
            color: rgba(255,255,255,.82);
            font-size: 15px;
            transition: .2s ease;
        }

        .menu a i {
            width: 20px;
            text-align: center;
        }

        .menu a:hover,
        .menu a.active {
            background: rgba(255,255,255,.13);
            color: white;
        }

        .logout {
            position: absolute;
            left: 18px;
            right: 18px;
            bottom: 25px;
        }

        .logout a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,.80);
            text-decoration: none;
            padding: 14px;
            border-top: 1px solid rgba(255,255,255,.14);
        }

        .main {
            margin-left: 260px;
            min-height: 100vh;
        }

        .topbar {
            height: 76px;
            background: white;
            border-bottom: 1px solid var(--borde);

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 34px;
        }

        .topbar-left h3 {
            font-size: 18px;
            color: var(--azul);
        }

        .topbar-left p {
            font-size: 12px;
            color: var(--texto-suave);
            margin-top: 4px;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .admin-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--azul);
            color: white;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-user strong {
            display: block;
            font-size: 14px;
        }

        .admin-user span {
            display: block;
            font-size: 11px;
            color: var(--texto-suave);
            margin-top: 2px;
        }

        .content {
            padding: 32px 34px 50px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-header h1 {
            color: var(--azul);
            font-size: 27px;
            margin-bottom: 6px;
        }

        .page-header p {
            color: var(--texto-suave);
            font-size: 13px;
        }

        .summary {
            background: white;
            border: 1px solid var(--borde);
            border-radius: 15px;
            padding: 22px 25px;
            margin-bottom: 25px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            box-shadow: 0 5px 18px rgba(18,38,63,.05);
        }

        .summary span {
            display: block;
            color: var(--texto-suave);
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .summary h2 {
            font-size: 29px;
            color: var(--texto);
        }

        .summary-icon {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            background: #e7f7f0;
            color: var(--verde);

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 22px;
        }

        .panel {
            background: white;
            border: 1px solid var(--borde);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 18px rgba(18,38,63,.05);
        }

        .panel-header {
            padding: 21px 24px;
            border-bottom: 1px solid var(--borde);
        }

        .panel-header h3 {
            font-size: 17px;
            color: var(--azul);
        }

        .panel-header p {
            color: var(--texto-suave);
            font-size: 12px;
            margin-top: 4px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 14px 18px;
            text-align: left;
            font-size: 11px;
            color: #718096;
            text-transform: uppercase;
        }

        td {
            padding: 16px 18px;
            border-top: 1px solid #edf1f5;
            font-size: 13px;
        }

        .client strong {
            display: block;
        }

        .client span {
            display: block;
            color: var(--texto-suave);
            font-size: 11px;
            margin-top: 3px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .approved {
            background: #e5f7ef;
            color: #16845a;
        }

        .processed {
            background: #e8f1fb;
            color: #1766a5;
        }

        .pending {
            background: #fff3d6;
            color: #9a6a00;
        }

        .error {
            background: #fdeaea;
            color: #b72e2e;
        }

        .view-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            border: 1px solid var(--borde);
            background: white;

            padding: 7px 10px;
            border-radius: 7px;

            color: var(--azul);
            text-decoration: none;
        }

        .view-button:hover {
            background: #f5f8fb;
        }

        .database-error {
            margin-bottom: 18px;
            padding: 13px 16px;
            background: #fdeaea;
            border: 1px solid #f3bcbc;
            color: #9f2727;
            border-radius: 10px;
            font-size: 12px;
        }

        .empty-table {
            text-align: center;
            color: var(--texto-suave);
            padding: 35px 20px;
        }

        @media (max-width: 760px) {

            .sidebar {
                width: 78px;
                padding: 25px 10px;
            }

            .logo-text,
            .menu-title,
            .menu a span,
            .logout span {
                display: none;
            }

            .logo-area {
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }

            .logo-area img {
                width: 45px;
                height: 45px;
            }

            .menu a {
                justify-content: center;
            }

            .main {
                margin-left: 78px;
            }

            .content {
                padding: 22px 18px;
            }

            .topbar {
                padding: 0 18px;
            }
        }

    </style>

</head>

<body>

<aside class="sidebar">

    <div class="logo-area">

        <img
            src="../logo.jpeg"
            alt="PROFIRMA"
        >

        <div class="logo-text">
            <h2>PROFIRMA</h2>
            <span>Administración</span>
        </div>

    </div>

    <div class="menu-title">
        MENÚ PRINCIPAL
    </div>

    <ul class="menu">

        <li>
            <a href="index.php">
                <i class="fa-solid fa-house"></i>
                <span>Inicio</span>
            </a>
        </li>

        <li>
            <a
                href="ventas.php"
                class="active"
            >
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Ventas</span>
            </a>
        </li>

        <li>
            <a href="solicitudes.php">
                <i class="fa-solid fa-file-signature"></i>
                <span>Solicitudes</span>
            </a>
        </li>

        <li>
            <a href="reportes.php">
                <i class="fa-solid fa-chart-column"></i>
                <span>Reportes</span>
            </a>
        </li>

    </ul>

    <div class="logout">

        <a href="logout.php">

            <i class="fa-solid fa-right-from-bracket"></i>

            <span>
                Cerrar sesión
            </span>

        </a>

    </div>

</aside>


<main class="main">

    <header class="topbar">

        <div class="topbar-left">

            <h3>
                Panel Administrativo
            </h3>

            <p>
                Gestión interna de PROFIRMA
            </p>

        </div>

        <div class="admin-user">

            <div class="admin-circle">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <div>
                <strong>Administrador</strong>
                <span>PROFIRMA</span>
            </div>

        </div>

    </header>


    <section class="content">

        <div class="page-header">

            <div>

                <h1>Ventas</h1>

                <p>
                    Pagos aprobados registrados en PROFIRMA.
                </p>

            </div>

        </div>


        <?php if ($errorBaseDatos !== ''): ?>

            <div class="database-error">

                <?= e($errorBaseDatos) ?>

            </div>

        <?php endif; ?>


        <div class="summary">

            <div>

                <span>
                    Total vendido
                </span>

                <h2>
                    <?= e(
                        dinero($totalVentas)
                    ) ?>
                </h2>

            </div>

            <div class="summary-icon">

                <i class="fa-solid fa-dollar-sign"></i>

            </div>

        </div>


        <div class="panel">

            <div class="panel-header">

                <h3>
                    Historial de ventas
                </h3>

                <p>
                    Todas las transacciones aprobadas por PayPhone.
                </p>

            </div>


            <div class="table-container">

                <table>

                    <thead>

                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Cédula</th>
                        <th>Plan</th>
                        <th>Valor</th>
                        <th>PayPhone</th>
                        <th>eNext</th>
                        <th>Acción</th>
                    </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($ventas)): ?>

                        <tr>

                            <td
                                colspan="8"
                                class="empty-table"
                            >
                                No existen ventas aprobadas.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($ventas as $venta): ?>

                            <?php

                            $datos = datosSolicitud(
                                $venta['datos_solicitud']
                                ?? null
                            );

                            $nombre = nombreCliente($datos);
                            $correo = correoCliente($datos);
                            $documento = documentoCliente($datos);
                            $plan = planCliente($datos);

                            $enextProcesado = filter_var(
                                $venta['enext_procesado']
                                ?? false,
                                FILTER_VALIDATE_BOOLEAN
                            );

                            $estado = trim(
                                (string) (
                                    $venta['estado']
                                    ?? ''
                                )
                            );

                            ?>

                            <tr>

                                <td>
                                    <?= e(
                                        fechaVenta(
                                            $venta['created_at']
                                            ?? null
                                        )
                                    ) ?>
                                </td>

                                <td class="client">

                                    <strong>
                                        <?= e($nombre) ?>
                                    </strong>

                                    <span>
                                        <?= e(
                                            $correo !== ''
                                                ? $correo
                                                : 'Sin correo'
                                        ) ?>
                                    </span>

                                </td>

                                <td>
                                    <?= e(
                                        $documento !== ''
                                            ? $documento
                                            : '-'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $plan !== ''
                                            ? $plan
                                            : '-'
                                    ) ?>
                                </td>

                                <td>

                                    <strong>
                                        <?= e(
                                            dinero(
                                                (int) (
                                                    $venta['monto_centavos']
                                                    ?? 0
                                                )
                                            )
                                        ) ?>
                                    </strong>

                                </td>

                                <td>

                                    <span class="status approved">
                                        Aprobado
                                    </span>

                                </td>

                                <td>

                                    <?php if ($enextProcesado): ?>

                                        <span class="status processed">
                                            Procesado
                                        </span>

                                    <?php elseif ($estado === 'error'): ?>

                                        <span class="status error">
                                            Error
                                        </span>

                                    <?php else: ?>

                                        <span class="status pending">
                                            Pendiente
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <a
                                        class="view-button"
                                        href="detalle.php?id=<?= (int) $venta['id'] ?>"
                                    >

                                        <i class="fa-solid fa-eye"></i>

                                        Ver

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </section>

</main>

</body>

</html>
