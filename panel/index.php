<?php

session_start();

if (empty($_SESSION['profirma_admin'])) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| PRO-FIRMA - PANEL ADMINISTRATIVO
|--------------------------------------------------------------------------
| Panel conectado a PostgreSQL.
| SOLO LEE información de la tabla solicitudes.
| NO modifica PayPhone.
| NO modifica eNext.
|--------------------------------------------------------------------------
*/

date_default_timezone_set('America/Guayaquil');


/*
|--------------------------------------------------------------------------
| VALORES INICIALES
|--------------------------------------------------------------------------
*/

$ventasHoy = 0;
$ventasMes = 0;
$totalSolicitudes = 0;
$totalErrores = 0;

$ultimasCompras = [];

$errorBaseDatos = '';


/*
|--------------------------------------------------------------------------
| CONEXIÓN POSTGRESQL
|--------------------------------------------------------------------------
*/

$databaseUrl = trim((string) getenv('DATABASE_URL'));

if ($databaseUrl === '') {

    $errorBaseDatos =
        'DATABASE_URL no está configurada en Railway.';

} else {

    try {

        $db = parse_url($databaseUrl);

        if (
            $db === false ||
            !isset($db['host'], $db['path'])
        ) {
            throw new RuntimeException(
                'DATABASE_URL no tiene un formato válido.'
            );
        }

        $dbHost = (string) $db['host'];

        $dbPort =
            isset($db['port'])
                ? (int) $db['port']
                : 5432;

        $dbName =
            ltrim(
                (string) $db['path'],
                '/'
            );

        $dbUser =
            isset($db['user'])
                ? urldecode((string) $db['user'])
                : '';

        $dbPass =
            isset($db['pass'])
                ? urldecode((string) $db['pass'])
                : '';

        $pdo = new PDO(
            "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE =>
                    PDO::ERRMODE_EXCEPTION,

                PDO::ATTR_DEFAULT_FETCH_MODE =>
                    PDO::FETCH_ASSOC,

                PDO::ATTR_EMULATE_PREPARES =>
                    false
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | VENTAS DE HOY
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query(
            "
            SELECT
                COALESCE(
                    SUM(monto_centavos),
                    0
                ) AS total
            FROM solicitudes
            WHERE
                payphone_status = 'Approved'
                AND created_at = CURRENT_DATE
            "
        );

        $resultado = $stmt->fetch();

        $ventasHoy =
            (int) (
                $resultado['total']
                ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | VENTAS DEL MES
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query(
            "
            SELECT
                COALESCE(
                    SUM(monto_centavos),
                    0
                ) AS total
            FROM solicitudes
            WHERE
                payphone_status = 'Approved'
                AND created_at >= DATE_TRUNC(
                    'month',
                    CURRENT_DATE
                )::date
                AND created_at <
                    (
                        DATE_TRUNC(
                            'month',
                            CURRENT_DATE
                        )
                        + INTERVAL '1 month'
                    )::date
            "
        );

        $resultado = $stmt->fetch();

        $ventasMes =
            (int) (
                $resultado['total']
                ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | TOTAL DE SOLICITUDES
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query(
            "
            SELECT COUNT(*) AS total
            FROM solicitudes
            "
        );

        $resultado = $stmt->fetch();

        $totalSolicitudes =
            (int) (
                $resultado['total']
                ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | SOLICITUDES CON ERROR
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query(
            "
            SELECT COUNT(*) AS total
            FROM solicitudes
            WHERE estado = 'error'
            "
        );

        $resultado = $stmt->fetch();

        $totalErrores =
            (int) (
                $resultado['total']
                ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | ÚLTIMAS COMPRAS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query(
            "
            SELECT
                id,
                client_transaction_id,
                estado,
                monto_centavos,
                datos_solicitud,
                payphone_status,
                enext_procesado,
                created_at
            FROM solicitudes
            WHERE payphone_status = 'Approved'
            ORDER BY id DESC
            LIMIT 10
            "
        );

        $ultimasCompras =
            $stmt->fetchAll();


    } catch (Throwable $e) {

        error_log(
            'PRO-FIRMA / Panel PostgreSQL: ' .
            $e->getMessage()
        );

        $errorBaseDatos =
            'No fue posible cargar la información de PostgreSQL.';
    }
}


/*
|--------------------------------------------------------------------------
| FUNCIONES DEL PANEL
|--------------------------------------------------------------------------
*/

function escapar(
    mixed $valor
): string {

    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


function leerDatosSolicitud(
    mixed $datos
): array {

    if (is_array($datos)) {
        return $datos;
    }

    if (
        $datos === null ||
        $datos === ''
    ) {
        return [];
    }

    $resultado =
        json_decode(
            (string) $datos,
            true
        );

    return is_array($resultado)
        ? $resultado
        : [];
}


function formatearDinero(
    int $centavos
): string {

    return '$' .
        number_format(
            $centavos / 100,
            2,
            '.',
            ','
        );
}


function formatearFecha(
    mixed $fecha
): string {

    if (
        $fecha === null ||
        trim((string) $fecha) === ''
    ) {
        return '-';
    }

    try {

        $objetoFecha =
            new DateTime(
                (string) $fecha
            );

        return $objetoFecha->format(
            'd/m/Y'
        );

    } catch (Throwable $e) {

        return escapar($fecha);
    }
}


function obtenerNombreCliente(
    array $datos
): string {

    $nombres =
        trim(
            (string) (
                $datos['nombres']
                ?? $datos['nombre']
                ?? ''
            )
        );

    $apellidos =
        trim(
            (string) (
                $datos['apellidos']
                ?? $datos['apellido']
                ?? ''
            )
        );

    $nombreCompleto =
        trim(
            $nombres . ' ' . $apellidos
        );

    if ($nombreCompleto === '') {
        return 'Sin nombre';
    }

    return $nombreCompleto;
}


function obtenerCorreo(
    array $datos
): string {

    return trim(
        (string) (
            $datos['correo']
            ?? $datos['email']
            ?? ''
        )
    );
}


function obtenerDocumento(
    array $datos
): string {

    return trim(
        (string) (
            $datos['cedula']
            ?? $datos['ruc']
            ?? $datos['documento']
            ?? ''
        )
    );
}


function obtenerPlan(
    array $datos
): string {

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

    <title>
        Panel Administrativo | PRO-FIRMA
    </title>

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
            --azul-claro: #eef5fb;
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

        /* =========================
           MENÚ LATERAL
        ========================== */

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

            border-bottom:
                1px solid rgba(255,255,255,.14);
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

            border-top:
                1px solid rgba(255,255,255,.14);
        }

        /* =========================
           CONTENIDO PRINCIPAL
        ========================== */

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

        /* =========================
           BIENVENIDA
        ========================== */

        .welcome {
            background: linear-gradient(
                120deg,
                var(--azul),
                #0b5597
            );

            border-radius: 18px;
            padding: 30px 34px;
            color: white;

            display: flex;
            justify-content: space-between;
            align-items: center;

            box-shadow:
                0 12px 30px rgba(7,57,107,.16);

            margin-bottom: 28px;
        }

        .welcome h1 {
            font-size: 27px;
            margin-bottom: 8px;
        }

        .welcome p {
            color: rgba(255,255,255,.80);
            font-size: 14px;
        }

        .welcome-icon {
            font-size: 65px;
            opacity: .15;
        }

        /* =========================
           TARJETAS
        ========================== */

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .card {
            background: white;
            border: 1px solid var(--borde);
            border-radius: 15px;
            padding: 22px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            box-shadow:
                0 5px 18px rgba(18,38,63,.05);
        }

        .card-info span {
            color: var(--texto-suave);
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .card-info h2 {
            font-size: 27px;
            margin-top: 8px;
            color: var(--texto);
        }

        .card-icon {
            width: 52px;
            height: 52px;
            border-radius: 13px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 21px;
        }

        .icon-blue {
            background: #eaf3fb;
            color: var(--azul);
        }

        .icon-green {
            background: #e7f7f0;
            color: var(--verde);
        }

        .icon-yellow {
            background: #fff6df;
            color: var(--amarillo);
        }

        .icon-red {
            background: #fdecec;
            color: var(--rojo);
        }

        /* =========================
           PANEL TABLA
        ========================== */

        .panel {
            background: white;
            border: 1px solid var(--borde);
            border-radius: 16px;
            overflow: hidden;

            box-shadow:
                0 5px 18px rgba(18,38,63,.05);
        }

        .panel-header {
            padding: 21px 24px;

            display: flex;
            justify-content: space-between;
            align-items: center;

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

        .report-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            border: none;
            background: var(--azul);
            color: white;

            padding: 11px 16px;
            border-radius: 9px;

            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
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
            letter-spacing: .4px;
        }

        td {
            padding: 16px 18px;
            border-top: 1px solid #edf1f5;
            font-size: 13px;
        }

        .client strong {
            display: block;
            color: var(--texto);
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
            cursor: pointer;
            text-decoration: none;
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

        /* =========================
           RESPONSIVE
        ========================== */

        @media (max-width: 1100px) {

            .cards {
                grid-template-columns:
                    repeat(2, 1fr);
            }
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

            .cards {
                grid-template-columns: 1fr;
            }

            .welcome {
                padding: 24px;
            }

            .welcome h1 {
                font-size: 21px;
            }

            .welcome-icon {
                display: none;
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

    <!-- MENÚ LATERAL -->

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
                <a
                    href="index.php"
                    class="active"
                >
                    <i class="fa-solid fa-house"></i>
                    <span>Inicio</span>
                </a>
            </li>


            <li>
                <a href="ventas.php">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Ventas</span>
                </a>
            </li>


            <li>
                <a href="#">
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


    <!-- CONTENIDO -->

    <main class="main">

        <header class="topbar">

            <div class="topbar-left">

                <h3>
                    Panel Administrativo
                </h3>

                <p>
                    Gestión interna de PRO-FIRMA
                </p>

            </div>


            <div class="admin-user">

                <div class="admin-circle">

                    <i class="fa-solid fa-user-shield"></i>

                </div>

                <div>

                    <strong>
                        Administrador
                    </strong>

                    <span>
                        PRO-FIRMA
                    </span>

                </div>

            </div>

        </header>


        <section class="content">

            <!-- BIENVENIDA -->

            <div class="welcome">

                <div>

                    <h1>
                        Bienvenido a PRO-FIRMA
                    </h1>

                    <p>
                        Consulta las ventas, solicitudes y operaciones
                        realizadas desde la plataforma.
                    </p>

                </div>


                <div class="welcome-icon">

                    <i class="fa-solid fa-file-signature"></i>

                </div>

            </div>


            <?php if ($errorBaseDatos !== ''): ?>

                <div class="database-error">

                    <strong>
                        Base de datos:
                    </strong>

                    <?= escapar($errorBaseDatos) ?>

                </div>

            <?php endif; ?>


            <!-- TARJETAS -->

            <div class="cards">


                <div class="card">

                    <div class="card-info">

                        <span>
                            Ventas de hoy
                        </span>

                        <h2>
                            <?= escapar(
                                formatearDinero(
                                    $ventasHoy
                                )
                            ) ?>
                        </h2>

                    </div>


                    <div class="card-icon icon-green">

                        <i class="fa-solid fa-dollar-sign"></i>

                    </div>

                </div>


                <div class="card">

                    <div class="card-info">

                        <span>
                            Ventas del mes
                        </span>

                        <h2>
                            <?= escapar(
                                formatearDinero(
                                    $ventasMes
                                )
                            ) ?>
                        </h2>

                    </div>


                    <div class="card-icon icon-blue">

                        <i class="fa-solid fa-chart-line"></i>

                    </div>

                </div>


                <div class="card">

                    <div class="card-info">

                        <span>
                            Solicitudes
                        </span>

                        <h2>
                            <?= escapar(
                                $totalSolicitudes
                            ) ?>
                        </h2>

                    </div>


                    <div class="card-icon icon-yellow">

                        <i class="fa-solid fa-file-lines"></i>

                    </div>

                </div>


                <div class="card">

                    <div class="card-info">

                        <span>
                            Con errores
                        </span>

                        <h2>
                            <?= escapar(
                                $totalErrores
                            ) ?>
                        </h2>

                    </div>


                    <div class="card-icon icon-red">

                        <i class="fa-solid fa-triangle-exclamation"></i>

                    </div>

                </div>

            </div>


            <!-- ÚLTIMAS COMPRAS -->

            <div class="panel">

                <div class="panel-header">

                    <div>

                        <h3>
                            Últimas compras
                        </h3>

                        <p>
                            Actividad reciente de la plataforma PRO-FIRMA
                        </p>

                    </div>


                    <a
                        class="report-button"
                        href="reportes.php"
                    >

                        <i class="fa-solid fa-chart-column"></i>

                        Ver reportes

                    </a>

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

                        <?php if (empty($ultimasCompras)): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="empty-table"
                                >
                                    No existen compras aprobadas
                                    para mostrar todavía.
                                </td>

                            </tr>

                        <?php else: ?>


                            <?php foreach ($ultimasCompras as $compra): ?>

                                <?php

                                $datos =
                                    leerDatosSolicitud(
                                        $compra['datos_solicitud']
                                        ?? null
                                    );

                                $nombreCliente =
                                    obtenerNombreCliente(
                                        $datos
                                    );

                                $correo =
                                    obtenerCorreo(
                                        $datos
                                    );

                                $documento =
                                    obtenerDocumento(
                                        $datos
                                    );

                                $plan =
                                    obtenerPlan(
                                        $datos
                                    );

                                $payphoneStatus =
                                    trim(
                                        (string) (
                                            $compra['payphone_status']
                                            ?? ''
                                        )
                                    );

                                $enextProcesado =
                                    filter_var(
                                        $compra['enext_procesado']
                                        ?? false,
                                        FILTER_VALIDATE_BOOLEAN
                                    );

                                $estado =
                                    trim(
                                        (string) (
                                            $compra['estado']
                                            ?? ''
                                        )
                                    );

                                ?>


                                <tr>

                                    <td>

                                        <?= escapar(
                                            formatearFecha(
                                                $compra['created_at']
                                                ?? null
                                            )
                                        ) ?>

                                    </td>


                                    <td class="client">

                                        <strong>

                                            <?= escapar(
                                                $nombreCliente
                                            ) ?>

                                        </strong>

                                        <span>

                                            <?= escapar(
                                                $correo !== ''
                                                    ? $correo
                                                    : 'Sin correo'
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?= escapar(
                                            $documento !== ''
                                                ? $documento
                                                : '-'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= escapar(
                                            $plan !== ''
                                                ? $plan
                                                : '-'
                                        ) ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= escapar(
                                                formatearDinero(
                                                    (int) (
                                                        $compra['monto_centavos']
                                                        ?? 0
                                                    )
                                                )
                                            ) ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?php if ($payphoneStatus === 'Approved'): ?>

                                            <span class="status approved">
                                                Aprobado
                                            </span>

                                        <?php else: ?>

                                            <span class="status pending">

                                                <?= escapar(
                                                    $payphoneStatus !== ''
                                                        ? $payphoneStatus
                                                        : 'Pendiente'
                                                ) ?>

                                            </span>

                                        <?php endif; ?>

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

                                        <button
                                            class="view-button"
                                            type="button"
                                        >

                                            <i class="fa-solid fa-eye"></i>

                                            Ver

                                        </button>

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
