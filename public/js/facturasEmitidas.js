(function () {
    var controlador = 'app/Controllers/FacturasEmitidasController.php';
    var busqueda = document.getElementById('filtro-busqueda');
    var desde = document.getElementById('filtro-desde');
    var hasta = document.getElementById('filtro-hasta');
    var estado = document.getElementById('filtro-estado');
    var limpiar = document.getElementById('limpiar-filtros');
    var aviso = document.getElementById('estado-consulta');
    var marco = document.getElementById('marco-facturas');
    var cuerpo = document.getElementById('cuerpo-facturas');
    var anterior = document.getElementById('pagina-anterior');
    var siguiente = document.getElementById('pagina-siguiente');
    var indicador = document.getElementById('pagina-indicador');
    var resumen = document.getElementById('resumen-pagina');
    var visor = document.getElementById('visor-factura');
    var visorAviso = document.getElementById('visor-aviso');
    var visorCuerpo = document.getElementById('visor-cuerpo');
    var pagina = 1;
    var totalPaginas = 0;
    var enCurso = false;
    var pendiente = false;
    var abriendo = false;
    var esperaBusqueda = 0;

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

    function fechaVisible(iso) {
        if (!iso) {
            return '—';
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

    function tipoIdentificacion(valor) {
        if (valor === 'CEDULA') {
            return 'Cédula';
        }
        if (valor === 'RUC') {
            return 'RUC';
        }
        if (valor === 'CONSUMIDOR_FINAL') {
            return 'Consumidor final';
        }
        return valor || '';
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
        marco.classList.toggle('listado--espera', activo && cuerpo.children.length > 0);
        anterior.disabled = activo || pagina <= 1;
        siguiente.disabled = activo || totalPaginas < 1 || pagina >= totalPaginas;
        limpiar.disabled = activo;
        cuerpo.querySelectorAll('button').forEach(function (boton) {
            boton.disabled = activo;
        });
        if (activo) {
            mostrarAviso('Cargando facturas...');
        }
    }

    function filaEstado(valor) {
        var badge = elemento('span', 'badge', valor || '—');
        if (valor === 'EMITIDA') {
            badge.classList.add('badge--emitida');
        } else if (valor === 'ANULADA') {
            badge.classList.add('badge--anulada');
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
            pintarVacio('No se encontraron facturas con los filtros seleccionados.');
            return;
        }
        filas.forEach(function (fila) {
            var tr = document.createElement('tr');
            var cliente = elemento('td', 'cliente');
            var accion = document.createElement('td');
            var boton = elemento('button', 'ver', 'Ver detalle');
            tr.appendChild(elemento('td', 'numero', fila.numero_comprobante || '—'));
            tr.appendChild(elemento('td', null, fechaVisible(fila.fecha_emision)));
            cliente.appendChild(elemento('strong', null, fila.razon_social_cliente || '—'));
            cliente.appendChild(elemento('small', null, fila.identificacion_cliente || ''));
            tr.appendChild(cliente);
            tr.appendChild(elemento('td', null, fila.metodo_pago || '—'));
            tr.appendChild(elemento('td', 'dinero', dinero(fila.total)));
            var estadoCelda = document.createElement('td');
            estadoCelda.appendChild(filaEstado(fila.estado));
            tr.appendChild(estadoCelda);
            boton.type = 'button';
            boton.addEventListener('click', function () {
                abrirDetalle(fila.id_factura);
            });
            accion.appendChild(boton);
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
            ? 'Mostrando 0 de 0 registros'
            : 'Mostrando ' + inicio + '–' + fin + ' de ' + total + ' registros';
        indicador.textContent = 'Página ' + (totalPaginas < 1 ? 1 : datos.pagina_actual) + ' de ' + (totalPaginas < 1 ? 1 : totalPaginas);
        anterior.disabled = pagina <= 1;
        siguiente.disabled = totalPaginas < 1 || pagina >= totalPaginas;
    }

    function consultar() {
        if (typeof axios === 'undefined') {
            mostrarAviso('No se pudo conectar. Inténtalo de nuevo.', true);
            return;
        }
        enCurso = true;
        pendiente = false;
        marcarEspera(true);
        axios.post(controlador, {
            op: 'listarFacturasEmitidas',
            busqueda: busqueda.value.trim(),
            fecha_desde: desde.value,
            fecha_hasta: hasta.value,
            estado: estado.value,
            pagina: pagina
        }, { timeout: 25000 }).then(function (respuesta) {
            if (pendiente) {
                return;
            }
            var datos = respuesta.data || {};
            if (datos.exito !== true || !Array.isArray(datos.filas)) {
                mostrarAviso(datos.mensaje || 'No se pudo consultar las facturas.', true);
                if (!cuerpo.querySelector('.numero')) {
                    pintarVacio(datos.mensaje || 'No se pudo consultar las facturas.');
                }
                return;
            }
            mostrarAviso('');
            pintarFilas(datos.filas);
            pintarPagina(datos, datos.filas.length);
        }).catch(function (error) {
            if (pendiente) {
                return;
            }
            var texto = mensajeError(error) || 'No se pudo consultar las facturas.';
            mostrarAviso(texto, true);
            if (!cuerpo.querySelector('.numero')) {
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

    function dato(etiqueta, valor) {
        if (valor == null || String(valor).trim() === '') {
            return null;
        }
        var fila = elemento('p', 'dato');
        fila.appendChild(elemento('span', null, etiqueta));
        fila.appendChild(elemento('strong', null, String(valor)));
        return fila;
    }

    function bloque(titulo, pares) {
        var seccion = elemento('section', 'bloque');
        var tiene = false;
        seccion.appendChild(elemento('h3', null, titulo));
        pares.forEach(function (par) {
            var fila = dato(par[0], par[1]);
            if (fila) {
                tiene = true;
                seccion.appendChild(fila);
            }
        });
        return tiene ? seccion : null;
    }

    function pintarDetalle(datos) {
        var factura = datos.factura || {};
        var emisor = datos.emisor || {};
        var nombreEmisor = ((emisor.nombres || '') + ' ' + (emisor.apellidos || '')).trim();
        var punto = factura.codigo_punto_emision || '';
        if (factura.descripcion_punto_emision) {
            punto = punto ? punto + ' · ' + factura.descripcion_punto_emision : factura.descripcion_punto_emision;
        }
        document.getElementById('visor-titulo').textContent = factura.numero_comprobante || 'Detalle de factura';
        document.getElementById('visor-fecha').textContent = fechaVisible(factura.fecha_emision);
        var badge = document.getElementById('visor-estado');
        badge.hidden = !factura.estado;
        badge.className = 'badge' + (factura.estado === 'EMITIDA' ? ' badge--emitida' : factura.estado === 'ANULADA' ? ' badge--anulada' : '');
        badge.textContent = factura.estado || '';
        limpiarNodo(visorCuerpo);

        var cabecera = elemento('div', 'visor__bloques');
        [
            bloque('Emisión', [
                ['Emisor', nombreEmisor],
                ['Punto de emisión', punto],
                ['Observación', factura.observacion]
            ]),
            bloque('Cliente', [
                ['Tipo', tipoIdentificacion(factura.tipo_identificacion_cliente)],
                ['Identificación', factura.identificacion_cliente],
                ['Razón social', factura.razon_social_cliente],
                ['Dirección', factura.direccion_cliente]
            ]),
            bloque('Empresa y sucursal', [
                ['Nombre comercial', factura.nombre_comercial_empresa],
                ['Razón social', factura.razon_social_empresa],
                ['Sucursal', factura.nombre_sucursal],
                ['Establecimiento', factura.codigo_establecimiento],
                ['Dirección', factura.direccion_sucursal]
            ])
        ].forEach(function (seccion) {
            if (seccion) {
                cabecera.appendChild(seccion);
            }
        });
        visorCuerpo.appendChild(cabecera);

        var articulos = elemento('section', 'visor__seccion');
        articulos.appendChild(elemento('h3', null, 'Detalle de artículos'));
        var marcoLineas = elemento('div', 'listado__marco');
        var tabla = elemento('table', 'lineas');
        var thead = document.createElement('thead');
        var encabezado = document.createElement('tr');
        ['Código', 'Producto', 'Cantidad', 'P. unitario', 'Impuesto', 'Subtotal', 'Total'].forEach(function (titulo) {
            encabezado.appendChild(elemento('th', null, titulo));
        });
        thead.appendChild(encabezado);
        var tbody = document.createElement('tbody');
        var lineas = Array.isArray(datos.lineas) ? datos.lineas : [];
        if (!lineas.length) {
            var vacia = document.createElement('tr');
            var celda = elemento('td', null, 'Sin artículos en el comprobante.');
            celda.colSpan = 7;
            vacia.appendChild(celda);
            tbody.appendChild(vacia);
        } else {
            lineas.forEach(function (linea) {
                var tr = document.createElement('tr');
                var impuesto = linea.nombre_impuesto || '';
                var tasa = porcentaje(linea.porcentaje_impuesto);
                if (tasa && impuesto.indexOf('%') === -1) {
                    impuesto = impuesto ? impuesto + ' ' + tasa : tasa;
                }
                if (linea.valor_impuesto) {
                    impuesto = impuesto ? impuesto + ' · ' + dinero(linea.valor_impuesto) : dinero(linea.valor_impuesto);
                }
                tr.appendChild(elemento('td', null, linea.codigo_producto || '—'));
                tr.appendChild(elemento('td', null, linea.nombre_producto || '—'));
                tr.appendChild(elemento('td', null, String(linea.cantidad)));
                tr.appendChild(elemento('td', 'dinero', dinero(linea.precio_unitario)));
                tr.appendChild(elemento('td', null, impuesto || '—'));
                tr.appendChild(elemento('td', 'dinero', dinero(linea.subtotal)));
                tr.appendChild(elemento('td', 'dinero', dinero(linea.total_linea)));
                tbody.appendChild(tr);
            });
        }
        tabla.appendChild(thead);
        tabla.appendChild(tbody);
        marcoLineas.appendChild(tabla);
        articulos.appendChild(marcoLineas);
        visorCuerpo.appendChild(articulos);

        var pagos = elemento('section', 'visor__seccion');
        pagos.appendChild(elemento('h3', null, 'Pagos'));
        var listaPagos = Array.isArray(datos.pagos) ? datos.pagos : [];
        if (!listaPagos.length) {
            pagos.appendChild(elemento('p', 'consulta__estado', 'Sin pagos registrados.'));
        } else {
            var tablaPagos = elemento('table', 'pagos');
            var cabezaPagos = document.createElement('tr');
            ['Método', 'Monto', 'Referencia'].forEach(function (titulo) {
                cabezaPagos.appendChild(elemento('th', null, titulo));
            });
            var theadPagos = document.createElement('thead');
            theadPagos.appendChild(cabezaPagos);
            var cuerpoPagos = document.createElement('tbody');
            listaPagos.forEach(function (pago) {
                var tr = document.createElement('tr');
                tr.appendChild(elemento('td', null, pago.nombre || '—'));
                tr.appendChild(elemento('td', 'dinero', dinero(pago.monto)));
                tr.appendChild(elemento('td', null, pago.referencia || '—'));
                cuerpoPagos.appendChild(tr);
            });
            tablaPagos.appendChild(theadPagos);
            tablaPagos.appendChild(cuerpoPagos);
            pagos.appendChild(tablaPagos);
        }
        visorCuerpo.appendChild(pagos);

        var resumenFactura = elemento('section', 'resumen');
        var lista = document.createElement('dl');
        [
            ['Subtotal', factura.subtotal],
            ['Descuento', factura.descuento],
            ['Impuesto', factura.valor_impuesto]
        ].forEach(function (par) {
            var fila = document.createElement('div');
            fila.appendChild(elemento('dt', null, par[0]));
            fila.appendChild(elemento('dd', null, dinero(par[1])));
            lista.appendChild(fila);
        });
        var total = elemento('div', 'total');
        total.appendChild(elemento('dt', null, 'Total'));
        total.appendChild(elemento('dd', null, ''));
        total.lastChild.appendChild(elemento('strong', null, dinero(factura.total)));
        lista.appendChild(total);
        resumenFactura.appendChild(lista);
        visorCuerpo.appendChild(resumenFactura);
    }

    function abrirDetalle(idFactura) {
        if (abriendo || enCurso || !idFactura) {
            return;
        }
        abriendo = true;
        visorAviso.hidden = false;
        visorAviso.textContent = 'Cargando factura...';
        visorAviso.classList.remove('consulta__estado--error');
        limpiarNodo(visorCuerpo);
        document.getElementById('visor-titulo').textContent = 'Detalle de factura';
        document.getElementById('visor-fecha').textContent = '';
        document.getElementById('visor-estado').hidden = true;
        if (!visor.open) {
            visor.showModal();
        }
        axios.post(controlador, {
            op: 'obtenerDetalleFacturaEmitida',
            id_factura: Number(idFactura)
        }, { timeout: 25000 }).then(function (respuesta) {
            var datos = respuesta.data || {};
            if (datos.exito !== true || !datos.factura) {
                visorAviso.hidden = false;
                visorAviso.textContent = datos.mensaje || 'No se pudo abrir la factura.';
                visorAviso.classList.add('consulta__estado--error');
                return;
            }
            visorAviso.hidden = true;
            pintarDetalle(datos);
        }).catch(function (error) {
            visorAviso.hidden = false;
            visorAviso.textContent = mensajeError(error) || 'No se pudo abrir la factura.';
            visorAviso.classList.add('consulta__estado--error');
        }).finally(function () {
            abriendo = false;
        });
    }

    busqueda.addEventListener('input', function () {
        window.clearTimeout(esperaBusqueda);
        esperaBusqueda = window.setTimeout(function () {
            solicitar(true);
        }, 320);
    });
    desde.addEventListener('change', function () {
        solicitar(true);
    });
    hasta.addEventListener('change', function () {
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
        desde.value = '';
        hasta.value = '';
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
    document.getElementById('cerrar-detalle').addEventListener('click', function () {
        visor.close();
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
