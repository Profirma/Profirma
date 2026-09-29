<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$databaseUrl = trim((string) getenv('DATABASE_URL'));

if ($databaseUrl === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'mensaje' => 'DATABASE_URL no está disponible.'
    ]);
    exit;
}

try {
    $db = parse_url($databaseUrl);

    if ($db === false) {
        throw new Exception('No se pudo interpretar DATABASE_URL.');
    }

    $host = $db['host'] ?? '';
    $port = $db['port'] ?? 5432;
    $user = $db['user'] ?? '';
    $pass = $db['pass'] ?? '';
    $name = isset($db['path']) ? ltrim($db['path'], '/') : '';

    if ($host === '' || $user === '' || $name === '') {
        throw new Exception('DATABASE_URL está incompleta.');
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $host,
        $port,
        $name
    );

    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $stmt = $pdo->query(
        "SELECT COUNT(*) AS total FROM solicitudes"
    );

    $resultado = $stmt->fetch();

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Conexión PostgreSQL correcta.',
        'tabla' => 'solicitudes',
        'registros' => (int) ($resultado['total'] ?? 0)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'mensaje' => 'No se pudo conectar con PostgreSQL.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
