<?php

session_start();

if (empty($_SESSION['profirma_admin'])) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('America/Guayaquil');

/*
|--------------------------------------------------------------------------
| PROFIRMA - REPORTES
|--------------------------------------------------------------------------
| SOLO lectura de PostgreSQL.
| NO modifica PayPhone.
| NO modifica eNext.
|--------------------------------------------------------------------------
*/

$ventasHoy = 0;
$ventasMes = 0;
$ventasTotal = 0;

$totalSolicitudes = 0;
$totalAprobadas = 0;
$totalProcesadas = 0;
$totalErrores = 0;
$totalPendientes = 0;

$ventas = [];
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

        $dbUser = isset($db['user'])
            ? urldecode((string) $db['user'])
            : '';

        $dbPass = isset($db['pass'])
            ? urldecode((string) $db['pass'])
            : '';

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
        | VENTAS DE HOY
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COALESCE(SUM(monto_centavos), 0) AS total
            FROM solicitudes
            WHERE
                payphone_status = 'Approved'
                AND created_at = CURRENT_DATE
        ");

        $ventasHoy = (int) (($stmt->fetch()['total'] ?? 0));


        /*
        |--------------------------------------------------------------------------
        | VENTAS DEL MES
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COALESCE(SUM(monto_centavos), 0) AS total
            FROM solicitudes
            WHERE
                payphone_status = 'Approved'
                AND created_at >= DATE_TRUNC('month', CURRENT_DATE)::date
                AND created_at <
                    (
                        DATE_TRUNC('month', CURRENT_DATE)
                        + INTERVAL '1 month'
                    )::date
        ");

        $ventasMes = (int) (($stmt->fetch()['total'] ?? 0));


        /*
        |--------------------------------------------------------------------------
        | TOTAL HISTÓRICO VENDIDO
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COALESCE(SUM(monto_centavos), 0) AS total
            FROM solicitudes
            WHERE payphone_status = 'Approved'
        ");

        $ventasTotal = (int) (($stmt->fetch()['total'] ?? 0));


        /*
        |--------------------------------------------------------------------------
        | TOTAL SOLICITUDES
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COUNT(*) AS total
            FROM solicitudes
        ");

        $totalSolicitudes = (int) (($stmt->fetch()['total'] ?? 0));


        /*
        |--------------------------------------------------------------------------
        | PAGOS APROBADOS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COUNT(*) AS total
            FROM solicitudes
            WHERE payphone_status = 'Approved'
        ");

        $totalAprobadas = (int) (($stmt->fetch()['total'] ?? 0));


        /*
        |--------------------------------------------------------------------------
        | PROCESADAS POR ENEXT
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COUNT(*) AS total
            FROM solicitudes
            WHERE enext_procesado = TRUE
        ");

        $totalProcesadas = (int) (($stmt->fetch()['total'] ?? 0));


        /*
        |--------------------------------------------------------------------------
        | ERRORES
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COUNT(*) AS total
            FROM solicitudes
            WHERE estado = 'error'
        ");

        $totalErrores = (int) (($stmt->fetch()['total'] ?? 0));


        /*
        |--------------------------------------------------------------------------
        | PENDIENTES
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT COUNT(*) AS total
            FROM solicitudes
            WHERE estado = 'pendiente'
        ");

        $totalPendientes = (int) (($stmt->fetch()['total'] ?? 0));


        /*
        |--------------------------------------------------------------------------
        | HISTORIAL DE VENTAS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
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
        ");

        $ventas = $stmt->fetchAll();

    } catch (Throwable $e) {

        error_log(
            'PROFIRMA / Reportes: ' .
            $e->getMessage()
        );

        $errorBaseDatos =
            'No fue posible cargar la información de reportes.';
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


function fechaReporte(mixed $fecha): string
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

<title>Reportes | PROFIRMA</title>

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

/* SIDEBAR */

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


/* CONTENIDO */

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


/* ENCABEZADO */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 20px;

    margin-bottom: 28px;
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

.pdf-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    background: var(--rojo);
    color: white;

    text-decoration: none;

    border-radius: 9px;
    padding: 12px 17px;

    font-size: 13px;
    font-weight: bold;
}

.pdf-button:hover {
    opacity: .9;
}


/* TARJETAS */

.cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);

    gap: 18px;
    margin-bottom: 18px;
}

.cards.second {
    grid-template-columns: repeat(4, 1fr);
    margin-bottom: 28px;
}

.card {
    background: white;

    border: 1px solid var(--borde);
    border-radius: 15px;

    padding: 22px;

    box-shadow:
        0 5px 18px rgba(18,38,63,.05);
}

.card span {
    color: var(--texto-suave);

    font-size: 11px;
    font-weight: bold;

    text-transform: uppercase;
}

.card h2 {
    margin-top: 9px;
    font-size: 27px;
}

.green h2 {
    color: var(--verde);
}

.blue h2 {
    color: var(--azul);
}

.red h2 {
    color: var(--rojo);
}

.yellow h2 {
    color: var(--amarillo);
}


/* TABLA */

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

    border-top:
        1px solid #edf1f5;

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

.database-error {
    margin-bottom: 18px;

    padding: 13px 16px;

    background: #fdeaea;

    border:
        1px solid #f3bcbc;

    color: #9f2727;

    border-radius: 10px;

    font-size: 12px;
}

.empty-table {
    text-align: center;

    color: var(--texto-suave);

    padding: 35px 20px;
}


/* RESPONSIVE */

@media (max-width: 1100px) {

    .cards,
    .cards.second {
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

    .content {
        padding: 22px 18px;
    }

    .topbar {
        padding: 0 18px;
    }

    .cards,
    .cards.second {
        grid-template-columns: 1fr;
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }
}

</style>

</head>


<body>


<!-- MENÚ -->

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

            <a
                href="reportes.php"
                class="active"
            >

                <i class="fa-solid fa-chart-column"></i>

                <span>Reportes</span>

            </a>

        </li>

    </ul>


    <div class="logout">

        <a href="logout.php">

            <i class="fa-solid fa-right-from-bracket"></i>

            <span>Cerrar sesión</span>

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
            Gestión interna de PROFIRMA
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
                PROFIRMA
            </span>

        </div>

    </div>

</header>


<section class="content">


    <div class="page-header">

        <div>

            <h1>
                Reportes
            </h1>

            <p>
                Resumen general de las operaciones registradas en PROFIRMA.
            </p>

        </div>


        <a
            class="pdf-button"
            href="reporte_pdf.php"
            target="_blank"
        >

            <i class="fa-solid fa-file-pdf"></i>

            Generar PDF completo

        </a>

    </div>


    <?php if ($errorBaseDatos !== ''): ?>

        <div class="database-error">

            <?= e($errorBaseDatos) ?>

        </div>

    <?php endif; ?>


    <!-- RESUMEN DE DINERO -->

    <div class="cards">

        <div class="card green">

            <span>
                Ventas de hoy
            </span>

            <h2>
                <?= e(dinero($ventasHoy)) ?>
            </h2>

        </div>


        <div class="card blue">

            <span>
                Ventas del mes
            </span>

            <h2>
                <?= e(dinero($ventasMes)) ?>
            </h2>

        </div>


        <div class="card green">

            <span>
                Total vendido
            </span>

            <h2>
                <?= e(dinero($ventasTotal)) ?>
            </h2>

        </div>

    </div>


    <!-- RESUMEN DE SOLICITUDES -->

    <div class="cards second">

        <div class="card">

            <span>
                Solicitudes
            </span>

            <h2>
                <?= e($totalSolicitudes) ?>
            </h2>

        </div>


        <div class="card green">

            <span>
                Pagos aprobados
            </span>

            <h2>
                <?= e($totalAprobadas) ?>
            </h2>

        </div>


        <div class="card blue">

            <span>
                Procesadas eNext
            </span>

            <h2>
                <?= e($totalProcesadas) ?>
            </h2>

        </div>


        <div class="card red">

            <span>
                Con errores
            </span>

            <h2>
                <?= e($totalErrores) ?>
            </h2>

        </div>

    </div>


    <!-- HISTORIAL -->

    <div class="panel">

        <div class="panel-header">

            <h3>
                Historial de ventas
            </h3>

            <p>
                Todas las ventas aprobadas registradas en PROFIRMA.
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

                    </tr>

                </thead>


                <tbody>


                <?php if (empty($ventas)): ?>

                    <tr>

                        <td
                            colspan="7"
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

                        $nombre =
                            nombreCliente($datos);

                        $correo =
                            correoCliente($datos);

                        $documento =
                            documentoCliente($datos);

                        $plan =
                            planCliente($datos);

                        $enextProcesado =
                            filter_var(
                                $venta['enext_procesado']
                                ?? false,
                                FILTER_VALIDATE_BOOLEAN
                            );

                        $estado =
                            trim(
                                (string) (
                                    $venta['estado']
                                    ?? ''
                                )
                            );

                        ?>


                        <tr>


                            <td>

                                <?= e(
                                    fechaReporte(
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
