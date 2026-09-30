<?php
session_start();

if (empty($_SESSION['profirma_admin'])) {
    header('Location: login.php');
    exit;
}
?>
<?php
/*
|--------------------------------------------------------------------------
| PROFIRMA - PANEL ADMINISTRATIVO
|--------------------------------------------------------------------------
| Primera versión: DISEÑO VISUAL
| Todavía NO consulta PostgreSQL.
| NO modifica PayPhone ni eNext.
|--------------------------------------------------------------------------
*/
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Panel Administrativo | PRO-FIRMA</title>

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

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
            box-shadow: 0 12px 30px rgba(7,57,107,.16);
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
            box-shadow: 0 5px 18px rgba(18,38,63,.05);
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
            box-shadow: 0 5px 18px rgba(18,38,63,.05);
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
            border: none;
            background: var(--azul);
            color: white;
            padding: 11px 16px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 13px;
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
        }

        .demo-note {
            margin-top: 18px;
            padding: 13px 16px;
            background: #fff9e8;
            border: 1px solid #f4df9b;
            color: #7b641d;
            border-radius: 10px;
            font-size: 12px;
        }

        /* =========================
           RESPONSIVE
        ========================== */

        @media (max-width: 1100px) {
            .cards {
                grid-template-columns: repeat(2, 1fr);
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
            <img src="../logo.jpeg" alt="PROFIRMA">

            <div class="logo-text">
                <h2>PROFIRMA</h2>
                <span>Administración</span>
            </div>
        </div>

        <div class="menu-title">MENÚ PRINCIPAL</div>

        <ul class="menu">

            <li>
                <a href="#" class="active">
                    <i class="fa-solid fa-house"></i>
                    <span>Inicio</span>
                </a>
            </li>

            <li>
                <a href="#">
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
                <a href="#">
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
                <h3>Panel Administrativo</h3>
                <p>Gestión interna de PROFIRMA</p>
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

            <!-- BIENVENIDA -->
            <div class="welcome">

                <div>
                    <h1>Bienvenido a PROFIRMA</h1>
                    <p>
                        Consulta las ventas, solicitudes y operaciones
                        realizadas desde la plataforma.
                    </p>
                </div>

                <div class="welcome-icon">
                    <i class="fa-solid fa-file-signature"></i>
                </div>

            </div>

            <!-- TARJETAS -->
            <div class="cards">

                <div class="card">
                    <div class="card-info">
                        <span>Ventas de hoy</span>
                        <h2>$48.00</h2>
                    </div>

                    <div class="card-icon icon-green">
                        <i class="fa-solid fa-dollar-sign"></i>
                    </div>
                </div>

                <div class="card">
                    <div class="card-info">
                        <span>Ventas del mes</span>
                        <h2>$348.00</h2>
                    </div>

                    <div class="card-icon icon-blue">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>

                <div class="card">
                    <div class="card-info">
                        <span>Solicitudes</span>
                        <h2>18</h2>
                    </div>

                    <div class="card-icon icon-yellow">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                </div>

                <div class="card">
                    <div class="card-info">
                        <span>Con errores</span>
                        <h2>1</h2>
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
                        <h3>Últimas compras</h3>
                        <p>Actividad reciente de la plataforma PROFIRMA</p>
                    </div>

                    <button class="report-button">
                        <i class="fa-solid fa-chart-column"></i>
                        Ver reportes
                    </button>

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

                            <tr>
                                <td>30/09/2026</td>

                                <td class="client">
                                    <strong>Cliente de ejemplo</strong>
                                    <span>cliente@ejemplo.com</span>
                                </td>

                                <td>XXXXXXXXXX</td>
                                <td>15 Días</td>
                                <td><strong>$8.00</strong></td>

                                <td>
                                    <span class="status approved">
                                        Aprobado
                                    </span>
                                </td>

                                <td>
                                    <span class="status processed">
                                        Procesado
                                    </span>
                                </td>

                                <td>
                                    <button class="view-button">
                                        <i class="fa-solid fa-eye"></i>
                                        Ver
                                    </button>
                                </td>
                            </tr>

                            <tr>
                                <td>30/09/2026</td>

                                <td class="client">
                                    <strong>Cliente de ejemplo</strong>
                                    <span>cliente@ejemplo.com</span>
                                </td>

                                <td>XXXXXXXXXX</td>
                                <td>1 Año</td>
                                <td><strong>$20.00</strong></td>

                                <td>
                                    <span class="status approved">
                                        Aprobado
                                    </span>
                                </td>

                                <td>
                                    <span class="status processed">
                                        Procesado
                                    </span>
                                </td>

                                <td>
                                    <button class="view-button">
                                        <i class="fa-solid fa-eye"></i>
                                        Ver
                                    </button>
                                </td>
                            </tr>

                            <tr>
                                <td>29/09/2026</td>

                                <td class="client">
                                    <strong>Cliente de ejemplo</strong>
                                    <span>cliente@ejemplo.com</span>
                                </td>

                                <td>XXXXXXXXXX</td>
                                <td>2 Años</td>
                                <td><strong>$30.00</strong></td>

                                <td>
                                    <span class="status approved">
                                        Aprobado
                                    </span>
                                </td>

                                <td>
                                    <span class="status pending">
                                        Pendiente
                                    </span>
                                </td>

                                <td>
                                    <button class="view-button">
                                        <i class="fa-solid fa-eye"></i>
                                        Ver
                                    </button>
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

            <div class="demo-note">
                <strong>Vista de diseño:</strong>
                los valores y clientes mostrados en este momento son ejemplos.
                En la siguiente etapa conectaremos este panel con PostgreSQL
                para mostrar las compras reales de PROFIRMA.
            </div>

        </section>

    </main>

</body>
</html>
