<?php

$token = getenv('PAYPHONE_TOKEN');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$clientTransactionId = isset($_GET['clientTransactionId'])
    ? trim($_GET['clientTransactionId'])
    : '';

if (!$token) {
    http_response_code(500);
    exit('Error de configuración de PayPhone.');
}

if ($id <= 0 || $clientTransactionId === '') {
    http_response_code(400);
    exit('No se recibieron los datos necesarios de la transacción.');
}

$url = 'https://pay.payphonetodoesposible.com/api/button/V2/Confirm';

$data = [
    'id' => $id,
    'clientTxId' => $clientTransactionId
];

$curl = curl_init($url);

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);

curl_close($curl);

if ($response === false || $curlError !== '') {
    http_response_code(502);
    exit('No fue posible verificar el pago con PayPhone.');
}

$result = json_decode($response, true);

if (
    $httpCode >= 200 &&
    $httpCode < 300 &&
    is_array($result) &&
    isset($result['statusCode'], $result['transactionStatus']) &&
    (int) $result['statusCode'] === 3 &&
    $result['transactionStatus'] === 'Approved' &&
    isset($result['clientTransactionId']) &&
    hash_equals(
        $clientTransactionId,
        (string) $result['clientTransactionId']
    )
) {
    echo '<!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Pago aprobado - PROFIRMA</title>
    </head>
    <body>
        <h1>Pago aprobado</h1>
        <p>Tu pago fue confirmado correctamente por PayPhone.</p>
        <p>Estamos procesando tu solicitud.</p>
    </body>
    </html>';

    exit;
}

http_response_code(400);

echo '<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pago no aprobado - PROFIRMA</title>
</head>
<body>
    <h1>Pago no aprobado</h1>
    <p>No se pudo confirmar el pago.</p>
    <p>No se ha iniciado el proceso de emisión de la firma electrónica.</p>
</body>
</html>';
