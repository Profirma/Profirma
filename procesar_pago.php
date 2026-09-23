<?php
header('Content-Type: application/json');

// Recibir los datos enviados desde el formulario web
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['codigo' => 0, 'mensaje' => 'Datos inválidos']);
    exit;
}

// Datos obligatorios que exige la API de eNext para Persona Natural (PNB.php)
$datosEnext = [
    "numero_tramite" => $input['numero_tramite'] ?? "TRM-" . rand(1000, 9999),
    "usuario" => "facbiometria", // Tu usuario proporcionado por eNext
    "password" => "bio9411",       // Tu contraseña proporcionada por eNext
    "perfil_firma"   => $input['perfil_firma'] ?? "002", // Ej: 002 para 1 año
    "nombres"        => $input['nombres'],
    "apellidos"      => $input['apellidos'],
    "cedula"         => $input['cedula'],
    "codigo_dactilar"=> $input['codigo_dactilar'],
    "correo"         => $input['correo'],
    "provincia"      => $input['provincia'],
    "ciudad"         => $input['ciudad'],
    "parroquia"      => "Inaquito",
    "direccion"      => $input['direccion'],
    "celular"        => $input['celular'],
    "tipo_envio"     => "EMAIL", // Esto hace que eNext envíe automáticamente el correo y WhatsApp al cliente[cite: 3]
    "tipo_clave"     => 1
];

// Realizar la petición cURL hacia la API oficial de eNext
$ch = curl_init('https://enext.online/factureroweb/apiFactu/PNB.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datosEnext));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode('facbiometria:bio9411') // Credenciales Basic Auth[cite: 3]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    // Retorna la respuesta exitosa de eNext a tu página web
    echo $response;
} else {
    echo json_encode([
        'codigo' => 0,
        'mensaje' => 'Error al conectar con la pasarela eNext (HTTP Code: ' . $httpCode . ')'
    ]);
}
?>