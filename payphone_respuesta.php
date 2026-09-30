<?php

declare(strict_types=1);

/*
 * ============================================================
 * PRO-FIRMA
 * PAYPHONE -> POSTGRESQL -> ENEXT
 * ============================================================
 *
 * IMPORTANTE:
 * - PayPhone se confirma primero.
 * - eNext SOLO se ejecuta después de un pago aprobado.
 * - Se valida el monto contra PostgreSQL.
 * - Se evita volver a ejecutar eNext si ya fue procesado.
 * - El link/token biométrico NO se muestra al cliente.
 * ============================================================
 */


/*
 * ============================================================
 * 1. VARIABLES
 * ============================================================
 */

$token = trim((string)getenv('PAYPHONE_TOKEN'));
$databaseUrl = trim((string)getenv('DATABASE_URL'));

$apiUrl = trim((string)getenv('ENEXT_API_URL'));
$basicUser = trim((string)getenv('ENEXT_BASIC_USER'));
$basicPass = (string)getenv('ENEXT_BASIC_PASSWORD');

$socioUser = trim((string)getenv('ENEXT_SOCIO_USER'));
$socioPass = (string)getenv('ENEXT_SOCIO_PASSWORD');


/*
 * ============================================================
 * 2. FUNCIONES DE RESPUESTA
 * ============================================================
 */

function mostrarPagina(
    string $titulo,
    string $mensaje,
    bool $correcto = false,
    int $httpCode = 200
): never {

    http_response_code($httpCode);

    $titulo = htmlspecialchars(
        $titulo,
        ENT_QUOTES,
        'UTF-8'
    );

    $mensaje = htmlspecialchars(
        $mensaje,
        ENT_QUOTES,
        'UTF-8'
    );

    $icono = $correcto ? '&#10003;' : '!';
    $color = $correcto ? '#2563eb' : '#dc2626';

    echo '<!doctype html>
<html lang="es">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<title>' . $titulo . ' - PROFIRMA</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 24px;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: #f8fafc;
    font-family: Arial, Helvetica, sans-serif;
    color: #0f172a;
}

.card {
    width: 100%;
    max-width: 620px;
    background: #ffffff;
    padding: 40px 32px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 15px 45px rgba(15, 23, 42, 0.10);
}

.icono {
    width: 72px;
    height: 72px;
    margin: 0 auto 22px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    background: ' . $color . ';
    color: white;
    font-size: 34px;
    font-weight: bold;
}

h1 {
    margin: 0 0 16px;
    font-size: 28px;
}

p {
    margin: 0;
    color: #475569;
    font-size: 16px;
    line-height: 1.7;
}

.marca {
    margin-top: 28px;
    color: #94a3b8;
    font-size: 13px;
}

</style>

</head>

<body>

<div class="card">

<div class="icono">
' . $icono . '
</div>

<h1>' . $titulo . '</h1>

<p>' . $mensaje . '</p>

<div class="marca">
PROFIRMA
</div>

</div>

</body>

</html>';

    exit;
}


/*
 * ============================================================
 * 3. DATOS QUE DEVUELVE PAYPHONE
 * ============================================================
 */

$id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

$clientTransactionId =
    isset($_GET['clientTransactionId'])
        ? trim((string)$_GET['clientTransactionId'])
        : '';


/*
 * ============================================================
 * 4. COMPROBAR PAYPHONE
 * ============================================================
 */

if ($token === '') {

    mostrarPagina(
        'Error de configuración',
        'No fue posible verificar el pago en este momento.',
        false,
        500
    );
}


if (
    $id <= 0 ||
    $clientTransactionId === ''
) {

    mostrarPagina(
        'Datos incompletos',
        'No se recibieron los datos necesarios de la transacción.',
        false,
        400
    );
}


/*
 * ============================================================
 * 5. MISMA CONFIRMACIÓN PAYPHONE QUE YA TENÍAS
 * ============================================================
 */

$url =
    'https://pay.payphonetodoesposible.com/api/button/V2/Confirm';

$data = [

    'id' =>
        $id,

    'clientTxId' =>
        $clientTransactionId

];


$curl = curl_init($url);


curl_setopt_array($curl, [

    CURLOPT_POST =>
        true,

    CURLOPT_RETURNTRANSFER =>
        true,

    CURLOPT_HTTPHEADER => [

        'Authorization: Bearer ' . $token,

        'Content-Type: application/json',

        'Accept: application/json'

    ],

    CURLOPT_POSTFIELDS =>
        json_encode($data),

    CURLOPT_CONNECTTIMEOUT =>
        10,

    CURLOPT_TIMEOUT =>
        30

]);


$response = curl_exec($curl);

$httpCode = (int)curl_getinfo(
    $curl,
    CURLINFO_HTTP_CODE
);

$curlError = curl_error($curl);

curl_close($curl);


/*
 * ============================================================
 * 6. ERROR DE CONEXIÓN PAYPHONE
 * ============================================================
 */

if (
    $response === false ||
    $curlError !== ''
) {

    error_log(
        'PROFIRMA / PayPhone Confirm: ' .
        $curlError
    );

    mostrarPagina(
        'No se pudo verificar el pago',
        'No fue posible comunicarse con PayPhone. No realices otro pago. Intenta nuevamente en unos minutos.',
        false,
        502
    );
}


$result = json_decode(
    (string)$response,
    true
);


/*
 * ============================================================
 * 7. VALIDAR RESPUESTA PAYPHONE
 * ============================================================
 */

if (
    $httpCode < 200 ||
    $httpCode >= 300 ||
    !is_array($result) ||
    !isset(
        $result['statusCode'],
        $result['transactionStatus'],
        $result['clientTransactionId']
    ) ||
    (int)$result['statusCode'] !== 3 ||
    (string)$result['transactionStatus'] !== 'Approved' ||
    !hash_equals(
        $clientTransactionId,
        (string)$result['clientTransactionId']
    )
) {

    mostrarPagina(
        'Pago no aprobado',
        'No se pudo confirmar el pago. No se ha iniciado el proceso de emisión de la firma electrónica.',
        false,
        400
    );
}


/*
 * ============================================================
 * DESDE AQUÍ EL PAGO ESTÁ CONFIRMADO POR PAYPHONE
 * ============================================================
 */


/*
 * ============================================================
 * 8. COMPROBAR DATABASE_URL
 * ============================================================
 */

if ($databaseUrl === '') {

    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero ocurrió un problema interno al continuar la solicitud. No realices otro pago.',
        false,
        500
    );
}


/*
 * ============================================================
 * 9. CONECTAR CON POSTGRESQL
 * ============================================================
 */

try {

    $db = parse_url($databaseUrl);

    if (
        $db === false ||
        !isset(
            $db['host'],
            $db['port'],
            $db['user'],
            $db['pass'],
            $db['path']
        )
    ) {

        throw new RuntimeException(
            'DATABASE_URL inválida.'
        );
    }


    $dbName = ltrim(
        (string)$db['path'],
        '/'
    );


    $dsn =
        'pgsql:host=' .
        $db['host'] .
        ';port=' .
        $db['port'] .
        ';dbname=' .
        $dbName;


    $pdo = new PDO(

        $dsn,

        urldecode(
            (string)$db['user']
        ),

        urldecode(
            (string)$db['pass']
        ),

        [

            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC

        ]
    );

} catch (Throwable $e) {

    error_log(
        'PROFIRMA / PostgreSQL: ' .
        $e->getMessage()
    );

    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero no pudimos continuar la solicitud en este momento. No realices otro pago.',
        false,
        500
    );
}


/*
 * ============================================================
 * 10. BUSCAR LA SOLICITUD
 * ============================================================
 */

try {

    $stmt = $pdo->prepare(
        "
        SELECT
            id,
            client_transaction_id,
            estado,
            monto_centavos,
            datos_solicitud,
            payphone_id,
            payphone_status,
            enext_procesado,
            link_biometria,
            token_biometria
        FROM solicitudes
        WHERE client_transaction_id = :client_transaction_id
        LIMIT 1
        "
    );


    $stmt->execute([

        ':client_transaction_id' =>
            $clientTransactionId

    ]);


    $solicitud = $stmt->fetch();


} catch (Throwable $e) {

    error_log(
        'PROFIRMA / Error buscando solicitud: ' .
        $e->getMessage()
    );

    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero no pudimos recuperar la solicitud. No realices otro pago.',
        false,
        500
    );
}


if (!$solicitud) {

    mostrarPagina(
        'Pago confirmado',
        'El pago fue aprobado, pero no encontramos la solicitud asociada. No realices otro pago.',
        false,
        404
    );
}


/*
 * ============================================================
 * 11. SI ENEXT YA FUE PROCESADO, NO REPETIR
 * ============================================================
 */

$enextYaProcesado = filter_var(
    $solicitud['enext_procesado'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);


if ($enextYaProcesado) {

    mostrarPagina(
        'Solicitud procesada',
        'Tu pago ya fue confirmado y tu solicitud ya fue procesada correctamente. Revisa tu correo electrónico y los canales de contacto registrados para continuar.',
        true,
        200
    );
}


/*
 * ============================================================
 * 12. VALIDAR MONTO
 * ============================================================
 */

$montoEsperado = (int)(
    $solicitud['monto_centavos']
    ?? 0
);

$montoPagado = isset($result['amount'])
    ? (int)$result['amount']
    : 0;


if (
    $montoEsperado <= 0 ||
    $montoPagado <= 0 ||
    $montoEsperado !== $montoPagado
) {

    error_log(
        'PROFIRMA / Monto incorrecto. Esperado: ' .
        $montoEsperado .
        ' / PayPhone: ' .
        $montoPagado
    );


    try {

        $stmt = $pdo->prepare(
            "
            UPDATE solicitudes
            SET
                estado = 'error',
                payphone_id = :payphone_id,
                payphone_status = 'Approved',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :client_transaction_id
            "
        );


        $stmt->execute([

            ':payphone_id' =>
                (string)$id,

            ':error' =>
                'El monto pagado no coincide con el monto esperado.',

            ':client_transaction_id' =>
                $clientTransactionId

        ]);

    } catch (Throwable $e) {

        error_log(
            'PROFIRMA / Error guardando diferencia de monto: ' .
            $e->getMessage()
        );
    }


    mostrarPagina(
        'Pago confirmado',
        'El pago fue aprobado, pero el valor confirmado no coincide con la solicitud. No se ha enviado la solicitud a eNext. No realices otro pago.',
        false,
        400
    );
}


/*
 * ============================================================
 * 13. VALIDAR MONEDA SI PAYPHONE LA DEVUELVE
 * ============================================================
 */

if (
    isset($result['currency']) &&
    trim((string)$result['currency']) !== ''
) {

    $currency = strtoupper(
        trim(
            (string)$result['currency']
        )
    );


    if ($currency !== 'USD') {

        mostrarPagina(
            'Pago confirmado',
            'El pago fue aprobado, pero la moneda no coincide con la solicitud. No se ha enviado la solicitud a eNext.',
            false,
            400
        );
    }
}


/*
 * ============================================================
 * 14. MARCAR PAGO COMO APROBADO
 * ============================================================
 */

try {

    $stmt = $pdo->prepare(
        "
        UPDATE solicitudes
        SET
            estado = 'pagado',
            payphone_id = :payphone_id,
            payphone_status = 'Approved',
            error_mensaje = NULL,
            updated_at = CURRENT_DATE
        WHERE client_transaction_id = :client_transaction_id
        "
    );


    $stmt->execute([

        ':payphone_id' =>
            (string)$id,

        ':client_transaction_id' =>
            $clientTransactionId

    ]);


} catch (Throwable $e) {

    error_log(
        'PROFIRMA / Error actualizando pago: ' .
        $e->getMessage()
    );

    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero ocurrió un problema interno al continuar. No realices otro pago.',
        false,
        500
    );
}


/*
 * ============================================================
 * 15. RECUPERAR DATOS ORIGINALES
 * ============================================================
 */

$datosSolicitud =
    $solicitud['datos_solicitud']
    ?? null;


if (is_array($datosSolicitud)) {

    $input = $datosSolicitud;

} else {

    $input = json_decode(
        (string)$datosSolicitud,
        true
    );
}


if (!is_array($input)) {

    try {

        $stmt = $pdo->prepare(
            "
            UPDATE solicitudes
            SET
                estado = 'error',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :client_transaction_id
            "
        );


        $stmt->execute([

            ':error' =>
                'No fue posible recuperar datos_solicitud.',

            ':client_transaction_id' =>
                $clientTransactionId

        ]);

    } catch (Throwable $e) {

        error_log(
            $e->getMessage()
        );
    }


    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero no pudimos recuperar los datos necesarios para emitir la firma. No realices otro pago.',
        false,
        500
    );
}


/*
 * ============================================================
 * 16. COMPROBAR CONFIGURACIÓN ENEXT
 * ============================================================
 */

if (
    $apiUrl === '' ||
    $basicUser === '' ||
    $basicPass === '' ||
    $socioUser === '' ||
    $socioPass === ''
) {

    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero el servicio de firma no está disponible en este momento. No realices otro pago.',
        false,
        500
    );
}


/*
 * ============================================================
 * 17. CAMPOS OBLIGATORIOS ENEXT
 * ============================================================
 */

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

        try {

            $stmt = $pdo->prepare(
                "
                UPDATE solicitudes
                SET
                    estado = 'error',
                    error_mensaje = :error,
                    updated_at = CURRENT_DATE
                WHERE client_transaction_id = :client_transaction_id
                "
            );


            $stmt->execute([

                ':error' =>
                    'Falta campo eNext: ' .
                    $field,

                ':client_transaction_id' =>
                    $clientTransactionId

            ]);

        } catch (Throwable $e) {

            error_log(
                $e->getMessage()
            );
        }


        mostrarPagina(
            'Pago confirmado',
            'Tu pago fue aprobado, pero faltan datos necesarios para procesar la firma. No realices otro pago.',
            false,
            500
        );
    }
}


/*
 * ============================================================
 * 18. NORMALIZAR DATOS
 * ============================================================
 */

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
    trim(
        (string)$input['codigo_dactilar']
    )
);


/*
 * ============================================================
 * 19. VALIDAR CÉDULA
 * ============================================================
 */

if (
    !preg_match(
        '/^\d{10}$/',
        $cedula
    )
) {

    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero la cédula registrada no pudo ser validada. No realices otro pago.',
        false,
        500
    );
}


/*
 * ============================================================
 * 20. VALIDAR CORREO
 * ============================================================
 */

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {

    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero el correo registrado no pudo ser validado. No realices otro pago.',
        false,
        500
    );
}


/*
 * ============================================================
 * 21. MISMOS PERFILES ENEXT QUE YA FUNCIONAN
 * ============================================================
 * 018 = 15 Días 
 * 002 = 1 año
 * 005 = 2 años
 * 010 = 3 años
 * 007 = 4 años
 * 013 = 5 años
 * ============================================================
 */

$perfilesPermitidos = [

    '018',

    '002',

    '005',

    '010',

    '007',

    '013'

];


if (
    !in_array(
        $perfil,
        $perfilesPermitidos,
        true
    )
) {

    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero el perfil de firma seleccionado no está permitido.',
        false,
        500
    );
}


/*
 * ============================================================
 * 22. MISMO PAYLOAD ENEXT QUE YA FUNCIONABA
 * ============================================================
 */

$payload = [

    'numero_tramite' =>
        trim(
            (string)$input['numero_tramite']
        ),

    'usuario' =>
        $socioUser,

    'password' =>
        $socioPass,

    'perfil_firma' =>
        $perfil,

    'nombres' =>
        trim(
            (string)$input['nombres']
        ),

    'apellidos' =>
        trim(
            (string)$input['apellidos']
        ),

    'cedula' =>
        $cedula,

    'codigo_dactilar' =>
        $codigoDactilar,

    'correo' =>
        $email,

    'provincia' =>
        trim(
            (string)$input['provincia']
        ),

    'ciudad' =>
        trim(
            (string)$input['ciudad']
        ),

    'parroquia' =>
        trim(
            (string)$input['parroquia']
        ),

    'direccion' =>
        trim(
            (string)$input['direccion']
        ),

    'celular' =>
        $celular,

    'tipo_envio' =>
        'EMAIL',

    'tipo_clave' =>
        1

];


/*
 * ============================================================
 * 23. CONVERTIR A JSON
 * ============================================================
 */

$jsonPayload = json_encode(

    $payload,

    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES

);


if ($jsonPayload === false) {

    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero no se pudo preparar la solicitud para eNext.',
        false,
        500
    );
}


/*
 * ============================================================
 * 24. MISMA CONEXIÓN ENEXT QUE YA FUNCIONABA
 * ============================================================
 */

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

]);


/*
 * ============================================================
 * 25. EJECUTAR ENEXT
 * ============================================================
 */

$responseBody =
    curl_exec($ch);


$curlError =
    curl_error($ch);


$enextHttpCode =
    (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );


curl_close($ch);


/*
 * ============================================================
 * 26. ERROR DE CONEXIÓN ENEXT
 * ============================================================
 */

if (
    $responseBody === false ||
    $curlError !== ''
) {

    error_log(
        'PROFIRMA / eNext error de conexión: ' .
        $curlError
    );


    try {

        $stmt = $pdo->prepare(
            "
            UPDATE solicitudes
            SET
                estado = 'error',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :client_transaction_id
            "
        );


        $stmt->execute([

            ':error' =>
                'No fue posible conectar con eNext.',

            ':client_transaction_id' =>
                $clientTransactionId

        ]);

    } catch (Throwable $e) {

        error_log(
            $e->getMessage()
        );
    }


    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero no pudimos conectar con el servicio de firma. No realices otro pago.',
        false,
        502
    );
}


/*
 * ============================================================
 * 27. MISMO TRATAMIENTO DE RESPUESTA ENEXT
 * ============================================================
 *
 * eNext puede anteponer avisos HTML/PHP antes del JSON.
 * ============================================================
 */

$responseText = trim(
    (string)$responseBody
);


$enextResult = json_decode(
    $responseText,
    true
);


if (!is_array($enextResult)) {

    $jsonStart = strpos(
        $responseText,
        '{'
    );


    if ($jsonStart !== false) {

        $jsonOnly = substr(
            $responseText,
            $jsonStart
        );


        $enextResult = json_decode(
            $jsonOnly,
            true
        );
    }
}


/*
 * ============================================================
 * 28. COMPROBAR JSON ENEXT
 * ============================================================
 */

if (!is_array($enextResult)) {

    error_log(
        'PROFIRMA / eNext respuesta no JSON. HTTP ' .
        $enextHttpCode .
        ' - ' .
        substr(
            $responseText,
            0,
            1000
        )
    );


    try {

        $stmt = $pdo->prepare(
            "
            UPDATE solicitudes
            SET
                estado = 'error',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :client_transaction_id
            "
        );


        $stmt->execute([

            ':error' =>
                'eNext devolvió una respuesta inválida.',

            ':client_transaction_id' =>
                $clientTransactionId

        ]);

    } catch (Throwable $e) {

        error_log(
            $e->getMessage()
        );
    }


    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero eNext devolvió una respuesta inválida. No realices otro pago.',
        false,
        502
    );
}


/*
 * ============================================================
 * 29. ENEXT DEBE RESPONDER CODIGO 1
 * ============================================================
 */

if (
    $enextHttpCode < 200 ||
    $enextHttpCode >= 300 ||
    (int)($enextResult['codigo'] ?? 0) !== 1
) {

    $mensajeEnext = trim(
        (string)(
            $enextResult['mensaje']
            ?? ''
        )
    );


    error_log(
        'PROFIRMA / eNext rechazó solicitud. HTTP ' .
        $enextHttpCode .
        ' - ' .
        $mensajeEnext
    );


    try {

        $stmt = $pdo->prepare(
            "
            UPDATE solicitudes
            SET
                estado = 'error',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :client_transaction_id
            "
        );


        $stmt->execute([

            ':error' =>
                $mensajeEnext !== ''
                    ? $mensajeEnext
                    : 'eNext rechazó la solicitud.',

            ':client_transaction_id' =>
                $clientTransactionId

        ]);

    } catch (Throwable $e) {

        error_log(
            $e->getMessage()
        );
    }


    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado, pero eNext no pudo completar la solicitud. No realices otro pago.',
        false,
        502
    );
}


/*
 * ============================================================
 * 30. OBTENER TOKEN Y LINK DE BIOMETRÍA
 * ============================================================
 *
 * SE GUARDAN INTERNAMENTE.
 * NO SE MUESTRAN EN LA PÁGINA.
 * ============================================================
 */

$tokenBiometria = trim(
    (string)(
        $enextResult['token_biometria']
        ?? ''
    )
);


$linkBiometria = trim(
    (string)(
        $enextResult['link_biometria']
        ?? ''
    )
);


/*
 * ============================================================
 * 31. VALIDAR RESPUESTA EXITOSA
 * ============================================================
 */

if (
    $tokenBiometria === '' ||
    $linkBiometria === ''
) {

    error_log(
        'PROFIRMA / eNext código 1 sin token/link biométrico.'
    );


    try {

        $stmt = $pdo->prepare(
            "
            UPDATE solicitudes
            SET
                estado = 'error',
                error_mensaje = :error,
                updated_at = CURRENT_DATE
            WHERE client_transaction_id = :client_transaction_id
            "
        );


        $stmt->execute([

            ':error' =>
                'eNext respondió código 1 pero sin token/link biométrico.',

            ':client_transaction_id' =>
                $clientTransactionId

        ]);

    } catch (Throwable $e) {

        error_log(
            $e->getMessage()
        );
    }


    mostrarPagina(
        'Pago confirmado',
        'Tu pago fue aprobado y la solicitud fue registrada, pero falta información del proceso biométrico. No realices otro pago.',
        false,
        502
    );
}


/*
 * ============================================================
 * 32. GUARDAR RESULTADO EN POSTGRESQL
 * ============================================================
 */

try {

    $stmt = $pdo->prepare(
        "
        UPDATE solicitudes
        SET
            estado = 'procesado',
            payphone_id = :payphone_id,
            payphone_status = 'Approved',
            enext_procesado = TRUE,
            token_biometria = :token_biometria,
            link_biometria = :link_biometria,
            error_mensaje = NULL,
            updated_at = CURRENT_DATE
        WHERE
            client_transaction_id = :client_transaction_id
            AND enext_procesado = FALSE
        "
    );


    $stmt->execute([

        ':payphone_id' =>
            (string)$id,

        ':token_biometria' =>
            $tokenBiometria,

        ':link_biometria' =>
            $linkBiometria,

        ':client_transaction_id' =>
            $clientTransactionId

    ]);


} catch (Throwable $e) {

    error_log(
        'PROFIRMA / Error guardando resultado eNext: ' .
        $e->getMessage()
    );


    mostrarPagina(
        'Solicitud recibida',
        'Tu pago fue aprobado y la solicitud fue enviada al servicio de firma, pero ocurrió un problema al guardar el resultado interno. No realices otro pago.',
        false,
        500
    );
}


/*
 * ============================================================
 * 33. RESPUESTA FINAL
 * ============================================================
 *
 * NO SE MUESTRA:
 * - link_biometria
 * - token_biometria
 * ============================================================
 */

mostrarPagina(
    'Solicitud procesada correctamente',
    'Tu pago fue confirmado y tu solicitud de firma electrónica fue procesada correctamente. Revisa tu correo electrónico y los canales de contacto registrados para continuar el proceso.',
    true,
    200
);
