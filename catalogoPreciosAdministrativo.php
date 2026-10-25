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
    <title>Catálogo &amp; Precios | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/dashboardAdministrativo.css?v=2">
    <link rel="stylesheet" href="public/css/catalogoPreciosAdministrativo.css?v=4">
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
                    <button class="barra__item barra__item--actual" type="button" aria-current="page">
                        <?= $icono('<path d="M4 7h16M4 12h16M4 17h10"/>') ?>
                        <span>Catálogo &amp; Precios</span>
                    </button>
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
                        <h1>Catálogo &amp; Precios</h1>
                        <p class="bajada">Consulta y mantenimiento de productos, precios e IVA del catálogo comercial.</p>
                        <a class="volver" href="dashboardAdministrativo.php">← Volver al Dashboard</a>
                    </div>
                </header>

                <section class="kpis" id="indicadores" aria-label="Indicadores del catálogo" aria-busy="true">
                    <article class="tarjeta indicador indicador--espera">
                        <h2>Productos activos</h2>
                        <p class="cifra" id="kpi-productos">…</p>
                    </article>
                    <article class="tarjeta indicador indicador--espera">
                        <h2>Categorías activas</h2>
                        <p class="cifra" id="kpi-categorias">…</p>
                    </article>
                    <article class="tarjeta indicador indicador--espera">
                        <h2>Proveedores activos</h2>
                        <p class="cifra" id="kpi-proveedores">…</p>
                    </article>
                    <article class="tarjeta indicador indicador--espera">
                        <h2>Cobertura parcial</h2>
                        <p class="cifra cifra--media" id="kpi-cobertura">…</p>
                        <p class="ayuda">Disponibles en menos de todas las sucursales</p>
                    </article>
                </section>

                <div class="filtros filtros--catalogo">
                    <label class="filtros__buscar">Búsqueda
                        <input id="filtro-busqueda" type="search" maxlength="80" placeholder="Producto, código o código de barras" autocomplete="off">
                    </label>
                    <label>Categoría
                        <select id="filtro-categoria">
                            <option value="">Todas las categorías</option>
                        </select>
                    </label>
                    <label>Proveedor
                        <select id="filtro-proveedor">
                            <option value="">Todos los proveedores</option>
                        </select>
                    </label>
                    <label>Impuesto
                        <select id="filtro-impuesto">
                            <option value="">Todos los impuestos</option>
                        </select>
                    </label>
                    <label>Estado
                        <select id="filtro-estado">
                            <option value="ACTIVO" selected>Activos</option>
                            <option value="INACTIVO">Inactivos</option>
                            <option value="TODOS">Todos</option>
                        </select>
                    </label>
                    <label>Sucursal
                        <select id="filtro-sucursal">
                            <option value="">Todas las sucursales</option>
                        </select>
                    </label>
                </div>

                <p class="exito" id="exito-catalogo" role="status" hidden></p>

                <section class="panel" aria-labelledby="titulo-catalogo">
                    <div class="panel__cabeza">
                        <div>
                            <h2 id="titulo-catalogo">Productos</h2>
                            <p>Maestro de productos, precios e IVA.</p>
                        </div>
                        <button id="nuevo-producto" type="button">+ Nuevo producto</button>
                    </div>
                    <div class="marco">
                        <table class="listado">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Código</th>
                                    <th>Categoría</th>
                                    <th>Proveedor</th>
                                    <th>Precio</th>
                                    <th>Impuesto</th>
                                    <th>Disponibilidad</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo-catalogo">
                                <tr>
                                    <td class="listado__aviso" colspan="9">Cargando catálogo...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="paginacion">
                        <p id="listado-resumen">Mostrando 0 de 0 productos</p>
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

    <dialog class="detalle" id="detalle-producto" aria-labelledby="detalle-titulo">
        <div class="detalle__hoja">
            <header class="detalle__tope">
                <div>
                    <p>Consulta comercial</p>
                    <h2 id="detalle-titulo">Detalle del producto</h2>
                </div>
                <button class="detalle__cerrar" type="button" data-cerrar-detalle>Cerrar</button>
            </header>
            <p class="detalle__carga" id="detalle-carga" role="status">Cargando detalle...</p>
            <p class="detalle__error" id="detalle-error" role="alert" hidden></p>
            <div id="detalle-contenido" hidden>
                <section aria-labelledby="detalle-datos-titulo">
                    <h3 id="detalle-datos-titulo">Datos generales</h3>
                    <dl class="detalle__datos" id="detalle-datos"></dl>
                </section>
                <section aria-labelledby="detalle-comercial-titulo">
                    <h3 id="detalle-comercial-titulo">Datos comerciales</h3>
                    <dl class="detalle__comercial">
                        <div>
                            <dt>Precio</dt>
                            <dd id="detalle-precio"></dd>
                        </div>
                        <div>
                            <dt>Costo</dt>
                            <dd id="detalle-costo"></dd>
                        </div>
                        <div>
                            <dt>Margen bruto</dt>
                            <dd id="detalle-margen"></dd>
                        </div>
                        <div>
                            <dt>Impuesto</dt>
                            <dd id="detalle-impuesto"></dd>
                        </div>
                    </dl>
                </section>
                <section aria-labelledby="detalle-presencia-titulo">
                    <h3 id="detalle-presencia-titulo">Disponibilidad por sucursal</h3>
                    <ul class="presencia" id="detalle-sucursales"></ul>
                    <div class="alta-sucursal" id="alta-sucursal" hidden>
                        <h4 id="alta-sucursal-titulo">Agregar a sucursales</h4>
                        <p class="alta-sucursal__aviso" id="alta-sucursal-aviso" role="alert" hidden></p>
                        <label class="alta-sucursal__campo" id="alta-sucursal-campo-select">Sucursal
                            <select id="alta-sucursal-select">
                                <option value="">Seleccione</option>
                            </select>
                        </label>
                        <label class="alta-sucursal__todas">
                            <input type="checkbox" id="alta-todas-sucursales">
                            <span>Registrar en todas las sucursales activas no registradas</span>
                        </label>
                        <div class="alta-sucursal__stocks">
                            <label>Stock actual
                                <input type="number" id="alta-stock-actual" min="0" step="1" value="0" inputmode="numeric">
                            </label>
                            <label>Stock mínimo
                                <input type="number" id="alta-stock-minimo" min="0" step="1" value="0" inputmode="numeric">
                            </label>
                            <label>Stock máximo
                                <input type="number" id="alta-stock-maximo" min="0" step="1" value="0" inputmode="numeric">
                            </label>
                        </div>
                        <button class="ver" id="alta-sucursal-agregar" type="button">Agregar</button>
                    </div>
                </section>
                <div class="detalle__acciones" id="detalle-acciones" hidden>
                    <button class="ver" id="detalle-editar" type="button">Editar</button>
                    <button class="ver" id="detalle-estado" type="button">Desactivar</button>
                </div>
            </div>
        </div>
    </dialog>

    <dialog class="detalle" id="formulario-producto" aria-labelledby="formulario-titulo">
        <form class="detalle__hoja formulario" id="form-producto" novalidate>
            <header class="detalle__tope">
                <div>
                    <p>Mantenimiento</p>
                    <h2 id="formulario-titulo">Nuevo producto</h2>
                </div>
                <button class="detalle__cerrar" id="cerrar-formulario" type="button">Cerrar</button>
            </header>
            <p class="detalle__error" id="aviso-formulario" role="alert" hidden></p>
            <p class="detalle__carga" id="carga-formulario" role="status" hidden>Cargando formulario...</p>
            <div class="formulario__cuerpo" id="cuerpo-formulario">
                <label>Código
                    <input id="campo-codigo" type="text" maxlength="50" autocomplete="off" spellcheck="false" required>
                </label>
                <label>Código de barras
                    <input id="campo-barras" type="text" maxlength="50" autocomplete="off" spellcheck="false">
                </label>
                <label class="formulario__ancho">Nombre
                    <input id="campo-nombre" type="text" maxlength="200" autocomplete="off" required>
                </label>
                <label class="formulario__ancho">Descripción
                    <textarea id="campo-descripcion" maxlength="500" rows="3"></textarea>
                </label>
                <label>Categoría
                    <select id="campo-categoria" required>
                        <option value="">Seleccione</option>
                    </select>
                </label>
                <label>Proveedor
                    <select id="campo-proveedor" required>
                        <option value="">Seleccione</option>
                    </select>
                </label>
                <label>Unidad de medida
                    <input id="campo-unidad" type="text" maxlength="50" list="lista-unidades" autocomplete="off" required>
                    <datalist id="lista-unidades"></datalist>
                </label>
                <label class="formulario__iva">IVA
                    <span class="formulario__toggle">
                        <input id="campo-aplica-iva" type="checkbox" checked>
                        <span id="etiqueta-aplica-iva">Con IVA</span>
                    </span>
                </label>
                <label>Precio
                    <input id="campo-precio" type="text" inputmode="decimal" maxlength="16" autocomplete="off" required>
                </label>
                <label>Costo
                    <input id="campo-costo" type="text" inputmode="decimal" maxlength="16" autocomplete="off" required>
                </label>
                <section class="formulario__sucursales" id="bloque-asignar-sucursales" aria-labelledby="titulo-asignar-sucursales" hidden>
                    <h3 id="titulo-asignar-sucursales">Asignar a sucursales</h3>
                    <p class="formulario__sucursales-ayuda">Opcional. Registra el inventario inicial en las sucursales elegidas al crear el producto.</p>
                    <label class="formulario__asignar">
                        <input type="checkbox" id="form-asignar-sucursales">
                        <span>Asignar a sucursales al crear</span>
                    </label>
                    <div class="formulario__sucursales-campos" id="form-sucursales-campos" hidden>
                        <label class="formulario__asignar">
                            <input type="checkbox" id="form-todas-sucursales">
                            <span>Todas las sucursales</span>
                        </label>
                        <div class="formulario__lista-sucursales" id="form-lista-sucursales" role="group" aria-label="Sucursales activas"></div>
                        <div class="formulario__stocks">
                            <label>Stock actual
                                <input type="number" id="form-stock-actual" min="0" step="1" value="0" inputmode="numeric">
                            </label>
                            <label>Stock mínimo
                                <input type="number" id="form-stock-minimo" min="0" step="1" value="0" inputmode="numeric">
                            </label>
                            <label>Stock máximo
                                <input type="number" id="form-stock-maximo" min="0" step="1" value="0" inputmode="numeric">
                            </label>
                        </div>
                    </div>
                </section>
                <section class="formulario__sucursales" id="bloque-sucursales-asignadas" aria-labelledby="titulo-sucursales-asignadas" hidden>
                    <h3 id="titulo-sucursales-asignadas">Sucursales asignadas</h3>
                    <p class="formulario__sucursales-ayuda">Sucursales donde el producto tiene inventario activo. Al quitar, dejará de aparecer en el punto de venta de esa sucursal.</p>
                    <ul class="formulario__asignadas" id="lista-sucursales-asignadas"></ul>
                    <p class="formulario__sucursales-vacio" id="aviso-sin-sucursales-asignadas" hidden>No está asignado a ninguna sucursal.</p>
                    <div class="formulario__agregar-sucursal" id="bloque-agregar-sucursal">
                        <h4 id="titulo-agregar-sucursal">Agregar a otra sucursal</h4>
                        <p class="formulario__sucursales-ayuda">Opcional. Registra o reactiva el producto en una sucursal pendiente.</p>
                        <p class="formulario__agregar-aviso" id="aviso-agregar-sucursal" role="alert" hidden></p>
                        <label class="formulario__agregar-campo" id="campo-agregar-sucursal-select">Sucursal
                            <select id="form-agregar-sucursal-select">
                                <option value="">Seleccione</option>
                            </select>
                        </label>
                        <div class="formulario__stocks">
                            <label>Stock actual
                                <input type="number" id="form-agregar-stock-actual" min="0" step="1" value="0" inputmode="numeric">
                            </label>
                            <label>Stock mínimo
                                <input type="number" id="form-agregar-stock-minimo" min="0" step="1" value="0" inputmode="numeric">
                            </label>
                            <label>Stock máximo
                                <input type="number" id="form-agregar-stock-maximo" min="0" step="1" value="0" inputmode="numeric">
                            </label>
                        </div>
                        <button class="ver" id="form-agregar-sucursal" type="button">Agregar</button>
                    </div>
                </section>
            </div>
            <div class="detalle__acciones">
                <button class="ver ver--primario" id="guardar-producto" type="submit">Guardar</button>
            </div>
        </form>
    </dialog>

    <dialog class="detalle" id="confirma-producto" aria-labelledby="confirma-titulo">
        <div class="detalle__hoja">
            <header class="detalle__tope">
                <div>
                    <p>Confirmación</p>
                    <h2 id="confirma-titulo">Confirmar</h2>
                </div>
                <button class="detalle__cerrar" id="cerrar-confirma" type="button">Cerrar</button>
            </header>
            <p id="texto-confirma"></p>
            <div class="detalle__acciones" id="acciones-confirma">
                <button class="ver" id="cancelar-confirma" type="button">Cancelar</button>
                <button class="ver ver--primario" id="aceptar-confirma" type="button">Confirmar</button>
            </div>
        </div>
    </dialog>

    <script src="public/js/axios.min.js"></script>
    <script src="public/js/catalogoPreciosAdministrativo.js?v=4"></script>
</body>
</html>
