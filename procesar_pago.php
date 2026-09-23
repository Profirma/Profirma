<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['codigo' => 0, 'mensaje' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(int $http, string $message): never {
    http_response_code($http);
    echo json_encode(['codigo' => 0, 'mensaje' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) fail(400, 'Solicitud JSON inválida.');

$apiUrl  = trim((string) getenv('SIGN_API_URL'));
$apiUser = trim((string) getenv('SIGN_API_USER'));
$apiPass = (string) getenv('SIGN_API_PASSWORD');
if ($apiUrl === '' || $apiUser === '' || $apiPass === '') {
    fail(500, 'La integración privada de PROFIRMA no está configurada.');
}

$required = ['numero_tramite','perfil_firma','nombres','apellidos','cedula','codigo_dactilar','correo','provincia','ciudad','parroquia','direccion','celular'];
foreach ($required as $field) {
    if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
        fail(422, 'Falta el campo requerido: ' . $field . '.');
    }
}

$cedula = preg_replace('/\D+/', '', (string)$input['cedula']);
$celular = preg_replace('/[^0-9+]/', '', (string)$input['celular']);
$email = trim((string)$input['correo']);
$perfil = trim((string)$input['perfil_firma']);

if (!preg_match('/^\d{10}$/', $cedula)) fail(422, 'La cédula debe tener 10 dígitos.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'El correo electrónico no es válido.');
if (!in_array($perfil, ['002','005','010','013'], true)) fail(422, 'La vigencia seleccionada no está disponible.');

$payload = [
    'numero_tramite'  => trim((string)$input['numero_tramite']),
    'usuario'         => $apiUser,
    'password'        => $apiPass,
    'perfil_firma'    => $perfil,
    'nombres'         => trim((string)$input['nombres']),
    'apellidos'       => trim((string)$input['apellidos']),
    'cedula'          => $cedula,
    'codigo_dactilar' => strtoupper(trim((string)$input['codigo_dactilar'])),
    'correo'          => $email,
    'provincia'       => trim((string)$input['provincia']),
    'ciudad'          => trim((string)$input['ciudad']),
    'parroquia'       => trim((string)$input['parroquia']),
    'direccion'       => trim((string)$input['direccion']),
    'celular'         => $celular,
    'tipo_envio'      => 'EMAIL',
    'tipo_clave'      => 1,
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 45,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $apiUser . ':' . $apiPass,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($responseBody === false || $curlError !== '') {
    error_log('PROFIRMA provider connection error: ' . $curlError);
    fail(502, 'No fue posible conectar con el servicio de emisión. Intenta nuevamente.');
}

$result = json_decode((string)$responseBody, true);
if (!is_array($result)) {
    error_log('PROFIRMA provider invalid response HTTP ' . $httpCode . ': ' . substr((string)$responseBody, 0, 1000));
    fail(502, 'El servicio de emisión devolvió una respuesta inválida.');
}

if ($httpCode < 200 || $httpCode >= 300 || (int)($result['codigo'] ?? 0) !== 1) {
    $providerMessage = trim((string)($result['mensaje'] ?? ''));
    error_log('PROFIRMA provider rejected request HTTP ' . $httpCode . ': ' . $providerMessage);
    $publicMessage = $providerMessage !== '' ? $providerMessage : 'No se pudo registrar la solicitud.';
    fail($httpCode >= 400 && $httpCode <= 599 ? $httpCode : 502, $publicMessage);
}

$link = trim((string)($result['link_biometria'] ?? ''));
$token = trim((string)($result['token_biometria'] ?? ''));
if ($link === '' || $token === '') {
    error_log('PROFIRMA provider success response missing validation link/token.');
    fail(502, 'La solicitud fue registrada, pero no se recibió el enlace de validación.');
}

echo json_encode([
    'codigo' => 1,
    'mensaje' => 'Solicitud registrada correctamente.',
    'token_biometria' => $token,
    'link_biometria' => $link,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

