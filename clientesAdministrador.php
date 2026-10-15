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
    <title>Clientes | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardAdministrador.css?v=3">
    <link rel="stylesheet" href="public/css/clientesAdministrador.css?v=1">
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
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<path d="M8 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3Z"/><path d="M16 11a3 3 0 1 0-3-3"/><path d="M2 19a6 6 0 0 1 10.5-3.8"/><path d="M22 19a6 6 0 0 0-4-5.6"/>') ?>
                        <span>Clientes</span>
                    </button>
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
            <p class="barra__nota">Maestro de clientes</p>
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
                    <div class="encabezado__cuerpo encabezado--clientes">
                        <div>
                            <h1>Clientes</h1>
                            <p>Gestión del maestro de clientes y compradores.</p>
                        </div>
                        <button class="actualizar" id="nuevo-cliente" type="button">+ Nuevo cliente</button>
                    </div>
                </header>

                <section class="bloque" aria-labelledby="titulo-clientes">
                    <h2 class="solo-lectura" id="titulo-clientes">Listado de clientes</h2>
                    <div class="kpis" aria-label="Resumen de clientes">
                        <article><h2>Clientes activos</h2><p id="kpi-activos">—</p></article>
                        <article><h2>Clientes inactivos</h2><p id="kpi-inactivos">—</p></article>
                        <article><h2>Cédulas</h2><p id="kpi-cedulas">—</p></article>
                        <article><h2>RUC</h2><p id="kpi-ruc">—</p></article>
                        <article><h2>Pasaportes</h2><p id="kpi-pasaportes">—</p></article>
                    </div>
                    <form class="filtros" id="filtros">
                        <label class="selector">
                            <span>Buscar</span>
                            <input id="buscar-cliente" type="search" placeholder="Identificación o razón social" maxlength="200" autocomplete="off">
                        </label>
                        <label class="selector">
                            <span>Tipo</span>
                            <select id="filtro-tipo">
                                <option value="">Todos</option>
                                <option value="CEDULA">CEDULA</option>
                                <option value="RUC">RUC</option>
                                <option value="PASAPORTE">PASAPORTE</option>
                            </select>
                        </label>
                        <label class="selector">
                            <span>Estado</span>
                            <select id="filtro-estado">
                                <option value="">Todos</option>
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </label>
                    </form>
                    <p class="carga" id="carga-clientes" role="status" aria-busy="true">Cargando clientes...</p>
                    <div class="aviso aviso--fila" id="aviso-clientes" role="alert" hidden>
                        <span id="texto-aviso">No se pudo actualizar el listado de clientes.</span>
                        <button class="secundario" id="reintentar-clientes" type="button">Reintentar</button>
                    </div>
                    <p class="exito" id="exito-clientes" role="status" hidden></p>
                    <p class="vacio" id="vacio-clientes" hidden>No hay clientes para los filtros seleccionados.</p>
                    <div class="listado__scroll">
                        <table class="listado">
                            <thead>
                                <tr>
                                    <th scope="col">Identificación</th>
                                    <th scope="col">Razón social</th>
                                    <th scope="col">Tipo</th>
                                    <th scope="col">Teléfono</th>
                                    <th scope="col">Correo</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo-clientes"></tbody>
                        </table>
                    </div>
                    <div class="paginacion" id="paginacion" hidden>
                        <p class="nota" id="resumen-listado"></p>
                        <div class="paginacion__controles">
                            <button class="secundario" id="pagina-anterior" type="button">Anterior</button>
                            <p id="pagina-texto">Página 1 de 1</p>
                            <button class="secundario" id="pagina-siguiente" type="button">Siguiente</button>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <dialog class="ventana" id="ventana-cliente" aria-labelledby="titulo-ventana">
        <form class="ventana__cuerpo" id="formulario-cliente" novalidate>
            <div class="ventana__cabeza">
                <h2 id="titulo-ventana">Nuevo cliente</h2>
                <button class="secundario" id="cerrar-cliente" type="button">Cerrar</button>
            </div>
            <p class="aviso" id="aviso-formulario" role="alert" hidden></p>
            <p class="carga" id="carga-formulario" role="status" hidden>Cargando cliente...</p>
            <p class="nota nota--protegido" id="nota-protegido" hidden>Registro protegido</p>
            <label class="selector" id="bloque-tipo">
                <span>Tipo de identificación</span>
                <select id="campo-tipo">
                    <option value="">Seleccione</option>
                    <option value="CEDULA">CEDULA</option>
                    <option value="RUC">RUC</option>
                    <option value="PASAPORTE">PASAPORTE</option>
                </select>
            </label>
            <p class="selector" id="bloque-tipo-lectura" hidden>
                <span>Tipo de identificación</span>
                <strong id="tipo-lectura"></strong>
            </p>
            <label class="selector">
                <span>Identificación</span>
                <input id="campo-identificacion" type="text" maxlength="20" autocomplete="off" spellcheck="false">
            </label>
            <label class="selector">
                <span>Razón social</span>
                <input id="campo-razon" type="text" maxlength="200" autocomplete="off">
            </label>
            <label class="selector">
                <span>Dirección</span>
                <input id="campo-direccion" type="text" maxlength="255" autocomplete="off">
            </label>
            <label class="selector">
                <span>Teléfono</span>
                <input id="campo-telefono" type="text" maxlength="30" autocomplete="off">
            </label>
            <label class="selector">
                <span>Correo</span>
                <input id="campo-correo" type="text" maxlength="150" autocomplete="off">
            </label>
            <dl class="ficha" id="bloque-fechas" hidden>
                <div>
                    <dt>Estado</dt>
                    <dd id="detalle-estado">—</dd>
                </div>
                <div>
                    <dt>Creación</dt>
                    <dd id="detalle-creacion">—</dd>
                </div>
                <div>
                    <dt>Actualización</dt>
                    <dd id="detalle-actualizacion">—</dd>
                </div>
            </dl>
            <div class="ventana__acciones">
                <button class="actualizar" id="guardar-cliente" type="submit">Guardar</button>
            </div>
        </form>
    </dialog>

    <dialog class="ventana" id="ventana-confirma" aria-labelledby="titulo-confirma">
        <div class="ventana__cuerpo">
            <div class="ventana__cabeza">
                <h2 id="titulo-confirma">Confirmar</h2>
                <button class="secundario" id="cerrar-confirma" type="button">Cerrar</button>
            </div>
            <p id="texto-confirma"></p>
            <div class="ventana__acciones" id="acciones-confirma">
                <button class="secundario" id="cancelar-confirma" type="button">Cancelar</button>
                <button class="actualizar" id="aceptar-confirma" type="button">Confirmar</button>
            </div>
        </div>
    </dialog>

    <script src="public/js/axios.min.js"></script>
    <script src="public/js/senalesAdministrador.js?v=2"></script>
    <script src="public/js/clientesAdministrador.js?v=1"></script>
</body>
</html>
