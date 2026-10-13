(function () {
    var controlador = 'app/Controllers/StockAgenciaController.php';
    var busqueda = document.getElementById('filtro-busqueda');
    var categoria = document.getElementById('filtro-categoria');
    var estado = document.getElementById('filtro-estado');
    var limpiar = document.getElementById('limpiar-filtros');
    var aviso = document.getElementById('estado-consulta');
    var marco = document.getElementById('marco-stock');
    var cuerpo = document.getElementById('cuerpo-stock');
    var anterior = document.getElementById('pagina-anterior');
    var siguiente = document.getElementById('pagina-siguiente');
    var indicador = document.getElementById('pagina-indicador');
    var resumen = document.getElementById('resumen-pagina');
    var historial = document.getElementById('historial');
    var historialAviso = document.getElementById('historial-aviso');
    var historialCuerpo = document.getElementById('historial-cuerpo');
    var pagina = 1;
    var totalPaginas = 0;
    var enCurso = false;
    var pendiente = false;
    var esperaBusqueda = 0;
    var firmaCategorias = '';
    var tokenMovimiento = 0;
    var tipos = {
        SALIDA_FACTURA: 'Salida por factura',
        ENTRADA: 'Entrada',
        AJUSTE_POSITIVO: 'Ajuste positivo',
        AJUSTE_NEGATIVO: 'Ajuste negativo',
        ANULACION_FACTURA: 'Anulación de factura'
    };

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

    function entero(valor) {
        var texto = String(valor == null ? '' : valor);
        var negativo = texto.charAt(0) === '-';
        var digitos = texto.replace('-', '').replace(/\D/g, '');
        if (digitos === '') {
            return '—';
        }
        return (negativo ? '-' : '') + digitos.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function fechaVisible(iso) {
        if (!iso) {
            return '';
        }
        var fecha = new Date(iso);
        if (isNaN(fecha.getTime())) {
            return String(iso);
        }
        return new Intl.DateTimeFormat('es-EC', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
            timeZone: 'America/Guayaquil'
        }).format(fecha);
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
        cuerpo.querySelectorAll('button').forEach(function (boton) {
            boton.disabled = activo;
        });
        if (activo) {
            mostrarAviso('Cargando inventario...');
        }
    }

    function pintarKpis(kpis) {
        var datos = kpis || {};
        document.getElementById('kpi-inventariados').textContent = entero(datos.productos_inventariados);
        document.getElementById('kpi-criticos').textContent = entero(datos.productos_criticos);
        document.getElementById('kpi-unidades').textContent = entero(datos.unidades_disponibles);
        document.getElementById('kpi-sin-stock').textContent = entero(datos.productos_sin_stock);
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
            var actual = elemento('td', 'actual');
            var accion = document.createElement('td');
            var boton = elemento('button', 'movimientos', 'Movimientos');
            var estadoCelda = document.createElement('td');
            var idProducto = Number(fila.id_producto);
            producto.appendChild(elemento('strong', null, fila.nombre || '—'));
            if (Number(fila.stock_actual) === 0) {
                actual.classList.add('actual--cero');
                actual.appendChild(elemento('strong', null, '0'));
                actual.appendChild(elemento('small', null, 'Sin stock'));
            } else {
                actual.appendChild(elemento('strong', null, entero(fila.stock_actual)));
            }
            estadoCelda.appendChild(badgeEstado(fila.estado_stock));
            boton.type = 'button';
            boton.addEventListener('click', function () {
                abrirMovimientos(idProducto, fila.nombre, fila.codigo);
            });
            accion.appendChild(boton);
            tr.appendChild(producto);
            tr.appendChild(elemento('td', 'codigo', fila.codigo || '—'));
            tr.appendChild(elemento('td', null, fila.categoria || '—'));
            tr.appendChild(actual);
            tr.appendChild(elemento('td', 'minimo', entero(fila.stock_minimo)));
            tr.appendChild(estadoCelda);
            tr.appendChild(accion);
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
            op: 'listarStockAgencia',
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
                mostrarAviso(datos.mensaje || 'No se pudo consultar el inventario.', true);
                if (!cuerpo.querySelector('.producto')) {
                    pintarVacio(datos.mensaje || 'No se pudo consultar el inventario.');
                }
                return;
            }
            mostrarAviso('');
            pintarKpis(datos.kpis);
            pintarCategorias(datos.categorias);
            pintarFilas(datos.filas);
            pintarPagina(datos, datos.filas.length);
        }).catch(function (error) {
            if (pendiente) {
                return;
            }
            var texto = mensajeError(error) || 'No se pudo consultar el inventario.';
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

    function tipoVisible(valor) {
        if (tipos[valor]) {
            return tipos[valor];
        }
        return valor || 'Movimiento';
    }

    function cantidadVisible(valor) {
        var numero = Number(valor);
        var texto = entero(valor);
        return 'Cantidad: ' + texto + (numero === 1 || numero === -1 ? ' unidad' : ' unidades');
    }

    function pintarMovimientos(lista) {
        limpiarNodo(historialCuerpo);
        if (!lista.length) {
            historialCuerpo.appendChild(elemento('p', 'historial__vacio', 'Este producto no registra movimientos de inventario.'));
            return;
        }
        lista.forEach(function (movimiento) {
            var tarjeta = elemento('article', 'movimiento');
            var usuario = ((movimiento.nombres || '') + ' ' + (movimiento.apellidos || '')).trim();
            var fecha = fechaVisible(movimiento.fecha_movimiento);
            tarjeta.appendChild(elemento('p', 'movimiento__tipo', tipoVisible(movimiento.tipo_movimiento)));
            tarjeta.appendChild(elemento('p', 'movimiento__flujo', entero(movimiento.stock_anterior) + ' → ' + entero(movimiento.stock_nuevo)));
            tarjeta.appendChild(elemento('p', null, cantidadVisible(movimiento.cantidad)));
            if (movimiento.numero_comprobante) {
                tarjeta.appendChild(elemento('p', 'movimiento__comprobante', movimiento.numero_comprobante));
            }
            if (usuario) {
                tarjeta.appendChild(elemento('p', null, usuario));
            }
            if (movimiento.observacion) {
                tarjeta.appendChild(elemento('p', null, movimiento.observacion));
            }
            if (fecha) {
                tarjeta.appendChild(elemento('p', null, fecha));
            }
            historialCuerpo.appendChild(tarjeta);
        });
    }

    function abrirMovimientos(idProducto, nombre, codigo) {
        if (!idProducto || idProducto < 1 || typeof axios === 'undefined') {
            return;
        }
        var token = ++tokenMovimiento;
        document.getElementById('historial-titulo').textContent = nombre || 'Producto';
        document.getElementById('historial-codigo').textContent = codigo || '';
        historialAviso.hidden = false;
        historialAviso.textContent = 'Cargando movimientos...';
        historialAviso.classList.remove('consulta__estado--error');
        limpiarNodo(historialCuerpo);
        document.querySelector('.turno').inert = true;
        if (!historial.open) {
            historial.showModal();
        }
        axios.post(controlador, {
            op: 'obtenerMovimientosProducto',
            id_producto: idProducto
        }, { timeout: 25000 }).then(function (respuesta) {
            if (token !== tokenMovimiento) {
                return;
            }
            var datos = respuesta.data || {};
            if (datos.exito !== true || !Array.isArray(datos.movimientos)) {
                historialAviso.hidden = false;
                historialAviso.textContent = datos.mensaje || 'No se pudo consultar los movimientos.';
                historialAviso.classList.add('consulta__estado--error');
                return;
            }
            historialAviso.hidden = true;
            historialAviso.textContent = '';
            document.getElementById('historial-titulo').textContent = datos.producto || nombre || 'Producto';
            document.getElementById('historial-codigo').textContent = datos.codigo || codigo || '';
            pintarMovimientos(datos.movimientos);
        }).catch(function (error) {
            if (token !== tokenMovimiento) {
                return;
            }
            historialAviso.hidden = false;
            historialAviso.textContent = mensajeError(error) || 'No se pudo consultar los movimientos.';
            historialAviso.classList.add('consulta__estado--error');
        });
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
    function cerrarHistorial() {
        document.querySelector('.turno').inert = false;
        if (historial.open) {
            historial.close();
        }
    }

    document.getElementById('cerrar-historial').addEventListener('click', cerrarHistorial);
    historial.addEventListener('close', cerrarHistorial);
    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && (historial.open || document.querySelector('.turno').inert)) {
            cerrarHistorial();
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
