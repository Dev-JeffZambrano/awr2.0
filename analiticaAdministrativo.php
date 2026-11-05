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

$rutaInformeRelativa = 'documentacion/AWR_Analitica.pbix';
$rutaInformeAbsoluta = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaInformeRelativa);
$informeDisponible = is_file($rutaInformeAbsoluta);
$dirPowerBi = __DIR__ . DIRECTORY_SEPARATOR . 'documentacion' . DIRECTORY_SEPARATOR . 'powerbi';
$capturaResumen = is_file($dirPowerBi . DIRECTORY_SEPARATOR . 'resumen-ejecutivo.png');
$capturaKpis = is_file($dirPowerBi . DIRECTORY_SEPARATOR . 'resumen-kpis.png');
$capturaGraficos = is_file($dirPowerBi . DIRECTORY_SEPARATOR . 'resumen-graficos.png');

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
    <title>Analítica / Reportes BI | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardAdministrativo.css?v=2">
    <link rel="stylesheet" href="public/css/analiticaAdministrativo.css?v=4">
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
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<path d="M4 19V5M4 19h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>') ?>
                        <span>Analítica / Reportes BI</span>
                    </button>
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
                        <p class="migas">Analítica</p>
                        <h1>Analítica / Reportes BI</h1>
                        <p class="bajada">
                            Capa BI de AWR: ClickHouse alimenta el modelo y Power BI Desktop
                            presenta el informe ejecutivo.
                        </p>
                        <a class="volver" href="dashboardAdministrativo.php">← Volver al Dashboard</a>
                    </div>
                    <p class="bi-leyenda <?= $informeDisponible ? 'bi-leyenda--ok' : 'bi-leyenda--pendiente' ?>" id="leyenda-informe" role="status">
                        <?php if ($informeDisponible): ?>
                            Informe Power BI: <strong>Listo</strong>
                        <?php else: ?>
                            Informe Power BI: <strong>Pendiente</strong>
                            <span>— coloca <code>documentacion/AWR_Analitica.pbix</code></span>
                        <?php endif; ?>
                    </p>
                </header>

                <section class="bi-estado" aria-label="Estado del modelo analítico">
                    <article class="tarjeta bi-chip bi-chip--ok">
                        <p class="bi-chip__etiqueta">Fuente</p>
                        <h2>ClickHouse</h2>
                        <p class="bi-chip__dato" id="chip-clickhouse">…</p>
                        <p class="ayuda">Vista <code>vw_ventas_analiticas</code></p>
                    </article>
                    <article class="tarjeta bi-chip <?= $informeDisponible ? 'bi-chip--ok' : 'bi-chip--aviso' ?>">
                        <p class="bi-chip__etiqueta">Informe .pbix</p>
                        <h2 id="chip-pbix-titulo"><?= $informeDisponible ? 'Listo' : 'Pendiente' ?></h2>
                        <p class="bi-chip__dato" id="chip-pbix-dato">AWR_Analitica.pbix</p>
                        <p class="ayuda" id="chip-pbix-ayuda">
                            <?= $informeDisponible ? 'Disponible para descargar.' : 'Archivo no encontrado.' ?>
                        </p>
                    </article>
                    <article class="tarjeta bi-chip <?= $capturaResumen ? 'bi-chip--ok' : 'bi-chip--aviso' ?>">
                        <p class="bi-chip__etiqueta">Vista previa</p>
                        <h2 id="chip-previa-titulo"><?= $capturaResumen ? 'Listo' : 'Pendiente' ?></h2>
                        <p class="bi-chip__dato">Modal + capturas</p>
                        <p class="ayuda" id="chip-previa-ayuda">
                            <?= $capturaResumen ? 'Captura de Resumen ejecutivo disponible.' : 'Falta captura PNG.' ?>
                        </p>
                    </article>
                    <article class="tarjeta bi-chip bi-chip--ok">
                        <p class="bi-chip__etiqueta">Export CSV</p>
                        <h2 id="chip-csv-titulo">…</h2>
                        <p class="bi-chip__dato">Resumen por sucursal/mes</p>
                        <p class="ayuda">Desde el mismo modelo analítico</p>
                    </article>
                </section>

                <section class="panel bi-accion" aria-labelledby="titulo-abrir-bi">
                    <div class="panel__cabeza">
                        <div>
                            <h2 id="titulo-abrir-bi">Informe ejecutivo Power BI</h2>
                            <p>
                                Descarga el <code>.pbix</code>, previsualiza capturas del dashboard
                                o exporta un CSV agregado desde ClickHouse.
                            </p>
                        </div>
                    </div>

                    <div class="bi-accion__cuerpo">
                        <div class="bi-accion__botones">
                            <?php if ($informeDisponible): ?>
                                <a class="bi-boton" id="btn-descargar-pbix" href="<?= htmlspecialchars($rutaInformeRelativa, ENT_QUOTES, 'UTF-8') ?>" download>
                                    Descargar informe .pbix
                                </a>
                            <?php else: ?>
                                <button class="bi-boton bi-boton--secundario" id="btn-descargar-pbix" type="button" disabled>
                                    Descargar informe · Pendiente
                                </button>
                            <?php endif; ?>
                            <button class="bi-boton" id="btn-vista-previa" type="button" <?= $capturaResumen ? '' : 'disabled' ?>>
                                Vista previa
                            </button>
                            <button class="bi-boton bi-boton--secundario" id="btn-exportar-csv" type="button" disabled>
                                Exportar CSV
                            </button>
                            <button class="bi-boton bi-boton--secundario" id="copiar-ruta" type="button"
                                data-ruta="<?= htmlspecialchars($rutaInformeRelativa, ENT_QUOTES, 'UTF-8') ?>">
                                Copiar ruta del archivo
                            </button>
                        </div>
                        <p class="bi-aviso" id="aviso-copia" role="status" hidden></p>
                    </div>
                </section>

                <section class="panel bi-galeria" aria-labelledby="titulo-galeria">
                    <div class="panel__cabeza">
                        <div>
                            <h2 id="titulo-galeria">Galería del informe</h2>
                            <p>Vistas del mismo dashboard Resumen ejecutivo en Power BI.</p>
                        </div>
                    </div>
                    <div class="bi-galeria__grid" id="galeria-informe">
                        <article class="bi-galeria__item">
                            <p class="bi-galeria__titulo">Vista completa</p>
                            <?php if ($capturaResumen): ?>
                                <button class="bi-galeria__foto" type="button" data-pagina="resumen" aria-label="Ampliar vista completa">
                                    <img src="documentacion/powerbi/resumen-ejecutivo.png" alt="Vista completa del dashboard">
                                </button>
                            <?php else: ?>
                                <div class="bi-galeria__vacio">
                                    <span class="bi-badge bi-badge--pendiente">Pendiente</span>
                                    <p>Falta captura del dashboard</p>
                                </div>
                            <?php endif; ?>
                        </article>
                        <article class="bi-galeria__item">
                            <p class="bi-galeria__titulo">Indicadores KPI</p>
                            <?php if ($capturaKpis): ?>
                                <button class="bi-galeria__foto" type="button" data-pagina="kpis" aria-label="Ampliar indicadores KPI">
                                    <img src="documentacion/powerbi/resumen-kpis.png" alt="Indicadores KPI del dashboard">
                                </button>
                            <?php else: ?>
                                <div class="bi-galeria__vacio">
                                    <span class="bi-badge bi-badge--pendiente">Pendiente</span>
                                    <p>Falta recorte de KPIs</p>
                                </div>
                            <?php endif; ?>
                        </article>
                        <article class="bi-galeria__item">
                            <p class="bi-galeria__titulo">Gráficos del informe</p>
                            <?php if ($capturaGraficos): ?>
                                <button class="bi-galeria__foto" type="button" data-pagina="graficos" aria-label="Ampliar gráficos">
                                    <img src="documentacion/powerbi/resumen-graficos.png" alt="Gráficos del dashboard">
                                </button>
                            <?php else: ?>
                                <div class="bi-galeria__vacio">
                                    <span class="bi-badge bi-badge--pendiente">Pendiente</span>
                                    <p>Falta recorte de gráficos</p>
                                </div>
                            <?php endif; ?>
                        </article>
                    </div>
                </section>

                <section class="panel bi-flujo" aria-labelledby="titulo-flujo">
                    <div class="panel__cabeza">
                        <div>
                            <h2 id="titulo-flujo">Flujo de datos</h2>
                            <p>Cómo llega la venta al informe de Power BI Desktop.</p>
                        </div>
                    </div>
                    <ol class="bi-flujo__lista">
                        <li><span>PostgreSQL</span><small>Facturas confirmadas</small></li>
                        <li><span>Redpanda</span><small>Eventos de venta</small></li>
                        <li><span>ClickHouse</span><small>vw_ventas_analiticas</small></li>
                        <li><span>Power BI Desktop</span><small>Visualización</small></li>
                    </ol>
                </section>
            </main>
        </div>
    </div>

    <div class="bi-modal" id="modal-previa" hidden>
        <div class="bi-modal__fondo" data-cerrar-modal></div>
        <div class="bi-modal__caja" role="dialog" aria-modal="true" aria-labelledby="modal-previa-titulo">
            <header class="bi-modal__cabeza">
                <div>
                    <h2 id="modal-previa-titulo">Vista previa del informe</h2>
                    <p>KPIs desde ClickHouse + captura del dashboard Power BI.</p>
                </div>
                <button class="bi-modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar">×</button>
            </header>
            <div class="bi-modal__cuerpo">
                <section class="bi-modal__kpis" aria-label="Indicadores ClickHouse">
                    <article>
                        <p>Ventas totales</p>
                        <strong id="kpi-ventas">…</strong>
                    </article>
                    <article>
                        <p>Facturas</p>
                        <strong id="kpi-facturas">…</strong>
                    </article>
                    <article>
                        <p>Ticket promedio</p>
                        <strong id="kpi-ticket">…</strong>
                    </article>
                    <article>
                        <p>Margen bruto</p>
                        <strong id="kpi-margen">…</strong>
                    </article>
                    <article>
                        <p>% Margen</p>
                        <strong id="kpi-porcentaje">…</strong>
                    </article>
                    <article>
                        <p>Líneas</p>
                        <strong id="kpi-lineas">…</strong>
                    </article>
                </section>
                <figure class="bi-modal__figura">
                    <img id="modal-previa-img" src="documentacion/powerbi/resumen-ejecutivo.png" alt="Vista previa Resumen ejecutivo">
                    <figcaption id="modal-previa-caption">Resumen ejecutivo · Power BI Desktop</figcaption>
                </figure>
                <p class="bi-modal__nota">
                    Esta vista previa no embebe Power BI. El informe interactivo se abre en Desktop
                    con <code>AWR_Analitica.pbix</code>.
                </p>
            </div>
            <footer class="bi-modal__pie">
                <?php if ($informeDisponible): ?>
                    <a class="bi-boton" href="<?= htmlspecialchars($rutaInformeRelativa, ENT_QUOTES, 'UTF-8') ?>" download>
                        Descargar .pbix
                    </a>
                <?php endif; ?>
                <button class="bi-boton bi-boton--secundario" type="button" data-cerrar-modal>Cerrar</button>
            </footer>
        </div>
    </div>

    <script src="public/js/axios.min.js"></script>
    <script src="public/js/seguridad.js"></script>
    <script src="public/js/analiticaAdministrativo.js?v=4"></script>
</body>
</html>
