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

$rolEsperado = 'Administrador';
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
    <title>Configuración | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardAdministrador.css?v=4">
    <link rel="stylesheet" href="public/css/configuracionAdministrador.css?v=1">
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
                    <a class="barra__item" href="monitoreoAdministrador.php">
                        <?= $icono('<path d="M4 13h4l2-6 4 12 2-6h4"/>') ?>
                        <span>Monitoreo en Tiempo Real</span>
                    </a>
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
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/>') ?>
                        <span>Configuración</span>
                    </button>
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
            <p class="barra__nota">Parámetros del sistema</p>
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
                    <div class="encabezado__cuerpo encabezado--configuracion">
                        <div>
                            <h1>Configuración</h1>
                            <p>Parámetros generales, de facturación e integraciones de AWR.</p>
                        </div>
                        <button class="secundario" id="actualizar-estado" type="button">Actualizar estado</button>
                    </div>
                </header>

                <p class="carga" id="carga-configuracion" role="status" aria-busy="true">Cargando configuración...</p>
                <div class="aviso aviso--fila" id="aviso-configuracion" role="alert" hidden>
                    <span id="texto-aviso">No se pudo consultar la configuración.</span>
                    <button class="secundario" id="reintentar-configuracion" type="button">Reintentar</button>
                </div>

                <section class="bloque" id="panel-empresa" hidden>
                    <div class="bloque__cabeza">
                        <div>
                            <h2>Datos generales</h2>
                            <p>Información principal de la empresa utilizada por AWR.</p>
                        </div>
                        <span class="estado" id="empresa-estado">—</span>
                    </div>
                    <p class="nota">Los cambios aplican a nuevas facturas. Los comprobantes ya emitidos conservan sus datos históricos.</p>
                    <form id="formulario-empresa" novalidate>
                        <div class="campos">
                            <label class="selector">
                                <span>Nombre</span>
                                <input id="campo-nombre" type="text" maxlength="150" autocomplete="organization">
                            </label>
                            <label class="selector">
                                <span>Razón social</span>
                                <input id="campo-razon" type="text" maxlength="200" autocomplete="organization">
                            </label>
                            <label class="selector">
                                <span>Nombre comercial</span>
                                <input id="campo-comercial" type="text" maxlength="200" autocomplete="organization">
                            </label>
                            <label class="selector">
                                <span>Identificación</span>
                                <input id="campo-identificacion" type="text" maxlength="20" autocomplete="off">
                            </label>
                            <label class="selector ancho">
                                <span>Dirección</span>
                                <input id="campo-direccion" type="text" maxlength="255" autocomplete="street-address">
                            </label>
                            <label class="selector">
                                <span>Teléfono</span>
                                <input id="campo-telefono" type="text" maxlength="30" autocomplete="tel">
                            </label>
                            <label class="selector">
                                <span>Correo</span>
                                <input id="campo-correo" type="text" maxlength="150" autocomplete="email">
                            </label>
                        </div>
                        <p class="aviso" id="aviso-empresa" role="alert" hidden></p>
                        <p class="exito" id="exito-empresa" role="status" hidden></p>
                        <div class="acciones">
                            <button class="actualizar" id="guardar-empresa" type="submit">Guardar datos generales</button>
                        </div>
                    </form>
                </section>

                <section class="bloque" id="panel-facturacion" hidden>
                    <div class="bloque__cabeza">
                        <div>
                            <h2>Facturación</h2>
                            <p>Ambiente y leyenda que se registran en las nuevas facturas.</p>
                        </div>
                    </div>
                    <form id="formulario-facturacion" novalidate>
                        <div class="campos">
                            <label class="selector">
                                <span>Ambiente</span>
                                <select id="campo-ambiente">
                                    <option value="PRUEBAS">PRUEBAS</option>
                                    <option value="PRODUCCION">PRODUCCION</option>
                                </select>
                            </label>
                            <label class="selector ancho">
                                <span>Leyenda</span>
                                <textarea id="campo-leyenda" maxlength="255" rows="3"></textarea>
                            </label>
                        </div>
                        <p class="nota" id="aviso-produccion" hidden>Este valor solo modifica el ambiente registrado en nuevas facturas. No habilita integración con SRI.</p>
                        <dl class="ficha">
                            <div>
                                <dt>Moneda</dt>
                                <dd id="facturacion-moneda">—</dd>
                            </div>
                            <div>
                                <dt>Tipo de emisión</dt>
                                <dd id="facturacion-emision">—</dd>
                            </div>
                            <div>
                                <dt>Estado</dt>
                                <dd><span class="estado" id="facturacion-estado">—</span></dd>
                            </div>
                        </dl>
                        <p class="aviso" id="aviso-facturacion" role="alert" hidden></p>
                        <p class="exito" id="exito-facturacion" role="status" hidden></p>
                        <div class="acciones">
                            <button class="actualizar" id="guardar-facturacion" type="submit">Guardar configuración de facturación</button>
                        </div>
                    </form>
                </section>

                <section class="bloque" id="panel-referencias" hidden>
                    <div class="bloque__cabeza">
                        <div>
                            <h2>Referencias operativas</h2>
                            <p>Catálogos de facturación en consulta.</p>
                        </div>
                    </div>
                    <div class="referencias">
                        <section>
                            <h3>Tipo de comprobante</h3>
                            <ul class="lista-ref" id="lista-tipos"></ul>
                        </section>
                        <section>
                            <h3>Impuestos</h3>
                            <ul class="lista-ref" id="lista-impuestos"></ul>
                        </section>
                        <section>
                            <h3>Puntos de emisión</h3>
                            <ul class="lista-ref" id="lista-puntos"></ul>
                        </section>
                        <section>
                            <h3>Secuenciales</h3>
                            <ul class="lista-ref" id="lista-secuenciales"></ul>
                        </section>
                    </div>
                </section>

                <section class="bloque" id="panel-integraciones" hidden>
                    <div class="bloque__cabeza">
                        <div>
                            <h2>Integraciones</h2>
                            <p>Estado de los componentes conectados a AWR.</p>
                        </div>
                    </div>
                    <div class="integraciones">
                        <article class="integracion integracion--destacada">
                            <div class="bloque__cabeza">
                                <h3>Toxiproxy</h3>
                                <span class="estado" id="toxiproxy-estado">—</span>
                            </div>
                            <p class="nota" id="toxiproxy-proxy" hidden></p>
                            <label class="selector">
                                <span>Latencia artificial</span>
                                <span class="medida">
                                    <input id="campo-latencia" type="number" min="0" max="3000" step="1" inputmode="numeric">
                                    <span>ms</span>
                                </span>
                            </label>
                            <p class="nota">Rango permitido: 0 a 3000 ms</p>
                            <div class="presets">
                                <button class="secundario" type="button" data-preset="0">0 ms</button>
                                <button class="secundario" type="button" data-preset="200">200 ms</button>
                                <button class="secundario" type="button" data-preset="500">500 ms</button>
                                <button class="secundario" type="button" data-preset="1000">1000 ms</button>
                            </div>
                            <p class="nota" id="degradacion"></p>
                            <p class="aviso" id="aviso-latencia" role="alert" hidden></p>
                            <p class="exito" id="exito-latencia" role="status" hidden></p>
                            <div class="acciones">
                                <button class="actualizar" id="aplicar-latencia" type="button">Aplicar latencia</button>
                            </div>
                        </article>
                        <article class="integracion integracion--neutral">
                            <div class="bloque__cabeza">
                                <h3>Redpanda</h3>
                                <span class="estado" id="redpanda-estado">No integrado</span>
                            </div>
                            <p id="redpanda-texto">Integración pendiente.</p>
                        </article>
                        <article class="integracion integracion--neutral">
                            <div class="bloque__cabeza">
                                <h3>ClickHouse</h3>
                                <span class="estado" id="clickhouse-estado">No integrado</span>
                            </div>
                            <p id="clickhouse-texto">Motor analítico pendiente de integración.</p>
                        </article>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="public/js/axios.min.js"></script>
    <script src="public/js/seguridad.js"></script>
    <script src="public/js/senalesAdministrador.js?v=2"></script>
    <script src="public/js/configuracionAdministrador.js?v=1"></script>
</body>
</html>
