(function () {
    var controlador = 'app/Controllers/CatalogoPreciosAdministrativoController.php';
    var boton = document.getElementById('cerrar-sesion');
    var mensaje = document.getElementById('mensaje-sesion');
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
    var comercial = document.querySelector('.comercial');
    var pagina = 1;
    var tokenLista = 0;
    var tokenDetalle = 0;
    var esperaBusqueda = null;
    var resumenListo = false;

    function mostrarMensaje(texto) {
        mensaje.textContent = texto;
        mensaje.hidden = false;
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

    function impuestoVisible(nombre, porcentaje) {
        var titulo = nombre == null ? '' : String(nombre).trim();
        var valor = tasa(porcentaje);

        if (titulo === '' && valor === '') {
            return 'Sin impuesto';
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

    function llenarSelect(select, lista, valor, texto) {
        var actual = select.value;
        var opciones = Array.prototype.slice.call(select.options);

        opciones.forEach(function (opcion, indice) {
            if (indice > 0) {
                select.removeChild(opcion);
            }
        });

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
            return impuestoVisible(item.nombre, item.porcentaje);
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
            var ver = document.createElement('button');

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
            impuestoCelda.textContent = impuestoVisible(fila.impuesto_nombre, fila.impuesto_porcentaje);
            disponibilidad.appendChild(cobertura(fila.sucursales_disponibles, fila.total_sucursales_activas));
            insignia.className = 'estado ' + (fila.estado ? 'estado--activo' : 'estado--inactivo');
            insignia.textContent = fila.estado ? 'Activo' : 'Inactivo';
            estadoCelda.appendChild(insignia);
            ver.className = 'ver';
            ver.type = 'button';
            ver.textContent = 'Ver detalle';
            ver.addEventListener('click', function () {
                abrirDetalle(fila.id_producto);
            });
            accion.appendChild(ver);

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
            return;
        }

        axios.post(controlador, {
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

    function prepararDetalle() {
        detalleCarga.hidden = false;
        detalleError.hidden = true;
        detalleContenido.hidden = true;
        detalleTitulo.textContent = 'Detalle del producto';
        limpiar(detalleDatos);
        limpiar(detalleSucursales);
    }

    function cerrarDetalle() {
        if (comercial) {
            comercial.inert = false;
        }

        if (detalle.open) {
            detalle.close();
        }
    }

    function abrirDetalle(idProducto) {
        var token = ++tokenDetalle;

        if (!idProducto) {
            return;
        }

        prepararDetalle();

        if (comercial) {
            comercial.inert = true;
        }

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
            var producto = datos.producto || {};

            detalleCarga.hidden = true;

            if (datos.exito !== true) {
                detalleError.hidden = false;
                detalleError.textContent = datos.mensaje || 'No disponible';
                return;
            }

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
            document.getElementById('detalle-precio').textContent = dinero(producto.precio);
            document.getElementById('detalle-costo').textContent = dinero(producto.costo);
            document.getElementById('detalle-margen').textContent = margenVisible(producto.margen_bruto_porcentaje);
            document.getElementById('detalle-impuesto').textContent = impuestoVisible(producto.impuesto_nombre, producto.impuesto_porcentaje);

            (datos.sucursales || []).forEach(function (item) {
                var fila = document.createElement('li');
                var nombre = document.createElement('strong');
                var insignia = document.createElement('span');

                nombre.textContent = item.nombre || '';
                insignia.className = 'presencia__estado ' + clasePresencia(item.estado_disponibilidad);
                insignia.textContent = item.estado_disponibilidad || '';
                fila.appendChild(nombre);
                fila.appendChild(insignia);
                detalleSucursales.appendChild(fila);
            });

            detalleContenido.hidden = false;
        }).catch(function () {
            if (token !== tokenDetalle || !detalle.open) {
                return;
            }

            detalleCarga.hidden = true;
            detalleError.hidden = false;
            detalleError.textContent = 'No se pudo cargar el detalle.';
        });
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
        if (comercial) {
            comercial.inert = false;
        }
    });
    detalle.addEventListener('close', function () {
        if (comercial) {
            comercial.inert = false;
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

    cargarResumen();
    cargarLista();
}());
