<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');


/* =========================================================
   RESPUESTA JSON
========================================================= */

function responder(int $http, array $data): never
{
    http_response_code($http);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/* =========================================================
   1. VERIFICAR SESIÓN ADMIN
========================================================= */

if (empty($_SESSION['profirma_admin'])) {

    responder(401, [
        'codigo' => 0,
        'mensaje' => 'La sesión de administrador no está activa.'
    ]);
}


/* =========================================================
   2. SOLO POST
========================================================= */

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    !== 'POST'
) {

    responder(405, [
        'codigo' => 0,
        'mensaje' => 'Método no permitido.'
    ]);
}


/* =========================================================
   3. RECIBIR JSON
========================================================= */

$raw = file_get_contents('php://input');

$input = json_decode(
    $raw ?: '',
    true
);


if (!is_array($input)) {

    responder(400, [
        'codigo' => 0,
        'mensaje' => 'Solicitud JSON inválida.'
    ]);
}


/* =========================================================
   4. TIPO DE PERSONA
========================================================= */

/*
 * Por ahora conectamos PERSONA NATURAL.
 *
 * Persona Jurídica necesita campos adicionales de eNext
 * y la conectaremos después con su formulario correspondiente.
 */

$tipoPersona = strtolower(
    trim(
        (string)($input['tipo_persona'] ?? '')
    )
);


if ($tipoPersona !== 'natural') {

    responder(422, [
        'codigo' => 0,
        'mensaje' =>
            'Por ahora la emisión directa está habilitada únicamente para Persona Natural.'
    ]);
}


/* =========================================================
   5. VARIABLES PRIVADAS ENEXT
========================================================= */

/*
 * Son las MISMAS variables de Railway
 * utilizadas por la integración eNext que ya funciona.
 */

$apiUrl = trim(
    (string)getenv('ENEXT_API_URL')
);

$basicUser = trim(
    (string)getenv('ENEXT_BASIC_USER')
);

$basicPass = (string)getenv(
    'ENEXT_BASIC_PASSWORD'
);

$socioUser = trim(
    (string)getenv('ENEXT_SOCIO_USER')
);

$socioPass = (string)getenv(
    'ENEXT_SOCIO_PASSWORD'
);


/* =========================================================
   6. COMPROBAR CONFIGURACIÓN
========================================================= */

if (
    $apiUrl === '' ||
    $basicUser === '' ||
    $basicPass === '' ||
    $socioUser === '' ||
    $socioPass === ''
) {

    error_log(
        'PROFIRMA ADMIN / Configuración eNext incompleta.'
    );

    responder(500, [
        'codigo' => 0,
        'mensaje' =>
            'La configuración privada de eNext está incompleta.'
    ]);
}


/* =========================================================
   7. CAMPOS OBLIGATORIOS
========================================================= */

$required = [

    'perfil_firma',

    'nombres',

    'apellidos',

    'cedula',

    'codigo_dactilar',

    'correo',

    'provincia',

    'ciudad',

    'parroquia',

    'direccion',

    'celular'

];


foreach ($required as $field) {

    if (
        !isset($input[$field]) ||
        trim((string)$input[$field]) === ''
    ) {

        responder(422, [
            'codigo' => 0,
            'mensaje' =>
                'Falta el campo requerido: ' .
                $field .
                '.'
        ]);
    }
}


/* =========================================================
   8. NORMALIZAR DATOS
========================================================= */

$nombres = trim(
    (string)$input['nombres']
);


$apellidos = trim(
    (string)$input['apellidos']
);


$cedula = preg_replace(
    '/\D+/',
    '',
    (string)$input['cedula']
);


$codigoDactilar = strtoupper(
    trim(
        (string)$input['codigo_dactilar']
    )
);


$email = trim(
    (string)$input['correo']
);


$celular = preg_replace(
    '/[^0-9+]/',
    '',
    (string)$input['celular']
);


$provincia = trim(
    (string)$input['provincia']
);


$ciudad = trim(
    (string)$input['ciudad']
);


$parroquia = trim(
    (string)$input['parroquia']
);


$direccion = trim(
    (string)$input['direccion']
);


$perfil = trim(
    (string)$input['perfil_firma']
);


/* =========================================================
   9. VALIDAR CÉDULA
========================================================= */

if (!preg_match('/^\d{10}$/', $cedula)) {

    responder(422, [
        'codigo' => 0,
        'mensaje' =>
            'La cédula debe tener 10 dígitos.'
    ]);
}


/* =========================================================
   10. VALIDAR CORREO
========================================================= */

if (!filter_var(
    $email,
    FILTER_VALIDATE_EMAIL
)) {

    responder(422, [
        'codigo' => 0,
        'mensaje' =>
            'El correo electrónico no es válido.'
    ]);
}


/* =========================================================
   11. PERFILES ENEXT CONFIRMADOS
========================================================= */

/*
 * 018 = 15 días
 * 001 = 1 mes
 * 002 = 1 año
 * 005 = 2 años
 * 010 = 3 años
 * 007 = 4 años
 * 013 = 5 años
 */

$perfilesPermitidos = [

    '018',

    '001',

    '002',

    '005',

    '010',

    '007',

    '013'

];


if (!in_array(
    $perfil,
    $perfilesPermitidos,
    true
)) {

    responder(422, [
        'codigo' => 0,
        'mensaje' =>
            'El perfil de firma seleccionado no está habilitado.'
    ]);
}


/* =========================================================
   12. CREAR NÚMERO DE TRÁMITE
========================================================= */

/*
 * Este número pertenece a la emisión administrativa.
 * No tiene ninguna relación con PayPhone.
 */

try {

    $aleatorio = strtoupper(
        bin2hex(
            random_bytes(4)
        )
    );

} catch (Throwable $e) {

    $aleatorio = strtoupper(
        substr(
            hash(
                'sha256',
                uniqid('', true)
            ),
            0,
            8
        )
    );
}


$numeroTramite =
    'ADM-' .
    date('YmdHis') .
    '-' .
    $aleatorio;


/* =========================================================
   13. PAYLOAD PARA ENEXT
========================================================= */

/*
 * IMPORTANTE:
 *
 * NOMBRES y APELLIDOS viajan separados.
 *
 * No se envía precio.
 * No se envía PayPhone.
 */

$payload = [

    'numero_tramite' =>
        $numeroTramite,

    'usuario' =>
        $socioUser,

    'password' =>
        $socioPass,

    'perfil_firma' =>
        $perfil,

    'nombres' =>
        $nombres,

    'apellidos' =>
        $apellidos,

    'cedula' =>
        $cedula,

    'codigo_dactilar' =>
        $codigoDactilar,

    'correo' =>
        $email,

    'provincia' =>
        $provincia,

    'ciudad' =>
        $ciudad,

    'parroquia' =>
        $parroquia,

    'direccion' =>
        $direccion,

    'celular' =>
        $celular,

    'tipo_envio' =>
        'EMAIL',

    'tipo_clave' =>
        1

];


/* =========================================================
   14. CONVERTIR A JSON
========================================================= */

$jsonPayload = json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);


if ($jsonPayload === false) {

    responder(500, [
        'codigo' => 0,
        'mensaje' =>
            'No se pudo preparar la solicitud.'
    ]);
}


/* =========================================================
   15. CONECTAR CON ENEXT
========================================================= */

$ch = curl_init($apiUrl);


if ($ch === false) {

    responder(500, [
        'codigo' => 0,
        'mensaje' =>
            'No fue posible iniciar la conexión con eNext.'
    ]);
}


curl_setopt_array(
    $ch,
    [

        CURLOPT_POST =>
            true,

        CURLOPT_RETURNTRANSFER =>
            true,

        CURLOPT_CONNECTTIMEOUT =>
            15,

        CURLOPT_TIMEOUT =>
            60,

        CURLOPT_HTTPAUTH =>
            CURLAUTH_BASIC,

        CURLOPT_USERPWD =>
            $basicUser .
            ':' .
            $basicPass,

        CURLOPT_HTTPHEADER => [

            'Content-Type: application/json',

            'Accept: application/json'

        ],

        CURLOPT_POSTFIELDS =>
            $jsonPayload

    ]
);


/* =========================================================
   16. EJECUTAR PETICIÓN
========================================================= */

$responseBody =
    curl_exec($ch);


$curlError =
    curl_error($ch);


$httpCode =
    (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );


curl_close($ch);


/* =========================================================
   17. ERROR DE CONEXIÓN
========================================================= */

if (
    $responseBody === false ||
    $curlError !== ''
) {

    error_log(
        'PROFIRMA ADMIN / eNext error de conexión: ' .
        $curlError
    );


    responder(502, [
        'codigo' => 0,
        'mensaje' =>
            'No fue posible conectar con el servicio de eNext.'
    ]);
}


/* =========================================================
   18. DECODIFICAR RESPUESTA ENEXT
========================================================= */

/*
 * Conservamos esta parte porque la integración
 * que ya funciona contempla que eNext pueda devolver
 * contenido antes del JSON.
 */

$responseText =
    trim(
        (string)$responseBody
    );


$result = json_decode(
    $responseText,
    true
);


/*
 * Si existe texto antes del JSON,
 * buscamos el primer {
 */

if (!is_array($result)) {

    $jsonStart = strpos(
        $responseText,
        '{'
    );


    if ($jsonStart !== false) {

        $jsonOnly = substr(
            $responseText,
            $jsonStart
        );


        $result = json_decode(
            $jsonOnly,
            true
        );
    }
}


/* =========================================================
   19. RESPUESTA INVÁLIDA
========================================================= */

if (!is_array($result)) {

    error_log(
        'PROFIRMA ADMIN / eNext respuesta no JSON. HTTP ' .
        $httpCode .
        ' - ' .
        substr(
            $responseText,
            0,
            1000
        )
    );


    responder(502, [
        'codigo' => 0,
        'mensaje' =>
            'eNext devolvió una respuesta inválida.'
    ]);
}


/* =========================================================
   20. ENEXT RECHAZÓ LA SOLICITUD
========================================================= */

if (
    $httpCode < 200 ||
    $httpCode >= 300 ||
    (int)($result['codigo'] ?? 0) !== 1
) {

    $mensajeEnext = trim(
        (string)(
            $result['mensaje']
            ?? ''
        )
    );


    error_log(
        'PROFIRMA ADMIN / eNext rechazó solicitud. ' .
        'Trámite: ' .
        $numeroTramite .
        ' / HTTP ' .
        $httpCode .
        ' / ' .
        $mensajeEnext
    );


    $mensajePublico =
        $mensajeEnext !== ''
            ? $mensajeEnext
            : 'eNext rechazó la solicitud.';


    $codigoHttp =
        (
            $httpCode >= 400 &&
            $httpCode <= 599
        )
            ? $httpCode
            : 502;


    responder(
        $codigoHttp,
        [
            'codigo' => 0,
            'mensaje' =>
                $mensajePublico
        ]
    );
}


/* =========================================================
   21. COMPROBAR TOKEN Y LINK
========================================================= */

/*
 * eNext devuelve estos valores cuando acepta
 * correctamente la solicitud.
 *
 * LOS LEEMOS SOLAMENTE PARA VALIDAR.
 *
 * NO LOS DEVOLVEMOS AL NAVEGADOR.
 * NO LOS MOSTRAMOS EN PROFIRMA.
 */

$tokenBiometria = trim(
    (string)(
        $result['token_biometria']
        ?? ''
    )
);


$linkBiometria = trim(
    (string)(
        $result['link_biometria']
        ?? ''
    )
);


if (
    $tokenBiometria === '' ||
    $linkBiometria === ''
) {

    error_log(
        'PROFIRMA ADMIN / eNext respondió código 1 ' .
        'pero sin token/link. Trámite: ' .
        $numeroTramite
    );


    responder(502, [
        'codigo' => 0,
        'mensaje' =>
            'La solicitud fue registrada, pero la respuesta del servicio quedó incompleta.'
    ]);
}


/* =========================================================
   22. ÉXITO
========================================================= */

/*
 * MUY IMPORTANTE:
 *
 * Aquí NO devolvemos:
 *
 * token_biometria
 * link_biometria
 *
 * Por lo tanto la biometría NO aparece en PROFIRMA.
 *
 * El payload enviado a eNext utiliza:
 *
 * tipo_envio = EMAIL
 */

responder(200, [

    'codigo' => 1,

    'mensaje' =>
        (string)(
            $result['mensaje']
            ?? 'Proceso ingresado correctamente.'
        ),

    'numero_tramite' =>
        $numeroTramite

]);
