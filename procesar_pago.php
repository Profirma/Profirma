<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
header('Content-Type: application/json');

// Recibir los datos enviados desde el formulario web
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['codigo' => 0, 'mensaje' => 'Datos inválidos o JSON vacío']);
    exit;
}

// Datos obligatorios que exige la API de eNext para Persona Natural (PNB.php)
$datosEnext = [
    "numero_tramite" => $input['numero_tramite'] ?? "TRM-" . rand(1000, 9999),
    "usuario" => "facbiometria",
    "password" => "bio9411",
    "perfil_firma" => $input['perfil_firma'] ?? "002",
    "nombres" => $input['nombres'] ?? '',
    "apellidos" => $input['apellidos'] ?? '',
    "cedula" => $input['cedula'] ?? '',
    "codigo_dactilar" => $input['codigo_dactilar'] ?? '',
    "correo" => $input['correo'] ?? '',
    "provincia" => $input['provincia'] ?? 'Pichincha',
    "ciudad" => $input['ciudad'] ?? 'Quito',
    "parroquia" => "Inaquito",
    "direccion" => $input['direccion'] ?? 'Matriz',
    "celular" => $input['celular'] ?? '',
    "tipo_envio" => "EMAIL", // Envío automático de correo por eNext
    "tipo_clave" => 1
];

// Realizar la petición cURL hacia la API oficial de eNext
$ch = curl_init('https://enext.online/factureroweb/apiFactu/PNB.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datosEnext));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode('facbiometria:bio9411')
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode === 200 && $response) {
    // Decodificar la respuesta de eNext para verificar si devolvió el enlace o éxito
    $enextData = json_decode($response, true);
    
    // Si eNext responde correctamente, asegúrate de retornar la estructura esperada por tu frontend
    echo $response;
} else {
    echo json_encode([
        'codigo' => 0,
        'mensaje' => 'Error al conectar con la pasarela eNext',
        'http_code' => $httpCode,
        'detalles' => $curlError ?: 'Respuesta vacía del servidor remoto'
    ]);
}
?>
