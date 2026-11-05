<?php

/**
 * Controles de seguridad compartidos (CSRF + rate-limit de login).
 */

function awr_configurar_cookie_sesion(?string $rutaCookie = null): void
{
    if ($rutaCookie === null) {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $niveles = str_contains($script, '/app/Controllers/') ? 3 : 1;
        $directorio = str_replace('\\', '/', dirname($script, $niveles));
        $rutaCookie = rtrim($directorio, '/');
        $rutaCookie = ($rutaCookie === '' || $rutaCookie === '.') ? '/' : $rutaCookie . '/';
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $rutaCookie,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

function awr_iniciar_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function awr_csrf_token(): string
{
    awr_iniciar_sesion();

    $actual = $_SESSION['_csrf'] ?? null;

    if (!is_string($actual) || strlen($actual) < 32) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_csrf'];
}

function awr_csrf_meta(): string
{
    return '<meta name="csrf-token" content="' .
        htmlspecialchars(awr_csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function awr_token_csrf_recibido(array $entrada = []): string
{
    $cabecera = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (is_string($cabecera) && $cabecera !== '') {
        return $cabecera;
    }

    $cuerpo = $entrada['csrf_token'] ?? '';

    return is_string($cuerpo) ? $cuerpo : '';
}

function awr_csrf_valido(array $entrada = []): bool
{
    awr_iniciar_sesion();

    $esperado = $_SESSION['_csrf'] ?? null;
    $recibido = awr_token_csrf_recibido($entrada);

    return is_string($esperado)
        && $esperado !== ''
        && $recibido !== ''
        && hash_equals($esperado, $recibido);
}

function awr_exigir_csrf(array $entrada = []): void
{
    if (awr_csrf_valido($entrada)) {
        return;
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'exito' => false,
        'mensaje' => 'Sesión no válida. Recarga la página.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function awr_cliente_rate_key(string $usuario): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $usuario = mb_strtolower(trim($usuario));

    return hash('sha256', $ip . '|' . $usuario);
}

function awr_rate_limit_archivo(string $clave): string
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'awr_login_rl';

    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    return $dir . DIRECTORY_SEPARATOR . $clave . '.json';
}

/**
 * @return array{intentos:int,desde:int}
 */
function awr_rate_limit_leer(string $clave): array
{
    $ruta = awr_rate_limit_archivo($clave);

    if (!is_file($ruta)) {
        return ['intentos' => 0, 'desde' => time()];
    }

    $crudo = @file_get_contents($ruta);
    $datos = is_string($crudo) ? json_decode($crudo, true) : null;

    if (!is_array($datos)) {
        return ['intentos' => 0, 'desde' => time()];
    }

    $intentos = isset($datos['intentos']) ? (int) $datos['intentos'] : 0;
    $desde = isset($datos['desde']) ? (int) $datos['desde'] : time();

    return ['intentos' => max(0, $intentos), 'desde' => $desde];
}

function awr_rate_limit_guardar(string $clave, int $intentos, int $desde): void
{
    $ruta = awr_rate_limit_archivo($clave);
    @file_put_contents(
        $ruta,
        json_encode(['intentos' => $intentos, 'desde' => $desde], JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function awr_login_permitido(string $usuario, int $maxIntentos = 5, int $ventanaSegundos = 900): bool
{
    $clave = awr_cliente_rate_key($usuario);
    $estado = awr_rate_limit_leer($clave);
    $ahora = time();

    if (($ahora - $estado['desde']) > $ventanaSegundos) {
        awr_rate_limit_guardar($clave, 0, $ahora);
        return true;
    }

    return $estado['intentos'] < $maxIntentos;
}

function awr_login_registrar_fallo(string $usuario, int $ventanaSegundos = 900): void
{
    $clave = awr_cliente_rate_key($usuario);
    $estado = awr_rate_limit_leer($clave);
    $ahora = time();

    if (($ahora - $estado['desde']) > $ventanaSegundos) {
        awr_rate_limit_guardar($clave, 1, $ahora);
        return;
    }

    awr_rate_limit_guardar($clave, $estado['intentos'] + 1, $estado['desde']);
}

function awr_login_limpiar(string $usuario): void
{
    $ruta = awr_rate_limit_archivo(awr_cliente_rate_key($usuario));

    if (is_file($ruta)) {
        @unlink($ruta);
    }
}
