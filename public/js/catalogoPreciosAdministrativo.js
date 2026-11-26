(function () {
    var controlador = 'app/Controllers/CatalogoPreciosAdministrativoController.php';
    var boton = document.getElementById('cerrar-sesion');
    var mensaje = document.getElementById('mensaje-sesion');
    var exitoCatalogo = document.getElementById('exito-catalogo');
    var busqueda = document.getElementById('filtro-busqueda');
    var categoria = document.getElementById('filtro-categoria');
    var proveedor = document.getElementById('filtro-proveedor');
    var impuesto = document.getElementById('filtro-impuesto');
    var estado = document.getElementById('filtro-estado');
    var sucursal = document.getElementById('filtro-sucursal');
    var indicadores = document.getElementById('indicadores');
    var cuerpo = document.getElementById('cuerpo-catalogo');
    var resumenLista = document.getElementById('listado-resumen');
    var indicadorPagina = document.getElementById('pagina-indicador');
    var anterior = document.getElementById('pagina-anterior');
    var siguiente = document.getElementById('pagina-siguiente');
    var detalle = document.getElementById('detalle-producto');
    var detalleTitulo = document.getElementById('detalle-titulo');
    var detalleCarga = document.getElementById('detalle-carga');
    var detalleError = document.getElementById('detalle-error');
    var detalleContenido = document.getElementById('detalle-contenido');
    var detalleDatos = document.getElementById('detalle-datos');
    var detalleSucursales = document.getElementById('detalle-sucursales');
    var altaSucursal = document.getElementById('alta-sucursal');
    var altaSucursalAviso = document.getElementById('alta-sucursal-aviso');
    var altaSucursalCampoSelect = document.getElementById('alta-sucursal-campo-select');
    var altaSucursalSelect = document.getElementById('alta-sucursal-select');
    var altaTodasSucursales = document.getElementById('alta-todas-sucursales');
    var altaStockActual = document.getElementById('alta-stock-actual');
    var altaStockMinimo = document.getElementById('alta-stock-minimo');
    var altaStockMaximo = document.getElementById('alta-stock-maximo');
    var altaSucursalAgregar = document.getElementById('alta-sucursal-agregar');
    var detalleAcciones = document.getElementById('detalle-acciones');
    var detalleEditar = document.getElementById('detalle-editar');
    var detalleEstado = document.getElementById('detalle-estado');
    var nuevoProducto = document.getElementById('nuevo-producto');
    var dialogoForm = document.getElementById('formulario-producto');
    var formProducto = document.getElementById('form-producto');
    var formularioTitulo = document.getElementById('formulario-titulo');
    var avisoFormulario = document.getElementById('aviso-formulario');
    var cargaFormulario = document.getElementById('carga-formulario');
    var cuerpoFormulario = document.getElementById('cuerpo-formulario');
    var campoCodigo = document.getElementById('campo-codigo');
    var campoBarras = document.getElementById('campo-barras');
    var campoNombre = document.getElementById('campo-nombre');
    var campoDescripcion = document.getElementById('campo-descripcion');
    var campoCategoria = document.getElementById('campo-categoria');
    var campoProveedor = document.getElementById('campo-proveedor');
    var campoUnidad = document.getElementById('campo-unidad');
    var listaUnidades = document.getElementById('lista-unidades');
    var campoAplicaIva = document.getElementById('campo-aplica-iva');
    var etiquetaAplicaIva = document.getElementById('etiqueta-aplica-iva');
    var campoPrecio = document.getElementById('campo-precio');
    var campoCosto = document.getElementById('campo-costo');
    var bloqueAsignarSucursales = document.getElementById('bloque-asignar-sucursales');
    var formAsignarSucursales = document.getElementById('form-asignar-sucursales');
    var formSucursalesCampos = document.getElementById('form-sucursales-campos');
    var formTodasSucursales = document.getElementById('form-todas-sucursales');
    var formListaSucursales = document.getElementById('form-lista-sucursales');
    var formStockActual = document.getElementById('form-stock-actual');
    var formStockMinimo = document.getElementById('form-stock-minimo');
    var formStockMaximo = document.getElementById('form-stock-maximo');
    var bloqueSucursalesAsignadas = document.getElementById('bloque-sucursales-asignadas');
    var listaSucursalesAsignadas = document.getElementById('lista-sucursales-asignadas');
    var avisoSinSucursalesAsignadas = document.getElementById('aviso-sin-sucursales-asignadas');
    var bloqueAgregarSucursal = document.getElementById('bloque-agregar-sucursal');
    var avisoAgregarSucursal = document.getElementById('aviso-agregar-sucursal');
    var formAgregarSucursalSelect = document.getElementById('form-agregar-sucursal-select');
    var campoAgregarSucursalSelect = document.getElementById('campo-agregar-sucursal-select');
    var formAgregarStockActual = document.getElementById('form-agregar-stock-actual');
    var formAgregarStockMinimo = document.getElementById('form-agregar-stock-minimo');
    var formAgregarStockMaximo = document.getElementById('form-agregar-stock-maximo');
    var formAgregarSucursal = document.getElementById('form-agregar-sucursal');
    var guardarProducto = document.getElementById('guardar-producto');
    var confirma = document.getElementById('confirma-producto');
    var textoConfirma = document.getElementById('texto-confirma');
    var aceptarConfirma = document.getElementById('aceptar-confirma');
    var comercial = document.querySelector('.comercial');
    var pagina = 1;
    var tokenLista = 0;
    var tokenDetalle = 0;
    var tokenForm = 0;
    var esperaBusqueda = null;
    var resumenListo = false;
    var formularioListo = false;
    var modoForm = 'crear';
    var idEdicion = null;
    var productoDetalle = null;
    var sucursalesPendientes = [];
    var sucursalesFormulario = [];
    var sucursalesDetalleEdicion = [];
    var confirmarAccion = null;
    var esperaExito = null;

    function mostrarMensaje(texto) {
        mensaje.textContent = texto;
        mensaje.hidden = false;
    }

    function mostrarExito(texto) {
        window.clearTimeout(esperaExito);
        exitoCatalogo.textContent = texto;
        exitoCatalogo.hidden = false;
        esperaExito = window.setTimeout(function () {
            exitoCatalogo.hidden = true;
        }, 4000);
    }

    function redireccionLocal(valor) {
        if (typeof valor !== 'string') {
            return false;
        }

        var ruta = valor.trim();

        if (ruta === '' || ruta.length > 200) {
            return false;
        }

        if (/^[a-z][a-z0-9+.-]*:/i.test(ruta) || ruta.indexOf('//') === 0 || ruta.indexOf('\\') !== -1) {
            return false;
        }

        return true;
    }

    function limpiar(nodo) {
        while (nodo.firstChild) {
            nodo.removeChild(nodo.firstChild);
        }
    }

    function entero(valor) {
        var numero = Math.trunc(Number(valor));

        if (!isFinite(numero)) {
            numero = 0;
        }

        return String(Math.abs(numero)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function dinero(valor) {
        if (valor == null || valor === '') {
            return '$ 0,00';
        }

        var texto = String(valor);
        var negativo = texto.charAt(0) === '-';
        var partes = texto.replace('-', '').split('.');
        var miles = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        var decimal = ((partes[1] || '00') + '00').slice(0, 2);

        return (negativo ? '-' : '') + '$ ' + miles + ',' + decimal;
    }

    function tasa(valor) {
        if (valor == null || valor === '') {
            return '';
        }

        var texto = String(valor);

        if (texto.indexOf('.') !== -1) {
            texto = texto.replace(/0+$/, '').replace(/\.$/, '');
        }

        return texto + '%';
    }

    function impuestoVisible(nombre, porcentaje, etiquetaIva, aplicaIva) {
        if (etiquetaIva) {
            return String(etiquetaIva);
        }

        if (aplicaIva === false) {
            return 'Sin IVA';
        }

        var titulo = nombre == null ? '' : String(nombre).trim();
        var valor = tasa(porcentaje);

        if (titulo === '' && valor === '') {
            return 'Sin IVA';
        }

        if (titulo !== '' && valor !== '' && titulo.indexOf('%') === -1) {
            return titulo + ' ' + valor;
        }

        return titulo !== '' ? titulo : valor;
    }

    function margenVisible(valor) {
        if (valor == null || valor === '') {
            return 'No disponible';
        }

        var partes = String(valor).split('.');
        var decimal = ((partes[1] || '00') + '00').slice(0, 2);

        return partes[0].replace('-', '') === partes[0]
            ? partes[0] + ',' + decimal + '%'
            : '-' + partes[0].replace('-', '') + ',' + decimal + '%';
    }

    function conValor(valor) {
        return valor != null && String(valor).trim() !== '';
    }

    function idOpcional(select) {
        return select.value === '' ? null : Number(select.value);
    }

    function marcarEspera(activo) {
        indicadores.setAttribute('aria-busy', activo ? 'true' : 'false');
        indicadores.querySelectorAll('.indicador').forEach(function (tarjeta) {
            tarjeta.classList.toggle('indicador--espera', activo && !resumenListo);
        });
    }

    function llenarSelect(select, lista, valor, texto, vacio) {
        var actual = select.value;
        var primera = select.options[0] ? select.options[0].cloneNode(true) : null;

        limpiar(select);

        if (primera) {
            if (vacio) {
                primera.value = '';
                primera.textContent = vacio;
            }

            select.appendChild(primera);
        }

        (lista || []).forEach(function (item) {
            var opcion = document.createElement('option');
            opcion.value = String(item[valor]);
            opcion.textContent = typeof texto === 'function' ? texto(item) : item[texto];
            select.appendChild(opcion);
        });

        if (actual !== '' && select.querySelector('option[value="' + actual + '"]')) {
            select.value = actual;
        }
    }

    function avisoTabla(texto) {
        limpiar(cuerpo);
        var fila = document.createElement('tr');
        var celda = document.createElement('td');
        celda.className = 'listado__aviso';
        celda.colSpan = 9;
        celda.textContent = texto;
        fila.appendChild(celda);
        cuerpo.appendChild(fila);
    }

    function pintarResumen(datos) {
        var kpis = datos.kpis || {};

        document.getElementById('kpi-productos').textContent = entero(kpis.productos_activos);
        document.getElementById('kpi-categorias').textContent = entero(kpis.categorias_activas);
        document.getElementById('kpi-proveedores').textContent = entero(kpis.proveedores_activos);
        document.getElementById('kpi-cobertura').textContent = entero(kpis.productos_cobertura_parcial) + ' productos';
        llenarSelect(categoria, datos.categorias, 'id_categoria', 'nombre');
        llenarSelect(proveedor, datos.proveedores, 'id_proveedor', 'nombre');
        llenarSelect(impuesto, datos.impuestos, 'id_impuesto', function (item) {
            return impuestoVisible(item.nombre, item.porcentaje, item.etiqueta_iva, item.aplica_iva);
        });
        llenarSelect(sucursal, datos.sucursales, 'id_sucursal', 'nombre');
    }

    function cargarResumen() {
        marcarEspera(true);

        if (typeof axios === 'undefined') {
            marcarEspera(false);
            mostrarMensaje('No se pudo conectar. Inténtalo de nuevo.');
            return;
        }

        axios.post(controlador, {
            op: 'obtenerResumenCatalogo'
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true) {
                mostrarMensaje(datos.mensaje || 'No disponible');
                return;
            }

            mensaje.hidden = true;
            resumenListo = true;
            pintarResumen(datos);
        }).catch(function () {
            mostrarMensaje('No se pudo cargar el catálogo.');
        }).finally(function () {
            marcarEspera(false);
        });
    }

    function cobertura(disponibles, total) {
        var caja = document.createElement('div');
        var texto = document.createElement('span');
        var barra = document.createElement('i');
        var cantidad = Number(disponibles);
        var universo = Number(total);
        var ancho = universo > 0 ? Math.max(0, Math.min(100, (cantidad / universo) * 100)) : 0;

        caja.className = 'cobertura';
        texto.textContent = entero(disponibles) + ' de ' + entero(total) + ' sucursales';
        barra.style.setProperty('--cobertura', ancho + '%');
        caja.appendChild(texto);
        caja.appendChild(barra);

        return caja;
    }

    function botonAccion(texto, alClick) {
        var botonAccionEl = document.createElement('button');
        botonAccionEl.className = 'ver';
        botonAccionEl.type = 'button';
        botonAccionEl.textContent = texto;
        botonAccionEl.addEventListener('click', alClick);
        return botonAccionEl;
    }

    function pintarLista(datos) {
        var filas = datos.filas || [];
        var total = Number(datos.total_registros) || 0;
        var actual = Number(datos.pagina_actual) || 0;
        var paginas = Number(datos.total_paginas) || 0;

        resumenLista.textContent = 'Mostrando ' + entero(filas.length) + ' de ' + entero(total) + ' productos';
        indicadorPagina.textContent = 'Página ' + entero(actual) + ' de ' + entero(paginas);
        anterior.disabled = actual <= 1;
        siguiente.disabled = paginas === 0 || actual >= paginas;
        limpiar(cuerpo);

        if (filas.length === 0) {
            avisoTabla('No se encontraron productos con los filtros seleccionados.');
            return;
        }

        filas.forEach(function (fila) {
            var tr = document.createElement('tr');
            var producto = document.createElement('td');
            var codigo = document.createElement('td');
            var categoriaCelda = document.createElement('td');
            var proveedorCelda = document.createElement('td');
            var precio = document.createElement('td');
            var impuestoCelda = document.createElement('td');
            var disponibilidad = document.createElement('td');
            var estadoCelda = document.createElement('td');
            var accion = document.createElement('td');
            var unidad = document.createElement('small');
            var insignia = document.createElement('span');
            var grupo = document.createElement('div');

            producto.textContent = fila.nombre || '';
            unidad.textContent = fila.unidad_medida || '';
            producto.appendChild(unidad);
            codigo.textContent = fila.codigo || '';

            if (conValor(fila.codigo_barras)) {
                var barras = document.createElement('small');
                barras.textContent = fila.codigo_barras;
                codigo.appendChild(barras);
            }

            categoriaCelda.textContent = fila.categoria || '';
            proveedorCelda.textContent = fila.proveedor || '';
            precio.textContent = dinero(fila.precio);
            impuestoCelda.textContent = impuestoVisible(
                fila.impuesto_nombre,
                fila.impuesto_porcentaje,
                fila.etiqueta_iva,
                fila.aplica_iva
            );
            disponibilidad.appendChild(cobertura(fila.sucursales_disponibles, fila.total_sucursales_activas));
            insignia.className = 'estado ' + (fila.estado ? 'estado--activo' : 'estado--inactivo');
            insignia.textContent = fila.estado ? 'Activo' : 'Inactivo';
            estadoCelda.appendChild(insignia);
            grupo.className = 'acciones';
            grupo.appendChild(botonAccion('Ver', function () {
                abrirDetalle(fila.id_producto);
            }));
            grupo.appendChild(botonAccion('Editar', function () {
                abrirEdicion(fila.id_producto);
            }));
            grupo.appendChild(botonAccion(fila.estado ? 'Desactivar' : 'Activar', function () {
                pedirEstado(fila.id_producto, !!fila.estado);
            }));
            accion.appendChild(grupo);

            tr.appendChild(producto);
            tr.appendChild(codigo);
            tr.appendChild(categoriaCelda);
            tr.appendChild(proveedorCelda);
            tr.appendChild(precio);
            tr.appendChild(impuestoCelda);
            tr.appendChild(disponibilidad);
            tr.appendChild(estadoCelda);
            tr.appendChild(accion);
            cuerpo.appendChild(tr);
        });
    }

    function cargarLista() {
        var token = ++tokenLista;

        anterior.disabled = true;
        siguiente.disabled = true;
        avisoTabla('Cargando catálogo...');

        if (typeof axios === 'undefined') {
            avisoTabla('No se pudo cargar el catálogo.');
            return Promise.resolve();
        }

        return axios.post(controlador, {
            op: 'listarCatalogoAdministrativo',
            busqueda: busqueda.value.trim(),
            id_categoria: idOpcional(categoria),
            id_proveedor: idOpcional(proveedor),
            id_impuesto: idOpcional(impuesto),
            estado_producto: estado.value,
            id_sucursal: idOpcional(sucursal),
            pagina: pagina
        }).then(function (respuesta) {
            if (token !== tokenLista) {
                return;
            }

            var datos = respuesta.data || {};

            if (datos.exito !== true) {
                avisoTabla(datos.mensaje || 'No disponible');
                return;
            }

            pagina = Number(datos.pagina_actual) || pagina;
            pintarLista(datos);
        }).catch(function () {
            if (token === tokenLista) {
                avisoTabla('No se pudo cargar el catálogo.');
            }
        });
    }

    function dato(titulo, valor) {
        var bloque = document.createElement('div');
        var termino = document.createElement('dt');
        var definicion = document.createElement('dd');

        termino.textContent = titulo;
        definicion.textContent = valor;
        bloque.appendChild(termino);
        bloque.appendChild(definicion);

        return bloque;
    }

    function clasePresencia(valor) {
        if (valor === 'Disponible') {
            return 'presencia--si';
        }

        if (valor === 'Sin stock') {
            return 'presencia--no';
        }

        return 'presencia--neutro';
    }

    function bloquearFondo(activo) {
        if (comercial) {
            comercial.inert = !!activo;
        }
    }

    function avisoAltaSucursal(texto) {
        altaSucursalAviso.textContent = texto || '';
        altaSucursalAviso.hidden = !texto;
    }

    function reiniciarAltaSucursal() {
        sucursalesPendientes = [];
        altaSucursal.hidden = true;
        avisoAltaSucursal('');
        altaTodasSucursales.checked = false;
        altaSucursalCampoSelect.hidden = false;
        altaSucursalSelect.disabled = false;
        altaStockActual.value = '0';
        altaStockMinimo.value = '0';
        altaStockMaximo.value = '0';
        limpiar(altaSucursalSelect);
        var vacia = document.createElement('option');
        vacia.value = '';
        vacia.textContent = 'Seleccione';
        altaSucursalSelect.appendChild(vacia);
        altaSucursalAgregar.disabled = false;
        altaSucursalAgregar.removeAttribute('aria-busy');
    }

    function sincronizarAltaTodas() {
        var todas = altaTodasSucursales.checked;
        altaSucursalCampoSelect.hidden = todas;
        altaSucursalSelect.disabled = todas;
        avisoAltaSucursal('');
    }

    function prepararDetalle() {
        detalleCarga.hidden = false;
        detalleError.hidden = true;
        detalleContenido.hidden = true;
        detalleAcciones.hidden = true;
        detalleTitulo.textContent = 'Detalle del producto';
        productoDetalle = null;
        reiniciarAltaSucursal();
        limpiar(detalleDatos);
        limpiar(detalleSucursales);
    }

    function cerrarDetalle() {
        if (detalle.open) {
            detalle.close();
        }

        if (!dialogoForm.open && !confirma.open) {
            bloquearFondo(false);
        }
    }

    function pintarDetalle(producto, sucursales) {
        productoDetalle = producto;
        detalleTitulo.textContent = producto.nombre || 'Detalle del producto';
        detalleDatos.appendChild(dato('Nombre', producto.nombre || ''));

        if (conValor(producto.descripcion)) {
            detalleDatos.appendChild(dato('Descripción', producto.descripcion));
        }

        detalleDatos.appendChild(dato('Código', producto.codigo || ''));

        if (conValor(producto.codigo_barras)) {
            detalleDatos.appendChild(dato('Código de barras', producto.codigo_barras));
        }

        detalleDatos.appendChild(dato('Unidad de medida', producto.unidad_medida || ''));
        detalleDatos.appendChild(dato('Categoría', producto.categoria || ''));
        detalleDatos.appendChild(dato('Proveedor', producto.proveedor || ''));
        detalleDatos.appendChild(dato('Estado', producto.estado ? 'Activo' : 'Inactivo'));
        detalleDatos.appendChild(dato(
            'IVA',
            impuestoVisible(producto.impuesto_nombre, producto.impuesto_porcentaje, producto.etiqueta_iva, producto.aplica_iva)
        ));
        document.getElementById('detalle-precio').textContent = dinero(producto.precio);
        document.getElementById('detalle-costo').textContent = dinero(producto.costo);
        document.getElementById('detalle-margen').textContent = margenVisible(producto.margen_bruto_porcentaje);
        document.getElementById('detalle-impuesto').textContent = impuestoVisible(
            producto.impuesto_nombre,
            producto.impuesto_porcentaje,
            producto.etiqueta_iva,
            producto.aplica_iva
        );

        sucursalesPendientes = [];

        (sucursales || []).forEach(function (item) {
            var fila = document.createElement('li');
            var nombre = document.createElement('strong');
            var acciones = document.createElement('div');
            var insignia = document.createElement('span');
            var noRegistrado = item.registrada === false
                || item.estado_disponibilidad === 'No registrado';

            nombre.textContent = item.nombre || '';
            acciones.className = 'presencia__acciones';
            insignia.className = 'presencia__estado ' + clasePresencia(item.estado_disponibilidad);
            insignia.textContent = item.estado_disponibilidad || '';
            acciones.appendChild(insignia);

            if (noRegistrado && item.id_sucursal) {
                sucursalesPendientes.push({
                    id_sucursal: Number(item.id_sucursal),
                    nombre: item.nombre || ''
                });
                acciones.appendChild(botonAccion('Registrar', function () {
                    altaTodasSucursales.checked = false;
                    sincronizarAltaTodas();
                    altaSucursalSelect.value = String(item.id_sucursal);
                    altaStockActual.focus();
                    avisoAltaSucursal('');
                }));
            }

            fila.appendChild(nombre);
            fila.appendChild(acciones);
            detalleSucursales.appendChild(fila);
        });

        if (sucursalesPendientes.length > 0) {
            llenarSelect(altaSucursalSelect, sucursalesPendientes, 'id_sucursal', 'nombre', 'Seleccione');
            altaSucursal.hidden = false;
            sincronizarAltaTodas();
        } else {
            reiniciarAltaSucursal();
        }

        detalleEstado.textContent = producto.estado ? 'Desactivar' : 'Activar';
        detalleAcciones.hidden = false;
        detalleContenido.hidden = false;
    }

    function stockEntero(campo, etiqueta) {
        var texto = String(campo.value || '').trim();

        if (!/^(0|[1-9][0-9]*)$/.test(texto)) {
            avisoAltaSucursal(etiqueta + ' no válido.');
            return null;
        }

        return Number(texto);
    }

    function registrarEnSucursales() {
        var stockActual;
        var stockMinimo;
        var stockMaximo;
        var payloadSucursales;
        var idProducto;

        if (!productoDetalle || !productoDetalle.id_producto || altaSucursalAgregar.disabled) {
            return;
        }

        idProducto = Number(productoDetalle.id_producto);
        stockActual = stockEntero(altaStockActual, 'Stock actual');

        if (stockActual === null) {
            return;
        }

        stockMinimo = stockEntero(altaStockMinimo, 'Stock mínimo');

        if (stockMinimo === null) {
            return;
        }

        stockMaximo = stockEntero(altaStockMaximo, 'Stock máximo');

        if (stockMaximo === null) {
            return;
        }

        if (stockMaximo < stockMinimo || stockMaximo < stockActual) {
            avisoAltaSucursal('El stock máximo debe ser mayor o igual al mínimo y al stock actual.');
            return;
        }

        if (altaTodasSucursales.checked) {
            if (sucursalesPendientes.length === 0) {
                avisoAltaSucursal('No hay sucursales pendientes.');
                return;
            }

            payloadSucursales = sucursalesPendientes.map(function (item) {
                return {
                    id_sucursal: item.id_sucursal,
                    stock_actual: stockActual,
                    stock_minimo: stockMinimo,
                    stock_maximo: stockMaximo
                };
            });
        } else {
            if (!altaSucursalSelect.value) {
                avisoAltaSucursal('Seleccione una sucursal.');
                return;
            }

            payloadSucursales = [{
                id_sucursal: Number(altaSucursalSelect.value),
                stock_actual: stockActual,
                stock_minimo: stockMinimo,
                stock_maximo: stockMaximo
            }];
        }

        avisoAltaSucursal('');
        altaSucursalAgregar.disabled = true;
        altaSucursalAgregar.setAttribute('aria-busy', 'true');

        axios.post(controlador, {
            op: 'registrarProductoEnSucursales',
            id_producto: idProducto,
            sucursales: payloadSucursales
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true) {
                avisoAltaSucursal(datos.mensaje || 'No se pudo registrar el producto.');
                return;
            }

            mostrarExito(datos.mensaje || 'Producto registrado en sucursal.');
            cargarResumen();
            cargarLista();
            abrirDetalle(idProducto);
        }).catch(function () {
            avisoAltaSucursal('No se pudo registrar el producto.');
        }).finally(function () {
            if (altaSucursalAgregar.getAttribute('aria-busy') === 'true') {
                altaSucursalAgregar.disabled = false;
                altaSucursalAgregar.removeAttribute('aria-busy');
            }
        });
    }

    function abrirDetalle(idProducto) {
        var token = ++tokenDetalle;

        if (!idProducto) {
            return;
        }

        prepararDetalle();
        bloquearFondo(true);

        if (!detalle.open) {
            detalle.showModal();
        }

        if (typeof axios === 'undefined') {
            detalleCarga.hidden = true;
            detalleError.hidden = false;
            detalleError.textContent = 'No se pudo conectar. Inténtalo de nuevo.';
            return;
        }

        axios.post(controlador, {
            op: 'obtenerDetalleProducto',
            id_producto: idProducto
        }).then(function (respuesta) {
            if (token !== tokenDetalle || !detalle.open) {
                return;
            }

            var datos = respuesta.data || {};

            detalleCarga.hidden = true;

            if (datos.exito !== true) {
                detalleError.hidden = false;
                detalleError.textContent = datos.mensaje || 'No disponible';
                return;
            }

            pintarDetalle(datos.producto || {}, datos.sucursales || []);
        }).catch(function () {
            if (token !== tokenDetalle || !detalle.open) {
                return;
            }

            detalleCarga.hidden = true;
            detalleError.hidden = false;
            detalleError.textContent = 'No se pudo cargar el detalle.';
        });
    }

    function avisoForm(texto) {
        avisoFormulario.textContent = texto || '';
        avisoFormulario.hidden = !texto;
    }

    function sincronizarEtiquetaIva() {
        etiquetaAplicaIva.textContent = campoAplicaIva.checked ? 'Con IVA' : 'Sin IVA';
    }

    function pintarListaSucursalesForm(lista) {
        sucursalesFormulario = [];
        limpiar(formListaSucursales);

        (lista || []).forEach(function (item) {
            var id = Number(item.id_sucursal);
            var nombre = item.nombre == null ? '' : String(item.nombre);
            var etiqueta;
            var casilla;

            if (!id || nombre === '') {
                return;
            }

            sucursalesFormulario.push({
                id_sucursal: id,
                nombre: nombre
            });

            etiqueta = document.createElement('label');
            etiqueta.className = 'formulario__sucursal-item';
            casilla = document.createElement('input');
            casilla.type = 'checkbox';
            casilla.name = 'form-sucursal';
            casilla.value = String(id);
            etiqueta.appendChild(casilla);
            etiqueta.appendChild(document.createTextNode(' ' + nombre));
            formListaSucursales.appendChild(etiqueta);
        });
    }

    function sincronizarFormTodasSucursales() {
        var marcarTodas = formTodasSucursales.checked;
        var casillas = formListaSucursales.querySelectorAll('input[type="checkbox"]');

        casillas.forEach(function (casilla) {
            casilla.checked = marcarTodas;
            casilla.disabled = marcarTodas;
        });
    }

    function sincronizarCamposAsignacion() {
        var asignar = modoForm === 'crear' && formAsignarSucursales.checked;

        formSucursalesCampos.hidden = !asignar;

        if (!asignar) {
            return;
        }

        sincronizarFormTodasSucursales();
    }

    function mostrarBloqueSucursalesCrear(visible) {
        bloqueAsignarSucursales.hidden = !visible;

        if (!visible) {
            formAsignarSucursales.checked = false;
            formTodasSucursales.checked = false;
            formSucursalesCampos.hidden = true;
            return;
        }

        sincronizarCamposAsignacion();
    }

    function avisoAgregarForm(texto) {
        avisoAgregarSucursal.textContent = texto || '';
        avisoAgregarSucursal.hidden = !texto;
    }

    function reiniciarBloqueAgregarSucursal() {
        avisoAgregarForm('');
        formAgregarStockActual.value = '0';
        formAgregarStockMinimo.value = '0';
        formAgregarStockMaximo.value = '0';
        formAgregarSucursal.disabled = false;
        formAgregarSucursal.removeAttribute('aria-busy');
        limpiar(formAgregarSucursalSelect);
        var vacia = document.createElement('option');
        vacia.value = '';
        vacia.textContent = 'Seleccione';
        formAgregarSucursalSelect.appendChild(vacia);
    }

    function mostrarBloqueSucursalesAsignadas(visible) {
        bloqueSucursalesAsignadas.hidden = !visible;

        if (!visible) {
            sucursalesDetalleEdicion = [];
            limpiar(listaSucursalesAsignadas);
            avisoSinSucursalesAsignadas.hidden = true;
            reiniciarBloqueAgregarSucursal();
            bloqueAgregarSucursal.hidden = true;
        }
    }

    function sucursalEstaRegistrada(item) {
        if (!item) {
            return false;
        }

        if (item.registrada === true) {
            return true;
        }

        if (item.registrada === false) {
            return false;
        }

        return item.estado_disponibilidad !== 'No registrado';
    }

    function pintarSucursalesAsignadas(sucursales) {
        var asignadas = [];
        var pendientes = [];

        sucursalesDetalleEdicion = Array.isArray(sucursales) ? sucursales.slice() : [];
        limpiar(listaSucursalesAsignadas);
        reiniciarBloqueAgregarSucursal();

        (sucursalesDetalleEdicion || []).forEach(function (item) {
            var id = Number(item.id_sucursal);
            var nombre = item.nombre == null ? '' : String(item.nombre);

            if (!id || nombre === '') {
                return;
            }

            if (sucursalEstaRegistrada(item)) {
                asignadas.push(item);
            } else {
                pendientes.push({
                    id_sucursal: id,
                    nombre: nombre
                });
            }
        });

        if (asignadas.length === 0) {
            avisoSinSucursalesAsignadas.hidden = false;
        } else {
            avisoSinSucursalesAsignadas.hidden = true;
            asignadas.forEach(function (item) {
                var fila = document.createElement('li');
                var info = document.createElement('div');
                var nombreEl = document.createElement('strong');
                var meta = document.createElement('span');
                var stock = item.stock_actual;
                var etiquetaEstado = item.estado_disponibilidad || '';

                fila.className = 'formulario__asignada';
                info.className = 'formulario__asignada-info';
                nombreEl.textContent = item.nombre || '';

                if (stock != null && stock !== '') {
                    meta.textContent = etiquetaEstado + ' · Stock ' + entero(stock);
                } else {
                    meta.textContent = etiquetaEstado;
                }

                info.appendChild(nombreEl);
                info.appendChild(meta);
                fila.appendChild(info);
                fila.appendChild(botonAccion('Quitar', function () {
                    pedirRetiroSucursal(Number(item.id_sucursal), item.nombre || '');
                }));
                listaSucursalesAsignadas.appendChild(fila);
            });
        }

        if (pendientes.length > 0) {
            llenarSelect(formAgregarSucursalSelect, pendientes, 'id_sucursal', 'nombre', 'Seleccione');
            campoAgregarSucursalSelect.hidden = false;
            formAgregarSucursalSelect.disabled = false;
            bloqueAgregarSucursal.hidden = false;
        } else {
            bloqueAgregarSucursal.hidden = true;
        }
    }

    function refrescarSucursalesEdicion() {
        if (modoForm !== 'editar' || !idEdicion || !dialogoForm.open) {
            return Promise.resolve();
        }

        return axios.post(controlador, {
            op: 'obtenerDetalleProducto',
            id_producto: idEdicion
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (!dialogoForm.open || modoForm !== 'editar' || !idEdicion) {
                return;
            }

            if (datos.exito !== true) {
                avisoForm(datos.mensaje || 'No se pudo actualizar las sucursales.');
                return;
            }

            pintarSucursalesAsignadas(datos.sucursales || []);
        }).catch(function () {
            if (dialogoForm.open && modoForm === 'editar') {
                avisoForm('No se pudo actualizar las sucursales.');
            }
        });
    }

    function pedirRetiroSucursal(idSucursal, nombreSucursal) {
        var idProducto = idEdicion;
        var etiqueta = nombreSucursal ? String(nombreSucursal) : 'esta sucursal';

        if (!idProducto || !idSucursal) {
                return;
            }

        textoConfirma.textContent = '¿Desea quitar el producto de ' + etiqueta + '? Dejará de aparecer en el punto de venta de esa sucursal.';
        confirmarAccion = function () {
            return axios.post(controlador, {
                op: 'retirarProductoDeSucursal',
                id_producto: idProducto,
                id_sucursal: idSucursal
            }).then(function (respuesta) {
                var datos = respuesta.data || {};

                if (datos.exito !== true) {
                    textoConfirma.textContent = datos.mensaje || 'No se pudo retirar el producto.';
                    return false;
                }

                cargarResumen();
                return Promise.all([
                    cargarLista(),
                    refrescarSucursalesEdicion()
                ]).then(function () {
                    if (detalle.open && Number(idProducto) > 0) {
                        abrirDetalle(idProducto);
                    }

                    mostrarExito(datos.mensaje || 'Producto retirado de la sucursal.');
                    return true;
                });
            }).catch(function () {
                textoConfirma.textContent = 'No se pudo retirar el producto.';
                return false;
            });
        };
        bloquearFondo(true);

        if (!confirma.open) {
            confirma.showModal();
        }
    }

    function agregarSucursalDesdeEdicion() {
        var stockActual;
        var stockMinimo;
        var stockMaximo;
        var idProducto;
        var idSucursal;

        if (modoForm !== 'editar' || !idEdicion || formAgregarSucursal.disabled) {
            return;
        }

        idProducto = Number(idEdicion);

        if (!formAgregarSucursalSelect.value) {
            avisoAgregarForm('Seleccione una sucursal.');
            return;
        }

        idSucursal = Number(formAgregarSucursalSelect.value);
        stockActual = stockEnteroFormCampo(formAgregarStockActual, 'Stock actual', avisoAgregarForm);

        if (stockActual === null) {
            return;
        }

        stockMinimo = stockEnteroFormCampo(formAgregarStockMinimo, 'Stock mínimo', avisoAgregarForm);

        if (stockMinimo === null) {
            return;
        }

        stockMaximo = stockEnteroFormCampo(formAgregarStockMaximo, 'Stock máximo', avisoAgregarForm);

        if (stockMaximo === null) {
            return;
        }

        if (stockMaximo < stockMinimo || stockMaximo < stockActual) {
            avisoAgregarForm('El stock máximo debe ser mayor o igual al mínimo y al stock actual.');
            return;
        }

        avisoAgregarForm('');
        formAgregarSucursal.disabled = true;
        formAgregarSucursal.setAttribute('aria-busy', 'true');

        axios.post(controlador, {
            op: 'registrarProductoEnSucursales',
            id_producto: idProducto,
            sucursales: [{
                id_sucursal: idSucursal,
                stock_actual: stockActual,
                stock_minimo: stockMinimo,
                stock_maximo: stockMaximo
            }]
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true) {
                avisoAgregarForm(datos.mensaje || 'No se pudo agregar a la sucursal.');
                return;
            }

            mostrarExito(datos.mensaje || 'Producto registrado en la sucursal.');
            cargarResumen();
            return Promise.all([
                cargarLista(),
                refrescarSucursalesEdicion()
            ]).then(function () {
                if (detalle.open && Number(idProducto) > 0) {
                    abrirDetalle(idProducto);
                }
            });
        }).catch(function () {
            avisoAgregarForm('No se pudo agregar a la sucursal.');
        }).finally(function () {
            if (formAgregarSucursal.getAttribute('aria-busy') === 'true') {
                formAgregarSucursal.disabled = false;
                formAgregarSucursal.removeAttribute('aria-busy');
            }
        });
    }

    function reiniciarAsignacionSucursales() {
        formAsignarSucursales.checked = false;
        formTodasSucursales.checked = false;
        formStockActual.value = '0';
        formStockMinimo.value = '0';
        formStockMaximo.value = '0';
        formListaSucursales.querySelectorAll('input[type="checkbox"]').forEach(function (casilla) {
            casilla.checked = false;
            casilla.disabled = false;
        });
        formSucursalesCampos.hidden = true;
    }

    function stockEnteroFormCampo(campo, etiqueta, avisar) {
        var texto = String(campo.value == null ? '' : campo.value).trim();
        var mostrar = typeof avisar === 'function' ? avisar : avisoForm;

        if (texto === '' || !/^(0|[1-9][0-9]*)$/.test(texto)) {
            mostrar(etiqueta + ' no válido.');
            return null;
        }

        return Number(texto);
    }

    function stockEnteroForm(campo, etiqueta) {
        return stockEnteroFormCampo(campo, etiqueta, avisoForm);
    }

    function sucursalesSeleccionadasForm() {
        if (formTodasSucursales.checked) {
            return sucursalesFormulario.slice();
        }

        return Array.prototype.slice.call(
            formListaSucursales.querySelectorAll('input[type="checkbox"]:checked')
        ).map(function (casilla) {
            return {
                id_sucursal: Number(casilla.value),
                nombre: ''
            };
        }).filter(function (item) {
            return item.id_sucursal > 0;
        });
    }

    function construirAsignacionesSucursal() {
        var stockActual;
        var stockMinimo;
        var stockMaximo;
        var seleccion;

        if (!formAsignarSucursales.checked) {
            return [];
        }

        seleccion = sucursalesSeleccionadasForm();

        if (seleccion.length === 0) {
            avisoForm('Seleccione al menos una sucursal.');
            return null;
        }

        stockActual = stockEnteroForm(formStockActual, 'Stock actual');

        if (stockActual === null) {
            return null;
        }

        stockMinimo = stockEnteroForm(formStockMinimo, 'Stock mínimo');

        if (stockMinimo === null) {
            return null;
        }

        stockMaximo = stockEnteroForm(formStockMaximo, 'Stock máximo');

        if (stockMaximo === null) {
            return null;
        }

        if (stockMaximo < stockMinimo || stockMaximo < stockActual) {
            avisoForm('El stock máximo debe ser mayor o igual al mínimo y al stock actual.');
            return null;
        }

        return seleccion.map(function (item) {
            return {
                id_sucursal: item.id_sucursal,
                stock_actual: stockActual,
                stock_minimo: stockMinimo,
                stock_maximo: stockMaximo
            };
        });
    }

    function aplicarOpcionesFormulario(datos) {
        llenarSelect(campoCategoria, datos.categorias, 'id_categoria', 'nombre', 'Seleccione');
        llenarSelect(campoProveedor, datos.proveedores, 'id_proveedor', 'nombre', 'Seleccione');
        pintarListaSucursalesForm(datos.sucursales || []);
        limpiar(listaUnidades);
        (datos.unidades_sugeridas || []).forEach(function (unidad) {
            var opcion = document.createElement('option');
            opcion.value = unidad;
            listaUnidades.appendChild(opcion);
        });
        formularioListo = true;
    }

    function asegurarFormulario() {
        if (formularioListo) {
            return Promise.resolve(true);
        }

        if (typeof axios === 'undefined') {
            return Promise.resolve(false);
        }

        return axios.post(controlador, {
            op: 'obtenerFormularioProducto'
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true) {
                return false;
            }

            aplicarOpcionesFormulario(datos);
            return true;
        }).catch(function () {
            return false;
        });
    }

    function limpiarFormulario() {
        modoForm = 'crear';
        idEdicion = null;
        formProducto.reset();
        campoAplicaIva.checked = true;
        sincronizarEtiquetaIva();
        reiniciarAsignacionSucursales();
        mostrarBloqueSucursalesCrear(true);
        mostrarBloqueSucursalesAsignadas(false);
        avisoForm('');
        cargaFormulario.hidden = true;
        cuerpoFormulario.hidden = false;
        guardarProducto.hidden = false;
        formularioTitulo.textContent = 'Nuevo producto';
    }

    function aplicarProductoForm(producto, sucursales) {
        idEdicion = producto.id_producto;
        modoForm = 'editar';
        formularioTitulo.textContent = 'Editar producto';
        campoCodigo.value = producto.codigo || '';
        campoBarras.value = producto.codigo_barras || '';
        campoNombre.value = producto.nombre || '';
        campoDescripcion.value = producto.descripcion || '';
        campoCategoria.value = producto.id_categoria ? String(producto.id_categoria) : '';
        campoProveedor.value = producto.id_proveedor ? String(producto.id_proveedor) : '';
        campoUnidad.value = producto.unidad_medida || '';
        campoPrecio.value = producto.precio != null ? String(producto.precio) : '';
        campoCosto.value = producto.costo != null ? String(producto.costo) : '';
        campoAplicaIva.checked = producto.aplica_iva !== false;
        sincronizarEtiquetaIva();
        reiniciarAsignacionSucursales();
        mostrarBloqueSucursalesCrear(false);
        mostrarBloqueSucursalesAsignadas(true);
        pintarSucursalesAsignadas(sucursales || []);
        cargaFormulario.hidden = true;
        cuerpoFormulario.hidden = false;
        guardarProducto.hidden = false;
    }

    function abrirDialogoForm() {
        bloquearFondo(true);

        if (!dialogoForm.open) {
            dialogoForm.showModal();
        }
    }

    function cerrarFormulario() {
        tokenForm += 1;

        if (dialogoForm.open) {
            dialogoForm.close();
        }

        if (!detalle.open && !confirma.open) {
            bloquearFondo(false);
        }
    }

    function abrirCreacion() {
        var token = ++tokenForm;

        limpiarFormulario();
        cargaFormulario.hidden = false;
        cuerpoFormulario.hidden = true;
        guardarProducto.hidden = true;
        abrirDialogoForm();

        asegurarFormulario().then(function (ok) {
            if (token !== tokenForm || !dialogoForm.open) {
                return;
            }

            cargaFormulario.hidden = true;

            if (!ok) {
                avisoForm('No se pudo cargar el formulario.');
                return;
            }

            cuerpoFormulario.hidden = false;
            guardarProducto.hidden = false;
            campoCodigo.focus();
        });
    }

    function abrirEdicion(idProducto) {
        var token = ++tokenForm;

        if (!idProducto) {
            return;
        }

        if (detalle.open) {
            detalle.close();
        }

        limpiarFormulario();
        modoForm = 'editar';
        idEdicion = idProducto;
        formularioTitulo.textContent = 'Editar producto';
        mostrarBloqueSucursalesCrear(false);
        mostrarBloqueSucursalesAsignadas(true);
        cargaFormulario.hidden = false;
        cuerpoFormulario.hidden = true;
        guardarProducto.hidden = true;
        abrirDialogoForm();

        Promise.all([
            asegurarFormulario(),
            axios.post(controlador, {
                op: 'obtenerDetalleProducto',
                id_producto: idProducto
            })
        ]).then(function (resultados) {
            var okForm = resultados[0];
            var datos = (resultados[1] && resultados[1].data) || {};

            if (token !== tokenForm || !dialogoForm.open) {
                return;
            }

            cargaFormulario.hidden = true;

            if (!okForm || datos.exito !== true || !datos.producto) {
                avisoForm((datos && datos.mensaje) || 'No se pudo cargar el producto.');
                return;
            }

            aplicarProductoForm(datos.producto, datos.sucursales || []);
            campoNombre.focus();
        }).catch(function () {
            if (token !== tokenForm || !dialogoForm.open) {
                return;
            }

            cargaFormulario.hidden = true;
            avisoForm('No se pudo cargar el producto.');
        });
    }

    function montoValido(texto) {
        return /^\d+(\.\d{1,4})?$/.test(texto);
    }

    function validarFormulario() {
        var codigo = campoCodigo.value.trim();
        var barras = campoBarras.value.trim();
        var nombre = campoNombre.value.trim();
        var descripcion = campoDescripcion.value.trim();
        var unidad = campoUnidad.value.trim();
        var precio = campoPrecio.value.trim().replace(',', '.');
        var costo = campoCosto.value.trim().replace(',', '.');

        if (codigo === '' || codigo.length > 50) {
            avisoForm('Código no válido.');
            return null;
        }

        if (barras.length > 50) {
            avisoForm('Código de barras no válido.');
            return null;
        }

        if (nombre === '' || nombre.length > 200) {
            avisoForm('Nombre no válido.');
            return null;
        }

        if (descripcion.length > 500) {
            avisoForm('Descripción no válida.');
            return null;
        }

        if (!campoCategoria.value) {
            avisoForm('Categoría no válida.');
            return null;
        }

        if (!campoProveedor.value) {
            avisoForm('Proveedor no válido.');
            return null;
        }

        if (unidad === '' || unidad.length > 50) {
            avisoForm('Unidad de medida no válida.');
            return null;
        }

        if (!montoValido(precio)) {
            avisoForm('Precio no válido.');
            return null;
        }

        if (!montoValido(costo)) {
            avisoForm('Costo no válido.');
            return null;
        }

        var payload = {
            codigo: codigo,
            codigo_barras: barras === '' ? null : barras,
            nombre: nombre,
            descripcion: descripcion === '' ? null : descripcion,
            id_categoria: Number(campoCategoria.value),
            id_proveedor: Number(campoProveedor.value),
            unidad_medida: unidad,
            precio: precio,
            costo: costo,
            aplica_iva: campoAplicaIva.checked
        };
        var asignaciones;

        if (modoForm === 'crear') {
            asignaciones = construirAsignacionesSucursal();

            if (asignaciones === null) {
                return null;
            }

            payload.op = 'crearProducto';

            if (asignaciones.length > 0) {
                payload.sucursales = asignaciones;
            }

            return payload;
        }

        payload.op = 'actualizarProducto';
        payload.id_producto = idEdicion;
        return payload;
    }

    function guardarFormulario(evento) {
        var peticion;

        evento.preventDefault();

        if (guardarProducto.hidden || guardarProducto.disabled) {
            return;
        }

        peticion = validarFormulario();

        if (!peticion) {
            return;
        }

        avisoForm('');
        guardarProducto.disabled = true;
        guardarProducto.setAttribute('aria-busy', 'true');

        axios.post(controlador, peticion).then(function (respuesta) {
            var datos = respuesta.data || {};
            var textoExito;

            if (datos.exito !== true) {
                avisoForm(datos.mensaje || 'No se pudo guardar el producto.');
                return;
            }

            if (modoForm === 'crear') {
                textoExito = datos.mensaje || 'Producto creado correctamente.';
            } else {
                textoExito = datos.mensaje || 'Producto actualizado correctamente.';
            }

            cerrarFormulario();
            limpiarFormulario();
            cargarResumen();
            pagina = 1;
            return cargarLista().then(function () {
                mostrarExito(textoExito);
            });
        }).catch(function (error) {
            var datos = error && error.response && error.response.data;

            avisoForm((datos && datos.mensaje) || 'No se pudo guardar el producto.');
        }).finally(function () {
            guardarProducto.disabled = false;
            guardarProducto.removeAttribute('aria-busy');
        });
    }

    function cerrarConfirma() {
        confirmarAccion = null;

        if (confirma.open) {
            confirma.close();
        }

        if (!detalle.open && !dialogoForm.open) {
            bloquearFondo(false);
        }
    }

    function pedirEstado(idProducto, activo) {
        textoConfirma.textContent = activo
            ? '¿Desea desactivar este producto?'
            : '¿Desea activar este producto?';
        confirmarAccion = function () {
            return axios.post(controlador, {
                op: 'cambiarEstadoProducto',
                id_producto: idProducto,
                nuevo_estado: !activo
            }).then(function (respuesta) {
                var datos = respuesta.data || {};

                if (datos.exito !== true) {
                    textoConfirma.textContent = datos.mensaje || 'No se pudo cambiar el estado.';
                    return false;
                }

                if (detalle.open && productoDetalle && productoDetalle.id_producto === idProducto) {
                    cerrarDetalle();
                }

                cargarResumen();
                return cargarLista().then(function () {
                    mostrarExito(activo ? 'Producto desactivado.' : 'Producto activado.');
                    return true;
                });
            }).catch(function () {
                textoConfirma.textContent = 'No se pudo cambiar el estado.';
                return false;
            });
        };
        bloquearFondo(true);

        if (!confirma.open) {
            confirma.showModal();
        }
    }

    function alCambiarFiltro() {
        pagina = 1;
        cargarLista();
    }

    categoria.addEventListener('change', alCambiarFiltro);
    proveedor.addEventListener('change', alCambiarFiltro);
    impuesto.addEventListener('change', alCambiarFiltro);
    estado.addEventListener('change', alCambiarFiltro);
    sucursal.addEventListener('change', alCambiarFiltro);
    busqueda.addEventListener('input', function () {
        window.clearTimeout(esperaBusqueda);
        esperaBusqueda = window.setTimeout(alCambiarFiltro, 350);
    });
    anterior.addEventListener('click', function () {
        if (anterior.disabled || pagina <= 1) {
            return;
        }

        pagina -= 1;
        cargarLista();
    });
    siguiente.addEventListener('click', function () {
        if (siguiente.disabled) {
            return;
        }

        pagina += 1;
        cargarLista();
    });
    detalle.querySelector('[data-cerrar-detalle]').addEventListener('click', cerrarDetalle);
    detalle.addEventListener('cancel', function () {
        if (!dialogoForm.open && !confirma.open) {
            bloquearFondo(false);
        }
    });
    detalle.addEventListener('close', function () {
        if (!dialogoForm.open && !confirma.open) {
            bloquearFondo(false);
        }
    });
    detalleEditar.addEventListener('click', function () {
        if (!productoDetalle) {
            return;
        }

        abrirEdicion(productoDetalle.id_producto);
    });
    detalleEstado.addEventListener('click', function () {
        if (!productoDetalle) {
            return;
        }

        pedirEstado(productoDetalle.id_producto, !!productoDetalle.estado);
    });
    altaTodasSucursales.addEventListener('change', sincronizarAltaTodas);
    altaSucursalAgregar.addEventListener('click', registrarEnSucursales);
    nuevoProducto.addEventListener('click', abrirCreacion);
    formProducto.addEventListener('submit', guardarFormulario);
    campoAplicaIva.addEventListener('change', sincronizarEtiquetaIva);
    formAsignarSucursales.addEventListener('change', sincronizarCamposAsignacion);
    formTodasSucursales.addEventListener('change', sincronizarFormTodasSucursales);
    formAgregarSucursal.addEventListener('click', agregarSucursalDesdeEdicion);
    formListaSucursales.addEventListener('change', function () {
        if (formTodasSucursales.checked) {
            return;
        }

        var casillas = formListaSucursales.querySelectorAll('input[type="checkbox"]');
        var todasMarcadas = casillas.length > 0 && Array.prototype.every.call(casillas, function (casilla) {
            return casilla.checked;
        });

        if (todasMarcadas) {
            formTodasSucursales.checked = true;
            sincronizarFormTodasSucursales();
        }
    });
    document.getElementById('cerrar-formulario').addEventListener('click', cerrarFormulario);
    dialogoForm.addEventListener('cancel', function () {
        tokenForm += 1;

        if (!detalle.open && !confirma.open) {
            bloquearFondo(false);
        }
    });
    dialogoForm.addEventListener('close', function () {
        if (!detalle.open && !confirma.open) {
            bloquearFondo(false);
        }
    });
    document.getElementById('cerrar-confirma').addEventListener('click', cerrarConfirma);
    document.getElementById('cancelar-confirma').addEventListener('click', cerrarConfirma);
    aceptarConfirma.addEventListener('click', function () {
        var accion = confirmarAccion;

        if (!accion || aceptarConfirma.disabled) {
            return;
        }

        aceptarConfirma.disabled = true;
        Promise.resolve(accion()).then(function (ok) {
            if (ok) {
                cerrarConfirma();
            }
        }).finally(function () {
            aceptarConfirma.disabled = false;
        });
    });
    confirma.addEventListener('cancel', function () {
        confirmarAccion = null;

        if (!detalle.open && !dialogoForm.open) {
            bloquearFondo(false);
        }
    });
    confirma.addEventListener('close', function () {
        confirmarAccion = null;

        if (!detalle.open && !dialogoForm.open) {
            bloquearFondo(false);
        }
    });

    boton.addEventListener('click', function () {
        if (boton.disabled) {
            return;
        }

        mensaje.hidden = true;
        boton.disabled = true;
        boton.setAttribute('aria-busy', 'true');

        if (typeof axios === 'undefined') {
            mostrarMensaje('No se pudo conectar. Inténtalo de nuevo.');
            boton.disabled = false;
            boton.removeAttribute('aria-busy');
            return;
        }

        var redirigio = false;

        axios.post('app/Controllers/AuthController.php', {
            op: 'cerrarSesion'
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito === true && redireccionLocal(datos.redireccion)) {
                redirigio = true;
                window.location.href = datos.redireccion;
                return;
            }

            mostrarMensaje('No se pudo cerrar la sesión.');
        }).catch(function () {
            mostrarMensaje('No se pudo conectar. Inténtalo de nuevo.');
        }).finally(function () {
            if (!redirigio) {
                boton.disabled = false;
                boton.removeAttribute('aria-busy');
            }
        });
    });

    sincronizarEtiquetaIva();
    cargarResumen();
    cargarLista();
}());
