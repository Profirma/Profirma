<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $http, array $data): never
{
    http_response_code($http);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}


/* =========================================================
   1. SOLO PERMITIR POST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responder(405, [
        'codigo' => 0,
        'mensaje' => 'Método no permitido.'
    ]);
}


/* =========================================================
   2. RECIBIR JSON DESDE PROFIRMA
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
   3. VARIABLES PRIVADAS DE RAILWAY
   ========================================================= */

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
   4. COMPROBAR CONFIGURACIÓN
   ========================================================= */

if (
    $apiUrl === '' ||
    $basicUser === '' ||
    $basicPass === '' ||
    $socioUser === '' ||
    $socioPass === ''
) {

    responder(500, [
        'codigo' => 0,
        'mensaje' => 'La configuración privada de eNext está incompleta.'
    ]);
}


/* =========================================================
   5. CAMPOS OBLIGATORIOS
   ========================================================= */

$required = [
    'numero_tramite',
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
                'Falta el campo requerido: ' . $field . '.'
        ]);
    }
}


/* =========================================================
   6. LIMPIAR Y NORMALIZAR DATOS
   ========================================================= */

$cedula = preg_replace(
    '/\D+/',
    '',
    (string)$input['cedula']
);

$celular = preg_replace(
    '/[^0-9+]/',
    '',
    (string)$input['celular']
);

$email = trim(
    (string)$input['correo']
);

$perfil = trim(
    (string)$input['perfil_firma']
);

$codigoDactilar = strtoupper(
    trim((string)$input['codigo_dactilar'])
);


/* =========================================================
   7. VALIDAR CÉDULA
   ========================================================= */

if (!preg_match('/^\d{10}$/', $cedula)) {

    responder(422, [
        'codigo' => 0,
        'mensaje' =>
            'La cédula debe tener 10 dígitos.'
    ]);
}


/* =========================================================
   8. VALIDAR CORREO
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
   9. PERFILES PERSONA NATURAL
   =========================================================
   002 = 1 año
   005 = 2 años
   010 = 3 años
   013 = 5 años
   ========================================================= */

$perfilesPermitidos = [
    '002',
    '005',
    '010',
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
            'El perfil de firma seleccionado no está permitido.'
    ]);
}


/* =========================================================
   10. CREAR JSON PARA ENEXT
   ========================================================= */

$payload = [

    'numero_tramite' =>
        trim((string)$input['numero_tramite']),

    /*
     * CREDENCIALES DEL SOCIO
     * Estas viajan DENTRO del JSON.
     */

    'usuario' =>
        $socioUser,

    'password' =>
        $socioPass,

    'perfil_firma' =>
        $perfil,

    'nombres' =>
        trim((string)$input['nombres']),

    'apellidos' =>
        trim((string)$input['apellidos']),

    'cedula' =>
        $cedula,

    'codigo_dactilar' =>
        $codigoDactilar,

    'correo' =>
        $email,

    'provincia' =>
        trim((string)$input['provincia']),

    'ciudad' =>
        trim((string)$input['ciudad']),

    'parroquia' =>
        trim((string)$input['parroquia']),

    'direccion' =>
        trim((string)$input['direccion']),

    'celular' =>
        $celular,

    'tipo_envio' =>
        'EMAIL',

    'tipo_clave' =>
        1
];


/* =========================================================
   11. CONVERTIR A JSON
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
            'No se pudo preparar la solicitud para eNext.'
    ]);
}


/* =========================================================
   12. CONECTAR CON ENEXT
   ========================================================= */

$ch = curl_init($apiUrl);


curl_setopt_array($ch, [

    CURLOPT_POST =>
        true,

    CURLOPT_RETURNTRANSFER =>
        true,

    CURLOPT_CONNECTTIMEOUT =>
        15,

    CURLOPT_TIMEOUT =>
        60,

    /*
     * BASIC AUTH
     * Independiente del usuario/password
     * enviados dentro del JSON.
     */

    CURLOPT_HTTPAUTH =>
        CURLAUTH_BASIC,

    CURLOPT_USERPWD =>
        $basicUser . ':' . $basicPass,

    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],

    CURLOPT_POSTFIELDS =>
        $jsonPayload
]);


/* =========================================================
   13. EJECUTAR PETICIÓN
   ========================================================= */

$responseBody = curl_exec($ch);

$curlError = curl_error($ch);

$httpCode = (int)curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);


/* =========================================================
   14. ERROR DE CONEXIÓN
   ========================================================= */

if (
    $responseBody === false ||
    $curlError !== ''
) {

    error_log(
        'PROFIRMA / eNext error de conexión: ' .
        $curlError
    );

    responder(502, [
        'codigo' => 0,
        'mensaje' =>
            'No fue posible conectar con el servicio de eNext.'
    ]);
}


/* =========================================================
   15. DECODIFICAR RESPUESTA ENEXT
   ========================================================= */

$result = json_decode(
    (string)$responseBody,
    true
);


if (!is_array($result)) {

    error_log(
        'PROFIRMA / eNext respuesta no JSON. HTTP ' .
        $httpCode .
        ' - ' .
        substr((string)$responseBody, 0, 1000)
    );

    responder(502, [
        'codigo' => 0,
        'mensaje' =>
            'eNext devolvió una respuesta inválida.'
    ]);
}


/* =========================================================
   16. SI ENEXT RECHAZA LA SOLICITUD
   ========================================================= */

if (
    $httpCode < 200 ||
    $httpCode >= 300 ||
    (int)($result['codigo'] ?? 0) !== 1
) {

    $mensajeEnext = trim(
        (string)($result['mensaje'] ?? '')
    );

    error_log(
        'PROFIRMA / eNext rechazó solicitud. HTTP ' .
        $httpCode .
        ' - ' .
        $mensajeEnext
    );

    $mensajePublico =
        $mensajeEnext !== ''
            ? $mensajeEnext
            : 'eNext rechazó la solicitud.';

    $codigoHttp =
        ($httpCode >= 400 && $httpCode <= 599)
            ? $httpCode
            : 502;

    responder(
        $codigoHttp,
        [
            'codigo' => 0,
            'mensaje' => $mensajePublico
        ]
    );
}


/* =========================================================
   17. LEER TOKEN Y LINK DE BIOMETRÍA
   ========================================================= */

$token = trim(
    (string)($result['token_biometria'] ?? '')
);

$link = trim(
    (string)($result['link_biometria'] ?? '')
);


/* =========================================================
   18. COMPROBAR RESPUESTA EXITOSA
   ========================================================= */

if ($token === '' || $link === '') {

    error_log(
        'PROFIRMA / eNext respondió código 1 pero sin token/link biométrico.'
    );

    responder(502, [
        'codigo' => 0,
        'mensaje' =>
            'La solicitud fue registrada, pero eNext no devolvió el enlace de biometría.'
    ]);
}


/* =========================================================
   19. DEVOLVER RESULTADO A PROFIRMA
   ========================================================= */

responder(200, [

    'codigo' =>
        1,

    'mensaje' =>
        (string)(
            $result['mensaje']
            ?? 'Proceso ingresado correctamente.'
        ),

    'token_biometria' =>
        $token,

    'link_biometria' =>
        $link
]);
