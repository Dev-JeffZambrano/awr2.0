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

$rolEsperado = 'Operador';
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
    <?php echo awr_csrf_meta(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Operador | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardOperador.css?v=5">
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
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>') ?>
                        <span>Dashboard Turno</span>
                    </button>
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
                    <a class="barra__item" href="stockAgencia.php">
                        <?= $icono('<path d="M4 7h16v12H4z"/><path d="M4 11h16M9 7V5h6v2"/>') ?>
                        <span>Stock en Agencia</span>
                    </a>
                    <a class="barra__item" href="ficheroClientes.php">
                        <?= $icono('<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/><path d="M5 20a7 7 0 0 1 14 0"/>') ?>
                        <span>Fichero de Clientes</span>
                    </a>
                </nav>
            </details>
            <p class="barra__nota" id="operaciones-pendientes">Operaciones pendientes: …</p>
        </aside>

        <div class="zona">
            <header class="tope">
                <label class="buscar">
                    <span class="solo-lectura">Búsqueda visual</span>
                    <?= $icono('<circle cx="11" cy="11" r="6"/><path d="m20 20-3.5-3.5"/>') ?>
                    <input type="search" placeholder="Buscar por código, SKU o cliente" readonly>
                </label>
                <ul class="tope__senales" aria-label="Indicadores pendientes">
                    <li><span>Sincronización</span><strong id="senal-sincronizacion">Pendiente</strong></li>
                </ul>
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

            <section class="comandos" aria-label="Accesos rápidos del turno">
                <p>Accesos rápidos</p>
                <div>
                    <a href="puntoVenta.php" data-atajo="F2"><kbd>F2</kbd> Nueva factura</a>
                    <a href="catalogoPrecios.php" data-atajo="F3"><kbd>F3</kbd> Buscar producto</a>
                    <button type="button" data-atajo="F1"><kbd>F1</kbd> Ayuda</button>
                </div>
            </section>

            <main class="principal">
                <section class="jornada">
                    <div>
                        <p class="jornada__etiqueta">Jornada operativa</p>
                        <p class="jornada__estado" id="jornada-estado">Estado de jornada: Pendiente</p>
                        <h1>Terminal POS · Resumen Comercial</h1>
                        <p>Resumen operativo de ventas, facturación y stock de la sucursal asignada.</p>
                        <p class="jornada__meta" id="jornada-meta">Sucursal: … · Punto de emisión: …</p>
                    </div>
                    <div class="jornada__acciones">
                        <a href="catalogoPrecios.php">Consultar precios<span>Catálogo</span></a>
                        <a href="puntoVenta.php">Emitir factura<span>Punto de venta</span></a>
                    </div>
                </section>

                <section class="kpis" aria-label="Indicadores del turno">
                    <article class="tarjeta">
                        <h2>Ventas de hoy</h2>
                        <p class="cifra" id="ventas-hoy">…</p>
                        <p class="nota" id="ventas-nota">Hoy</p>
                    </article>
                    <article class="tarjeta">
                        <h2>Facturas emitidas</h2>
                        <p class="cifra" id="facturas-hoy">…</p>
                        <p class="nota" id="ticket-promedio">Ticket promedio: …</p>
                    </article>
                    <article class="tarjeta">
                        <h2>Unidades vendidas</h2>
                        <p class="cifra" id="unidades-hoy">…</p>
                        <p class="nota" id="unidades-nota">…</p>
                    </article>
                    <article class="tarjeta">
                        <h2>Cobros en efectivo</h2>
                        <p class="cifra" id="efectivo-hoy">…</p>
                        <p class="nota" id="efectivo-nota">…</p>
                    </article>
                    <article class="tarjeta tarjeta--alerta">
                        <h2>Stock crítico</h2>
                        <p class="cifra" id="stock-critico">…</p>
                        <p class="nota">productos</p>
                    </article>
                </section>

                <div class="lienzo">
                    <section class="panel" aria-labelledby="titulo-horas">
                        <div class="panel__cabeza">
                            <div>
                                <h2 id="titulo-horas">Ventas Horarias &amp; Flujo de Caja</h2>
                                <p id="rango-horas">Hoy</p>
                            </div>
                            <span class="chip" id="chip-horas">Hoy</span>
                        </div>
                        <div class="grafico">
                            <svg id="grafico-horas" viewBox="0 0 640 180" role="img" aria-label="Ventas por hora de hoy">
                                <path d="M24 150h592M24 112h592M24 74h592M24 36h592" fill="none" stroke="#d5e0ee" stroke-width="1"/>
                                <path d="M24 150V28" fill="none" stroke="#d5e0ee" stroke-width="1"/>
                                <polyline id="linea-horas" points="" fill="none" stroke="#1664f5" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle id="punto-pico" cx="0" cy="0" r="4" fill="#1664f5" hidden/>
                                <g id="etiquetas-horas"></g>
                            </svg>
                            <p id="grafico-vacio" hidden>Sin ventas registradas hoy</p>
                        </div>
                        <ul class="cobros">
                            <li><span>Pico de ventas</span><strong id="pico-hora">…</strong><em id="pico-monto"></em></li>
                            <li><span>Promedio horario</span><strong id="promedio-horario">…</strong><em id="promedio-nota"></em></li>
                            <li><span>Tickets emitidos</span><strong id="tickets-emitidos">…</strong><em id="tickets-nota"></em></li>
                        </ul>
                    </section>

                    <section class="panel" aria-labelledby="titulo-cobro">
                        <div class="panel__cabeza">
                            <div>
                                <h2 id="titulo-cobro">Métodos de Cobro Recibidos</h2>
                                <p id="total-cobrado">Total cobrado hoy: …</p>
                            </div>
                        </div>
                        <ul class="cobros" id="lista-cobros"></ul>
                    </section>
                </div>

                <div class="base">
                    <section class="panel" aria-labelledby="titulo-facturas">
                        <div class="panel__cabeza">
                            <div>
                                <h2 id="titulo-facturas">Últimas Facturas Emitidas</h2>
                                <p>Comprobantes de hoy.</p>
                            </div>
                        </div>
                        <div class="tabla-envoltorio">
                            <table class="tabla">
                                <thead>
                                    <tr>
                                        <th>Comprobante</th>
                                        <th>Hora</th>
                                        <th>Cliente</th>
                                        <th>Pago</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="facturas-recientes"></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="panel" aria-labelledby="titulo-stock">
                        <div class="panel__cabeza">
                            <div>
                                <h2 id="titulo-stock">Stock Crítico en Agencia</h2>
                                <p>Producto, SKU, stock actual y mínimo.</p>
                            </div>
                        </div>
                        <ul class="stock" id="lista-stock"></ul>
                    </section>
                </div>
            </main>
        </div>
    </div>
    <dialog class="ayuda" id="ayuda-turno" aria-labelledby="ayuda-titulo">
        <div class="ayuda__hoja">
            <h2 id="ayuda-titulo">Atajos disponibles</h2>
            <ul>
                <li><kbd>F2</kbd> Nueva factura</li>
                <li><kbd>F3</kbd> Buscar producto</li>
                <li><kbd>F1</kbd> Ayuda</li>
            </ul>
            <button class="ayuda__cerrar" type="button" data-cerrar-ayuda>Cerrar</button>
        </div>
    </dialog>
    <script src="public/js/axios.min.js"></script>
    <script src="public/js/seguridad.js"></script>
    <script src="public/js/dashboardOperador.js?v=5"></script>
</body>
</html>
