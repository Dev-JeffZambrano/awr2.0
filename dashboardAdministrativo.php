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

$rolEsperado = 'Administrativo';
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
    <title>Dashboard Administrativo | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardAdministrativo.css?v=2">
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
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>') ?>
                        <span>Dashboard Ejecutivo</span>
                    </button>
                    <a class="barra__item" href="clientesCartera.php">
                        <?= $icono('<path d="M16 19v-1a3 3 0 0 0-3-3H7a3 3 0 0 0-3 3v1"/><circle cx="10" cy="8" r="3"/><path d="M20 8v6M23 11h-6"/>') ?>
                        <span>Clientes &amp; Comportamiento Comercial</span>
                    </a>
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
                        <h1>Resumen comercial</h1>
                        <p>Ventas, facturación e inventario consolidados en tiempo real.</p>
                    </div>
                    <div class="filtros">
                        <label>Período
                            <select id="filtro-periodo">
                                <option value="7">Últimos 7 días</option>
                                <option value="30" selected>Últimos 30 días</option>
                                <option value="mes">Este mes</option>
                                <option value="mes-anterior">Mes anterior</option>
                            </select>
                        </label>
                        <label>Sucursal
                            <select id="filtro-sucursal">
                                <option value="">Todas las sucursales</option>
                            </select>
                        </label>
                        <p class="filtros__estado" id="estado-carga" hidden>Actualizando…</p>
                        <button class="filtros__principal" type="button" disabled title="Pendiente">Informe Ejecutivo · Pendiente</button>
                    </div>
                </header>

                <section class="kpis" aria-label="Indicadores">
                    <article class="tarjeta">
                        <div class="tarjeta__cabeza">
                            <h2>Ventas netas</h2>
                            <span class="delta" id="delta-ventas">…</span>
                        </div>
                        <p class="cifra" id="cifra-ventas">…</p>
                        <p class="ayuda" id="ayuda-ventas">…</p>
                    </article>
                    <article class="tarjeta">
                        <div class="tarjeta__cabeza">
                            <h2>Facturas emitidas</h2>
                            <span class="delta" id="delta-facturas">…</span>
                        </div>
                        <p class="cifra" id="cifra-facturas">…</p>
                        <p class="ayuda" id="ayuda-facturas">…</p>
                    </article>
                    <article class="tarjeta">
                        <div class="tarjeta__cabeza">
                            <h2>Ganancia bruta</h2>
                            <span class="delta" id="delta-ganancia">…</span>
                        </div>
                        <p class="cifra" id="cifra-ganancia">…</p>
                        <p class="ayuda" id="ayuda-ganancia">…</p>
                    </article>
                    <article class="tarjeta">
                        <div class="tarjeta__cabeza">
                            <h2>Productos vendidos</h2>
                            <span class="delta" id="delta-productos">…</span>
                        </div>
                        <p class="cifra" id="cifra-productos">…</p>
                        <p class="ayuda" id="ayuda-productos">…</p>
                    </article>
                </section>

                <div class="lienzo">
                    <section class="panel" aria-labelledby="titulo-tiempo">
                        <div class="panel__cabeza">
                            <div>
                                <h2 id="titulo-tiempo">Ventas en el tiempo</h2>
                                <p id="texto-tiempo">Facturación diaria del período.</p>
                            </div>
                        </div>
                        <ul class="resumen-serie">
                            <li><span>Promedio día</span><strong id="promedio-dia">…</strong></li>
                            <li><span>Día con mayor volumen</span><strong id="dia-mayor">…</strong></li>
                        </ul>
                        <div class="grafico">
                            <p class="grafico__marca" id="grafico-marca"></p>
                            <p id="grafico-vacio" hidden>Sin datos para el período seleccionado</p>
                            <svg id="grafico-ventas" viewBox="0 0 640 190" role="img" aria-label="Gráfico de ventas diarias">
                                <defs>
                                    <linearGradient id="relleno-ventas" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#1664f5" stop-opacity="0.28"/>
                                        <stop offset="100%" stop-color="#1664f5" stop-opacity="0"/>
                                    </linearGradient>
                                </defs>
                                <path id="grafico-area" d="" fill="url(#relleno-ventas)"/>
                                <path id="grafico-linea" d="" fill="none" stroke="#1664f5" stroke-width="2.6" stroke-linecap="round"/>
                                <circle id="grafico-punto" cx="0" cy="0" r="4.5" fill="#ffffff" stroke="#1664f5" stroke-width="2.4"/>
                            </svg>
                            <div class="grafico__eje" id="grafico-eje"></div>
                        </div>
                    </section>

                    <section class="panel" aria-labelledby="titulo-sucursal">
                        <div class="panel__cabeza">
                            <div>
                                <h2 id="titulo-sucursal">Ventas por sucursal</h2>
                                <p>Participación sobre el total.</p>
                            </div>
                            <span class="chip" id="chip-sucursales">…</span>
                        </div>
                        <ol class="ranking" id="lista-sucursales"></ol>
                    </section>
                </div>

                <div class="doble">
                    <section class="panel" aria-labelledby="titulo-categoria">
                        <div class="panel__cabeza">
                            <div>
                                <h2 id="titulo-categoria">Ventas por categoría</h2>
                                <p>Categorías con ventas en el período.</p>
                            </div>
                        </div>
                        <div class="tabla-envoltorio tabla-envoltorio--categorias">
                            <table class="tabla tabla--categorias">
                                <thead>
                                    <tr>
                                        <th>Categoría</th>
                                        <th>Ventas</th>
                                        <th>Participación</th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpo-categorias"></tbody>
                            </table>
                        </div>
                        <div class="paginacion">
                            <button type="button" id="categorias-anterior" disabled>Anterior</button>
                            <span id="categorias-pagina">Página 1 de 1</span>
                            <button type="button" id="categorias-siguiente" disabled>Siguiente</button>
                        </div>
                    </section>

                    <section class="panel" aria-labelledby="titulo-pago">
                        <div class="panel__cabeza">
                            <div>
                                <h2 id="titulo-pago">Métodos de pago</h2>
                                <p>Distribución del período.</p>
                            </div>
                            <span class="chip" id="chip-pagos">…</span>
                        </div>
                        <div class="mixto" id="mixto-pagos" aria-hidden="true"></div>
                        <ul class="pagos" id="lista-pagos"></ul>
                    </section>
                </div>

                <div class="base">
                    <section class="panel" aria-labelledby="titulo-productos">
                        <div class="panel__cabeza">
                            <div>
                                <h2 id="titulo-productos">Top productos</h2>
                                <p>Unidades y ventas del período.</p>
                            </div>
                            <button type="button" disabled>Ver catálogo</button>
                        </div>
                        <div class="tabla-envoltorio">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Margen</th>
                                        <th>Ventas</th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpo-productos"></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="panel" aria-labelledby="titulo-stock">
                        <div class="panel__cabeza">
                            <div>
                                <h2 id="titulo-stock">Stock bajo crítico</h2>
                                <p>Productos con stock actual en o bajo el mínimo.</p>
                            </div>
                            <button type="button" disabled>Generar orden</button>
                        </div>
                        <ul class="stock" id="lista-stock"></ul>
                    </section>
                </div>
            </main>
        </div>
    </div>
    <script src="public/js/axios.min.js"></script>
    <script src="public/js/dashboardAdministrativo.js"></script>
</body>
</html>
