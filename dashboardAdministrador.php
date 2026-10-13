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

$rolEsperado = 'Administrador';
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

$nombreCompleto = trim(
    ($_SESSION['nombres'] ?? '') . ' ' .
    ($_SESSION['apellidos'] ?? '')
);

if ($nombreCompleto === '') {
    $nombreCompleto = $_SESSION['username'] ?? 'Usuario';
}

$nombreVisible = htmlspecialchars((string) $nombreCompleto, ENT_QUOTES, 'UTF-8');
$rol = htmlspecialchars((string) $_SESSION['rol'], ENT_QUOTES, 'UTF-8');
$inicialFuente = (string) $nombreCompleto;
$inicialBruta = function_exists('mb_substr')
    ? mb_substr($inicialFuente, 0, 1, 'UTF-8')
    : substr($inicialFuente, 0, 1);
$inicial = htmlspecialchars($inicialBruta, ENT_QUOTES, 'UTF-8');

$icono = static function (string $contenido): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $contenido . '</svg>';
};

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Administrador | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardAdministrador.css">
</head>
<body>
    <div class="mando">
        <aside class="barra" aria-label="Navegación del administrador">
            <div class="barra__marca">
                <div class="barra__logo">
                    <img src="public/img/branding/awr-logo.png" alt="AWR">
                </div>
                <p>Aplicativo Web Resiliente</p>
            </div>
            <details class="barra__menu" open>
                <summary>Secciones</summary>
                <nav class="barra__nav">
                    <p class="barra__grupo">Operación</p>
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>') ?>
                        <span>Dashboard Principal</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M4 13h4l2-6 4 12 2-6h4"/>') ?>
                        <span>Monitoreo en Tiempo Real</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>') ?>
                        <span>Servicios</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>') ?>
                        <span>Bases de Datos</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M4 19V5M4 19h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>') ?>
                        <span>Reportes</span>
                    </button>

                    <p class="barra__grupo">Analítica</p>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M4 16 9 11l3 3 8-8"/><path d="M15 6h5v5"/>') ?>
                        <span>Métricas de Rendimiento</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>') ?>
                        <span>Logs y Trazas</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M6 9a6 6 0 0 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>') ?>
                        <span>Alertas</span>
                    </button>

                    <p class="barra__grupo">Administración</p>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/><path d="M5 20a7 7 0 0 1 14 0"/>') ?>
                        <span>Usuarios</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<circle cx="8" cy="15" r="3"/><path d="M11 15h10v2h-2v2h-2v-2h-2"/><path d="M13 15v-2"/>') ?>
                        <span>Roles y Permisos</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/>') ?>
                        <span>Configuración</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M8 4h8v16H8z"/><path d="M8 8H6M8 12H6M8 16H6M12 8h2M12 12h2"/>') ?>
                        <span>Auditoría</span>
                    </button>

                    <p class="barra__grupo">Sistema &amp; Soporte</p>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>') ?>
                        <span>Estado del Sistema</span>
                    </button>
                </nav>
            </details>
            <p class="barra__nota">Monitoreo no conectado</p>
        </aside>

        <div class="zona">
            <header class="tope">
                <label class="buscar">
                    <span class="solo-lectura">Búsqueda visual</span>
                    <?= $icono('<circle cx="11" cy="11" r="6"/><path d="m20 20-3.5-3.5"/>') ?>
                    <input type="search" placeholder="Buscar" readonly>
                </label>
                <ul class="tope__senales" aria-label="Indicadores pendientes">
                    <li><span>Disponibilidad</span><strong>Pendiente de monitoreo</strong></li>
                    <li><span>Latencia</span><strong>Pendiente de monitoreo</strong></li>
                    <li><span>Sincronización</span><strong>Pendiente</strong></li>
                </ul>
                <div class="tope__cuenta">
                    <div class="tope__identidad">
                        <span class="tope__avatar" aria-hidden="true"><?= $inicial ?></span>
                        <div>
                            <p class="tope__usuario"><?= $nombreVisible ?></p>
                            <p class="tope__rol"><?= $rol ?></p>
                        </div>
                    </div>
                    <button class="cerrar" id="cerrar-sesion" type="button">Cerrar sesión</button>
                </div>
            </header>
            <p class="tope__aviso" id="mensaje-sesion" role="alert" hidden></p>

            <main class="principal">
                <header class="encabezado">
                    <p class="migas">
                        <span>Centro de mando</span>
                        <span>Observabilidad AWR</span>
                    </p>
                    <div class="encabezado__cuerpo">
                        <div>
                            <h1>Centro de Mando · Telemetría &amp; Infraestructura AWR</h1>
                            <p>Panel administrativo para supervisión operativa, resiliencia y componentes principales de la plataforma.</p>
                        </div>
                        <p class="sello" id="sello-fuente">…</p>
                    </div>
                </header>

                <section class="franja" aria-labelledby="titulo-indicadores">
                    <h2 id="titulo-indicadores" class="solo-lectura">Indicadores</h2>
                    <div class="indicadores">
                        <article class="tarjeta">
                            <div class="tarjeta__cabeza">
                                <h3>Usuarios &amp; Acceso</h3>
                                <span class="insignia" id="usuarios-insignia">…</span>
                            </div>
                            <p class="valor-pendiente" id="usuarios-total">…</p>
                            <p class="nota-pendiente" id="usuarios-nota">…</p>
                            <div class="huella" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                        </article>
                        <article class="tarjeta">
                            <div class="tarjeta__cabeza">
                                <h3>Latencia de Red</h3>
                                <span class="insignia" id="latencia-insignia">Toxiproxy</span>
                            </div>
                            <p class="valor-pendiente" id="latencia-valor">…</p>
                            <p class="nota-pendiente" id="latencia-nota">Latencia configurada</p>
                            <div class="huella" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                        </article>
                        <article class="tarjeta">
                            <div class="tarjeta__cabeza">
                                <h3>Disponibilidad</h3>
                                <span class="insignia">Pendiente</span>
                            </div>
                            <p class="valor-pendiente">Pendiente</p>
                            <p class="nota-pendiente">Pendiente de monitoreo</p>
                            <div class="huella" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                        </article>
                        <article class="tarjeta">
                            <div class="tarjeta__cabeza">
                                <h3>Seguridad &amp; Cumplimiento</h3>
                                <span class="insignia">Pendiente</span>
                            </div>
                            <p class="valor-pendiente">Pendiente</p>
                            <p class="nota-pendiente">Sin indicador porcentual</p>
                            <div class="huella" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                        </article>
                    </div>
                </section>

                <section class="franja" aria-labelledby="titulo-arquitectura">
                    <div class="franja__cabeza">
                        <h2 id="titulo-arquitectura">Arquitectura de Datos &amp; Motor Analítico</h2>
                        <p id="arquitectura-resumen">…</p>
                    </div>
                    <div class="arquitectura">
                        <article class="tarjeta tarjeta--motor">
                            <div class="motor">
                                <?= $icono('<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>') ?>
                            </div>
                            <div>
                                <h3>PostgreSQL</h3>
                                <p id="pg-detalle">…</p>
                                <p class="nota-pendiente" id="pg-nota">…</p>
                            </div>
                        </article>
                        <article class="tarjeta tarjeta--motor">
                            <div class="motor motor--cian">
                                <?= $icono('<path d="M4 19V5M4 19h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>') ?>
                            </div>
                            <div>
                                <h3>ClickHouse</h3>
                                <p>Estado: Pendiente de integración</p>
                                <p class="nota-pendiente">Pendiente de integración</p>
                            </div>
                        </article>
                        <article class="tarjeta tarjeta--motor">
                            <div class="motor motor--profundo">
                                <?= $icono('<path d="M4 12h16"/><path d="m8 8 4 4-4 4M14 8l4 4-4 4"/>') ?>
                            </div>
                            <div>
                                <h3>Redpanda</h3>
                                <p>Estado: Pendiente de integración</p>
                                <p class="nota-pendiente">Pendiente de integración</p>
                            </div>
                        </article>
                    </div>
                </section>

                <div class="columnas">
                    <section class="panel" aria-labelledby="titulo-servicios">
                        <div class="panel__cabeza">
                            <h2 id="titulo-servicios">Estado de Servicios &amp; Daemons</h2>
                            <span class="insignia">Pendiente</span>
                        </div>
                        <p class="panel__texto">Pendiente de monitoreo. PostgreSQL se comprueba con la conexión activa.</p>
                        <ul class="lista">
                            <li><span><i class="punto" aria-hidden="true"></i>Aplicación PHP</span><strong>Pendiente</strong></li>
                            <li><span><i class="punto" aria-hidden="true"></i><span id="servicio-postgres">PostgreSQL</span></span><strong id="servicio-postgres-estado">…</strong></li>
                            <li><span><i class="punto" aria-hidden="true"></i>Redpanda</span><strong>Pendiente</strong></li>
                            <li><span><i class="punto" aria-hidden="true"></i>ClickHouse</span><strong>Pendiente</strong></li>
                        </ul>
                    </section>

                    <section class="panel" aria-labelledby="titulo-rendimiento">
                        <div class="panel__cabeza">
                            <h2 id="titulo-rendimiento">Rendimiento del Sistema &amp; Hardware</h2>
                            <span class="insignia">Pendiente</span>
                        </div>
                        <p class="panel__texto">Pendiente de monitoreo.</p>
                        <ul class="lista lista--pista">
                            <li><span>Tiempo API</span><span class="pista" aria-hidden="true"></span><strong>Pendiente</strong></li>
                            <li><span>PostgreSQL</span><span class="pista" aria-hidden="true"></span><strong id="tiempo-postgres">…</strong></li>
                            <li><span>RTT</span><span class="pista" aria-hidden="true"></span><strong>Pendiente</strong></li>
                            <li><span>Throughput</span><span class="pista" aria-hidden="true"></span><strong>Pendiente</strong></li>
                            <li><span>ClickHouse</span><span class="pista" aria-hidden="true"></span><strong>Pendiente</strong></li>
                        </ul>
                    </section>
                </div>

                <div class="columnas">
                    <section class="panel" aria-labelledby="titulo-usuarios">
                        <div class="panel__cabeza">
                            <h2 id="titulo-usuarios">Usuarios, Auditoría &amp; Seguridad</h2>
                            <span class="insignia">PostgreSQL</span>
                        </div>
                        <ul class="lista">
                            <li><span>Usuarios registrados</span><strong id="usuarios-registrados">…</strong></li>
                            <li><span>Usuarios habilitados</span><strong id="usuarios-habilitados">…</strong></li>
                            <li><span>Sesiones</span><strong id="usuarios-sesiones">…</strong></li>
                            <li><span>Roles</span><strong id="usuarios-roles">…</strong></li>
                            <li><span>Último acceso</span><strong id="usuarios-ultimo-acceso">…</strong></li>
                            <li><span>Intentos fallidos</span><strong id="usuarios-fallidos">…</strong></li>
                        </ul>
                    </section>

                    <section class="panel" aria-labelledby="titulo-accesos">
                        <div class="panel__cabeza">
                            <h2 id="titulo-accesos">Accesos Administrativos &amp; Operación</h2>
                            <span class="insignia">PostgreSQL</span>
                        </div>
                        <div class="accesos">
                            <button type="button" disabled>
                                <span>Gestionar Usuarios y Cuentas</span>
                                <strong id="acceso-usuarios">…</strong>
                            </button>
                            <button type="button" disabled>
                                <span>Matriz de Roles y Permisos</span>
                                <strong id="acceso-roles">…</strong>
                            </button>
                            <button type="button" disabled>
                                <span>Configuración</span>
                                <strong>Pendiente</strong>
                            </button>
                            <button type="button" disabled>
                                <span>Auditoría</span>
                                <strong id="acceso-auditoria">…</strong>
                            </button>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
    <script src="public/js/axios.min.js"></script>
    <script src="public/js/dashboardAdministrador.js"></script>
</body>
</html>
