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
$idUsuarioSesion = (int) ($_SESSION['id_usuario'] ?? 0);

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
    <title>Usuarios y Accesos | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardAdministrador.css?v=3">
    <link rel="stylesheet" href="public/css/usuariosAccesosAdministrador.css?v=1">
</head>
<body data-usuario="<?= $idUsuarioSesion ?>">
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
<p class="barra__grupo">Administración</p>
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z"/><path d="M5 20a7 7 0 0 1 14 0"/>') ?>
                        <span>Usuarios y Accesos</span>
                    </button>
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
</nav>
            </details>
            <p class="barra__nota">Usuarios internos</p>
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
                    <div class="encabezado__cuerpo encabezado--usuarios">
                        <div>
                            <h1>Usuarios y Accesos</h1>
                            <p>Gestión de usuarios internos del sistema.</p>
                        </div>
                        <button class="actualizar" id="nuevo-usuario" type="button">+ Nuevo usuario</button>
                    </div>
                </header>

                <div class="pestanas" role="tablist" aria-label="Secciones de usuarios y accesos">
                    <button class="pestanas__opcion pestanas__opcion--activa" id="tab-usuarios" type="button" role="tab" aria-selected="true" aria-controls="panel-usuarios">Usuarios</button>
                    <button class="pestanas__opcion" id="tab-roles" type="button" role="tab" aria-selected="false" aria-controls="panel-roles">Roles y Permisos</button>
                </div>

                <section class="bloque" id="panel-usuarios" role="tabpanel" aria-labelledby="tab-usuarios">
                    <div class="kpis" id="kpis" aria-label="Resumen de usuarios">
                        <article><h2>Usuarios activos</h2><p id="kpi-activos">—</p></article>
                        <article><h2>Usuarios inactivos</h2><p id="kpi-inactivos">—</p></article>
                        <article><h2>Administradores</h2><p id="kpi-administradores">—</p></article>
                        <article><h2>Administrativos</h2><p id="kpi-administrativos">—</p></article>
                        <article><h2>Operadores</h2><p id="kpi-operadores">—</p></article>
                    </div>
                    <form class="filtros" id="filtros">
                        <label class="selector">
                            <span>Buscar</span>
                            <input id="buscar-usuario" type="search" placeholder="Nombres, apellidos o username" autocomplete="off">
                        </label>
                        <label class="selector">
                            <span>Rol</span>
                            <select id="filtro-rol"><option value="">Todos</option></select>
                        </label>
                        <label class="selector">
                            <span>Estado</span>
                            <select id="filtro-estado">
                                <option value="">Todos</option>
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </label>
                        <label class="selector">
                            <span>Sucursal</span>
                            <select id="filtro-sucursal"><option value="">Todas</option></select>
                        </label>
                    </form>
                    <p class="nota" id="nota-sucursal" hidden>La sucursal se filtra sobre la página cargada.</p>
                    <p class="carga" id="carga-usuarios" role="status">Cargando usuarios...</p>
                    <p class="aviso" id="aviso-usuarios" role="alert" hidden>No se pudo actualizar el listado de usuarios.</p>
                    <p class="exito" id="exito-usuarios" role="status" hidden></p>
                    <p class="vacio" id="vacio-usuarios" hidden>No hay usuarios para los filtros seleccionados.</p>
                    <div class="listado__scroll">
                        <table class="listado">
                            <thead>
                                <tr>
                                    <th scope="col">Usuario</th>
                                    <th scope="col">Nombre completo</th>
                                    <th scope="col">Rol</th>
                                    <th scope="col">Sucursal</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo-usuarios"></tbody>
                        </table>
                    </div>
                    <div class="paginacion" id="paginacion" hidden>
                        <button class="secundario" id="pagina-anterior" type="button">Anterior</button>
                        <p id="pagina-texto">Página 1 de 1</p>
                        <button class="secundario" id="pagina-siguiente" type="button">Siguiente</button>
                    </div>
                </section>

                <section class="bloque" id="panel-roles" role="tabpanel" aria-labelledby="tab-roles" hidden>
                    <p class="nota">Los permisos se muestran en modo consulta. La autorización actual del sistema continúa basada en el rol.</p>
                    <p class="carga" id="carga-roles" role="status" hidden>Cargando roles y permisos...</p>
                    <p class="aviso" id="aviso-roles" role="alert" hidden>No se pudo consultar los roles y permisos.</p>
                    <div class="roles" id="lista-roles"></div>
                </section>
            </main>
        </div>
    </div>

    <dialog class="ventana" id="ventana-usuario" aria-labelledby="titulo-ventana">
        <form class="ventana__cuerpo" id="formulario-usuario">
            <div class="ventana__cabeza">
                <h2 id="titulo-ventana">Nuevo usuario</h2>
                <button class="secundario" id="cerrar-usuario" type="button">Cerrar</button>
            </div>
            <p class="aviso" id="aviso-formulario" role="alert" hidden></p>
            <p class="carga" id="carga-formulario" role="status" hidden>Cargando usuario...</p>
            <label class="selector">
                <span>Nombres</span>
                <input id="campo-nombres" name="nombres" type="text" maxlength="150" required autocomplete="off">
            </label>
            <label class="selector">
                <span>Apellidos</span>
                <input id="campo-apellidos" name="apellidos" type="text" maxlength="150" required autocomplete="off">
            </label>
            <label class="selector">
                <span>Username</span>
                <input id="campo-username" name="username" type="text" maxlength="30" autocomplete="off" spellcheck="false">
            </label>
            <p class="nota" id="username-sugerido" hidden></p>
            <label class="selector" id="bloque-password">
                <span>Contraseña inicial</span>
                <span class="clave">
                    <input id="campo-password" name="password" type="password" maxlength="255" autocomplete="new-password">
                    <button class="secundario" id="ver-password" type="button">Mostrar</button>
                </span>
            </label>
            <label class="selector">
                <span>Rol</span>
                <select id="campo-rol" required></select>
            </label>
            <label class="selector" id="bloque-sucursal" hidden>
                <span>Sucursal</span>
                <select id="campo-sucursal"></select>
            </label>
            <div class="ventana__acciones">
                <button class="actualizar" id="guardar-usuario" type="submit">Guardar</button>
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
            <div class="ventana__acciones">
                <button class="secundario" id="cancelar-confirma" type="button">Cancelar</button>
                <button class="actualizar" id="aceptar-confirma" type="button">Confirmar</button>
            </div>
        </div>
    </dialog>

    <dialog class="ventana" id="ventana-clave" aria-labelledby="titulo-clave">
        <div class="ventana__cuerpo">
            <div class="ventana__cabeza">
                <h2 id="titulo-clave">Contraseña temporal</h2>
                <button class="secundario" id="cerrar-clave" type="button">Cerrar</button>
            </div>
            <p class="clave__valor" id="clave-temporal"></p>
            <p>Esta contraseña se mostrará una sola vez.</p>
            <div class="ventana__acciones">
                <button class="actualizar" id="copiar-clave" type="button">Copiar</button>
            </div>
            <p class="nota" id="aviso-copia" role="status" hidden></p>
        </div>
    </dialog>

    <script src="public/js/axios.min.js"></script>
    <script src="public/js/seguridad.js"></script>
    <script src="public/js/senalesAdministrador.js?v=2"></script>
    <script src="public/js/usuariosAccesosAdministrador.js?v=4"></script>
</body>
</html>
