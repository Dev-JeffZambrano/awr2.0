(function () {
    var boton = document.getElementById('cerrar-sesion');
    var mensaje = document.getElementById('mensaje-sesion');
    var periodo = document.getElementById('filtro-periodo');
    var sucursal = document.getElementById('filtro-sucursal');
    var busqueda = document.getElementById('busqueda');
    var estadoCarga = document.getElementById('estado-carga');
    var indicadores = document.getElementById('indicadores');
    var cuerpo = document.getElementById('cuerpo-clientes');
    var resumenLista = document.getElementById('listado-resumen');
    var indicadorPagina = document.getElementById('pagina-indicador');
    var anterior = document.getElementById('pagina-anterior');
    var siguiente = document.getElementById('pagina-siguiente');
    var detalle = document.getElementById('detalle-cliente');
    var detalleTitulo = document.getElementById('detalle-titulo');
    var detalleCarga = document.getElementById('detalle-carga');
    var detalleError = document.getElementById('detalle-error');
    var detalleContenido = document.getElementById('detalle-contenido');
    var detalleDatos = document.getElementById('detalle-datos');
    var detalleFacturas = document.getElementById('detalle-facturas-cuerpo');
    var detalleSinCompras = document.getElementById('detalle-sin-compras');
    var detalleMarco = document.getElementById('detalle-marco');
    var comercial = document.querySelector('.comercial');
    var pagina = 1;
    var resumenListo = false;
    var tokenResumen = 0;
    var tokenLista = 0;
    var tokenDetalle = 0;
    var esperaBusqueda = null;
    var tipos = {
        CEDULA: 'Cédula',
        RUC: 'RUC',
        PASAPORTE: 'Pasaporte'
    };

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

        var signo = numero < 0 ? '-' : '';

        return signo + String(Math.abs(numero)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function dinero(valor) {
        var numero = Number(valor);

        if (!isFinite(numero)) {
            numero = 0;
        }

        var partes = Math.abs(numero).toFixed(2).split('.');
        var miles = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return (numero < 0 ? '-' : '') + '$ ' + miles + ',' + partes[1];
    }

    function porcentaje(valor) {
        var numero = Number(valor);

        if (!isFinite(numero)) {
            numero = 0;
        }

        var partes = Math.abs(numero).toFixed(2).split('.');
        var miles = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return (numero < 0 ? '-' : '') + miles + ',' + partes[1] + '%';
    }

    function fechaAmigable(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return 'Sin compras en el período';
        }

        var fecha = new Date(valor);

        if (isNaN(fecha.getTime())) {
            return String(valor);
        }

        return new Intl.DateTimeFormat('es-EC', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'America/Guayaquil'
        }).format(fecha);
    }

    function tipoVisible(codigo) {
        return tipos[codigo] || String(codigo || '');
    }

    function filtro() {
        var id = sucursal.value;

        return {
            periodo: periodo.value,
            id_sucursal: id === '' ? null : Number(id)
        };
    }

    function marcarEspera(activo) {
        estadoCarga.hidden = !activo;
        indicadores.setAttribute('aria-busy', activo ? 'true' : 'false');
        indicadores.querySelectorAll('.indicador').forEach(function (tarjeta) {
            tarjeta.classList.toggle('indicador--espera', activo && !resumenListo);
        });
    }

    function conValor(valor) {
        return typeof valor === 'string' && valor.trim() !== '';
    }

    function avisoTabla(texto) {
        limpiar(cuerpo);
        var fila = document.createElement('tr');
        var celda = document.createElement('td');
        celda.className = 'listado__aviso';
        celda.colSpan = 8;
        celda.textContent = texto;
        fila.appendChild(celda);
        cuerpo.appendChild(fila);
    }

    function pintarSucursales(lista) {
        var actual = sucursal.value;
        var opciones = Array.prototype.slice.call(sucursal.options);

        opciones.forEach(function (opcion, indice) {
            if (indice > 0) {
                sucursal.removeChild(opcion);
            }
        });

        (lista || []).forEach(function (item) {
            var opcion = document.createElement('option');
            opcion.value = String(item.id_sucursal);
            opcion.textContent = item.nombre;
            sucursal.appendChild(opcion);
        });

        if (actual !== '' && sucursal.querySelector('option[value="' + actual + '"]')) {
            sucursal.value = actual;
        }
    }

    function pintarResumen(datos) {
        var kpis = datos.kpis || {};
        var secundaria = datos.informacion_secundaria || {};
        var finalConsumidor = datos.consumidor_final || {};
        var top = kpis.cliente_mayor_facturacion;
        var nombre = document.getElementById('kpi-top-nombre');
        var total = document.getElementById('kpi-top-total');

        document.getElementById('kpi-compras').textContent = entero(kpis.clientes_con_compras);
        document.getElementById('kpi-recurrentes').textContent = entero(kpis.clientes_recurrentes);
        document.getElementById('kpi-promedio').textContent = dinero(kpis.venta_promedio_por_cliente);
        document.getElementById('final-facturas').textContent = entero(finalConsumidor.facturas_consumidor_final);
        document.getElementById('final-ventas').textContent = dinero(finalConsumidor.ventas_consumidor_final);
        document.getElementById('final-participacion').textContent = porcentaje(finalConsumidor.participacion_consumidor_final);
        document.getElementById('informacion-secundaria').textContent =
            entero(secundaria.clientes_identificados_registrados) + ' clientes identificados registrados · ' +
            entero(secundaria.clientes_sin_compras_periodo) + ' sin compras en el período';

        if (!top) {
            nombre.textContent = 'Sin datos en el período';
            total.textContent = '';
            return;
        }

        nombre.textContent = top.razon_social || 'Sin datos en el período';
        total.textContent = dinero(top.total_comprado);
    }

    function cargarResumen() {
        var token = ++tokenResumen;
        var cuerpo = filtro();

        marcarEspera(true);
        cuerpo.op = 'obtenerResumenClientes';

        if (typeof axios === 'undefined') {
            marcarEspera(false);
            mostrarMensaje('No se pudo conectar. Inténtalo de nuevo.');
            return;
        }

        axios.post('app/Controllers/ClientesCarteraController.php', cuerpo).then(function (respuesta) {
            if (token !== tokenResumen) {
                return;
            }

            var datos = respuesta.data || {};

            if (datos.exito !== true) {
                mostrarMensaje(datos.mensaje || 'No disponible');
                return;
            }

            mensaje.hidden = true;
            resumenListo = true;
            pintarSucursales(datos.sucursales);
            pintarResumen(datos);
        }).catch(function () {
            if (token === tokenResumen) {
                mostrarMensaje('No se pudo cargar el análisis.');
            }
        }).finally(function () {
            if (token === tokenResumen) {
                marcarEspera(false);
            }
        });
    }

    function pintarLista(datos) {
        var filas = datos.filas || [];
        var total = Number(datos.total_registros) || 0;
        var actual = Number(datos.pagina_actual) || 0;
        var paginas = Number(datos.total_paginas) || 0;

        resumenLista.textContent = 'Mostrando ' + entero(filas.length) + ' de ' + entero(total) + ' clientes';
        indicadorPagina.textContent = 'Página ' + entero(actual) + ' de ' + entero(paginas);
        anterior.disabled = actual <= 1;
        siguiente.disabled = paginas === 0 || actual >= paginas;
        limpiar(cuerpo);

        if (filas.length === 0) {
            avisoTabla('No se encontraron clientes con los filtros seleccionados.');
            return;
        }

        filas.forEach(function (fila) {
            var tr = document.createElement('tr');
            var cliente = document.createElement('td');
            var identificacion = document.createElement('td');
            var facturas = document.createElement('td');
            var comprado = document.createElement('td');
            var ticket = document.createElement('td');
            var ultima = document.createElement('td');
            var estado = document.createElement('td');
            var accion = document.createElement('td');
            var tipo = document.createElement('small');
            var insignia = document.createElement('span');
            var ver = document.createElement('button');

            cliente.textContent = fila.razon_social || '';
            identificacion.textContent = fila.identificacion || '';
            tipo.textContent = tipoVisible(fila.tipo_identificacion);
            identificacion.appendChild(tipo);
            facturas.textContent = entero(fila.facturas_periodo);
            comprado.textContent = dinero(fila.total_comprado);
            ticket.textContent = dinero(fila.ticket_promedio);
            ultima.textContent = fechaAmigable(fila.ultima_compra);
            insignia.className = 'estado ' + (fila.estado ? 'estado--activo' : 'estado--inactivo');
            insignia.textContent = fila.estado ? 'Activo' : 'Inactivo';
            estado.appendChild(insignia);
            ver.className = 'ver';
            ver.type = 'button';
            ver.textContent = 'Ver detalle';
            ver.addEventListener('click', function () {
                abrirDetalle(fila.id_cliente);
            });
            accion.appendChild(ver);

            tr.appendChild(cliente);
            tr.appendChild(identificacion);
            tr.appendChild(facturas);
            tr.appendChild(comprado);
            tr.appendChild(ticket);
            tr.appendChild(ultima);
            tr.appendChild(estado);
            tr.appendChild(accion);
            cuerpo.appendChild(tr);
        });
    }

    function cargarLista() {
        var token = ++tokenLista;
        var cuerpoPeticion = filtro();

        cuerpoPeticion.op = 'listarClientesComerciales';
        cuerpoPeticion.busqueda = busqueda.value.trim();
        cuerpoPeticion.pagina = pagina;
        anterior.disabled = true;
        siguiente.disabled = true;
        avisoTabla('Cargando clientes...');

        if (typeof axios === 'undefined') {
            avisoTabla('No se pudo cargar los clientes.');
            return;
        }

        axios.post('app/Controllers/ClientesCarteraController.php', cuerpoPeticion).then(function (respuesta) {
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
                avisoTabla('No se pudo cargar los clientes.');
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

    function prepararDetalle() {
        detalleCarga.hidden = false;
        detalleError.hidden = true;
        detalleContenido.hidden = true;
        detalleTitulo.textContent = 'Detalle del cliente';
        limpiar(detalleDatos);
        limpiar(detalleFacturas);
    }

    function cerrarDetalle() {
        if (comercial) {
            comercial.inert = false;
        }

        if (detalle.open) {
            detalle.close();
        }
    }

    function abrirDetalle(idCliente) {
        var token = ++tokenDetalle;
        var cuerpoPeticion = filtro();

        if (!idCliente) {
            return;
        }

        cuerpoPeticion.op = 'obtenerDetalleCliente';
        cuerpoPeticion.id_cliente = idCliente;
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

        axios.post('app/Controllers/ClientesCarteraController.php', cuerpoPeticion).then(function (respuesta) {
            if (token !== tokenDetalle || !detalle.open) {
                return;
            }

            var datos = respuesta.data || {};
            var cliente = datos.cliente || {};
            var resumen = datos.resumen || {};
            var facturas = datos.facturas || [];

            detalleCarga.hidden = true;

            if (datos.exito !== true) {
                detalleError.hidden = false;
                detalleError.textContent = datos.mensaje || 'No disponible';
                return;
            }

            detalleTitulo.textContent = cliente.razon_social || 'Detalle del cliente';
            detalleDatos.appendChild(dato('Tipo de identificación', tipoVisible(cliente.tipo_identificacion)));
            detalleDatos.appendChild(dato('Identificación', cliente.identificacion || ''));
            detalleDatos.appendChild(dato('Razón social', cliente.razon_social || ''));

            if (conValor(cliente.direccion)) {
                detalleDatos.appendChild(dato('Dirección', cliente.direccion));
            }

            if (conValor(cliente.telefono)) {
                detalleDatos.appendChild(dato('Teléfono', cliente.telefono));
            }

            if (conValor(cliente.correo)) {
                detalleDatos.appendChild(dato('Correo', cliente.correo));
            }

            detalleDatos.appendChild(dato('Estado', cliente.estado ? 'Activo' : 'Inactivo'));
            document.getElementById('detalle-facturas').textContent = entero(resumen.facturas_periodo);
            document.getElementById('detalle-total').textContent = dinero(resumen.total_comprado);
            document.getElementById('detalle-ticket').textContent = dinero(resumen.ticket_promedio);
            document.getElementById('detalle-ultima').textContent = fechaAmigable(resumen.ultima_compra);
            detalleSinCompras.hidden = facturas.length !== 0;
            detalleMarco.hidden = facturas.length === 0;

            facturas.forEach(function (factura) {
                var fila = document.createElement('tr');
                ['numero_comprobante', 'fecha_emision', 'sucursal', 'metodo_pago', 'total'].forEach(function (clave) {
                    var celda = document.createElement('td');
                    celda.textContent = clave === 'fecha_emision'
                        ? fechaAmigable(factura.fecha_emision)
                        : clave === 'total'
                            ? dinero(factura.total)
                            : (factura[clave] || '');
                    fila.appendChild(celda);
                });
                detalleFacturas.appendChild(fila);
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
        tokenDetalle += 1;
        cerrarDetalle();
        cargarResumen();
        cargarLista();
    }

    periodo.addEventListener('change', alCambiarFiltro);
    sucursal.addEventListener('change', alCambiarFiltro);
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
    busqueda.addEventListener('input', function () {
        window.clearTimeout(esperaBusqueda);
        esperaBusqueda = window.setTimeout(function () {
            pagina = 1;
            cargarLista();
        }, 350);
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
