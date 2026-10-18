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
$sucursalSesion = trim((string) ($_SESSION['sucursal'] ?? ''));
$ciudadSesion = trim((string) ($_SESSION['ciudad'] ?? ''));
$sucursalVisible = htmlspecialchars(
    $sucursalSesion === '' ? 'Sucursal no disponible' : $sucursalSesion,
    ENT_QUOTES,
    'UTF-8'
);
$ciudadVisible = $ciudadSesion === ''
    ? ''
    : htmlspecialchars($ciudadSesion, ENT_QUOTES, 'UTF-8');

$icono = static function (string $contenido): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $contenido . '</svg>';
};

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock en Agencia | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardOperador.css?v=3">
    <link rel="stylesheet" href="public/css/stockAgencia.css">
</head>
<body>
    <div class="turno">
        <aside class="barra" aria-label="Navegación del operador">
            <div class="barra__marca">
                <div class="barra__logo">
                    <img src="public/img/branding/awr-logo.png" alt="AWR">
                </div>
                <p>Aplicativo Web Resiliente</p>
                <span class="placa">POS</span>
            </div>
            <div class="barra__sucursal">
                <p>Sucursal asignada</p>
                <strong><?= $sucursalVisible ?></strong>
                <?php if ($ciudadVisible !== ''): ?>
                    <span><?= $ciudadVisible ?></span>
                <?php endif; ?>
            </div>
            <details class="barra__menu" open>
                <summary>Secciones</summary>
                <nav class="barra__nav">
                    <p class="barra__grupo">Operación</p>
                    <a class="barra__item" href="dashboardOperador.php">
                        <?= $icono('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>') ?>
                        <span>Dashboard Turno</span>
                    </a>
                    <a class="barra__item" href="puntoVenta.php">
                        <?= $icono('<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10h4M7 14h10"/>') ?>
                        <span>Punto de Venta POS</span>
                    </a>
                    <a class="barra__item" href="facturasEmitidas.php">
                        <?= $icono('<path d="M7 3h7l5 5v13H7V3Z"/><path d="M14 3v5h5"/>') ?>
                        <span>Facturas Emitidas</span>
                    </a>
                    <a class="barra__item" href="catalogoPrecios.php">
                        <?= $icono('<path d="M4 7h16M4 12h16M4 17h10"/>') ?>
                        <span>Catálogo &amp; Precios</span>
                    </a>

                    <p class="barra__grupo">Agencia &amp; stock</p>
                    <a class="barra__item barra__item--actual" href="stockAgencia.php" aria-current="page">
                        <?= $icono('<path d="M4 7h16v12H4z"/><path d="M4 11h16M9 7V5h6v2"/>') ?>
                        <span>Stock en Agencia</span>
                    </a>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M3 8 12 4l9 4-9 4-9-4Z"/><path d="M3 8v8l9 4 9-4V8"/>') ?>
                        <span>Pedidos a Bodega Central</span>
                    </button>
                    <a class="barra__item" href="ficheroClientes.php">
                        <?= $icono('<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/><path d="M5 20a7 7 0 0 1 14 0"/>') ?>
                        <span>Fichero de Clientes</span>
                    </a>

                    <p class="barra__grupo">Arquitectura AWR</p>
                    <button class="barra__item" type="button" disabled>
                        <?= $icono('<path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3Z"/>') ?>
                        <span>Modo Resiliente</span>
                        <em id="modo-resiliente">…</em>
                    </button>
                </nav>
            </details>
            <p class="barra__nota">Supervisión del inventario de la sucursal</p>
        </aside>

        <div class="zona">
            <header class="tope">
                <div class="tope__cuenta">
                    <div class="tope__identidad">
                        <span class="tope__avatar" aria-hidden="true"><?= $inicial ?></span>
                        <div>
                            <p class="tope__usuario"><?= $nombreVisible ?></p>
                            <p class="tope__rol"><?= $rol ?></p>
                        </div>
                    </div>
                    <button class="cerrar" type="button" data-cerrar-sesion>Cerrar sesión</button>
                </div>
            </header>
            <p class="tope__aviso" id="mensaje-sesion" role="alert" hidden></p>

            <main class="principal consulta">
                <header class="consulta__encabezado">
                    <div>
                        <a class="consulta__volver" href="dashboardOperador.php">← Volver al Dashboard</a>
                        <h1>Stock en Agencia</h1>
                        <p>Supervisión del inventario disponible en la sucursal asignada.</p>
                    </div>
                    <p class="consulta__rol"><?= $rol ?></p>
                </header>

                <section class="kpis" aria-label="Resumen del inventario">
                    <article class="kpi">
                        <h2>Productos inventariados</h2>
                        <strong id="kpi-inventariados">—</strong>
                    </article>
                    <article class="kpi kpi--alerta">
                        <h2>Productos críticos</h2>
                        <strong id="kpi-criticos">—</strong>
                    </article>
                    <article class="kpi">
                        <h2>Unidades disponibles</h2>
                        <strong id="kpi-unidades">—</strong>
                    </article>
                    <article class="kpi">
                        <h2>Sin stock</h2>
                        <strong id="kpi-sin-stock">—</strong>
                    </article>
                </section>

                <form class="filtros" id="filtros">
                    <label>
                        <span>Búsqueda</span>
                        <input id="filtro-busqueda" type="search" maxlength="80" autocomplete="off" placeholder="Producto, código o código de barras">
                    </label>
                    <label>
                        <span>Categoría</span>
                        <select id="filtro-categoria">
                            <option value="">Todas las categorías</option>
                        </select>
                    </label>
                    <label>
                        <span>Estado</span>
                        <select id="filtro-estado">
                            <option value="TODAS">Todos</option>
                            <option value="CRITICO">Crítico</option>
                            <option value="NORMAL">Normal</option>
                        </select>
                    </label>
                    <button class="filtros__limpiar" id="limpiar-filtros" type="button">Limpiar filtros</button>
                </form>

                <p class="consulta__estado" id="estado-consulta" role="status" hidden>Cargando inventario...</p>

                <section class="listado" id="marco-stock" aria-labelledby="titulo-listado" aria-busy="false">
                    <h2 class="solo-lectura" id="titulo-listado">Inventario</h2>
                    <div class="listado__marco">
                        <table class="inventario">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Código</th>
                                    <th>Categoría</th>
                                    <th>Stock actual</th>
                                    <th>Stock mínimo</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo-stock"></tbody>
                        </table>
                    </div>
                </section>

                <footer class="paginacion">
                    <p id="resumen-pagina">Mostrando 0 de 0 productos</p>
                    <div>
                        <button type="button" id="pagina-anterior" disabled>Anterior</button>
                        <span id="pagina-indicador">Página 1 de 1</span>
                        <button type="button" id="pagina-siguiente" disabled>Siguiente</button>
                    </div>
                </footer>
            </main>
        </div>
    </div>

    <dialog class="historial" id="historial" aria-labelledby="historial-titulo">
        <article class="historial__hoja">
            <header class="historial__tope">
                <div>
                    <p>Movimientos de inventario</p>
                    <h2 id="historial-titulo">Producto</h2>
                    <p id="historial-codigo"></p>
                </div>
                <button type="button" id="cerrar-historial">Cerrar</button>
            </header>
            <p class="consulta__estado" id="historial-aviso" role="status" hidden></p>
            <div id="historial-cuerpo"></div>
        </article>
    </dialog>

    <script src="public/js/axios.min.js"></script>
    <script src="public/js/senalesOperador.js?v=1"></script>
    <script src="public/js/stockAgencia.js?v=3"></script>
</body>
</html>
