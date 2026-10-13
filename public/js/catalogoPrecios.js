(function () {
    var controlador = 'app/Controllers/CatalogoPreciosController.php';
    var busqueda = document.getElementById('filtro-busqueda');
    var categoria = document.getElementById('filtro-categoria');
    var estado = document.getElementById('filtro-estado');
    var limpiar = document.getElementById('limpiar-filtros');
    var aviso = document.getElementById('estado-consulta');
    var marco = document.getElementById('marco-catalogo');
    var cuerpo = document.getElementById('cuerpo-catalogo');
    var anterior = document.getElementById('pagina-anterior');
    var siguiente = document.getElementById('pagina-siguiente');
    var indicador = document.getElementById('pagina-indicador');
    var resumen = document.getElementById('resumen-pagina');
    var pagina = 1;
    var totalPaginas = 0;
    var enCurso = false;
    var pendiente = false;
    var esperaBusqueda = 0;
    var firmaCategorias = '';

    function limpiarNodo(nodo) {
        while (nodo.firstChild) {
            nodo.removeChild(nodo.firstChild);
        }
    }

    function elemento(etiqueta, clase, texto) {
        var nodo = document.createElement(etiqueta);
        if (clase) {
            nodo.className = clase;
        }
        if (texto != null) {
            nodo.textContent = texto;
        }
        return nodo;
    }

    function dinero(valor) {
        if (valor == null || valor === '') {
            return '—';
        }
        var texto = String(valor);
        var negativo = texto.charAt(0) === '-';
        var partes = texto.replace('-', '').split('.');
        var entera = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        var decimal = (partes[1] || '00').padEnd(2, '0').slice(0, 2);
        return (negativo ? '-' : '') + '$ ' + entera + ',' + decimal;
    }

    function porcentaje(valor) {
        if (valor == null || valor === '') {
            return '';
        }
        return String(valor).replace(/\.?0+$/, '') + '%';
    }

    function impuestoVisible(fila) {
        var nombre = fila.impuesto_nombre == null ? '' : String(fila.impuesto_nombre).trim();
        var tasa = porcentaje(fila.impuesto_porcentaje);
        if (nombre === '' && tasa === '') {
            return 'Sin impuesto';
        }
        if (nombre !== '' && tasa !== '' && nombre.indexOf('%') === -1) {
            return nombre + ' · ' + tasa;
        }
        return nombre !== '' ? nombre : tasa;
    }

    function mostrarAviso(mensaje, error) {
        if (!mensaje) {
            aviso.hidden = true;
            aviso.textContent = '';
            aviso.classList.remove('consulta__estado--error');
            return;
        }
        aviso.hidden = false;
        aviso.textContent = mensaje;
        aviso.classList.toggle('consulta__estado--error', error === true);
    }

    function mensajeError(error) {
        if (!error || !error.response) {
            return '';
        }
        if (error.response.status === 403) {
            return 'Sesión no disponible. Vuelva a ingresar.';
        }
        var datos = error.response.data || {};
        return typeof datos.mensaje === 'string' ? datos.mensaje : '';
    }

    function marcarEspera(activo) {
        enCurso = activo;
        marco.classList.toggle('listado--espera', activo && cuerpo.querySelector('.producto'));
        marco.setAttribute('aria-busy', activo ? 'true' : 'false');
        limpiar.disabled = activo;
        anterior.disabled = activo || pagina <= 1;
        siguiente.disabled = activo || totalPaginas < 1 || pagina >= totalPaginas;
        if (activo) {
            mostrarAviso('Cargando catálogo...');
        }
    }

    function pintarCategorias(lista) {
        var categorias = Array.isArray(lista) ? lista : [];
        var firma = categorias.map(function (item) {
            return String(item.id_categoria) + ':' + String(item.nombre);
        }).join('|');
        if (firma === firmaCategorias) {
            return;
        }
        firmaCategorias = firma;
        var actual = categoria.value;
        while (categoria.options.length > 1) {
            categoria.remove(1);
        }
        categorias.forEach(function (item) {
            var opcion = document.createElement('option');
            opcion.value = String(item.id_categoria);
            opcion.textContent = item.nombre || 'Categoría';
            categoria.appendChild(opcion);
        });
        if (actual && categorias.some(function (item) {
            return String(item.id_categoria) === actual;
        })) {
            categoria.value = actual;
        }
    }

    function badgeEstado(valor) {
        var badge = elemento('span', 'badge', valor || '—');
        if (valor === 'Crítico') {
            badge.classList.add('badge--critico');
        } else if (valor === 'Normal') {
            badge.classList.add('badge--normal');
        }
        return badge;
    }

    function pintarVacio(mensaje) {
        limpiarNodo(cuerpo);
        var fila = document.createElement('tr');
        var celda = elemento('td', null, mensaje);
        fila.className = 'vacio-fila';
        celda.colSpan = 7;
        fila.appendChild(celda);
        cuerpo.appendChild(fila);
    }

    function pintarFilas(filas) {
        limpiarNodo(cuerpo);
        if (!filas.length) {
            pintarVacio('No se encontraron productos con los filtros seleccionados.');
            return;
        }
        filas.forEach(function (fila) {
            var tr = document.createElement('tr');
            var producto = elemento('td', 'producto');
            var stock = elemento('td', 'stock');
            var estadoCelda = document.createElement('td');
            var barras = fila.codigo_barras == null ? '' : String(fila.codigo_barras).trim();
            producto.appendChild(elemento('strong', null, fila.nombre || '—'));
            if (barras !== '') {
                producto.appendChild(elemento('small', null, barras));
            }
            stock.appendChild(elemento('strong', null, String(fila.stock_actual)));
            stock.appendChild(elemento('small', null, 'mínimo ' + String(fila.stock_minimo)));
            estadoCelda.appendChild(badgeEstado(fila.estado_stock));
            tr.appendChild(producto);
            tr.appendChild(elemento('td', 'codigo', fila.codigo || '—'));
            tr.appendChild(elemento('td', 'col-categoria', fila.categoria || '—'));
            tr.appendChild(elemento('td', 'precio', dinero(fila.precio)));
            tr.appendChild(elemento('td', 'col-impuesto', impuestoVisible(fila)));
            tr.appendChild(stock);
            tr.appendChild(estadoCelda);
            cuerpo.appendChild(tr);
        });
    }

    function pintarPagina(datos, cantidad) {
        var total = Number(datos.total_registros) || 0;
        var porPagina = Number(datos.por_pagina) || 20;
        var inicio = total === 0 ? 0 : ((pagina - 1) * porPagina) + 1;
        var fin = total === 0 ? 0 : inicio + cantidad - 1;
        totalPaginas = Number(datos.total_paginas) || 0;
        resumen.textContent = total === 0
            ? 'Mostrando 0 de 0 productos'
            : 'Mostrando ' + inicio + '–' + fin + ' de ' + total + ' productos';
        indicador.textContent = 'Página ' + (totalPaginas < 1 ? 1 : datos.pagina_actual) + ' de ' + (totalPaginas < 1 ? 1 : totalPaginas);
        anterior.disabled = pagina <= 1;
        siguiente.disabled = totalPaginas < 1 || pagina >= totalPaginas;
    }

    function consultar() {
        if (typeof axios === 'undefined') {
            mostrarAviso('No se pudo conectar. Inténtalo de nuevo.', true);
            return;
        }
        var idCategoria = categoria.value === '' ? null : Number(categoria.value);
        enCurso = true;
        pendiente = false;
        marcarEspera(true);
        axios.post(controlador, {
            op: 'listarCatalogoPrecios',
            busqueda: busqueda.value.trim(),
            id_categoria: idCategoria,
            estado_stock: estado.value,
            pagina: pagina
        }, { timeout: 25000 }).then(function (respuesta) {
            if (pendiente) {
                return;
            }
            var datos = respuesta.data || {};
            if (datos.exito !== true || !Array.isArray(datos.filas)) {
                mostrarAviso(datos.mensaje || 'No se pudo consultar el catálogo.', true);
                if (!cuerpo.querySelector('.producto')) {
                    pintarVacio(datos.mensaje || 'No se pudo consultar el catálogo.');
                }
                return;
            }
            mostrarAviso('');
            pintarCategorias(datos.categorias);
            pintarFilas(datos.filas);
            pintarPagina(datos, datos.filas.length);
        }).catch(function (error) {
            if (pendiente) {
                return;
            }
            var texto = mensajeError(error) || 'No se pudo consultar el catálogo.';
            mostrarAviso(texto, true);
            if (!cuerpo.querySelector('.producto')) {
                pintarVacio(texto);
            }
        }).finally(function () {
            marcarEspera(false);
            if (pendiente) {
                consultar();
            }
        });
    }

    function solicitar(reiniciar) {
        if (reiniciar) {
            pagina = 1;
        }
        if (enCurso) {
            pendiente = true;
            return;
        }
        consultar();
    }

    busqueda.addEventListener('input', function () {
        window.clearTimeout(esperaBusqueda);
        esperaBusqueda = window.setTimeout(function () {
            solicitar(true);
        }, 320);
    });
    categoria.addEventListener('change', function () {
        solicitar(true);
    });
    estado.addEventListener('change', function () {
        solicitar(true);
    });
    document.getElementById('filtros').addEventListener('submit', function (evento) {
        evento.preventDefault();
        window.clearTimeout(esperaBusqueda);
        solicitar(true);
    });
    limpiar.addEventListener('click', function () {
        window.clearTimeout(esperaBusqueda);
        busqueda.value = '';
        categoria.value = '';
        estado.value = 'TODAS';
        solicitar(true);
    });
    anterior.addEventListener('click', function () {
        if (pagina > 1) {
            pagina -= 1;
            solicitar(false);
        }
    });
    siguiente.addEventListener('click', function () {
        if (totalPaginas > 0 && pagina < totalPaginas) {
            pagina += 1;
            solicitar(false);
        }
    });

    var botonSesion = document.querySelector('[data-cerrar-sesion]');
    var mensajeSesion = document.getElementById('mensaje-sesion');
    botonSesion.addEventListener('click', function () {
        if (botonSesion.disabled) {
            return;
        }
        mensajeSesion.hidden = true;
        botonSesion.disabled = true;
        if (typeof axios === 'undefined') {
            mensajeSesion.hidden = false;
            mensajeSesion.textContent = 'No se pudo conectar. Inténtalo de nuevo.';
            botonSesion.disabled = false;
            return;
        }
        var redirigio = false;
        axios.post('app/Controllers/AuthController.php', { op: 'cerrarSesion' }).then(function (respuesta) {
            var datos = respuesta.data || {};
            var ruta = typeof datos.redireccion === 'string' ? datos.redireccion.trim() : '';
            var local = ruta !== '' && ruta.length <= 200 && !/^[a-z][a-z0-9+.-]*:/i.test(ruta) && ruta.indexOf('//') !== 0 && ruta.indexOf('\\') === -1;
            if (datos.exito === true && local) {
                redirigio = true;
                window.location.href = ruta;
                return;
            }
            mensajeSesion.hidden = false;
            mensajeSesion.textContent = 'No se pudo cerrar la sesión.';
        }).catch(function () {
            mensajeSesion.hidden = false;
            mensajeSesion.textContent = 'No se pudo conectar. Inténtalo de nuevo.';
        }).finally(function () {
            if (!redirigio) {
                botonSesion.disabled = false;
            }
        });
    });

    solicitar(true);
}());
