<?php

session_start();

if (empty($_SESSION['profirma_admin'])) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('America/Guayaquil');

?>
<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Solicitudes | PROFIRMA</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link
    href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --navy: #07396b;
    --navy-dark: #052d55;
    --blue: #0b4d8d;
    --background: #f5f7fb;
    --white: #ffffff;
    --text: #172033;
    --muted: #718096;
    --border: #e3e8ef;
    --green: #16845a;
    --red: #c93636;
}

body {
    font-family: 'Manrope', Arial, sans-serif;
    background: var(--background);
    color: var(--text);
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;

    width: 250px;
    height: 100vh;

    background: linear-gradient(
        180deg,
        #07396b 0%,
        #052d55 100%
    );

    color: white;

    display: flex;
    flex-direction: column;

    z-index: 100;
}

.logo-area {
    padding: 24px 22px;

    border-bottom:
        1px solid rgba(255,255,255,.10);
}

.logo-area img {
    width: 145px;
    max-height: 70px;

    object-fit: contain;

    background: white;

    border-radius: 10px;

    padding: 5px;
}

.logo-area span {
    display: block;

    margin-top: 9px;

    font-size: 11px;

    color: rgba(255,255,255,.65);

    letter-spacing: .5px;
}

.menu {
    padding: 20px 12px;
    flex: 1;
}

.menu a {
    display: flex;
    align-items: center;

    gap: 13px;

    color: rgba(255,255,255,.75);

    text-decoration: none;

    padding: 13px 14px;

    margin-bottom: 6px;

    border-radius: 9px;

    font-size: 13px;

    font-weight: 600;

    transition: .2s;
}

.menu a i {
    width: 20px;
    text-align: center;
}

.menu a:hover,
.menu a.active {
    background: rgba(255,255,255,.12);
    color: white;
}

.logout {
    padding: 12px;

    border-top:
        1px solid rgba(255,255,255,.10);
}


/* =========================================================
   CONTENIDO
========================================================= */

.main {
    margin-left: 250px;
    min-height: 100vh;
}

.topbar {
    height: 76px;

    background: white;

    border-bottom:
        1px solid var(--border);

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 32px;
}

.topbar h1 {
    font-size: 19px;
    color: var(--navy);
}

.topbar p {
    margin-top: 3px;

    font-size: 11px;

    color: var(--muted);
}

.admin-user {
    display: flex;
    align-items: center;

    gap: 10px;

    font-size: 12px;

    font-weight: 700;

    color: var(--navy);
}

.avatar {
    width: 38px;
    height: 38px;

    border-radius: 50%;

    background: var(--navy);

    color: white;

    display: flex;
    align-items: center;
    justify-content: center;
}

.content {
    padding: 30px 34px 50px;
}


/* =========================================================
   ENCABEZADO
========================================================= */

.page-header {
    margin-bottom: 24px;
}

.page-header h2 {
    color: var(--navy);

    font-size: 25px;

    font-weight: 800;
}

.page-header p {
    color: var(--muted);

    margin-top: 5px;

    font-size: 13px;
}


/* =========================================================
   PASOS
========================================================= */

.steps {
    display: flex;
    align-items: center;

    margin-bottom: 25px;
}

.step {
    display: flex;
    align-items: center;

    gap: 8px;

    font-size: 11px;

    font-weight: 700;

    color: #9aa5b4;
}

.step-number {
    width: 28px;
    height: 28px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #e8edf3;

    color: #7b8794;
}

.step.active {
    color: var(--navy);
}

.step.active .step-number {
    background: var(--navy);
    color: white;
}

.step-line {
    width: 50px;
    height: 2px;

    margin: 0 10px;

    background: #e1e6ec;
}


/* =========================================================
   TARJETAS
========================================================= */

.card {
    background: white;

    border: 1px solid var(--border);

    border-radius: 15px;

    padding: 25px;

    box-shadow:
        0 5px 20px rgba(15,23,42,.04);

    margin-bottom: 20px;
}

.card-title {
    color: var(--navy);

    font-size: 17px;

    font-weight: 800;

    margin-bottom: 5px;
}

.card-description {
    color: var(--muted);

    font-size: 12px;

    margin-bottom: 20px;
}


/* =========================================================
   PERSONA
========================================================= */

.person-type-grid {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 16px;
}

.person-card {
    border: 2px solid var(--border);

    border-radius: 13px;

    padding: 23px;

    cursor: pointer;

    background: white;

    transition: .2s;

    display: flex;
    align-items: center;

    gap: 16px;
}

.person-card:hover {
    border-color: #9fc1df;
    transform: translateY(-1px);
}

.person-card.active {
    border-color: var(--navy);
    background: #f3f8fc;
}

.person-icon {
    width: 50px;
    height: 50px;

    border-radius: 12px;

    background: #edf4fa;

    color: var(--navy);

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 21px;
}

.person-card.active .person-icon {
    background: var(--navy);
    color: white;
}

.person-card strong {
    display: block;

    color: var(--navy);

    font-size: 14px;
}

.person-card span {
    display: block;

    color: var(--muted);

    margin-top: 4px;

    font-size: 11px;
}


/* =========================================================
   PLANES
========================================================= */

.hidden {
    display: none !important;
}

.plan-grid {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 12px;
}

.plan {
    border: 2px solid var(--border);

    border-radius: 12px;

    padding: 17px 14px;

    background: white;

    cursor: pointer;

    transition: .2s;

    text-align: center;
}

.plan:hover {
    border-color: #a9c8e4;
}

.plan.active {
    border-color: var(--navy);
    background: #f2f7fc;
}

.plan-name {
    color: var(--navy);

    font-size: 13px;

    font-weight: 800;
}

.plan-price {
    margin-top: 6px;

    color: var(--green);

    font-size: 20px;

    font-weight: 800;
}

.plan-reference {
    display: block;

    margin-top: 2px;

    color: var(--muted);

    font-size: 9px;
}

.selection-summary {
    margin-top: 20px;

    padding: 15px 17px;

    border-radius: 11px;

    background: #f5f8fb;

    border: 1px solid #dfe8f0;

    display: flex;

    justify-content: space-between;
    align-items: center;
}

.selection-summary span {
    display: block;

    color: var(--muted);

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;
}

.selection-summary strong {
    display: block;

    margin-top: 3px;

    color: var(--navy);

    font-size: 14px;
}

.summary-price {
    text-align: right;
}

.summary-price strong {
    color: var(--green);
    font-size: 21px;
}


/* =========================================================
   FORMULARIO
========================================================= */

.form-grid {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 16px;
}

.form-grid.three {
    grid-template-columns:
        repeat(3, minmax(0, 1fr));
}

.field.full {
    grid-column: 1 / -1;
}

.field label {
    display: block;

    margin-bottom: 7px;

    color: #485467;

    font-size: 10px;

    font-weight: 800;

    text-transform: uppercase;

    letter-spacing: .3px;
}

.field input,
.field select {
    width: 100%;

    height: 44px;

    border: 1px solid #dce3ea;

    border-radius: 9px;

    padding: 0 12px;

    outline: none;

    background: #fbfcfd;

    color: var(--text);

    font-family: inherit;

    font-size: 12px;

    transition: .2s;
}

.field input:focus,
.field select:focus {
    border-color: var(--navy);

    background: white;

    box-shadow:
        0 0 0 3px rgba(7,57,107,.07);
}

.form-section-title {
    grid-column: 1 / -1;

    color: var(--navy);

    font-size: 12px;

    font-weight: 800;

    margin-top: 8px;

    padding-bottom: 8px;

    border-bottom: 1px solid var(--border);
}


/* =========================================================
   MENSAJES
========================================================= */

.admin-notice {
    display: flex;

    gap: 12px;

    margin-top: 20px;

    padding: 14px;

    background: #fff9e8;

    border: 1px solid #f0dfad;

    border-radius: 10px;

    color: #715b1b;

    font-size: 11px;

    line-height: 1.6;
}

.result-message {
    margin-top: 20px;

    padding: 17px;

    border-radius: 11px;

    font-size: 12px;

    line-height: 1.6;
}

.result-success {
    background: #eaf8f1;

    border: 1px solid #b9e4ce;

    color: #126342;
}

.result-error {
    background: #fdecec;

    border: 1px solid #f0c0c0;

    color: #a62f2f;
}

.result-message strong {
    display: block;

    margin-bottom: 4px;

    font-size: 13px;
}


/* =========================================================
   BOTÓN
========================================================= */

.actions {
    margin-top: 22px;

    display: flex;

    justify-content: flex-end;
}

.submit-button {
    border: 0;

    border-radius: 10px;

    padding: 13px 23px;

    background: linear-gradient(
        135deg,
        #07396b,
        #0b4d8d
    );

    color: white;

    font-family: inherit;

    font-size: 12px;

    font-weight: 800;

    cursor: pointer;

    display: inline-flex;

    align-items: center;

    gap: 9px;

    min-width: 160px;

    justify-content: center;
}

.submit-button:hover {
    background: var(--navy-dark);
}

.submit-button:disabled {
    opacity: .6;
    cursor: not-allowed;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .plan-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 760px) {

    .sidebar {
        display: none;
    }

    .main {
        margin-left: 0;
    }

    .content {
        padding: 20px 15px;
    }

    .person-type-grid,
    .form-grid,
    .form-grid.three {
        grid-template-columns: 1fr;
    }

    .plan-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

}

</style>

</head>

<body>


<!-- ======================================================
     SIDEBAR
======================================================= -->

<aside class="sidebar">

    <div class="logo-area">

        <img
            src="logo.jpeg"
            alt="PROFIRMA"
        >

        <span>
            Administración
        </span>

    </div>


    <nav class="menu">

        <a href="index.php">
            <i class="fa-solid fa-house"></i>
            Inicio
        </a>

        <a href="ventas.php">
            <i class="fa-solid fa-chart-line"></i>
            Ventas
        </a>

        <a
            href="solicitudes.php"
            class="active"
        >
            <i class="fa-solid fa-file-signature"></i>
            Solicitudes
        </a>

        <a href="reportes.php">
            <i class="fa-solid fa-chart-pie"></i>
            Reportes
        </a>

    </nav>


    <div class="logout">

        <a
            href="logout.php"
            style="
                display:flex;
                align-items:center;
                gap:12px;
                color:rgba(255,255,255,.75);
                text-decoration:none;
                padding:13px 14px;
                font-size:13px;
                font-weight:600;
            "
        >
            <i class="fa-solid fa-right-from-bracket"></i>
            Cerrar sesión
        </a>

    </div>

</aside>


<!-- ======================================================
     CONTENIDO
======================================================= -->

<main class="main">


    <header class="topbar">

        <div>

            <h1>
                Panel Administrativo
            </h1>

            <p>
                Gestión interna de PROFIRMA
            </p>

        </div>


        <div class="admin-user">

            <div class="avatar">
                <i class="fa-solid fa-user"></i>
            </div>

            Administrador

        </div>

    </header>


    <div class="content">


        <div class="page-header">

            <h2>
                Emitir firma electrónica
            </h2>

            <p>
                Emisión administrativa directa mediante eNext.
                No utiliza PayPhone.
            </p>

        </div>


        <!-- PASOS -->

        <div class="steps">

            <div
                class="step active"
                id="step1"
            >
                <div class="step-number">1</div>
                Tipo de persona
            </div>

            <div class="step-line"></div>

            <div
                class="step"
                id="step2"
            >
                <div class="step-number">2</div>
                Firma
            </div>

            <div class="step-line"></div>

            <div
                class="step"
                id="step3"
            >
                <div class="step-number">3</div>
                Datos
            </div>

        </div>


        <!-- ==================================================
             TIPO DE PERSONA
        =================================================== -->

        <section class="card">

            <div class="card-title">
                Primero elige el tipo de persona
            </div>

            <div class="card-description">
                Selecciona para quién se emitirá la firma.
            </div>


            <div class="person-type-grid">


                <div
                    class="person-card"
                    data-type="natural"
                >

                    <div class="person-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>

                    <div>

                        <strong>
                            Persona Natural
                        </strong>

                        <span>
                            Firma electrónica para persona natural.
                        </span>

                    </div>

                </div>


                <div
                    class="person-card"
                    data-type="juridica"
                >

                    <div class="person-icon">
                        <i class="fa-solid fa-building"></i>
                    </div>

                    <div>

                        <strong>
                            Persona Jurídica
                        </strong>

                        <span>
                            Firma electrónica para empresa.
                        </span>

                    </div>

                </div>


            </div>

        </section>


        <!-- ==================================================
             PLANES
        =================================================== -->

        <section
            class="card hidden"
            id="plansSection"
        >

            <div class="card-title">
                Selecciona la firma
            </div>

            <div
                class="card-description"
                id="plansDescription"
            >
            </div>


            <div
                class="plan-grid"
                id="plansContainer"
            >
            </div>


            <div
                class="selection-summary hidden"
                id="selectionSummary"
            >

                <div>

                    <span>
                        Selección
                    </span>

                    <strong id="summaryPlan">
                        -
                    </strong>

                </div>


                <div class="summary-price">

                    <span>
                        Precio de referencia
                    </span>

                    <strong id="summaryPrice">
                        $0.00
                    </strong>

                </div>

            </div>

        </section>


        <!-- ==================================================
             FORMULARIO
        =================================================== -->

        <section
            class="card hidden"
            id="formSection"
        >

            <div class="card-title">
                Datos para la emisión
            </div>

            <div class="card-description">
                Ingresa la información exactamente como consta
                en los documentos del titular.
            </div>


            <form
                id="firmaForm"
                autocomplete="off"
            >


                <div class="form-grid">


                    <div class="form-section-title">
                        Información del titular
                    </div>


                    <div class="field">

                        <label>
                            Nombres *
                        </label>

                        <input
                            type="text"
                            id="nombres"
                            name="nombres"
                            required
                            placeholder="Ej. Carlos Alfredo"
                        >

                    </div>


                    <div class="field">

                        <label>
                            Apellidos *
                        </label>

                        <input
                            type="text"
                            id="apellidos"
                            name="apellidos"
                            required
                            placeholder="Ej. Mendoza Paredes"
                        >

                    </div>


                    <div class="field">

                        <label>
                            Cédula *
                        </label>

                        <input
                            type="text"
                            id="cedula"
                            name="cedula"
                            required
                            maxlength="10"
                            inputmode="numeric"
                            placeholder="1712345678"
                        >

                    </div>


                    <div class="field">

                        <label>
                            Código dactilar *
                        </label>

                        <input
                            type="text"
                            id="codigo_dactilar"
                            name="codigo_dactilar"
                            required
                            placeholder="Ej. V123456789"
                        >

                    </div>


                    <div class="form-section-title">
                        Contacto
                    </div>


                    <div class="field">

                        <label>
                            Teléfono *
                        </label>

                        <input
                            type="tel"
                            id="celular"
                            name="celular"
                            required
                            placeholder="0991234567"
                        >

                    </div>


                    <div class="field">

                        <label>
                            Correo electrónico *
                        </label>

                        <input
                            type="email"
                            id="correo"
                            name="correo"
                            required
                            placeholder="cliente@correo.com"
                        >

                    </div>


                    <div class="form-section-title">
                        Dirección
                    </div>


                    <div class="field full">

                        <label>
                            Dirección de residencia *
                        </label>

                        <input
                            type="text"
                            id="direccion"
                            name="direccion"
                            required
                            placeholder="Av. principal y calle secundaria"
                        >

                    </div>


                    <div
                        class="form-grid three"
                        style="grid-column:1/-1;"
                    >


                        <div class="field">

                            <label>
                                Provincia *
                            </label>

                            <select
                                id="provincia"
                                name="provincia"
                                required
                            >

                                <option value="">
                                    Selecciona
                                </option>

                                <option>Azuay</option>
                                <option>Bolívar</option>
                                <option>Cañar</option>
                                <option>Carchi</option>
                                <option>Chimborazo</option>
                                <option>Cotopaxi</option>
                                <option>El Oro</option>
                                <option>Esmeraldas</option>
                                <option>Galápagos</option>
                                <option>Guayas</option>
                                <option>Imbabura</option>
                                <option>Loja</option>
                                <option>Los Ríos</option>
                                <option>Manabí</option>
                                <option>Morona Santiago</option>
                                <option>Napo</option>
                                <option>Orellana</option>
                                <option>Pastaza</option>
                                <option>Pichincha</option>
                                <option>Santa Elena</option>
                                <option>Santo Domingo de los Tsáchilas</option>
                                <option>Sucumbíos</option>
                                <option>Tungurahua</option>
                                <option>Zamora Chinchipe</option>

                            </select>

                        </div>


                        <div class="field">

                            <label>
                                Ciudad *
                            </label>

                            <input
                                type="text"
                                id="ciudad"
                                name="ciudad"
                                required
                                placeholder="Ej. Guayaquil"
                            >

                        </div>


                        <div class="field">

                            <label>
                                Parroquia *
                            </label>

                            <input
                                type="text"
                                id="parroquia"
                                name="parroquia"
                                required
                                placeholder="Ej. Tarqui"
                            >

                        </div>


                    </div>

                </div>


                <div class="admin-notice">

                    <i class="fa-solid fa-circle-info"></i>

                    <div>

                        <strong>
                            Emisión administrativa
                        </strong>

                        <br>

                        El valor mostrado es únicamente una referencia.
                        Esta emisión no utiliza PayPhone ni se registra
                        como una venta pagada.

                    </div>

                </div>


                <div
                    id="resultMessage"
                    class="result-message hidden"
                >
                </div>


                <div class="actions">

                    <button
                        type="submit"
                        class="submit-button"
                        id="submitButton"
                    >

                        <i class="fa-solid fa-file-signature"></i>

                        <span id="submitText">
                            Emitir firma
                        </span>

                    </button>

                </div>


            </form>

        </section>


    </div>

</main>


<script>

/* =========================================================
   PLANES
========================================================= */

/*
    Los perfiles eNext confirmados son:

    018 = 15 días
    001 = 1 mes
    002 = 1 año
    005 = 2 años
    010 = 3 años
    007 = 4 años
    013 = 5 años
*/

const planes = {

    natural: [

        {
            nombre: '15 Días',
            precio: 8,
            perfil: '018'
        },

        {
            nombre: '1 Mes',
            precio: 12,
            perfil: '001'
        },

        {
            nombre: '1 Año',
            precio: 20,
            perfil: '002'
        },

        {
            nombre: '2 Años',
            precio: 30,
            perfil: '005'
        },

        {
            nombre: '3 Años',
            precio: 40,
            perfil: '010'
        },

        {
            nombre: '4 Años',
            precio: 50,
            perfil: '007'
        },

        {
            nombre: '5 Años',
            precio: 55,
            perfil: '013'
        }

    ]

};


/* =========================================================
   VARIABLES
========================================================= */

let tipoSeleccionado = '';
let planSeleccionado = null;
let enviando = false;


const personCards =
    document.querySelectorAll('.person-card');

const plansSection =
    document.getElementById('plansSection');

const plansContainer =
    document.getElementById('plansContainer');

const plansDescription =
    document.getElementById('plansDescription');

const selectionSummary =
    document.getElementById('selectionSummary');

const summaryPlan =
    document.getElementById('summaryPlan');

const summaryPrice =
    document.getElementById('summaryPrice');

const formSection =
    document.getElementById('formSection');

const firmaForm =
    document.getElementById('firmaForm');

const resultMessage =
    document.getElementById('resultMessage');

const submitButton =
    document.getElementById('submitButton');

const submitText =
    document.getElementById('submitText');


/* =========================================================
   TIPO DE PERSONA
========================================================= */

personCards.forEach(card => {

    card.addEventListener('click', () => {

        personCards.forEach(item => {
            item.classList.remove('active');
        });

        card.classList.add('active');


        tipoSeleccionado =
            card.dataset.type;


        planSeleccionado = null;


        selectionSummary.classList.add(
            'hidden'
        );

        formSection.classList.add(
            'hidden'
        );

        resultMessage.classList.add(
            'hidden'
        );


        document
            .getElementById('step3')
            .classList.remove('active');


        /*
        -------------------------------------------------------
        PERSONA JURÍDICA
        -------------------------------------------------------
        No la enviamos con el formulario de Persona Natural.
        eNext requiere información adicional para PJ.
        -------------------------------------------------------
        */

        if (tipoSeleccionado === 'juridica') {

            plansSection.classList.remove(
                'hidden'
            );


            plansContainer.innerHTML = '';


            plansDescription.innerHTML = `

                <div
                    style="
                        padding:15px;
                        background:#fff9e8;
                        border:1px solid #f0dfad;
                        border-radius:10px;
                        color:#715b1b;
                        line-height:1.6;
                    "
                >

                    <strong>
                        Persona Jurídica
                    </strong>

                    <br>

                    La emisión de Persona Jurídica se configurará
                    con su formulario específico antes de habilitarla.

                </div>

            `;


            document
                .getElementById('step2')
                .classList.add('active');


            return;
        }


        /*
        -------------------------------------------------------
        PERSONA NATURAL
        -------------------------------------------------------
        */

        renderPlanes('natural');


        plansSection.classList.remove(
            'hidden'
        );


        document
            .getElementById('step2')
            .classList.add('active');

    });

});


/* =========================================================
   MOSTRAR PLANES NATURAL
========================================================= */

function renderPlanes(tipo) {

    plansContainer.innerHTML = '';


    plansDescription.textContent =
        'Selecciona la vigencia de la firma electrónica.';


    planes[tipo].forEach(plan => {

        const item =
            document.createElement('div');


        item.className = 'plan';


        item.innerHTML = `

            <div class="plan-name">
                ${plan.nombre}
            </div>

            <div class="plan-price">
                $${plan.precio}
            </div>

            <span class="plan-reference">
                Precio de referencia
            </span>

        `;


        item.addEventListener(
            'click',
            () => seleccionarPlan(
                item,
                plan
            )
        );


        plansContainer.appendChild(
            item
        );

    });

}


/* =========================================================
   SELECCIONAR PLAN
========================================================= */

function seleccionarPlan(
    element,
    plan
) {

    document
        .querySelectorAll('.plan')
        .forEach(item => {

            item.classList.remove(
                'active'
            );

        });


    element.classList.add(
        'active'
    );


    planSeleccionado = plan;


    summaryPlan.textContent =
        'Persona Natural · ' +
        plan.nombre;


    summaryPrice.textContent =
        '$' +
        plan.precio.toFixed(2);


    selectionSummary.classList.remove(
        'hidden'
    );


    formSection.classList.remove(
        'hidden'
    );


    resultMessage.classList.add(
        'hidden'
    );


    document
        .getElementById('step3')
        .classList.add('active');


    setTimeout(() => {

        formSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

    }, 100);

}


/* =========================================================
   MOSTRAR RESULTADO
========================================================= */

function mostrarResultado(
    tipo,
    titulo,
    mensaje
) {

    resultMessage.className =
        'result-message ' +
        (
            tipo === 'success'
                ? 'result-success'
                : 'result-error'
        );


    resultMessage.innerHTML = `

        <strong>
            ${escaparHtml(titulo)}
        </strong>

        ${escaparHtml(mensaje)}

    `;


    resultMessage.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });

}


/* =========================================================
   ESCAPAR TEXTO
========================================================= */

function escaparHtml(valor) {

    const div =
        document.createElement('div');

    div.textContent =
        String(valor ?? '');

    return div.innerHTML;

}


/* =========================================================
   ENVIAR A ENEXT
========================================================= */

firmaForm.addEventListener(
    'submit',
    async function(event) {

        event.preventDefault();


        if (enviando) {
            return;
        }


        if (tipoSeleccionado !== 'natural') {

            mostrarResultado(
                'error',
                'Emisión no disponible',
                'Por ahora la emisión directa está habilitada únicamente para Persona Natural.'
            );

            return;
        }


        if (!planSeleccionado) {

            mostrarResultado(
                'error',
                'Falta seleccionar la firma',
                'Selecciona primero la vigencia de la firma.'
            );

            return;
        }


        if (!firmaForm.checkValidity()) {

            firmaForm.reportValidity();

            return;
        }


        const cedula =
            document
                .getElementById('cedula')
                .value
                .trim();


        if (!/^[0-9]{10}$/.test(cedula)) {

            mostrarResultado(
                'error',
                'Cédula incorrecta',
                'La cédula debe contener exactamente 10 dígitos.'
            );

            return;
        }


        /*
        -------------------------------------------------------
        DATOS PARA EL BACKEND
        -------------------------------------------------------
        NO enviamos precio a eNext.
        NO enviamos datos de PayPhone.
        -------------------------------------------------------
        */

        const payload = {

            tipo_persona:
                'natural',

            perfil_firma:
                planSeleccionado.perfil,

            nombres:
                document
                    .getElementById('nombres')
                    .value
                    .trim(),

            apellidos:
                document
                    .getElementById('apellidos')
                    .value
                    .trim(),

            cedula:
                cedula,

            codigo_dactilar:
                document
                    .getElementById('codigo_dactilar')
                    .value
                    .trim(),

            celular:
                document
                    .getElementById('celular')
                    .value
                    .trim(),

            correo:
                document
                    .getElementById('correo')
                    .value
                    .trim(),

            direccion:
                document
                    .getElementById('direccion')
                    .value
                    .trim(),

            provincia:
                document
                    .getElementById('provincia')
                    .value
                    .trim(),

            ciudad:
                document
                    .getElementById('ciudad')
                    .value
                    .trim(),

            parroquia:
                document
                    .getElementById('parroquia')
                    .value
                    .trim()

        };


        /*
        -------------------------------------------------------
        CONFIRMACIÓN
        -------------------------------------------------------
        Importante porque después de aceptar se crea
        una solicitud REAL en eNext.
        -------------------------------------------------------
        */

        const confirmar = window.confirm(

            'Vas a emitir una firma REAL en eNext.\n\n' +

            'Titular: ' +
            payload.nombres +
            ' ' +
            payload.apellidos +
            '\n' +

            'Cédula: ' +
            payload.cedula +
            '\n' +

            'Vigencia: ' +
            planSeleccionado.nombre +
            '\n\n' +

            '¿Deseas continuar?'

        );


        if (!confirmar) {
            return;
        }


        enviando = true;


        submitButton.disabled = true;


        submitText.textContent =
            'Enviando a eNext...';


        resultMessage.classList.add(
            'hidden'
        );


        try {


            const response = await fetch(
                'procesar_emision.php',
                {

                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json'
                    },

                    body:
                        JSON.stringify(payload)

                }
            );


            let result;


            try {

                result =
                    await response.json();

            } catch (jsonError) {

                throw new Error(
                    'El servidor devolvió una respuesta inválida.'
                );

            }


            if (
                !response.ok ||
                Number(result.codigo) !== 1
            ) {

                throw new Error(
                    result.mensaje ||
                    'eNext rechazó la solicitud.'
                );

            }


            /*
            ---------------------------------------------------
            ÉXITO
            ---------------------------------------------------
            NO mostramos token.
            NO mostramos enlace biométrico.
            ---------------------------------------------------
            */

            const numeroTramite =
                result.numero_tramite
                ? ' Trámite: ' +
                  result.numero_tramite +
                  '.'
                : '';


            mostrarResultado(
                'success',
                'Solicitud enviada correctamente a eNext',
                'La información para continuar el proceso fue enviada al cliente por el medio configurado.' +
                numeroTramite
            );


            /*
            ---------------------------------------------------
            LIMPIAR DATOS DEL CLIENTE
            ---------------------------------------------------
            Conservamos persona y plan seleccionados para
            facilitar una nueva emisión del mismo tipo.
            ---------------------------------------------------
            */

            firmaForm.reset();


        } catch (error) {


            mostrarResultado(
                'error',
                'No se pudo emitir la firma',
                error.message ||
                'Ocurrió un error al comunicarse con eNext.'
            );


        } finally {


            enviando = false;


            submitButton.disabled = false;


            submitText.textContent =
                'Emitir firma';

        }

    }
);

</script>


</body>
</html>
