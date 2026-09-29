<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PROFIRMA - PREPARAR PAGO PAYPHONE
|--------------------------------------------------------------------------
| Persona Natural  = $16 USD
| Persona Jurídica = $20 USD
|
| Flujo:
| 1. Recibe los datos de PROFIRMA.
| 2. Valida tipo y vigencia.
| 3. Determina el precio EN EL SERVIDOR.
| 4. Genera clientTransactionId.
| 5. Guarda la solicitud PENDIENTE en PostgreSQL.
| 6. Prepara la transacción con PayPhone.
| 7. Devuelve la URL oficial de PayPhone.
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

date_default_timezone_set('America/Guayaquil');


/*
|--------------------------------------------------------------------------
| RESPUESTA JSON
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
| SOLO POST
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
| VARIABLES DEL SERVIDOR
|--------------------------------------------------------------------------
*/

$payphoneToken = trim((string) getenv('PAYPHONE_TOKEN'));
$payphoneStoreId = trim((string) getenv('PAYPHONE_STORE_ID'));
$databaseUrl = trim((string) getenv('DATABASE_URL'));

if ($payphoneToken === '' || $payphoneStoreId === '') {
    responder(500, [
        'ok' => false,
        'message' => 'Las credenciales de PayPhone no están configuradas.'
    ]);
}

if ($databaseUrl === '') {
    responder(500, [
        'ok' => false,
        'message' => 'La conexión con la base de datos no está configurada.'
    ]);
}


/*
|--------------------------------------------------------------------------
| CONEXIÓN POSTGRESQL
|--------------------------------------------------------------------------
*/

$db = parse_url($databaseUrl);

if ($db === false || !isset($db['host'], $db['path'])) {
    responder(500, [
        'ok' => false,
        'message' => 'DATABASE_URL no tiene un formato válido.'
    ]);
}

$dbHost = (string) $db['host'];
$dbPort = isset($db['port']) ? (int) $db['port'] : 5432;
$dbName = ltrim((string) $db['path'], '/');
$dbUser = isset($db['user']) ? urldecode((string) $db['user']) : '';
$dbPass = isset($db['pass']) ? urldecode((string) $db['pass']) : '';

try {
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
} catch (Throwable $e) {
    responder(500, [
        'ok' => false,
        'message' => 'No se pudo conectar con PostgreSQL.'
    ]);
}


/*
|--------------------------------------------------------------------------
| LEER JSON
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
    $montoCentavos = 2000;
} else {
    $tipoPersona = 'natural';
    $nombreTipo = 'Persona Natural';
    $montoCentavos = 1600;
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

$vigenciasPermitidas =
    $tipoPersona === 'juridica'
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
| DATOS BÁSICOS
|--------------------------------------------------------------------------
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

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(400, [
        'ok' => false,
        'message' => 'El correo electrónico no es válido.'
    ]);
}


/*
|--------------------------------------------------------------------------
| CLIENT TRANSACTION ID
|--------------------------------------------------------------------------
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
| DATOS QUE CONSERVAREMOS PARA eNEXT
|--------------------------------------------------------------------------
|
| Guardamos el JSON completo recibido del formulario.
| No ejecutamos eNext todavía.
|--------------------------------------------------------------------------
*/

$datosSolicitud = $datos;

$datosSolicitud['tipoPersona'] = $tipoPersona;
$datosSolicitud['vigencia'] = $vigencia;

/*
| El precio válido es siempre el calculado por el servidor.
| No confiamos en el precio recibido desde el navegador.
*/
$datosSolicitud['monto_centavos'] = $montoCentavos;
$datosSolicitud['currency'] = 'USD';


$jsonSolicitud = json_encode(
    $datosSolicitud,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

if ($jsonSolicitud === false) {
    responder(500, [
        'ok' => false,
        'message' => 'No se pudieron preparar los datos de la solicitud.'
    ]);
}


/*
|--------------------------------------------------------------------------
| GUARDAR SOLICITUD PENDIENTE
|--------------------------------------------------------------------------
*/

try {
    $sql = "
        INSERT INTO solicitudes (
            client_transaction_id,
            estado,
            monto_centavos,
            datos_solicitud,
            enext_procesado,
            created_at,
            updated_at
        )
        VALUES (
            :client_transaction_id,
            'pendiente',
            :monto_centavos,
            CAST(:datos_solicitud AS json),
            false,
            CURRENT_DATE,
            CURRENT_DATE
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':client_transaction_id' => $clientTransactionId,
        ':monto_centavos' => $montoCentavos,
        ':datos_solicitud' => $jsonSolicitud
    ]);

} catch (Throwable $e) {
    responder(500, [
        'ok' => false,
        'message' => 'No se pudo registrar la solicitud antes del pago.'
    ]);
}


/*
|--------------------------------------------------------------------------
| REFERENCIA PAYPHONE
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
*/

$responseUrl =
    'https://profirma.up.railway.app/payphone_respuesta.php';


/*
|--------------------------------------------------------------------------
| DATOS PAYPHONE
|--------------------------------------------------------------------------
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
| CORREO
|--------------------------------------------------------------------------
|
| No enviamos automáticamente teléfono/documento porque los datos
| del solicitante pueden ser diferentes a los del titular del pago.
|--------------------------------------------------------------------------
*/

if ($email !== '') {
    $payphoneData['email'] = $email;
}


/*
|--------------------------------------------------------------------------
| CONVERTIR PETICIÓN PAYPHONE A JSON
|--------------------------------------------------------------------------
*/

$jsonPayphone = json_encode(
    $payphoneData,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

if ($jsonPayphone === false) {
    try {
        $stmt = $pdo->prepare("
            UPDATE solicitudes
            SET
                estado = 'error',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :id
        ");

        $stmt->execute([
            ':error' => 'No se pudo construir la solicitud PayPhone.',
            ':id' => $clientTransactionId
        ]);
    } catch (Throwable $ignored) {
    }

    responder(500, [
        'ok' => false,
        'message' => 'No se pudo preparar la información del pago.'
    ]);
}


/*
|--------------------------------------------------------------------------
| LLAMADA PAYPHONE PREPARE
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

$respuestaPayphone = curl_exec($curl);
$curlError = curl_error($curl);

$httpCode = (int) curl_getinfo(
    $curl,
    CURLINFO_HTTP_CODE
);

curl_close($curl);


/*
|--------------------------------------------------------------------------
| ERROR DE CONEXIÓN CON PAYPHONE
|--------------------------------------------------------------------------
*/

if ($respuestaPayphone === false) {

    try {
        $stmt = $pdo->prepare("
            UPDATE solicitudes
            SET
                estado = 'error',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :id
        ");

        $stmt->execute([
            ':error' => 'Error de conexión con PayPhone.',
            ':id' => $clientTransactionId
        ]);
    } catch (Throwable $ignored) {
    }

    responder(502, [
        'ok' => false,
        'message' => 'No fue posible conectar con PayPhone.',
        'detail' => $curlError
    ]);
}


/*
|--------------------------------------------------------------------------
| DECODIFICAR RESPUESTA
|--------------------------------------------------------------------------
*/

$resultado = json_decode(
    $respuestaPayphone,
    true
);

if (!is_array($resultado)) {

    try {
        $stmt = $pdo->prepare("
            UPDATE solicitudes
            SET
                estado = 'error',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :id
        ");

        $stmt->execute([
            ':error' => 'PayPhone devolvió una respuesta no válida.',
            ':id' => $clientTransactionId
        ]);
    } catch (Throwable $ignored) {
    }

    responder(502, [
        'ok' => false,
        'message' =>
            'PayPhone devolvió una respuesta que no pudo ser procesada.',
        'httpCode' => $httpCode
    ]);
}


/*
|--------------------------------------------------------------------------
| PAYPHONE RECHAZÓ PREPARE
|--------------------------------------------------------------------------
*/

if ($httpCode < 200 || $httpCode >= 300) {

    $mensajePayphone =
        $resultado['message']
        ?? 'PayPhone rechazó la preparación de la transacción.';

    try {
        $stmt = $pdo->prepare("
            UPDATE solicitudes
            SET
                estado = 'error',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :id
        ");

        $stmt->execute([
            ':error' => $mensajePayphone,
            ':id' => $clientTransactionId
        ]);
    } catch (Throwable $ignored) {
    }

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
| URL DE PAGO
|--------------------------------------------------------------------------
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
| VALIDAR URL
|--------------------------------------------------------------------------
*/

if ($paymentUrl === '') {

    try {
        $stmt = $pdo->prepare("
            UPDATE solicitudes
            SET
                estado = 'error',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :id
        ");

        $stmt->execute([
            ':error' => 'PayPhone no devolvió una URL de pago.',
            ':id' => $clientTransactionId
        ]);
    } catch (Throwable $ignored) {
    }

    responder(502, [
        'ok' => false,
        'message' => 'PayPhone no devolvió una URL de pago.',
        'clientTransactionId' => $clientTransactionId
    ]);
}


/*
|--------------------------------------------------------------------------
| GUARDAR PAYMENT ID
|--------------------------------------------------------------------------
*/

$paymentId =
    isset($resultado['paymentId'])
        ? trim((string) $resultado['paymentId'])
        : null;

if ($paymentId !== null && $paymentId !== '') {
    try {
        $stmt = $pdo->prepare("
            UPDATE solicitudes
            SET
                payphone_id = :payment_id,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :id
        ");

        $stmt->execute([
            ':payment_id' => $paymentId,
            ':id' => $clientTransactionId
        ]);
    } catch (Throwable $ignored) {
        /*
         * La orden principal ya está registrada.
         * No exponemos detalles internos de PostgreSQL.
         */
    }
}


/*
|--------------------------------------------------------------------------
| RESPUESTA FINAL
|--------------------------------------------------------------------------
*/

responder(200, [
    'ok' => true,

    'url' => $paymentUrl,
    'paymentUrl' => $paymentUrl,

    'payWithCard' => $payWithCard,
    'payWithPayPhone' => $payWithPayPhone,

    'clientTransactionId' =>
        $clientTransactionId,

    'paymentId' =>
        $paymentId,

    'tipoPersona' =>
        $tipoPersona,

    'vigencia' =>
        $vigencia,

    'amount' =>
        $montoCentavos,

    'currency' =>
        'USD'
]);
