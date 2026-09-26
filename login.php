<?php

$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$directorio = str_replace('\\', '/', dirname($script));
$rutaCookie = rtrim($directorio, '/');
$rutaCookie = ($rutaCookie === '' || $rutaCookie === '.') ? '/' : $rutaCookie . '/';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => $rutaCookie,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

$destinos = [
    'Administrador' => 'dashboardAdministrador.php',
    'Administrativo' => 'dashboardAdministrativo.php',
    'Operador' => 'dashboardOperador.php',
];

if (isset($_COOKIE[session_name()])) {
    session_start();
    $rol = $_SESSION['rol'] ?? null;
    $sesionValida = isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
        && is_string($rol)
        && isset($destinos[$rol]);

    if ($sesionValida) {
        header('Location: ' . $rutaCookie . $destinos[$rol], true, 302);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/login.css">
</head>
<body>
    <main class="acceso">
        <section class="acceso__identidad" aria-labelledby="nombre-awr">
            <p class="acceso__sigla">AWR</p>
            <h1 class="acceso__nombre" id="nombre-awr">Aplicativo Web Resiliente</h1>
            <p class="acceso__resumen">Ingresa con tu usuario para continuar en el sistema.</p>
        </section>
        <section class="acceso__lado">
            <form class="acceso__tarjeta" id="formulario-acceso" method="post" action="app/Controllers/AuthController.php" novalidate>
                <h2 class="acceso__encabezado">Iniciar sesión</h2>
                <p class="acceso__ayuda">Usa el usuario y la contraseña de tu cuenta.</p>
                <p class="acceso__mensaje" id="mensaje-acceso" role="alert" hidden></p>
                <div class="campo">
                    <label for="username">Usuario</label>
                    <input id="username" name="username" type="text" autocomplete="username" maxlength="100" required>
                </div>
                <div class="campo">
                    <label for="password">Contraseña</label>
                    <div class="campo__clave">
                        <input id="password" name="password" type="password" autocomplete="current-password" maxlength="255" required>
                        <button type="button" id="alternar-clave" aria-pressed="false" aria-controls="password">Mostrar</button>
                    </div>
                </div>
                <button class="acceso__enviar" id="boton-acceso" type="submit">Iniciar sesión</button>
            </form>
        </section>
    </main>
    <script src="public/js/axios.min.js"></script>
    <script src="public/js/login.js"></script>
</body>
</html>
