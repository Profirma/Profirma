<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PROFIRMA - PREPARAR PAGO PAYPHONE
|--------------------------------------------------------------------------
| Persona Natural  = $16 USD
| Persona Jurídica = $20 USD
|
| Este archivo:
| 1. Recibe los datos desde index.html.
| 2. Determina el precio EN EL SERVIDOR.
| 3. Prepara la transacción con PayPhone.
| 4. Devuelve al navegador la URL oficial de PayPhone.
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

date_default_timezone_set('America/Guayaquil');


/*
|--------------------------------------------------------------------------
| FUNCIÓN DE RESPUESTA JSON
|--------------------------------------------------------------------------
*/

function responder(int $httpCode, array $data): never
{
    http_response_code($httpCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| SOLO PERMITIR POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responder(405, [
        'ok' => false,
        'message' => 'Método no permitido.'
    ]);
}


/*
|--------------------------------------------------------------------------
| CREDENCIALES PAYPHONE DESDE RAILWAY
|--------------------------------------------------------------------------
*/

$payphoneToken = trim((string) getenv('PAYPHONE_TOKEN'));
$payphoneStoreId = trim((string) getenv('PAYPHONE_STORE_ID'));


if ($payphoneToken === '' || $payphoneStoreId === '') {

    responder(500, [
        'ok' => false,
        'message' => 'Las credenciales de PayPhone no están configuradas en el servidor.'
    ]);
}


/*
|--------------------------------------------------------------------------
| LEER JSON ENVIADO DESDE PROFIRMA
|--------------------------------------------------------------------------
*/

$rawBody = file_get_contents('php://input');

if ($rawBody === false || trim($rawBody) === '') {

    responder(400, [
        'ok' => false,
        'message' => 'No se recibieron datos de la solicitud.'
    ]);
}


$datos = json_decode($rawBody, true);

if (!is_array($datos)) {

    responder(400, [
        'ok' => false,
        'message' => 'Los datos recibidos no tienen un formato JSON válido.'
    ]);
}


/*
|--------------------------------------------------------------------------
| TIPO DE PERSONA
|--------------------------------------------------------------------------
*/

$tipo = strtolower(
    trim((string) (
        $datos['tipo']
        ?? $datos['tipoPersona']
        ?? $datos['tipo_persona']
        ?? 'natural'
    ))
);


/*
|--------------------------------------------------------------------------
| NORMALIZAR TIPO
|--------------------------------------------------------------------------
*/

$tipoNormalizado = str_replace(
    [' ', '_', '-', 'í'],
    ['', '', '', 'i'],
    $tipo
);


if (
    $tipoNormalizado === 'juridica' ||
    $tipoNormalizado === 'personajuridica'
) {

    $tipoPersona = 'juridica';
    $nombreTipo = 'Persona Jurídica';

} else {

    $tipoPersona = 'natural';
    $nombreTipo = 'Persona Natural';
}


/*
|--------------------------------------------------------------------------
| PRECIOS PROFIRMA
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| El navegador NO decide cuánto cobrar.
| El servidor determina el precio.
|
*/

if ($tipoPersona === 'juridica') {

    $montoCentavos = 2000; // $20.00

} else {

    $montoCentavos = 1600; // $16.00
}


/*
|--------------------------------------------------------------------------
| VIGENCIA
|--------------------------------------------------------------------------
*/

$vigencia = trim((string) (
    $datos['vigencia']
    ?? $datos['plan']
    ?? '1 Año'
));


/*
|--------------------------------------------------------------------------
| VIGENCIAS PERMITIDAS
|--------------------------------------------------------------------------
*/

$vigenciasNatural = [
    '7 Días',
    '15 Días',
    '1 Mes',
    '6 Meses',
    '1 Año',
    '2 Años',
    '3 Años',
    '4 Años',
    '5 Años'
];


$vigenciasJuridica = [
    '15 Días',
    '1 Mes',
    '6 Meses',
    '1 Año',
    '2 Años',
    '3 Años',
    '4 Años',
    '5 Años'
];


$vigenciasPermitidas = (
    $tipoPersona === 'juridica'
)
    ? $vigenciasJuridica
    : $vigenciasNatural;


if (!in_array($vigencia, $vigenciasPermitidas, true)) {

    responder(400, [
        'ok' => false,
        'message' => 'La vigencia seleccionada no es válida.'
    ]);
}


/*
|--------------------------------------------------------------------------
| DATOS BÁSICOS DEL CLIENTE
|--------------------------------------------------------------------------
|
| Estos datos NO son datos de tarjeta.
| PayPhone se encargará de capturar los datos sensibles de pago.
|
*/

$nombres = trim((string) (
    $datos['nombres']
    ?? $datos['nombre']
    ?? ''
));


$apellidos = trim((string) (
    $datos['apellidos']
    ?? $datos['apellido']
    ?? ''
));


$email = trim((string) (
    $datos['correo']
    ?? $datos['email']
    ?? ''
));


$telefono = trim((string) (
    $datos['celular']
    ?? $datos['telefono']
    ?? $datos['phone']
    ?? ''
));


$documento = trim((string) (
    $datos['cedula']
    ?? $datos['ruc']
    ?? $datos['documento']
    ?? ''
));


/*
|--------------------------------------------------------------------------
| VALIDACIÓN BÁSICA
|--------------------------------------------------------------------------
*/

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {

    responder(400, [
        'ok' => false,
        'message' => 'El correo electrónico no es válido.'
    ]);
}


/*
|--------------------------------------------------------------------------
| ID ÚNICO DE TRANSACCIÓN
|--------------------------------------------------------------------------
|
| PayPhone requiere un clientTransactionId único.
|
*/

try {

    $random = strtoupper(bin2hex(random_bytes(4)));

} catch (Throwable $e) {

    $random = strtoupper(substr(md5(uniqid('', true)), 0, 8));
}


$clientTransactionId =
    'PF-' .
    date('Ymd-His') .
    '-' .
    $random;


/*
|--------------------------------------------------------------------------
| REFERENCIA
|--------------------------------------------------------------------------
*/

$reference =
    'PROFIRMA - ' .
    $nombreTipo .
    ' - ' .
    $vigencia;


/*
|--------------------------------------------------------------------------
| URL DE RESPUESTA
|--------------------------------------------------------------------------
|
| PayPhone regresará aquí después del pago.
|
*/

$responseUrl =
    'https://profirma.up.railway.app/payphone_respuesta.php';


/*
|--------------------------------------------------------------------------
| DATOS PARA PAYPHONE
|--------------------------------------------------------------------------
|
| Por ahora el monto completo se registra como amountWithoutTax.
|
*/

$payphoneData = [

    'amount' => $montoCentavos,

    'amountWithoutTax' => $montoCentavos,

    'amountWithTax' => 0,

    'tax' => 0,

    'service' => 0,

    'tip' => 0,

    'clientTransactionId' => $clientTransactionId,

    'reference' => $reference,

    'storeId' => $payphoneStoreId,

    'currency' => 'USD',

    'responseUrl' => $responseUrl,

    'cancellationUrl' =>
        'https://profirma.up.railway.app/',

    'timeZone' => -5
];


/*
|--------------------------------------------------------------------------
| NO ENVIAMOS DATOS VACÍOS A PAYPHONE
|--------------------------------------------------------------------------
*/

if ($email !== '') {

    $payphoneData['email'] = $email;
}


if ($telefono !== '') {

    /*
     * PayPhone puede solicitar el teléfono del titular
     * directamente en su formulario.
     *
     * No forzamos aquí un formato inventado.
     */
}


if ($documento !== '') {

    /*
     * Tampoco enviamos documentId automáticamente.
     *
     * PayPhone advierte que phoneNumber, email y documentId
     * deben corresponder al titular del medio de pago.
     *
     * La persona que solicita la firma no necesariamente
     * tiene que ser el titular de la tarjeta.
     */
}


/*
|--------------------------------------------------------------------------
| CONVERTIR A JSON
|--------------------------------------------------------------------------
*/

$jsonPayphone = json_encode(
    $payphoneData,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);


if ($jsonPayphone === false) {

    responder(500, [
        'ok' => false,
        'message' => 'No se pudo preparar la información del pago.'
    ]);
}


/*
|--------------------------------------------------------------------------
| LLAMADA A PAYPHONE
|--------------------------------------------------------------------------
*/

$curl = curl_init();

if ($curl === false) {

    responder(500, [
        'ok' => false,
        'message' => 'No se pudo iniciar la conexión con PayPhone.'
    ]);
}


curl_setopt_array($curl, [

    CURLOPT_URL =>
        'https://pay.payphonetodoesposible.com/api/button/Prepare',

    CURLOPT_POST => true,

    CURLOPT_POSTFIELDS => $jsonPayphone,

    CURLOPT_HTTPHEADER => [

        'Authorization: Bearer ' . $payphoneToken,

        'Content-Type: application/json',

        'Accept: application/json'
    ],

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_CONNECTTIMEOUT => 15,

    CURLOPT_TIMEOUT => 30,

    CURLOPT_FOLLOWLOCATION => false
]);


/*
|--------------------------------------------------------------------------
| EJECUTAR
|--------------------------------------------------------------------------
*/

$respuestaPayphone = curl_exec($curl);

$curlError = curl_error($curl);

$httpCode = (int) curl_getinfo(
    $curl,
    CURLINFO_HTTP_CODE
);

curl_close($curl);


/*
|--------------------------------------------------------------------------
| ERROR DE CONEXIÓN
|--------------------------------------------------------------------------
*/

if ($respuestaPayphone === false) {

    responder(502, [
        'ok' => false,
        'message' =>
            'No fue posible conectar con PayPhone.',
        'detail' => $curlError
    ]);
}


/*
|--------------------------------------------------------------------------
| DECODIFICAR RESPUESTA DE PAYPHONE
|--------------------------------------------------------------------------
*/

$resultado = json_decode(
    $respuestaPayphone,
    true
);


if (!is_array($resultado)) {

    responder(502, [
        'ok' => false,
        'message' =>
            'PayPhone devolvió una respuesta que no pudo ser procesada.',
        'httpCode' => $httpCode
    ]);
}


/*
|--------------------------------------------------------------------------
| ERROR DEVUELTO POR PAYPHONE
|--------------------------------------------------------------------------
*/

if ($httpCode < 200 || $httpCode >= 300) {

    $mensajePayphone =
        $resultado['message']
        ?? 'PayPhone rechazó la preparación de la transacción.';


    responder(502, [

        'ok' => false,

        'message' => $mensajePayphone,

        'payphoneErrorCode' =>
            $resultado['errorCode'] ?? null,

        'errors' =>
            $resultado['errors'] ?? null
    ]);
}


/*
|--------------------------------------------------------------------------
| OBTENER URL DE PAGO
|--------------------------------------------------------------------------
|
| PayPhone puede devolver:
|
| payWithCard
| payWithPayPhone
|
| Para PROFIRMA damos prioridad al formulario de tarjeta.
|
*/

$payWithCard =
    isset($resultado['payWithCard'])
        ? trim((string) $resultado['payWithCard'])
        : '';


$payWithPayPhone =
    isset($resultado['payWithPayPhone'])
        ? trim((string) $resultado['payWithPayPhone'])
        : '';


$paymentUrl = '';

if ($payWithCard !== '') {

    $paymentUrl = $payWithCard;

} elseif ($payWithPayPhone !== '') {

    $paymentUrl = $payWithPayPhone;
}


/*
|--------------------------------------------------------------------------
| VALIDAR QUE PAYPHONE HAYA CREADO EL FORMULARIO
|--------------------------------------------------------------------------
*/

if ($paymentUrl === '') {

    responder(502, [

        'ok' => false,

        'message' =>
            'PayPhone no devolvió una URL de pago.',

        'clientTransactionId' =>
            $clientTransactionId
    ]);
}


/*
|--------------------------------------------------------------------------
| RESPUESTA FINAL PARA INDEX.HTML
|--------------------------------------------------------------------------
*/

responder(200, [

    'ok' => true,

    /*
     * Incluyo varios nombres para mantener compatibilidad
     * con el JavaScript del index.html.
     */

    'url' => $paymentUrl,

    'paymentUrl' => $paymentUrl,

    'payWithCard' => $payWithCard,

    'payWithPayPhone' => $payWithPayPhone,

    'clientTransactionId' =>
        $clientTransactionId,

    'paymentId' =>
        $resultado['paymentId'] ?? null,

    'tipoPersona' =>
        $tipoPersona,

    'vigencia' =>
        $vigencia,

    'amount' =>
        $montoCentavos,

    'currency' =>
        'USD'
]);
