<?php
session_start();

if (!empty($_SESSION['profirma_admin'])) {
    header('Location: index.php');
    exit;
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    $adminUser = getenv('ADMIN_USER');
    $adminPassword = getenv('ADMIN_PASSWORD');

    if (!$adminUser || !$adminPassword) {
        $mensaje = 'El acceso administrativo no está configurado.';
    } elseif (
        hash_equals($adminUser, $usuario) &&
        hash_equals($adminPassword, $password)
    ) {
        session_regenerate_id(true);

        $_SESSION['profirma_admin'] = true;
        $_SESSION['profirma_admin_user'] = $usuario;

        header('Location: index.php');
        exit;
    } else {
        $mensaje = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Administrativo | PROFIRMA</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 75% 20%, rgba(43, 118, 180, .35), transparent 32%),
                linear-gradient(135deg, #052b52 0%, #07396b 55%, #0b5597 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 22px;
            padding: 38px 38px 34px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, .25);
        }

        .logo {
            text-align: center;
            margin-bottom: 22px;
        }

        .logo img {
            width: 90px;
            max-height: 90px;
            object-fit: contain;
        }

        h1 {
            text-align: center;
            color: #07396b;
            font-size: 25px;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #718096;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .field {
            margin-bottom: 18px;
        }

        label {
            display: block;
            color: #172033;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            height: 50px;
            border: 1px solid #dce4ec;
            border-radius: 10px;
            padding: 0 14px;
            font-size: 15px;
            outline: none;
            transition: .2s;
        }

        input:focus {
            border-color: #07396b;
            box-shadow: 0 0 0 3px rgba(7, 57, 107, .10);
        }

        button {
            width: 100%;
            height: 51px;
            border: 0;
            border-radius: 10px;
            background: #07396b;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 5px;
            transition: .2s;
        }

        button:hover {
            background: #052b52;
            transform: translateY(-1px);
        }

        .error {
            background: #fdecec;
            color: #b72e2e;
            border: 1px solid #f5c2c2;
            border-radius: 9px;
            padding: 11px 13px;
            font-size: 13px;
            margin-bottom: 18px;
            text-align: center;
        }

        .security {
            text-align: center;
            color: #94a3b8;
            font-size: 11px;
            margin-top: 22px;
        }

        .security strong {
            color: #07396b;
        }
    </style>
</head>

<body>

<div class="login-box">

    <div class="logo">
        <img src="logo.jpeg" alt="PROFIRMA">
    </div>

    <h1>Panel Administrativo</h1>
    <p class="subtitle">Acceso exclusivo para PROFIRMA</p>

    <?php if ($mensaje !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <div class="field">
            <label for="usuario">Usuario</label>
            <input
                type="text"
                id="usuario"
                name="usuario"
                required
                autocomplete="username"
                placeholder="Usuario administrativo"
            >
        </div>

        <div class="field">
            <label for="password">Contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Contraseña"
            >
        </div>

        <button type="submit">
            Ingresar al panel
        </button>

    </form>

    <div class="security">
        Acceso protegido · <strong>PROFIRMA</strong>
    </div>

</div>

</body>
</html>
