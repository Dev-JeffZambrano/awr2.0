<?php

require_once __DIR__ . '/app/config/seguridad.php';

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

$rolEsperado = 'Administrativo';
$sesionValida = false;

if (isset($_COOKIE[session_name()])) {
    awr_iniciar_sesion();
    $sesionValida = isset($_SESSION['id_usuario'], $_SESSION['username'], $_SESSION['id_rol'], $_SESSION['rol'])
        && $_SESSION['rol'] === $rolEsperado;
}

if (!$sesionValida) {
    header('Location: ' . $login, true, 302);
    exit;
}

awr_csrf_token();


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
    <?php echo awr_csrf_meta(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clientes &amp; Comportamiento Comercial | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardAdministrativo.css?v=2">
    <link rel="stylesheet" href="public/css/clientesCartera.css?v=1">
</head>
<body>
    <div class="comercial">
        <aside class="barra" aria-label="Navegación administrativa">
            <div class="barra__marca">
                <div class="barra__logo">
                    <img src="public/img/branding/awr-logo.png" alt="AWR">
                </div>
                <p>Aplicativo Web Resiliente</p>
            </div>
            <details class="barra__menu" open>
                <summary>Secciones</summary>
                <nav class="barra__nav">
                    <p class="barra__grupo">Operación comercial</p>
                    <a class="barra__item" href="dashboardAdministrativo.php">
                        <?= $icono('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>') ?>
                        <span>Dashboard Ejecutivo</span>
                    </a>
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<path d="M16 19v-1a3 3 0 0 0-3-3H7a3 3 0 0 0-3 3v1"/><circle cx="10" cy="8" r="3"/><path d="M20 8v6M23 11h-6"/>') ?>
                        <span>Clientes &amp; Comportamiento Comercial</span>
                    </button>
                    <a class="barra__item" href="catalogoPreciosAdministrativo.php">
                        <?= $icono('<path d="M4 7h16M4 12h16M4 17h10"/>') ?>
                        <span>Catálogo &amp; Precios</span>
                    </a>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M3 8 12 4l9 4-9 4-9-4Z"/><path d="M3 8v8l9 4 9-4V8"/>') ?>
                        <span>Inventario Multibodega</span>
                    </button>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/>') ?>
                        <span>Pagos &amp; Caja</span>
                    </button>

                    <p class="barra__grupo">Analítica</p>
                    <a class="barra__item" href="analiticaAdministrativo.php">
                        <?= $icono('<path d="M4 19V5M4 19h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>') ?>
                        <span>Analítica / Reportes BI</span>
                    </a>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M4 16 9 11l3 3 8-8"/><path d="M15 6h5v5"/>') ?>
                        <span>Rentabilidad por Sucursal</span>
                    </button>
                </nav>
            </details>
        </aside>

        <div class="zona">
            <header class="tope">
                <label class="buscar">
                    <span class="solo-lectura">Búsqueda visual</span>
                    <?= $icono('<circle cx="11" cy="11" r="6"/><path d="m20 20-3.5-3.5"/>') ?>
                    <input type="search" placeholder="Buscar productos, clientes, facturas" readonly>
                </label>
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
                        <p class="migas">Operación comercial</p>
                        <h1>Clientes &amp; Comportamiento Comercial</h1>
                        <p class="bajada">Análisis de compra, recurrencia y facturación de clientes.</p>
                        <a class="volver" href="dashboardAdministrativo.php">← Volver al Dashboard</a>
                    </div>
                    <div class="filtros">
                        <label>Período
                            <select id="filtro-periodo">
                                <option value="ULTIMOS_7_DIAS">Últimos 7 días</option>
                                <option value="ULTIMOS_30_DIAS" selected>Últimos 30 días</option>
                                <option value="MES_ACTUAL">Mes actual</option>
                                <option value="MES_ANTERIOR">Mes anterior</option>
                            </select>
                        </label>
                        <label>Sucursal
                            <select id="filtro-sucursal">
                                <option value="">Todas las sucursales</option>
                            </select>
                        </label>
                        <p class="filtros__estado" id="estado-carga" hidden>Actualizando…</p>
                    </div>
                </header>

                <section class="kpis" id="indicadores" aria-label="Indicadores de clientes" aria-busy="true">
                    <article class="tarjeta indicador indicador--espera">
                        <h2>Clientes con compras</h2>
                        <p class="cifra" id="kpi-compras">…</p>
                        <p class="ayuda">Identificados en el período</p>
                    </article>
                    <article class="tarjeta indicador indicador--espera">
                        <h2>Clientes recurrentes</h2>
                        <p class="cifra" id="kpi-recurrentes">…</p>
                        <p class="ayuda">Con dos o más facturas</p>
                    </article>
                    <article class="tarjeta indicador indicador--espera">
                        <h2>Venta promedio por cliente</h2>
                        <p class="cifra" id="kpi-promedio">…</p>
                        <p class="ayuda">Sobre clientes con compras</p>
                    </article>
                    <article class="tarjeta indicador indicador--espera">
                        <h2>Cliente con mayor facturación</h2>
                        <p class="cifra cifra--nombre" id="kpi-top-nombre">…</p>
                        <p class="ayuda" id="kpi-top-total"></p>
                    </article>
                </section>
                <p class="secundarios" id="informacion-secundaria">Cargando indicadores…</p>

                <section class="final" aria-labelledby="titulo-final">
                    <div>
                        <p>Categoría de facturación</p>
                        <h2 id="titulo-final">Consumidor Final</h2>
                        <p>No forma parte del ranking ni de los indicadores de clientes identificados.</p>
                    </div>
                    <dl>
                        <div>
                            <dt>Facturas</dt>
                            <dd id="final-facturas">…</dd>
                        </div>
                        <div>
                            <dt>Ventas</dt>
                            <dd id="final-ventas">…</dd>
                        </div>
                        <div>
                            <dt>Participación sobre ventas</dt>
                            <dd id="final-participacion">…</dd>
                        </div>
                    </dl>
                </section>

                <section class="panel" aria-labelledby="titulo-clientes">
                    <div class="panel__cabeza">
                        <div>
                            <h2 id="titulo-clientes">Clientes identificados</h2>
                            <p>El orden lo define la facturación del período.</p>
                        </div>
                    </div>
                    <label class="consulta">Búsqueda
                        <input id="busqueda" type="search" maxlength="200" placeholder="Identificación o nombre del cliente" autocomplete="off">
                    </label>
                    <div class="marco">
                        <table class="listado">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Identificación</th>
                                    <th>Facturas</th>
                                    <th>Total comprado</th>
                                    <th>Ticket promedio</th>
                                    <th>Última compra</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo-clientes">
                                <tr>
                                    <td class="listado__aviso" colspan="8">Cargando clientes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="paginacion">
                        <p id="listado-resumen">Mostrando 0 de 0 clientes</p>
                        <div>
                            <button id="pagina-anterior" type="button" disabled>Anterior</button>
                            <span id="pagina-indicador">Página 0 de 0</span>
                            <button id="pagina-siguiente" type="button" disabled>Siguiente</button>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <dialog class="detalle" id="detalle-cliente" aria-labelledby="detalle-titulo">
        <div class="detalle__hoja">
            <header class="detalle__tope">
                <div>
                    <p>Comportamiento comercial</p>
                    <h2 id="detalle-titulo">Detalle del cliente</h2>
                </div>
                <button class="detalle__cerrar" type="button" data-cerrar-detalle>Cerrar</button>
            </header>
            <p class="detalle__carga" id="detalle-carga" role="status">Cargando detalle...</p>
            <p class="detalle__error" id="detalle-error" role="alert" hidden></p>
            <div id="detalle-contenido" hidden>
                <section aria-labelledby="detalle-datos-titulo">
                    <h3 id="detalle-datos-titulo">Datos del cliente</h3>
                    <dl class="detalle__datos" id="detalle-datos"></dl>
                </section>
                <section aria-labelledby="detalle-resumen-titulo">
                    <h3 id="detalle-resumen-titulo">Resumen del período</h3>
                    <dl class="detalle__resumen">
                        <div>
                            <dt>Facturas</dt>
                            <dd id="detalle-facturas"></dd>
                        </div>
                        <div>
                            <dt>Total comprado</dt>
                            <dd id="detalle-total"></dd>
                        </div>
                        <div>
                            <dt>Ticket promedio</dt>
                            <dd id="detalle-ticket"></dd>
                        </div>
                        <div>
                            <dt>Última compra</dt>
                            <dd id="detalle-ultima"></dd>
                        </div>
                    </dl>
                </section>
                <section aria-labelledby="detalle-facturas-titulo">
                    <h3 id="detalle-facturas-titulo">Últimas facturas</h3>
                    <p class="detalle__vacio" id="detalle-sin-compras" hidden>Este cliente no registra compras en el período seleccionado.</p>
                    <div class="marco" id="detalle-marco">
                        <table class="listado listado--detalle">
                            <thead>
                                <tr>
                                    <th>Comprobante</th>
                                    <th>Fecha</th>
                                    <th>Sucursal</th>
                                    <th>Método de pago</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="detalle-facturas-cuerpo"></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </dialog>
    <script src="public/js/axios.min.js"></script>
    <script src="public/js/seguridad.js"></script>
    <script src="public/js/clientesCartera.js?v=1"></script>
</body>
</html>
