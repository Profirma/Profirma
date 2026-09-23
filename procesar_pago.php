<?php
declare(strict_types=1);

/*
 * PROFIRMA - Backend de creación de solicitud
 * Las credenciales se obtienen exclusivamente desde Railway:
 *
 * SIGN_API_URL
 * SIGN_API_USER
 * SIGN_API_PASSWORD
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/* =========================================================
   FUNCIONES
   ========================================================= */

function respond(int $httpCode, array $data): never
{
    http_response_code($httpCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function fail(int $httpCode, string $message): never
{
    respond($httpCode, [
        'codigo'  => 0,
        'mensaje' => $message
    ]);
}

/* =========================================================
   SOLO ACEPTAR POST
   ========================================================= */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'Método no permitido.');
}

/* =========================================================
   LEER JSON DEL FRONTEND
   ========================================================= */

$raw = file_get_contents('php://input');

if ($raw === false || trim($raw) === '') {
    fail(400, 'No se recibieron datos de la solicitud.');
}

$input = json_decode($raw, true);

if (!is_array($input)) {
    fail(400, 'Solicitud JSON inválida.');
}

/* =========================================================
   CREDENCIALES PRIVADAS DE RAILWAY
   ========================================================= */

$apiUrl  = trim((string) getenv('SIGN_API_URL'));
$apiUser = trim((string) getenv('SIGN_API_USER'));
$apiPass = trim((string) getenv('SIGN_API_PASSWORD'));

if ($apiUrl === '' || $apiUser === '' || $apiPass === '') {
    error_log('PROFIRMA: faltan variables privadas de integración.');

    fail(
        500,
        'El servicio de emisión no está configurado correctamente.'
    );
}

/* =========================================================
   VALIDAR URL
   ========================================================= */

if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
    error_log('PROFIRMA: SIGN_API_URL no es una URL válida.');

    fail(
        500,
        'El servicio de emisión no está configurado correctamente.'
    );
}

/* =========================================================
   CAMPOS OBLIGATORIOS
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
        !array_key_exists($field, $input) ||
        trim((string) $input[$field]) === ''
    ) {
        fail(
            422,
            'Falta el campo requerido: ' . $field . '.'
        );
    }
}

/* =========================================================
   NORMALIZAR DATOS
   ========================================================= */

$numeroTramite = trim((string) $input['numero_tramite']);
$perfil        = trim((string) $input['perfil_firma']);

$nombres   = trim((string) $input['nombres']);
$apellidos = trim((string) $input['apellidos']);

$cedula = preg_replace(
    '/\D+/',
    '',
    (string) $input['cedula']
);

$codigoDactilar = strtoupper(
    trim((string) $input['codigo_dactilar'])
);

$email = strtolower(
    trim((string) $input['correo'])
);

$provincia = trim((string) $input['provincia']);
$ciudad    = trim((string) $input['ciudad']);
$parroquia = trim((string) $input['parroquia']);
$direccion = trim((string) $input['direccion']);

$celular = preg_replace(
    '/[^0-9+]/',
    '',
    (string) $input['celular']
);

/* =========================================================
   VALIDACIONES
   ========================================================= */

if (!preg_match('/^\d{10}$/', $cedula)) {
    fail(
        422,
        'La cédula debe contener 10 dígitos.'
    );
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail(
        422,
        'El correo electrónico no es válido.'
    );
}

/*
 * Perfiles Persona Natural:
 *
 * 002 = 1 año
 * 005 = 2 años
 * 010 = 3 años
 * 013 = 5 años
 */

$perfilesPermitidos = [
    '002',
    '005',
    '010',
    '013'
];

if (!in_array($perfil, $perfilesPermitidos, true)) {
    fail(
        422,
        'La vigencia seleccionada no está disponible.'
    );
}

if (strlen($nombres) < 2) {
    fail(
        422,
        'Ingresa los nombres del titular.'
    );
}

if (strlen($apellidos) < 2) {
    fail(
        422,
        'Ingresa los apellidos del titular.'
    );
}

if (strlen($codigoDactilar) < 5) {
    fail(
        422,
        'El código dactilar no es válido.'
    );
}

if (strlen($celular) < 9) {
    fail(
        422,
        'El número de celular no es válido.'
    );
}

/* =========================================================
   PAYLOAD PARA EL SERVICIO DE EMISIÓN
   ========================================================= */

$payload = [

    'numero_tramite' => $numeroTramite,

    /*
     * La API requiere estas credenciales también
     * dentro del JSON.
     */
    'usuario'  => $apiUser,
    'password' => $apiPass,

    'perfil_firma' => $perfil,

    'nombres'   => $nombres,
    'apellidos' => $apellidos,

    'cedula'          => $cedula,
    'codigo_dactilar' => $codigoDactilar,

    'correo' => $email,

    'provincia' => $provincia,
    'ciudad'    => $ciudad,
    'parroquia' => $parroquia,
    'direccion' => $direccion,

    'celular' => $celular,

    'tipo_envio' => 'EMAIL',

    /*
     * 1 = clave generada automáticamente
     */
    'tipo_clave' => 1
];

$jsonPayload = json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($jsonPayload === false) {
    error_log(
        'PROFIRMA: error generando JSON para servicio de emisión.'
    );

    fail(
        500,
        'No fue posible preparar la solicitud.'
    );
}

/* =========================================================
   CURL
   ========================================================= */

$ch = curl_init();

if ($ch === false) {
    fail(
        500,
        'No fue posible iniciar la comunicación con el servicio.'
    );
}

curl_setopt_array(
    $ch,
    [

        CURLOPT_URL => $apiUrl,

        CURLOPT_POST => true,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CONNECTTIMEOUT => 15,

        CURLOPT_TIMEOUT => 45,

        /*
         * BASIC AUTH
         */
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,

        CURLOPT_USERPWD =>
            $apiUser . ':' . $apiPass,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],

        CURLOPT_POSTFIELDS => $jsonPayload
    ]
);

/* =========================================================
   EJECUTAR SOLICITUD
   ========================================================= */

$responseBody = curl_exec($ch);

$curlError = curl_error($ch);

$httpCode = (int) curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);

/* =========================================================
   ERROR DE CONEXIÓN
   ========================================================= */

if ($responseBody === false || $curlError !== '') {

    error_log(
        'PROFIRMA: error de conexión con servicio: ' .
        $curlError
    );

    fail(
        502,
        'No fue posible conectar con el servicio de emisión. Intenta nuevamente.'
    );
}

/* =========================================================
   DECODIFICAR RESPUESTA
   ========================================================= */

$result = json_decode(
    (string) $responseBody,
    true
);

if (!is_array($result)) {

    error_log(
        'PROFIRMA: respuesta no JSON. HTTP ' .
        $httpCode .
        ' BODY: ' .
        substr((string) $responseBody, 0, 1000)
    );

    fail(
        502,
        'El servicio de emisión devolvió una respuesta inválida.'
    );
}

/* =========================================================
   VERIFICAR RESPUESTA
   ========================================================= */

$codigoProveedor = (int) ($result['codigo'] ?? 0);

$mensajeProveedor = trim(
    (string) ($result['mensaje'] ?? '')
);

/*
 * Nunca enviamos credenciales al navegador.
 */

if (
    $httpCode < 200 ||
    $httpCode >= 300 ||
    $codigoProveedor !== 1
) {

    error_log(
        'PROFIRMA: solicitud rechazada. HTTP=' .
        $httpCode .
        ' codigo=' .
        $codigoProveedor .
        ' mensaje=' .
        $mensajeProveedor
    );

    /*
     * Podemos conservar el mensaje funcional de la API,
     * pero no revelamos información interna adicional.
     */

    if ($mensajeProveedor === '') {
        $mensajeProveedor =
            'No se pudo registrar la solicitud.';
    }

    $responseCode = 502;

    if ($httpCode === 400) {
        $responseCode = 400;
    } elseif ($httpCode === 401) {
        $responseCode = 502;
    } elseif ($httpCode === 422) {
        $responseCode = 422;
    }

    fail(
        $responseCode,
        $mensajeProveedor
    );
}

/* =========================================================
   VALIDAR DATOS DE ÉXITO
   ========================================================= */

$linkBiometria = trim(
    (string) ($result['link_biometria'] ?? '')
);

$tokenBiometria = trim(
    (string) ($result['token_biometria'] ?? '')
);

if ($linkBiometria === '' || $tokenBiometria === '') {

    error_log(
        'PROFIRMA: respuesta exitosa sin enlace/token de validación.'
    );

    fail(
        502,
        'La solicitud fue registrada, pero no se recibió el enlace de validación.'
    );
}

/* =========================================================
   RESPUESTA PARA PROFIRMA
   ========================================================= */

respond(
    200,
    [
        'codigo' => 1,

        'mensaje' =>
            'Solicitud registrada correctamente.',

        'token_biometria' =>
            $tokenBiometria,

        'link_biometria' =>
            $linkBiometria
    ]
);

