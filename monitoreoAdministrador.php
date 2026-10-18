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
    <title>Monitoreo en Tiempo Real | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardAdministrador.css?v=3">
    <link rel="stylesheet" href="public/css/monitoreoAdministrador.css?v=9">
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
                    <a class="barra__item" href="dashboardAdministrador.php">
                        <?= $icono('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>') ?>
                        <span>Dashboard Principal</span>
                    </a>
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<path d="M4 13h4l2-6 4 12 2-6h4"/>') ?>
                        <span>Monitoreo en Tiempo Real</span>
                    </button>
                    <a class="barra__item" href="serviciosAdministrador.php">
                        <?= $icono('<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>') ?>
                        <span>Servicios</span>
                    </a>
                    <a class="barra__item" href="basesDatosAdministrador.php">
                        <?= $icono('<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>') ?>
                        <span>Bases de Datos</span>
                    </a>
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
                    <a class="barra__item" href="usuariosAccesosAdministrador.php">
                        <?= $icono('<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/><path d="M5 20a7 7 0 0 1 14 0"/>') ?>
                        <span>Usuarios y Accesos</span>
                    </a>
                    <a class="barra__item" href="clientesAdministrador.php">
                        <?= $icono('<path d="M8 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3Z"/><path d="M16 11a3 3 0 1 0-3-3"/><path d="M2 19a6 6 0 0 1 10.5-3.8"/><path d="M22 19a6 6 0 0 0-4-5.6"/>') ?>
                        <span>Clientes</span>
                    </a>
                    <a class="barra__item" href="configuracionAdministrador.php">
                        <?= $icono('<circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/>') ?>
                        <span>Configuración</span>
                    </a>
                    <a class="barra__item" href="portafolioAdministrador.php">
                        <?= $icono('<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/>') ?>
                        <span>Portafolio Digital</span>
                    </a>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M8 4h8v16H8z"/><path d="M8 8H6M8 12H6M8 16H6M12 8h2M12 12h2"/>') ?>
                        <span>Auditoría</span>
                    </button>
                </nav>
            </details>
            <p class="barra__nota">Actualización cada 5 segundos</p>
        </aside>

        <div class="zona">
            <header class="tope">
                <label class="buscar">
                    <span class="solo-lectura">Búsqueda visual</span>
                    <?= $icono('<circle cx="11" cy="11" r="6"/><path d="m20 20-3.5-3.5"/>') ?>
                    <input type="search" placeholder="Buscar" readonly>
                </label>
                <ul class="tope__senales" aria-label="Indicadores del sistema">
                    <li><span>Disponibilidad</span><strong id="senal-disponibilidad">…</strong></li>
                    <li><span>Latencia</span><strong id="senal-latencia">…</strong></li>
                    <li><span>Sincronización</span><strong id="senal-sincronizacion">…</strong></li>
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
                    <div>
                        <h1>Monitoreo en Tiempo Real</h1>
                        <p>Estado actual de los componentes principales de AWR.</p>
                        <a class="volver" href="dashboardAdministrador.php">← Volver al Dashboard</a>
                    </div>
                </header>

                <p class="carga" id="carga-monitoreo" role="status">Cargando estado del sistema...</p>
                <p class="aviso" id="aviso-monitoreo" role="alert" hidden>No se pudo actualizar el estado del sistema.</p>

                <div id="panel-monitoreo" hidden>
                    <section class="general" id="estado-general" aria-labelledby="titulo-general">
                        <div>
                            <h2 id="titulo-general">Estado general</h2>
                            <p class="general__valor" id="general-valor">…</p>
                        </div>
                        <p class="general__hora">Última actualización: <time id="general-hora">--:--:--</time></p>
                    </section>

                    <section class="componentes" aria-label="Componentes del sistema">
                        <article class="componente" id="card-aplicacion">
                            <header class="componente__cabeza">
                                <h2>AWR / PHP</h2>
                                <p class="estado" id="php-estado">…</p>
                            </header>
                            <dl>
                                <div>
                                    <dt>Versión PHP</dt>
                                    <dd id="php-version">…</dd>
                                </div>
                                <div>
                                    <dt>Memoria usada en la petición</dt>
                                    <dd id="php-memoria">…</dd>
                                </div>
                            </dl>
                        </article>

                        <article class="componente" id="card-postgresql">
                            <header class="componente__cabeza">
                                <h2>PostgreSQL</h2>
                                <p class="estado" id="pg-estado">…</p>
                            </header>
                            <dl>
                                <div>
                                    <dt>Versión</dt>
                                    <dd id="pg-version">…</dd>
                                </div>
                                <div>
                                    <dt>Base</dt>
                                    <dd id="pg-base">…</dd>
                                </div>
                                <div>
                                    <dt>Tablas</dt>
                                    <dd id="pg-tablas">…</dd>
                                </div>
                                <div>
                                    <dt>Tamaño</dt>
                                    <dd id="pg-tamano">…</dd>
                                </div>
                                <div>
                                    <dt>Conexiones</dt>
                                    <dd id="pg-conexiones">…</dd>
                                </div>
                                <div>
                                    <dt>Respuesta observada</dt>
                                    <dd id="pg-respuesta">…</dd>
                                </div>
                            </dl>
                        </article>

                        <article class="componente" id="card-toxiproxy">
                            <header class="componente__cabeza">
                                <h2>Toxiproxy</h2>
                                <p class="estado" id="toxi-estado">…</p>
                            </header>
                            <dl>
                                <div>
                                    <dt>Proxy</dt>
                                    <dd id="toxi-proxy">…</dd>
                                </div>
                                <div>
                                    <dt>Enabled</dt>
                                    <dd id="toxi-enabled">…</dd>
                                </div>
                                <div>
                                    <dt>Latencia configurada</dt>
                                    <dd id="toxi-latencia">…</dd>
                                </div>
                            </dl>
                        </article>

                        <article class="componente" id="card-redpanda">
                            <header class="componente__cabeza">
                                <h2>Redpanda</h2>
                                <p class="estado" id="redpanda-estado">…</p>
                            </header>
                            <dl>
                                <div>
                                    <dt>Broker</dt>
                                    <dd id="redpanda-broker">…</dd>
                                </div>
                                <div>
                                    <dt>Puerto Kafka</dt>
                                    <dd id="redpanda-kafka">…</dd>
                                </div>
                                <div>
                                    <dt>Admin API</dt>
                                    <dd id="redpanda-admin">…</dd>
                                </div>
                            </dl>
                        </article>

                        <article class="componente" id="card-clickhouse">
                            <header class="componente__cabeza">
                                <h2>ClickHouse</h2>
                                <p class="estado" id="clickhouse-estado">…</p>
                            </header>
                            <dl>
                                <div>
                                    <dt>Versión</dt>
                                    <dd id="clickhouse-version">…</dd>
                                </div>
                                <div>
                                    <dt>Base</dt>
                                    <dd id="clickhouse-base">…</dd>
                                </div>
                                <div>
                                    <dt>Tablas</dt>
                                    <dd id="clickhouse-tablas">…</dd>
                                </div>
                                <div>
                                    <dt>Respuesta observada</dt>
                                    <dd id="clickhouse-respuesta">…</dd>
                                </div>
                            </dl>
                        </article>
                    </section>

                    <section class="resiliencia" aria-labelledby="titulo-resiliencia">
                        <div class="resiliencia__cabeza">
                            <div>
                                <h2 id="titulo-resiliencia">Operaciones en Resiliencia</h2>
                                <p class="resiliencia__ayuda">Cola real de facturas pendientes según el lag del consumidor.</p>
                            </div>
                            <button type="button" class="resiliencia__boton" id="btn-ver-pendientes">
                                Ver facturas pendientes
                            </button>
                        </div>
                        <div class="resiliencia__metricas">
                            <article>
                                <h3>Facturas pendientes</h3>
                                <p class="resiliencia__cifra" id="resiliencia-pendientes">…</p>
                                <p class="resiliencia__sub" id="resiliencia-nota">…</p>
                            </article>
                            <article>
                                <h3>Redpanda</h3>
                                <p class="estado" id="resiliencia-redpanda">…</p>
                                <p class="resiliencia__sub">Estado del broker</p>
                            </article>
                            <article>
                                <h3>Consumidor</h3>
                                <p class="estado" id="resiliencia-consumidor">…</p>
                                <p class="resiliencia__sub" id="resiliencia-consumidor-detalle">Grupo de reproceso</p>
                            </article>
                        </div>
                    </section>

                    <section class="impacto" aria-labelledby="titulo-impacto">
                        <div class="impacto__cabeza">
                            <h2 id="titulo-impacto">Impacto de la latencia</h2>
                        </div>
                        <div class="impacto__metricas">
                            <article>
                                <h3>Latencia de Red</h3>
                                <p class="impacto__cifra" id="impacto-red">…</p>
                                <p class="impacto__sub">AWR ↔ PostgreSQL</p>
                                <p class="impacto__ayuda">Latencia base del enlace sin degradación artificial.</p>
                            </article>
                            <article class="impacto__metrica--principal">
                                <div class="impacto__titulo">
                                    <h3>Latencia Artificial</h3>
                                    <p class="condicion" id="impacto-condicion" hidden></p>
                                </div>
                                <p class="impacto__cifra" id="impacto-artificial">…</p>
                                <p class="impacto__sub">Toxiproxy</p>
                                <p class="impacto__ayuda" id="impacto-artificial-nota" hidden>Sin degradación artificial</p>
                            </article>
                            <article>
                                <h3>Respuesta PostgreSQL</h3>
                                <p class="impacto__cifra" id="impacto-respuesta">…</p>
                                <p class="impacto__sub">SELECT 1 observado</p>
                            </article>
                        </div>
                        <p class="impacto__nota">La latencia de red es la latencia base del enlace AWR ↔ PostgreSQL, sin degradación artificial.</p>
                        <p class="impacto__nota">La latencia artificial corresponde al retardo configurado mediante Toxiproxy.</p>
                        <p class="impacto__nota">La respuesta PostgreSQL representa el tiempo observado de una consulta SELECT 1 desde AWR.</p>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <dialog class="resiliencia-modal" id="modal-pendientes" aria-labelledby="titulo-modal-pendientes">
        <div class="resiliencia-modal__cabeza">
            <div>
                <h2 id="titulo-modal-pendientes">Facturas pendientes</h2>
                <p id="modal-pendientes-nota">Operaciones esperando reproceso</p>
            </div>
            <button type="button" class="resiliencia-modal__cerrar" id="cerrar-modal-pendientes" aria-label="Cerrar">
                Cerrar
            </button>
        </div>
        <div class="resiliencia-modal__cuerpo">
            <p class="resiliencia-modal__vacio" id="modal-pendientes-vacio" hidden>Sin operaciones pendientes</p>
            <p class="resiliencia-modal__aviso" id="modal-pendientes-aviso" hidden>No disponible</p>
            <p class="resiliencia-modal__carga" id="modal-pendientes-carga" hidden>Consultando operaciones pendientes…</p>
            <div class="resiliencia-modal__acciones" id="modal-pendientes-acciones" hidden>
                <label class="resiliencia-modal__todas">
                    <input type="checkbox" id="check-seleccionar-todas">
                    Seleccionar todas
                </label>
                <button type="button" class="resiliencia__boton" id="btn-procesar-seleccionadas">
                    Procesar seleccionadas
                </button>
            </div>
            <div class="resiliencia-modal__tabla-wrap" id="modal-pendientes-lista" hidden></div>
            <div class="resiliencia-modal__detalle" id="modal-pendientes-detalle" hidden>
                <h3>Productos</h3>
                <ul id="modal-pendientes-productos"></ul>
            </div>
        </div>
    </dialog>

    <script src="public/js/axios.min.js"></script>
    <script src="public/js/senalesAdministrador.js?v=2"></script>
    <script src="public/js/monitoreoAdministrador.js?v=5"></script>
</body>
</html>
