<?php

// =====================================================
// PROFIRMA - CONEXIÓN ENEXT
// MODO DEBUG TEMPORAL
// =====================================================

// ---------- CORS ----------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'mensaje' => 'Método no permitido. Utiliza POST.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


// =====================================================
// 1. LEER JSON RECIBIDO DESDE INDEX.HTML
// =====================================================

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!is_array($input)) {

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'mensaje' => 'Datos inválidos o JSON vacío',
        'json_error' => json_last_error_msg()
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


// =====================================================
// 2. OBTENER Y LIMPIAR DATOS
// =====================================================

$numeroTramite = trim(
    $input['numero_tramite'] ?? ('TRM-' . date('YmdHis') . '-' . rand(100,999))
);

$perfilFirma = trim($input['perfil_firma'] ?? '002');

$nombres = trim($input['nombres'] ?? '');

$apellidos = trim($input['apellidos'] ?? '');

$cedula = trim($input['cedula'] ?? '');

$codigoDactilar = trim($input['codigo_dactilar'] ?? '');

$correo = trim($input['correo'] ?? '');

$provincia = trim($input['provincia'] ?? 'Pichincha');

$ciudad = trim($input['ciudad'] ?? 'Quito');

$direccion = trim($input['direccion'] ?? '');

$celular = trim($input['celular'] ?? '');


// =====================================================
// 3. VALIDACIONES BÁSICAS
// =====================================================

$errores = [];

if ($nombres === '') {
    $errores[] = 'Falta nombres';
}

if ($apellidos === '') {
    $errores[] = 'Falta apellidos';
}

if ($cedula === '') {
    $errores[] = 'Falta cédula';
}

if ($codigoDactilar === '') {
    $errores[] = 'Falta código dactilar';
}

if ($correo === '') {
    $errores[] = 'Falta correo';
}

if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El correo electrónico no tiene un formato válido';
}

if ($celular === '') {
    $errores[] = 'Falta celular';
}

if ($direccion === '') {
    $errores[] = 'Falta dirección';
}


if (!empty($errores)) {

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'mensaje' => 'Faltan datos obligatorios',
        'errores' => $errores
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


// =====================================================
// 4. CREDENCIALES ENEXT
// =====================================================
//
// TEMPORALMENTE dejo aquí las mismas credenciales
// que ya estabas utilizando.
//
// IMPORTANTE:
// Después debemos moverlas a variables de entorno
// de Railway.
//

$usuarioEnext = 'facbiometria';
$passwordEnext = 'CAMBIA_AQUI_TU_PASSWORD_ENEXT';


// =====================================================
// 5. DATOS QUE SE ENVIARÁN A ENEXT
// =====================================================

$datosEnext = [

    'numero_tramite' => $numeroTramite,

    'usuario' => $usuarioEnext,

    'password' => $passwordEnext,

    'perfil_firma' => $perfilFirma,

    'nombres' => $nombres,

    'apellidos' => $apellidos,

    'cedula' => $cedula,

    'codigo_dactilar' => $codigoDactilar,

    'correo' => $correo,

    'provincia' => $provincia,

    'ciudad' => $ciudad,

    'parroquia' => 'Inaquito',

    'direccion' => $direccion,

    'celular' => $celular,

    'tipo_envio' => 'EMAIL',

    'tipo_clave' => 1
];


// =====================================================
// 6. CONVERTIR A JSON
// =====================================================

$jsonEnext = json_encode(
    $datosEnext,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($jsonEnext === false) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'mensaje' => 'No se pudo generar el JSON para eNext',
        'error' => json_last_error_msg()
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


// =====================================================
// 7. LLAMAR API ENEXT
// =====================================================

$urlEnext = 'https://enext.online/factureroweb/apiFactu/PNB.php';

$ch = curl_init($urlEnext);

curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_POSTFIELDS => $jsonEnext,

    CURLOPT_CONNECTTIMEOUT => 15,

    CURLOPT_TIMEOUT => 45,

    CURLOPT_HTTPHEADER => [

        'Content-Type: application/json',

        'Accept: application/json',

        'Authorization: Basic ' .
            base64_encode($usuarioEnext . ':' . $passwordEnext)

    ]

]);


// =====================================================
// 8. EJECUTAR PETICIÓN
// =====================================================

$response = curl_exec($ch);

$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

$curlErrorNumber = curl_errno($ch);

$curlError = curl_error($ch);

curl_close($ch);


// =====================================================
// 9. ERROR DE CONEXIÓN CURL
// =====================================================

if ($response === false || $curlErrorNumber !== 0) {

    http_response_code(502);

    echo json_encode([

        'ok' => false,

        'mensaje' => 'No se pudo conectar con eNext',

        'http_code' => $httpCode,

        'curl_error_number' => $curlErrorNumber,

        'curl_error' => $curlError

    ], JSON_UNESCAPED_UNICODE);

    exit;
}


// =====================================================
// 10. INTENTAR LEER RESPUESTA DE ENEXT
// =====================================================

$respuestaEnextJSON = json_decode($response, true);

$esJSON = (
    json_last_error() === JSON_ERROR_NONE
);


// =====================================================
// 11. RESPUESTA DEBUG
// =====================================================
//
// IMPORTANTE:
// NO devolvemos password ni Authorization.
//
// Esto nos permitirá ver exactamente qué responde
// eNext.
//

echo json_encode([

    'ok' => ($httpCode >= 200 && $httpCode < 300),

    'debug' => true,

    'http_code_enext' => $httpCode,

    'endpoint' => $urlEnext,

    'datos_enviados' => [

        'numero_tramite' => $numeroTramite,

        'perfil_firma' => $perfilFirma,

        'nombres' => $nombres,

        'apellidos' => $apellidos,

        'cedula' => $cedula,

        'codigo_dactilar' => $codigoDactilar,

        'correo' => $correo,

        'provincia' => $provincia,

        'ciudad' => $ciudad,

        'direccion' => $direccion,

        'celular' => $celular,

        'tipo_envio' => 'EMAIL',

        'tipo_clave' => 1
    ],

    'respuesta_enext_es_json' => $esJSON,

    'enext_response' => $esJSON
        ? $respuestaEnextJSON
        : $response

], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

exit;

?>
