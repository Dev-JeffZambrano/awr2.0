(function () {
    var boton = document.getElementById('cerrar-sesion');
    var mensaje = document.getElementById('mensaje-sesion');
    var carga = document.getElementById('carga-monitoreo');
    var aviso = document.getElementById('aviso-monitoreo');
    var panel = document.getElementById('panel-monitoreo');
    var btnPendientes = document.getElementById('btn-ver-pendientes');
    var modalPendientes = document.getElementById('modal-pendientes');
    var cerrarModalPendientes = document.getElementById('cerrar-modal-pendientes');
    var modalLista = document.getElementById('modal-pendientes-lista');
    var modalDetalle = document.getElementById('modal-pendientes-detalle');
    var modalProductos = document.getElementById('modal-pendientes-productos');
    var modalVacio = document.getElementById('modal-pendientes-vacio');
    var modalAviso = document.getElementById('modal-pendientes-aviso');
    var modalCarga = document.getElementById('modal-pendientes-carga');
    var modalNota = document.getElementById('modal-pendientes-nota');
    var modalAcciones = document.getElementById('modal-pendientes-acciones');
    var checkTodas = document.getElementById('check-seleccionar-todas');
    var btnProcesarSeleccionadas = document.getElementById('btn-procesar-seleccionadas');
    var enCurso = false;
    var detalleEnCurso = false;
    var procesoEnCurso = false;
    var intervaloMs = 5000;
    var operacionesPendientes = [];

    function escribir(id, valor) {
        var nodo = document.getElementById(id);

        if (nodo) {
            nodo.textContent = valor;
        }
    }

    function texto(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return 'No disponible';
        }

        return String(valor);
    }

    function entero(valor) {
        if (typeof valor === 'number' && isFinite(valor) && valor >= 0) {
            return Math.round(valor);
        }

        if (typeof valor === 'string' && /^\d+$/.test(valor)) {
            return Number(valor);
        }

        return null;
    }

    function megabytes(bytes) {
        var cantidad = entero(bytes);

        if (cantidad === null) {
            return 'No disponible';
        }

        return numero(cantidad / 1048576) + ' MB';
    }

    function numero(valor) {
        var textoNumero = valor.toFixed(1).replace('.', ',');

        if (textoNumero.slice(-2) === ',0') {
            return textoNumero.slice(0, -2);
        }

        return textoNumero;
    }

    function milisegundos(valor) {
        var cantidad = entero(valor);

        if (cantidad === null) {
            return 'No disponible';
        }

        return cantidad + ' ms';
    }

    function milisegundosDecimales(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return 'No disponible';
        }

        if (typeof valor === 'string') {
            if (!/^\d+(?:\.\d+)?$/.test(valor.trim())) {
                return 'No disponible';
            }

            valor = Number(valor);
        }

        if (typeof valor !== 'number' || !isFinite(valor) || valor < 0) {
            return 'No disponible';
        }

        var textoNumero = valor.toFixed(2).replace('.', ',');
        textoNumero = textoNumero.replace(/,00$/, '').replace(/(\,\d)0$/, '$1');

        return textoNumero + ' ms';
    }

    function hora(marca) {
        var coincidencia = typeof marca === 'string' ? marca.match(/T(\d{2}:\d{2}:\d{2})/) : null;

        return coincidencia ? coincidencia[1] : '--:--:--';
    }

    function claseEstado(valor) {
        if (valor === 'Operativo' || valor === 'Conectado' || valor === 'Activo') {
            return 'estado estado--ok';
        }

        if (valor === 'Inactivo') {
            return 'estado estado--aviso';
        }

        if (valor === 'Degradado') {
            return 'estado estado--aviso';
        }

        if (valor === 'No disponible') {
            return 'estado estado--critico';
        }

        return 'estado estado--neutro';
    }

    function pintarEstado(id, valor) {
        var nodo = document.getElementById(id);
        var etiqueta = texto(valor);

        if (!nodo) {
            return;
        }

        nodo.textContent = etiqueta;
        nodo.className = claseEstado(etiqueta);
    }

    function versionConPrefijo(prefijo, valor) {
        var limpio = texto(valor);

        if (limpio === 'No disponible') {
            return limpio;
        }

        if (limpio.indexOf(prefijo) === 0) {
            return limpio;
        }

        return prefijo + ' ' + limpio;
    }

    function enabled(valor) {
        if (valor === true || valor === 1 || valor === '1' || valor === 'true') {
            return 'Sí';
        }

        if (valor === false || valor === 0 || valor === '0' || valor === 'false') {
            return 'No';
        }

        return 'No disponible';
    }

    function latenciaConfigurada(valor) {
        var cantidad = entero(valor);

        if (cantidad === null) {
            return 'No disponible';
        }

        if (cantidad === 0) {
            return 'Sin latencia artificial';
        }

        return cantidad + ' ms';
    }

    function pintar(datos) {
        var aplicacion = datos.aplicacion || {};
        var postgresql = datos.postgresql || {};
        var toxiproxy = datos.toxiproxy || {};
        var redpanda = datos.redpanda || {};
        var clickhouse = datos.clickhouse || {};
        var latencias = datos.latencias || {};
        var general = document.getElementById('estado-general');
        var condicion = document.getElementById('impacto-condicion');
        var notaArtificial = document.getElementById('impacto-artificial-nota');
        var estadoGeneral = texto(datos.estado_general);
        var latencia = entero(latencias.latencia_artificial_ms);
        var claseGeneral = 'general';

        if (estadoGeneral === 'Operativo') {
            claseGeneral += ' general--ok';
        } else if (estadoGeneral === 'Degradado') {
            claseGeneral += ' general--aviso';
        } else if (estadoGeneral === 'No disponible') {
            claseGeneral += ' general--critico';
        }

        if (general) {
            general.className = claseGeneral;
        }

        escribir('general-valor', estadoGeneral);
        escribir('general-hora', hora(datos.timestamp_consulta));

        pintarEstado('php-estado', aplicacion.estado);
        escribir('php-version', versionConPrefijo('PHP', aplicacion.version_php));
        escribir('php-memoria', megabytes(aplicacion.memoria_usada_peticion));

        pintarEstado('pg-estado', postgresql.estado);
        escribir('pg-version', versionConPrefijo('PostgreSQL', postgresql.version));
        escribir('pg-base', texto(postgresql.base));
        escribir('pg-tablas', entero(postgresql.tablas) === null ? 'No disponible' : entero(postgresql.tablas) + ' tablas');
        escribir('pg-tamano', megabytes(postgresql.tamano_bytes));
        escribir('pg-conexiones', entero(postgresql.conexiones) === null ? 'No disponible' : entero(postgresql.conexiones) + ' conexiones');
        escribir('pg-respuesta', milisegundos(postgresql.tiempo_respuesta_ms));

        pintarEstado('toxi-estado', toxiproxy.estado);
        escribir('toxi-proxy', texto(toxiproxy.proxy));
        escribir('toxi-enabled', enabled(toxiproxy.enabled));
        escribir('toxi-latencia', latenciaConfigurada(toxiproxy.latencia_configurada_ms));

        pintarEstado('redpanda-estado', redpanda.estado);
        escribir('redpanda-broker', texto(redpanda.broker));
        escribir('redpanda-kafka', entero(redpanda.puerto_kafka) === null ? 'No disponible' : String(entero(redpanda.puerto_kafka)));
        escribir('redpanda-admin', entero(redpanda.puerto_admin) === null ? 'No disponible' : String(entero(redpanda.puerto_admin)));

        pintarEstado('clickhouse-estado', clickhouse.estado);
        escribir('clickhouse-version', versionConPrefijo('ClickHouse', clickhouse.version));
        escribir('clickhouse-base', texto(clickhouse.base));
        escribir('clickhouse-tablas', entero(clickhouse.tablas) === null ? 'No disponible' : entero(clickhouse.tablas) + ' tablas');
        escribir('clickhouse-respuesta', milisegundos(clickhouse.tiempo_respuesta_ms));

        escribir('impacto-red', milisegundosDecimales(latencias.latencia_red_ms));
        escribir('impacto-artificial', latencia === null ? 'No disponible' : latencia + ' ms');
        escribir('impacto-respuesta', milisegundos(latencias.tiempo_respuesta_postgresql_ms));

        pintarResiliencia(datos.resiliencia || {});

        if (notaArtificial) {
            notaArtificial.hidden = latencia !== 0;
        }

        if (!condicion) {
            return;
        }

        if (latencia !== null && latencia > 0) {
            condicion.hidden = false;
            condicion.textContent = 'Degradación simulada activa';
            condicion.className = 'condicion condicion--activa';
            return;
        }

        condicion.hidden = true;
        condicion.textContent = '';
    }

    function pintarResiliencia(resiliencia) {
        var disponible = resiliencia.disponible === true;
        var pendientes = entero(resiliencia.facturas_pendientes);

        if (!disponible || pendientes === null) {
            escribir('resiliencia-pendientes', 'No disponible');
            escribir('resiliencia-nota', 'No disponible');
            pintarEstado('resiliencia-redpanda', resiliencia.redpanda_estado || 'No disponible');
            pintarEstado('resiliencia-consumidor', resiliencia.consumidor_estado || 'No disponible');
            escribir('resiliencia-consumidor-detalle', 'Grupo de reproceso');
            return;
        }

        escribir('resiliencia-pendientes', String(pendientes));
        escribir(
            'resiliencia-nota',
            pendientes === 0
                ? 'Sin operaciones pendientes'
                : (typeof resiliencia.nota === 'string' ? resiliencia.nota : 'Operaciones esperando reproceso')
        );
        pintarEstado('resiliencia-redpanda', resiliencia.redpanda_estado || 'Operativo');
        pintarEstado('resiliencia-consumidor', resiliencia.consumidor_estado || 'No disponible');
        escribir(
            'resiliencia-consumidor-detalle',
            entero(resiliencia.consumidor_miembros) === null
                ? 'Grupo de reproceso'
                : (entero(resiliencia.consumidor_miembros) === 1
                    ? '1 miembro activo'
                    : entero(resiliencia.consumidor_miembros) + ' miembros activos')
        );
    }

    function ocultarNodosModal() {
        if (modalVacio) {
            modalVacio.hidden = true;
        }
        if (modalAviso) {
            modalAviso.hidden = true;
        }
        if (modalCarga) {
            modalCarga.hidden = true;
        }
        if (modalAcciones) {
            modalAcciones.hidden = true;
        }
        if (checkTodas) {
            checkTodas.checked = false;
        }
        if (modalLista) {
            modalLista.hidden = true;
            modalLista.innerHTML = '';
        }
        if (modalDetalle) {
            modalDetalle.hidden = true;
        }
        if (modalProductos) {
            modalProductos.innerHTML = '';
        }
    }

    function fechaCorta(valor) {
        if (typeof valor !== 'string' || valor === '') {
            return 'No disponible';
        }

        var fecha = new Date(valor);

        if (isNaN(fecha.getTime())) {
            return valor;
        }

        var dia = String(fecha.getDate()).padStart(2, '0');
        var mes = String(fecha.getMonth() + 1).padStart(2, '0');
        var anio = fecha.getFullYear();
        var hora = String(fecha.getHours()).padStart(2, '0');
        var minuto = String(fecha.getMinutes()).padStart(2, '0');

        return dia + '/' + mes + '/' + anio + ' ' + hora + ':' + minuto;
    }

    function textoProductos(cantidad) {
        var n = entero(cantidad);

        if (n === null) {
            return 'No disponible';
        }

        return n === 1 ? '1 producto' : n + ' productos';
    }

    function idsSeleccionados() {
        if (!modalLista) {
            return [];
        }

        var checks = modalLista.querySelectorAll('input[data-id-operacion]:checked');
        var ids = [];

        Array.prototype.forEach.call(checks, function (nodo) {
            var id = nodo.getAttribute('data-id-operacion');

            if (id) {
                ids.push(id);
            }
        });

        return ids;
    }

    function sincronizarCheckTodas() {
        if (!checkTodas || !modalLista) {
            return;
        }

        var checks = modalLista.querySelectorAll('input[data-id-operacion]');
        var marcados = modalLista.querySelectorAll('input[data-id-operacion]:checked');

        checkTodas.checked = checks.length > 0 && checks.length === marcados.length;
    }

    function setProcesandoUi(activo) {
        procesoEnCurso = activo;

        if (btnProcesarSeleccionadas) {
            btnProcesarSeleccionadas.disabled = activo;
        }

        if (checkTodas) {
            checkTodas.disabled = activo;
        }

        if (!modalLista) {
            return;
        }

        var controles = modalLista.querySelectorAll(
            'input[data-id-operacion], button[data-procesar], button[data-descartar]'
        );

        Array.prototype.forEach.call(controles, function (nodo) {
            nodo.disabled = activo;
        });
    }

    function pintarDetalleProductos(operacion) {
        if (!modalDetalle || !modalProductos) {
            return;
        }

        var productos = Array.isArray(operacion.productos) ? operacion.productos : [];
        modalProductos.innerHTML = '';

        if (productos.length === 0) {
            modalDetalle.hidden = true;
            return;
        }

        productos.forEach(function (producto) {
            var item = document.createElement('li');
            var nombre = producto.nombre_producto ? ' · ' + producto.nombre_producto : '';
            item.textContent = 'id_producto ' + texto(producto.id_producto)
                + ' · cantidad ' + texto(producto.cantidad)
                + nombre;
            modalProductos.appendChild(item);
        });

        modalDetalle.hidden = false;
    }

    function pintarListaPendientes(operaciones) {
        if (!modalLista) {
            return;
        }

        modalLista.innerHTML = '';
        operacionesPendientes = operaciones;

        if (operaciones.length === 0) {
            modalLista.hidden = true;

            if (modalAcciones) {
                modalAcciones.hidden = true;
            }

            return;
        }

        var tabla = document.createElement('table');
        var thead = document.createElement('thead');
        var tbody = document.createElement('tbody');
        var filaCabeza = document.createElement('tr');
        var encabezados = ['', 'ID operación', 'Fecha', 'Cliente', 'Sucursal / Punto', 'Productos', 'Estado', 'Acción'];

        encabezados.forEach(function (titulo) {
            var th = document.createElement('th');
            th.textContent = titulo;
            filaCabeza.appendChild(th);
        });

        thead.appendChild(filaCabeza);
        tabla.appendChild(thead);

        operaciones.forEach(function (operacion, indice) {
            var fila = document.createElement('tr');
            var cliente = operacion.cliente
                ? operacion.cliente
                : (operacion.id_cliente !== null && operacion.id_cliente !== undefined
                    ? String(operacion.id_cliente)
                    : 'No disponible');
            var punto = operacion.punto_emision
                ? operacion.punto_emision
                : (operacion.id_punto_emision !== null && operacion.id_punto_emision !== undefined
                    ? String(operacion.id_punto_emision)
                    : 'No disponible');
            var sucursal = operacion.sucursal ? String(operacion.sucursal) : '';
            var puntoCompleto = sucursal !== '' ? (sucursal + ' · ' + punto) : punto;
            var idOperacion = texto(operacion.id_operacion);

            var tdCheck = document.createElement('td');
            var check = document.createElement('input');
            check.type = 'checkbox';
            check.setAttribute('data-id-operacion', idOperacion);
            check.addEventListener('change', sincronizarCheckTodas);
            tdCheck.appendChild(check);

            var tdId = document.createElement('td');
            var enlaceId = document.createElement('button');
            enlaceId.type = 'button';
            enlaceId.className = 'resiliencia-modal__id';
            enlaceId.textContent = idOperacion;
            enlaceId.addEventListener('click', function () {
                pintarDetalleProductos(operacionesPendientes[indice] || {});
            });
            tdId.appendChild(enlaceId);

            var tdFecha = document.createElement('td');
            tdFecha.textContent = fechaCorta(operacion.fecha_solicitud);

            var tdCliente = document.createElement('td');
            tdCliente.textContent = cliente;

            var tdPunto = document.createElement('td');
            tdPunto.textContent = puntoCompleto;

            var tdProductos = document.createElement('td');
            tdProductos.textContent = textoProductos(operacion.cantidad_productos);

            var tdEstado = document.createElement('td');
            var spanEstado = document.createElement('span');
            spanEstado.className = 'resiliencia-modal__estado';
            spanEstado.setAttribute('data-estado-op', idOperacion);
            spanEstado.textContent = operacion.estado || 'Pendiente';
            tdEstado.appendChild(spanEstado);

            var tdAccion = document.createElement('td');
            var acciones = document.createElement('div');
            acciones.className = 'resiliencia-modal__acciones-fila';

            var btnProcesar = document.createElement('button');
            btnProcesar.type = 'button';
            btnProcesar.className = 'resiliencia-modal__procesar';
            btnProcesar.setAttribute('data-procesar', idOperacion);
            btnProcesar.textContent = 'Procesar';
            btnProcesar.addEventListener('click', function () {
                procesarUna(idOperacion);
            });

            var btnDescartar = document.createElement('button');
            btnDescartar.type = 'button';
            btnDescartar.className = 'resiliencia-modal__descartar';
            btnDescartar.setAttribute('data-descartar', idOperacion);
            btnDescartar.textContent = 'Descartar';
            btnDescartar.addEventListener('click', function () {
                descartarUna(idOperacion);
            });

            acciones.appendChild(btnProcesar);
            acciones.appendChild(btnDescartar);
            tdAccion.appendChild(acciones);

            fila.appendChild(tdCheck);
            fila.appendChild(tdId);
            fila.appendChild(tdFecha);
            fila.appendChild(tdCliente);
            fila.appendChild(tdPunto);
            fila.appendChild(tdProductos);
            fila.appendChild(tdEstado);
            fila.appendChild(tdAccion);
            tbody.appendChild(fila);
        });

        tabla.appendChild(tbody);
        modalLista.appendChild(tabla);
        modalLista.hidden = false;

        if (modalAcciones) {
            modalAcciones.hidden = false;
        }

        sincronizarCheckTodas();
    }

    function aplicarResultadoEnFila(resultado) {
        if (!resultado || typeof resultado.id_operacion !== 'string' || !modalLista) {
            return;
        }

        var span = modalLista.querySelector('[data-estado-op="' + resultado.id_operacion + '"]');

        if (!span) {
            return;
        }

        span.textContent = resultado.estado || 'Error';
        span.classList.remove(
            'resiliencia-modal__estado--ok',
            'resiliencia-modal__estado--aviso',
            'resiliencia-modal__estado--error'
        );

        if (resultado.estado === 'Procesada' || resultado.estado === 'Descartada') {
            span.classList.add('resiliencia-modal__estado--ok');
        } else if (resultado.estado === 'Sigue pendiente') {
            span.classList.add('resiliencia-modal__estado--aviso');
        } else {
            span.classList.add('resiliencia-modal__estado--error');
        }

        if (typeof resultado.mensaje === 'string' && resultado.mensaje !== '' && modalAviso) {
            modalAviso.hidden = false;
            modalAviso.textContent = resultado.mensaje;
        }
    }

    function descartarUna(idOperacion) {
        if (procesoEnCurso || typeof axios === 'undefined') {
            return;
        }

        if (!window.confirm('¿Descartar esta operación de la cola? No se emitirá factura.')) {
            return;
        }

        setProcesandoUi(true);

        axios.post('app/Controllers/MonitoreoAdministradorController.php', {
            op: 'descartarFacturaPendiente',
            id_operacion: idOperacion
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true || !datos.resultado) {
                if (modalAviso) {
                    modalAviso.hidden = false;
                    modalAviso.textContent = 'No disponible';
                }

                return;
            }

            aplicarResultadoEnFila(datos.resultado);
            refrescarContadorResiliencia(datos.resiliencia);
        }).catch(function () {
            if (modalAviso) {
                modalAviso.hidden = false;
                modalAviso.textContent = 'No disponible';
            }
        }).finally(function () {
            setProcesandoUi(false);
            cargarListadoPendientes(false);
            consultar();
        });
    }

    function refrescarContadorResiliencia(bloque) {
        if (!bloque || typeof bloque !== 'object') {
            consultar();
            return;
        }

        pintarResiliencia({
            disponible: bloque.disponible === true,
            facturas_pendientes: bloque.facturas_pendientes,
            nota: bloque.nota,
            redpanda_estado: bloque.disponible === true ? 'Operativo' : 'No disponible',
            consumidor_estado: document.getElementById('resiliencia-consumidor')
                ? document.getElementById('resiliencia-consumidor').textContent
                : 'No disponible'
        });
    }

    function cargarListadoPendientes(mostrarModal) {
        if (detalleEnCurso || procesoEnCurso) {
            return Promise.resolve();
        }

        if (typeof axios === 'undefined') {
            ocultarNodosModal();

            if (modalAviso) {
                modalAviso.hidden = false;
                modalAviso.textContent = 'No disponible';
            }

            if (mostrarModal && modalPendientes && typeof modalPendientes.showModal === 'function') {
                modalPendientes.showModal();
            }

            return Promise.resolve();
        }

        detalleEnCurso = true;

        if (btnPendientes) {
            btnPendientes.disabled = true;
        }

        ocultarNodosModal();

        if (modalCarga) {
            modalCarga.hidden = false;
        }

        if (modalNota) {
            modalNota.textContent = 'Consultando operaciones pendientes…';
        }

        if (mostrarModal && modalPendientes && typeof modalPendientes.showModal === 'function') {
            modalPendientes.showModal();
        }

        return axios.post('app/Controllers/MonitoreoAdministradorController.php', {
            op: 'obtenerFacturasPendientes'
        }).then(function (respuesta) {
            var datos = respuesta.data || {};
            ocultarNodosModal();

            if (datos.exito !== true || datos.disponible !== true) {
                if (modalAviso) {
                    modalAviso.hidden = false;
                    modalAviso.textContent = 'No disponible';
                }

                if (modalNota) {
                    modalNota.textContent = 'No disponible';
                }

                return;
            }

            var operaciones = Array.isArray(datos.operaciones) ? datos.operaciones : [];

            if (modalNota) {
                modalNota.textContent = typeof datos.nota === 'string' ? datos.nota : 'Operaciones esperando reproceso';
            }

            if (operaciones.length === 0) {
                if (modalVacio) {
                    modalVacio.hidden = false;
                }

                return;
            }

            pintarListaPendientes(operaciones);
        }).catch(function () {
            ocultarNodosModal();

            if (modalAviso) {
                modalAviso.hidden = false;
                modalAviso.textContent = 'No disponible';
            }

            if (modalNota) {
                modalNota.textContent = 'No disponible';
            }
        }).finally(function () {
            detalleEnCurso = false;

            if (btnPendientes) {
                btnPendientes.disabled = false;
            }
        });
    }

    function abrirModalPendientes() {
        if (!modalPendientes || typeof modalPendientes.showModal !== 'function') {
            return;
        }

        cargarListadoPendientes(true);
    }

    function procesarUna(idOperacion) {
        if (procesoEnCurso || typeof axios === 'undefined') {
            return;
        }

        if (!window.confirm('¿Deseas procesar esta operación?')) {
            return;
        }

        setProcesandoUi(true);

        axios.post('app/Controllers/MonitoreoAdministradorController.php', {
            op: 'procesarFacturaPendiente',
            id_operacion: idOperacion
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true || !datos.resultado) {
                if (modalAviso) {
                    modalAviso.hidden = false;
                    modalAviso.textContent = 'No disponible';
                }

                return;
            }

            aplicarResultadoEnFila(datos.resultado);
            refrescarContadorResiliencia(datos.resiliencia);
        }).catch(function () {
            if (modalAviso) {
                modalAviso.hidden = false;
                modalAviso.textContent = 'No disponible';
            }
        }).finally(function () {
            setProcesandoUi(false);
            cargarListadoPendientes(false);
            consultar();
        });
    }

    function procesarSeleccionadas() {
        var ids = idsSeleccionados();

        if (ids.length === 0 || procesoEnCurso || typeof axios === 'undefined') {
            return;
        }

        if (!window.confirm('¿Deseas procesar ' + ids.length + ' operaciones pendientes?')) {
            return;
        }

        setProcesandoUi(true);

        axios.post('app/Controllers/MonitoreoAdministradorController.php', {
            op: 'procesarFacturasPendientes',
            id_operacion: ids
        }).then(function (respuesta) {
            var datos = respuesta.data || {};
            var resultados = Array.isArray(datos.resultados) ? datos.resultados : [];

            if (datos.exito !== true) {
                if (modalAviso) {
                    modalAviso.hidden = false;
                    modalAviso.textContent = 'No disponible';
                }

                return;
            }

            resultados.forEach(aplicarResultadoEnFila);
            refrescarContadorResiliencia(datos.resiliencia);
        }).catch(function () {
            if (modalAviso) {
                modalAviso.hidden = false;
                modalAviso.textContent = 'No disponible';
            }
        }).finally(function () {
            setProcesandoUi(false);
            cargarListadoPendientes(false);
            consultar();
        });
    }

    function mostrarAviso() {
        if (carga) {
            carga.hidden = true;
        }

        if (aviso) {
            aviso.hidden = false;
        }
    }

    function consultar() {
        if (enCurso) {
            return;
        }

        if (typeof axios === 'undefined') {
            mostrarAviso();
            return;
        }

        enCurso = true;

        axios.post('app/Controllers/MonitoreoAdministradorController.php', {
            op: 'obtenerEstadoMonitoreo'
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true) {
                mostrarAviso();
                return;
            }

            pintar(datos);

            if (aviso) {
                aviso.hidden = true;
            }

            if (carga) {
                carga.hidden = true;
            }

            if (panel) {
                panel.hidden = false;
            }
        }).catch(function () {
            mostrarAviso();
        }).finally(function () {
            enCurso = false;
        });
    }

    function mostrarMensaje(textoMensaje) {
        if (!mensaje) {
            return;
        }

        mensaje.textContent = textoMensaje;
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

    consultar();
    window.setInterval(consultar, intervaloMs);

    if (btnPendientes) {
        btnPendientes.addEventListener('click', abrirModalPendientes);
    }

    if (cerrarModalPendientes && modalPendientes) {
        cerrarModalPendientes.addEventListener('click', function () {
            if (typeof modalPendientes.close === 'function') {
                modalPendientes.close();
            }
        });
    }

    if (checkTodas) {
        checkTodas.addEventListener('change', function () {
            if (!modalLista || procesoEnCurso) {
                return;
            }

            var checks = modalLista.querySelectorAll('input[data-id-operacion]');

            Array.prototype.forEach.call(checks, function (nodo) {
                nodo.checked = checkTodas.checked;
            });
        });
    }

    if (btnProcesarSeleccionadas) {
        btnProcesarSeleccionadas.addEventListener('click', procesarSeleccionadas);
    }

    if (!boton) {
        return;
    }

    boton.addEventListener('click', function () {
        if (boton.disabled) {
            return;
        }

        if (mensaje) {
            mensaje.hidden = true;
        }

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
}());
