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

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php echo awr_csrf_meta(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Emitir factura | AWR</title>
    <link rel="stylesheet" href="public/css/variables.css">
    <link rel="stylesheet" href="public/css/puntoVenta.css?v=7">
</head>
<body>
    <main class="doc" role="dialog" aria-modal="true" aria-labelledby="titulo-emision">
        <header class="doc__cabecera">
            <span class="doc__insignia" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22">
                    <path fill="currentColor" d="M6.5 3.5h7.2L19 8.8V20a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 20V5a1.5 1.5 0 0 1 1.5-1.5zm6.6 1.6V9h3.9l-3.9-3.9zM8 12.2h8v1.3H8v-1.3zm0 2.8h8v1.3H8V15zm0 2.8h5.2v1.3H8v-1.3z"/>
                </svg>
            </span>
            <div class="doc__titulo">
                <h1 id="titulo-emision">Emitir factura</h1>
                <p class="doc__meta">
                    <span>Sucursal <strong id="sucursal-nombre">…</strong></span>
                    <span>Punto <strong id="contexto-punto">…</strong></span>
                    <span>Operador <strong><?= $nombreVisible ?></strong></span>
                    <strong class="estado" id="estado-caja">Cargando</strong>
                </p>
            </div>
            <a class="doc__cerrar" data-volver href="dashboardOperador.php" aria-label="Cerrar y volver al dashboard">×</a>
        </header>

        <div class="doc__cuerpo">
            <p class="aviso" id="aviso-venta" role="status" hidden></p>

            <div class="puntos" id="campo-punto" hidden>
                <p>Punto de emisión</p>
                <div class="opciones" id="puntos-emision"></div>
            </div>

            <section class="bloque" aria-labelledby="titulo-cliente">
                <div class="bloque__tope">
                    <h2 id="titulo-cliente">Datos del cliente</h2>
                    <button class="chip" id="usar-consumidor" type="button" disabled>Consumidor Final</button>
                </div>
                <div class="cliente__datos">
                    <div class="lectura">
                        <span>Identificación</span>
                        <strong id="cliente-detalle">…</strong>
                    </div>
                    <div class="lectura">
                        <span>Razón social</span>
                        <strong id="cliente-actual">…</strong>
                    </div>
                </div>
                <form id="form-cliente">
                    <label class="solo-lectura" for="buscar-cliente">Buscar cliente</label>
                    <input id="buscar-cliente" type="search" autocomplete="off" placeholder="Buscar por identificación o razón social" disabled>
                </form>
                <ul class="resultados" id="resultados-clientes"></ul>
            </section>

            <section class="bloque" aria-labelledby="titulo-detalle">
                <div class="bloque__tope">
                    <h2 id="titulo-detalle">Detalle de artículos</h2>
                    <p id="cuenta-carrito">0 ítems</p>
                </div>
                <form id="form-buscar">
                    <label class="solo-lectura" for="buscar-producto">Buscar producto</label>
                    <input id="buscar-producto" type="search" autocomplete="off" placeholder="Buscar producto por nombre, SKU o código de barras" disabled>
                </form>
                <p class="ayuda" id="ayuda-busqueda">Escriba para buscar en la sucursal.</p>
                <ul class="resultados" id="resultados-productos" aria-live="polite"></ul>
                <div class="detalle">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Cant.</th>
                                <th>P. unitario</th>
                                <th>IVA</th>
                                <th>Subtotal</th>
                                <th><span class="solo-lectura">Quitar</span></th>
                            </tr>
                        </thead>
                        <tbody id="lineas-carrito"></tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="doc__bajos">
            <section class="panel" aria-labelledby="titulo-pago">
                <h2 id="titulo-pago">Forma de pago</h2>
                <div class="opciones opciones--pago" id="metodos-pago" role="radiogroup" aria-label="Método de pago"></div>
                <p class="pago-validacion" id="pago-transferencia-resumen" hidden></p>
            </section>
            <section class="panel panel--resumen" aria-labelledby="titulo-resumen">
                <h2 id="titulo-resumen">Resumen</h2>
                <dl class="totales">
                    <div>
                        <dt>Subtotal</dt>
                        <dd id="total-subtotal">—</dd>
                    </div>
                    <div>
                        <dt>IVA</dt>
                        <dd id="total-impuesto">—</dd>
                    </div>
                    <div class="totales__descuento">
                        <dt>
                            <label for="descuento-opcion">Descuento</label>
                        </dt>
                        <dd>
                            <div class="descuento-fila">
                                <select id="descuento-opcion" name="descuento_opcion" title="Descuento sobre el subtotal" aria-describedby="ayuda-descuento">
                                    <option value="0" selected>0%</option>
                                    <option value="10">10%</option>
                                    <option value="20">20%</option>
                                    <option value="otro">Otro</option>
                                </select>
                                <div class="descuento-otro" id="descuento-otro-caja" hidden>
                                    <label class="solo-lectura" for="descuento-otro">Porcentaje de descuento</label>
                                    <input
                                        id="descuento-otro"
                                        name="descuento_otro"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        inputmode="decimal"
                                        placeholder="%"
                                        title="Porcentaje sobre el subtotal"
                                        aria-describedby="ayuda-descuento"
                                        disabled
                                    >
                                    <span class="descuento-otro__sufijo" aria-hidden="true">%</span>
                                </div>
                                <span class="descuento-monto" id="total-descuento">—</span>
                            </div>
                            <p class="solo-lectura" id="ayuda-descuento">El descuento se aplica sobre el subtotal.</p>
                            <p class="descuento-aviso" id="descuento-aviso" hidden>Ingrese un porcentaje entre 0 y 100.</p>
                        </dd>
                    </div>
                </dl>
                <p class="pagar"><span>Total</span><strong id="total-pagar">—</strong></p>
            </section>
        </div>

        <footer class="doc__acciones">
            <button class="emitir" id="confirmar-venta" type="button" disabled>Emitir factura</button>
        </footer>
    </main>

    <dialog class="exito" id="resultado-venta">
        <p class="exito__marca" aria-hidden="true">✓</p>
        <h2 id="resultado-titulo">Venta emitida correctamente</h2>
        <p class="exito__nota" id="resultado-nota" hidden></p>
        <dl>
            <div><dt id="resultado-etiqueta">Comprobante</dt><dd id="resultado-numero"></dd></div>
            <div><dt>Total</dt><dd id="resultado-total"></dd></div>
            <div><dt>Estado</dt><dd id="resultado-estado"></dd></div>
        </dl>
        <div class="exito__acciones">
            <button type="button" id="nueva-venta">Nueva venta</button>
            <a href="dashboardOperador.php">Volver al dashboard</a>
        </div>
    </dialog>

    <dialog class="exito cuentas-transferencia" id="cuentas-transferencia" aria-labelledby="titulo-cuentas-transferencia">
        <h2 id="titulo-cuentas-transferencia">Cuentas para transferencia</h2>
        <p class="exito__nota">Elija la cuenta a la que realizó o realizará la transferencia.</p>
        <ul class="cuentas-transferencia__lista" id="lista-cuentas-transferencia"></ul>
        <div class="exito__acciones">
            <button type="button" id="cerrar-cuentas-transferencia">Cerrar</button>
        </div>
    </dialog>

    <dialog class="exito validar-transferencia" id="validar-transferencia" aria-labelledby="titulo-validar-transferencia">
        <h2 id="titulo-validar-transferencia">Validar transferencia</h2>
        <p class="exito__nota">Confirme la cuenta y registre el número de comprobante.</p>
        <dl class="validar-transferencia__resumen" id="resumen-cuenta-transferencia"></dl>
        <form id="form-validar-transferencia">
            <label for="comprobante-transferencia">Número de comprobante</label>
            <input
                id="comprobante-transferencia"
                name="comprobante_transferencia"
                type="text"
                maxlength="50"
                required
                autocomplete="off"
                placeholder="Ej. COMP-12345"
            >
            <p class="validar-transferencia__aviso" id="aviso-comprobante-transferencia" hidden>Ingrese el número de comprobante.</p>
            <div class="exito__acciones validar-transferencia__acciones">
                <button type="button" id="cancelar-validar-transferencia">Cancelar</button>
                <button type="submit" id="confirmar-validar-transferencia">Confirmar pago</button>
            </div>
        </form>
    </dialog>

    <script src="public/js/axios.min.js"></script>
    <script src="public/js/seguridad.js"></script>
    <script src="public/js/puntoVenta.js?v=7"></script>
</body>
</html>
