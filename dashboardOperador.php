<?php

$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$directorio = str_replace('\\', '/', dirname($script));
$rutaCookie = rtrim($directorio, '/');
$rutaCookie = ($rutaCookie === '' || $rutaCookie === '.') ? '/' : $rutaCookie . '/';
$login = $rutaCookie . 'login.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => $rutaCookie,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

$rolEsperado = 'Operador';
$sesionValida = false;

if (isset($_COOKIE[session_name()])) {
    session_start();
    $sesionValida = isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
        && $_SESSION['rol'] === $rolEsperado;
}

if (!$sesionValida) {
    header('Location: ' . $login, true, 302);
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Operador</title>
</head>
<body>
    <h1>Dashboard Operador</h1>
    <p>Rol: <?= htmlspecialchars((string) $_SESSION['rol'], ENT_QUOTES, 'UTF-8') ?></p>
</body>
</html>
