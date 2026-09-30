<?php

session_start();

if (empty($_SESSION['profirma_admin'])) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('America/Guayaquil');

/*
|--------------------------------------------------------------------------
| PROFIRMA - REPORTE PARA PDF / IMPRESIÓN
|--------------------------------------------------------------------------
| SOLO LECTURA.
| No modifica PayPhone.
| No modifica eNext.
| No requiere librerías adicionales.
|--------------------------------------------------------------------------
*/

$ventas = [];
$errorBaseDatos = '';

$resumen = [
    'solicitudes'      => 0,
    'pagos_aprobados' => 0,
    'procesadas'      => 0,
    'errores'         => 0,
    'pendientes'      => 0,
    'ventas_hoy'      => 0,
    'ventas_mes'      => 0,
    'total_vendido'   => 0
];


/*
|--------------------------------------------------------------------------
| CONEXIÓN POSTGRESQL
|--------------------------------------------------------------------------
*/

$databaseUrl = trim((string) getenv('DATABASE_URL'));

if ($databaseUrl === '') {

    $errorBaseDatos = 'No fue posible conectar con la base de datos.';

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

        $dbPort = isset($db['port'])
            ? (int) $db['port']
            : 5432;

        $dbName = ltrim(
            (string) $db['path'],
            '/'
        );

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
        | RESUMEN
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT

                COUNT(*) AS solicitudes,

                COUNT(*) FILTER (
                    WHERE payphone_status = 'Approved'
                ) AS pagos_aprobados,

                COUNT(*) FILTER (
                    WHERE enext_procesado = TRUE
                ) AS procesadas,

                COUNT(*) FILTER (
                    WHERE estado = 'error'
                ) AS errores,

                COUNT(*) FILTER (
                    WHERE estado = 'pendiente'
                ) AS pendientes,

                COALESCE(
                    SUM(monto_centavos) FILTER (
                        WHERE payphone_status = 'Approved'
                    ),
                    0
                ) AS total_vendido,

                COALESCE(
                    SUM(monto_centavos) FILTER (
                        WHERE
                            payphone_status = 'Approved'
                            AND created_at = CURRENT_DATE
                    ),
                    0
                ) AS ventas_hoy,

                COALESCE(
                    SUM(monto_centavos) FILTER (
                        WHERE
                            payphone_status = 'Approved'
                            AND created_at >=
                                DATE_TRUNC(
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
                    ),
                    0
                ) AS ventas_mes

            FROM solicitudes
        ");

        $resultado = $stmt->fetch();

        if (is_array($resultado)) {
            $resumen = array_merge(
                $resumen,
                $resultado
            );
        }


        /*
        |--------------------------------------------------------------------------
        | HISTORIAL COMPLETO DE VENTAS APROBADAS
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
                error_mensaje,
                created_at
            FROM solicitudes
            WHERE payphone_status = 'Approved'
            ORDER BY id DESC
        ");

        $ventas = $stmt->fetchAll();


    } catch (Throwable $e) {

        error_log(
            'PROFIRMA / Reporte impresión: ' .
            $e->getMessage()
        );

        $errorBaseDatos =
            'No fue posible cargar la información del reporte.';
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


function leerDatos(mixed $datos): array
{
    if (is_array($datos)) {
        return $datos;
    }

    if (
        $datos === null ||
        $datos === ''
    ) {
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


function telefonoCliente(array $datos): string
{
    return trim(
        (string) (
            $datos['celular']
            ?? $datos['telefono']
            ?? $datos['phone']
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


$fechaGeneracion = date('d/m/Y H:i');

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
    Reporte PROFIRMA
</title>


<style>

/* ==========================================================
   PANTALLA
========================================================== */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #eef3f8;
    color: #172033;
}

.toolbar {
    position: sticky;
    top: 0;
    z-index: 100;

    background: #07396b;

    padding: 14px 24px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    box-shadow:
        0 3px 12px rgba(0,0,0,.15);
}

.toolbar-title {
    color: white;
}

.toolbar-title strong {
    display: block;
    font-size: 17px;
}

.toolbar-title span {
    display: block;

    margin-top: 3px;

    color: rgba(255,255,255,.75);
    font-size: 11px;
}

.toolbar-buttons {
    display: flex;
    gap: 10px;
}

.button {
    border: 0;

    padding: 11px 16px;

    border-radius: 8px;

    cursor: pointer;

    font-size: 13px;
    font-weight: bold;

    text-decoration: none;
}

.button-back {
    background: white;
    color: #07396b;
}

.button-pdf {
    background: #d64545;
    color: white;
}


/* ==========================================================
   HOJA
========================================================== */

.document {
    max-width: 1200px;

    margin: 30px auto;

    background: white;

    padding: 38px;

    box-shadow:
        0 5px 25px rgba(0,0,0,.10);
}


/* ==========================================================
   ENCABEZADO
========================================================== */

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    padding-bottom: 22px;

    border-bottom: 3px solid #07396b;
}

.brand {
    display: flex;
    align-items: center;
    gap: 15px;
}

.brand img {
    width: 65px;
    height: 65px;

    object-fit: contain;
}

.brand h1 {
    margin: 0;

    color: #07396b;

    font-size: 27px;
}

.brand p {
    margin: 4px 0 0;

    color: #718096;

    font-size: 12px;
}

.report-info {
    text-align: right;
}

.report-info strong {
    display: block;

    color: #07396b;

    font-size: 16px;
}

.report-info span {
    display: block;

    margin-top: 5px;

    color: #718096;

    font-size: 11px;
}


/* ==========================================================
   TÍTULOS
========================================================== */

.section-title {
    margin:
        28px 0
        14px;

    color: #07396b;

    font-size: 17px;
}


/* ==========================================================
   RESUMEN
========================================================== */

.summary {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;
}

.summary-card {
    border: 1px solid #e2e8f0;

    border-radius: 9px;

    padding: 15px;

    background: #f8fafc;
}

.summary-card span {
    display: block;

    color: #718096;

    font-size: 9px;
    font-weight: bold;

    text-transform: uppercase;
}

.summary-card strong {
    display: block;

    margin-top: 7px;

    color: #07396b;

    font-size: 20px;
}

.summary-card.green strong {
    color: #16845a;
}

.summary-card.red strong {
    color: #c93636;
}


/* ==========================================================
   TABLA
========================================================== */

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    margin-top: 8px;
}

thead {
    display: table-header-group;
}

th {
    background: #07396b;
    color: white;

    padding: 10px 7px;

    text-align: left;

    font-size: 9px;
}

td {
    padding: 10px 7px;

    border-bottom:
        1px solid #e5eaf0;

    vertical-align: top;

    font-size: 9px;
}

tbody tr:nth-child(even) {
    background: #f8fafc;
}

.client-name {
    display: block;

    font-weight: bold;
}

.client-email {
    display: block;

    margin-top: 3px;

    color: #718096;

    font-size: 8px;
}

.money {
    font-weight: bold;
}

.badge {
    display: inline-block;

    padding: 4px 7px;

    border-radius: 12px;

    font-size: 8px;
    font-weight: bold;
}

.badge-green {
    background: #e5f7ef;
    color: #16845a;
}

.badge-blue {
    background: #e8f1fb;
    color: #1766a5;
}

.badge-yellow {
    background: #fff3d6;
    color: #9a6a00;
}

.badge-red {
    background: #fdeaea;
    color: #b72e2e;
}

.empty {
    padding: 30px;

    text-align: center;

    color: #718096;
}


/* ==========================================================
   PIE
========================================================== */

.footer {
    margin-top: 30px;

    padding-top: 15px;

    border-top:
        1px solid #dfe5ec;

    color: #718096;

    font-size: 9px;

    text-align: center;
}

.error-message {
    margin-bottom: 20px;

    padding: 13px;

    background: #fdeaea;

    border: 1px solid #f0bcbc;

    color: #a52c2c;

    border-radius: 8px;
}


/* ==========================================================
   IMPRESIÓN / GUARDAR COMO PDF
========================================================== */

@page {
    size: A4 landscape;
    margin: 10mm;
}

@media print {

    body {
        background: white;
    }

    .no-print {
        display: none !important;
    }

    .document {
        max-width: none;

        margin: 0;

        padding: 0;

        box-shadow: none;
    }

    .report-header {
        margin-top: 0;
    }

    .summary {
        display: table;

        width: 100%;

        border-spacing: 5px;
    }

    .summary-card {
        display: table-cell;

        width: 25%;

        page-break-inside: avoid;
    }

    table {
        page-break-inside: auto;
    }

    thead {
        display: table-header-group;
    }

    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    .footer {
        page-break-inside: avoid;
    }

}


/* ==========================================================
   MÓVIL
========================================================== */

@media screen and (max-width: 800px) {

    .document {
        margin: 15px;
        padding: 20px;
    }

    .summary {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .report-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 15px;
    }

    .report-info {
        text-align: left;
    }

    .toolbar {
        align-items: flex-start;
        gap: 12px;
        flex-direction: column;
    }

}

</style>

</head>


<body>


<!-- ========================================================
     BARRA SUPERIOR
========================================================= -->

<div class="toolbar no-print">

    <div class="toolbar-title">

        <strong>
            Reporte PROFIRMA
        </strong>

        <span>
            Vista previa antes de guardar el PDF
        </span>

    </div>


    <div class="toolbar-buttons">

        <a
            href="reportes.php"
            class="button button-back"
        >
            ← Volver a reportes
        </a>


        <button
            type="button"
            class="button button-pdf"
            onclick="window.print();"
        >
            Guardar / Imprimir PDF
        </button>

    </div>

</div>


<!-- ========================================================
     DOCUMENTO
========================================================= -->

<div class="document">


    <!-- ENCABEZADO -->

    <div class="report-header">

        <div class="brand">

            <img
                src="../logo.jpeg"
                alt="PROFIRMA"
            >

            <div>

                <h1>
                    PROFIRMA
                </h1>

                <p>
                    Firma Electrónica
                </p>

            </div>

        </div>


        <div class="report-info">

            <strong>
                Reporte Administrativo
            </strong>

            <span>
                Ventas y operaciones
            </span>

            <span>
                Generado:
                <?= e($fechaGeneracion) ?>
            </span>

        </div>

    </div>


    <?php if ($errorBaseDatos !== ''): ?>

        <div
            class="error-message"
            style="margin-top:20px;"
        >
            <?= e($errorBaseDatos) ?>
        </div>

    <?php endif; ?>


    <!-- ====================================================
         RESUMEN
    ===================================================== -->

    <h2 class="section-title">
        Resumen general
    </h2>


    <div class="summary">


        <div class="summary-card green">

            <span>
                Ventas de hoy
            </span>

            <strong>
                <?= e(
                    dinero(
                        (int) $resumen['ventas_hoy']
                    )
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <span>
                Ventas del mes
            </span>

            <strong>
                <?= e(
                    dinero(
                        (int) $resumen['ventas_mes']
                    )
                ) ?>
            </strong>

        </div>


        <div class="summary-card green">

            <span>
                Total vendido
            </span>

            <strong>
                <?= e(
                    dinero(
                        (int) $resumen['total_vendido']
                    )
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <span>
                Solicitudes
            </span>

            <strong>
                <?= e(
                    $resumen['solicitudes']
                ) ?>
            </strong>

        </div>


        <div class="summary-card green">

            <span>
                Pagos aprobados
            </span>

            <strong>
                <?= e(
                    $resumen['pagos_aprobados']
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <span>
                Procesadas eNext
            </span>

            <strong>
                <?= e(
                    $resumen['procesadas']
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <span>
                Pendientes
            </span>

            <strong>
                <?= e(
                    $resumen['pendientes']
                ) ?>
            </strong>

        </div>


        <div class="summary-card red">

            <span>
                Con errores
            </span>

            <strong>
                <?= e(
                    $resumen['errores']
                ) ?>
            </strong>

        </div>


    </div>


    <!-- ====================================================
         HISTORIAL
    ===================================================== -->

    <h2 class="section-title">
        Historial completo de ventas aprobadas
    </h2>


    <div class="table-wrap">

        <table>


            <thead>

                <tr>

                    <th>
                        Fecha
                    </th>

                    <th>
                        Cliente
                    </th>

                    <th>
                        Cédula / RUC
                    </th>

                    <th>
                        Teléfono
                    </th>

                    <th>
                        Plan
                    </th>

                    <th>
                        Valor
                    </th>

                    <th>
                        PayPhone
                    </th>

                    <th>
                        eNext
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (empty($ventas)): ?>


                <tr>

                    <td
                        colspan="8"
                        class="empty"
                    >

                        No existen ventas aprobadas
                        para mostrar.

                    </td>

                </tr>


            <?php else: ?>


                <?php foreach ($ventas as $venta): ?>


                    <?php

                    $datos = leerDatos(
                        $venta['datos_solicitud']
                        ?? null
                    );

                    $nombre =
                        nombreCliente($datos);

                    $documento =
                        documentoCliente($datos);

                    $correo =
                        correoCliente($datos);

                    $telefono =
                        telefonoCliente($datos);

                    $plan =
                        planCliente($datos);


                    $enextProcesado =
                        filter_var(
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
                                fechaReporte(
                                    $venta['created_at']
                                    ?? null
                                )
                            ) ?>

                        </td>


                        <td>

                            <span class="client-name">

                                <?= e($nombre) ?>

                            </span>


                            <span class="client-email">

                                <?= e(
                                    $correo !== ''
                                        ? $correo
                                        : '-'
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
                                $telefono !== ''
                                    ? $telefono
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


                        <td class="money">

                            <?= e(
                                dinero(
                                    (int) (
                                        $venta['monto_centavos']
                                        ?? 0
                                    )
                                )
                            ) ?>

                        </td>


                        <td>

                            <span class="badge badge-green">

                                Aprobado

                            </span>

                        </td>


                        <td>


                            <?php if ($enextProcesado): ?>


                                <span class="badge badge-blue">

                                    Procesado

                                </span>


                            <?php elseif ($estado === 'error'): ?>


                                <span class="badge badge-red">

                                    Error

                                </span>


                            <?php else: ?>


                                <span class="badge badge-yellow">

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


    <!-- PIE -->

    <div class="footer">

        Reporte generado automáticamente desde
        el Panel Administrativo de PROFIRMA.

        <br>

        Información obtenida de las operaciones
        registradas al momento de generar el reporte.

    </div>


</div>


</body>

</html>
