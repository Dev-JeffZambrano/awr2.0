(function () {
    var controlador = 'app/Controllers/ClientesAdministradorController.php';
    var botonSesion = document.getElementById('cerrar-sesion');
    var mensajeSesion = document.getElementById('mensaje-sesion');
    var cuerpo = document.getElementById('cuerpo-clientes');
    var buscar = document.getElementById('buscar-cliente');
    var filtroTipo = document.getElementById('filtro-tipo');
    var filtroEstado = document.getElementById('filtro-estado');
    var formulario = document.getElementById('formulario-cliente');
    var ventanaCliente = document.getElementById('ventana-cliente');
    var ventanaConfirma = document.getElementById('ventana-confirma');
    var campoTipo = document.getElementById('campo-tipo');
    var campoIdentificacion = document.getElementById('campo-identificacion');
    var campoRazon = document.getElementById('campo-razon');
    var campoDireccion = document.getElementById('campo-direccion');
    var campoTelefono = document.getElementById('campo-telefono');
    var campoCorreo = document.getElementById('campo-correo');
    var guardar = document.getElementById('guardar-cliente');
    var pagina = 1;
    var paginas = 1;
    var modo = 'crear';
    var idEdicion = null;
    var timerBusqueda = null;
    var confirmarAccion = null;
    var enListado = false;
    var listadoPendiente = false;
    var tokenDetalle = 0;

    var mensajesClaros = {
        'Identificación inválida': true,
        'El cliente ya se encuentra registrado': true,
        'Consumidor Final es un registro protegido': true,
        'Cliente no disponible': true,
        'Razón social no válida.': true,
        'Dirección no válida.': true,
        'Teléfono no válido.': true,
        'Correo no válido.': true,
        'Tipo de identificación no válido.': true,
        'Operación inválida.': true,
        'Página no válida.': true,
        'Datos incompletos.': true,
        'No disponible': true
    };

    var editables = [campoRazon, campoDireccion, campoTelefono, campoCorreo];

    function ocultar(id, valor) {
        var nodo = document.getElementById(id);

        if (nodo) {
            nodo.hidden = valor;
        }
    }

    function texto(id, valor) {
        var nodo = document.getElementById(id);

        if (nodo) {
            nodo.textContent = valor;
        }
    }

    function elemento(etiqueta, clase, contenido) {
        var nodo = document.createElement(etiqueta);

        if (clase) {
            nodo.className = clase;
        }

        if (contenido !== undefined) {
            nodo.textContent = contenido;
        }

        return nodo;
    }

    function mensajeClaro(valor) {
        return typeof valor === 'string' && mensajesClaros[valor] === true ? valor : '';
    }

    function datosRespuesta(respuesta) {
        return respuesta && respuesta.data ? respuesta.data : {};
    }

    function post(cuerpoPeticion) {
        return axios.post(controlador, cuerpoPeticion, {
            validateStatus: function (codigo) {
                return codigo >= 200 && codigo < 500;
            }
        });
    }

    function opcional(valor) {
        var limpio = valor.trim();

        return limpio === '' ? null : limpio;
    }

    function guion(valor) {
        if (valor === null || valor === undefined || String(valor).trim() === '') {
            return '—';
        }

        return String(valor);
    }

    function miles(valor) {
        return String(valor).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function fechaLegible(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '—';
        }

        var textoFecha = String(valor);
        var completa = textoFecha.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2}:\d{2})/);

        if (completa) {
            return completa[3] + '/' + completa[2] + '/' + completa[1] + ' ' + completa[4];
        }

        var dia = textoFecha.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (dia) {
            return dia[3] + '/' + dia[2] + '/' + dia[1];
        }

        return textoFecha;
    }

    function celda(etiqueta, contenido) {
        var td = document.createElement('td');

        td.setAttribute('data-etiqueta', etiqueta);

        if (typeof contenido === 'string') {
            td.textContent = contenido;
        } else if (contenido) {
            td.appendChild(contenido);
        }

        return td;
    }

    function botonAccion(accion, id, rotulo) {
        var boton = elemento('button', 'accion', rotulo);

        boton.type = 'button';
        boton.setAttribute('data-accion', accion);
        boton.setAttribute('data-id', String(id));

        return boton;
    }

    function pintarKpis(datos) {
        var resumen = datos || {};

        texto('kpi-activos', String(resumen.activos ?? '—'));
        texto('kpi-inactivos', String(resumen.inactivos ?? '—'));
        texto('kpi-cedulas', String(resumen.cedulas ?? '—'));
        texto('kpi-ruc', String(resumen.ruc ?? '—'));
        texto('kpi-pasaportes', String(resumen.pasaportes ?? '—'));
    }

    function pintarPaginacion(total) {
        var cantidad = Number(total) || 0;
        var resumen = document.getElementById('resumen-listado');

        texto('pagina-texto', paginas === 0 ? 'Página 0 de 0' : 'Página ' + pagina + ' de ' + paginas);

        if (resumen) {
            resumen.textContent = miles(cantidad) + (cantidad === 1 ? ' registro' : ' registros');
        }

        ocultar('paginacion', paginas <= 1 && cantidad === 0);
        document.getElementById('pagina-anterior').disabled = pagina <= 1;
        document.getElementById('pagina-siguiente').disabled = paginas === 0 || pagina >= paginas;
        document.getElementById('pagina-anterior').hidden = paginas <= 1;
        document.getElementById('pagina-siguiente').hidden = paginas <= 1;
        document.getElementById('pagina-texto').hidden = paginas <= 1;
    }

    function pintarFila(fila) {
        var tr = document.createElement('tr');
        var nombre = elemento('span', 'cliente__nombre', guion(fila.razon_social));
        var tipo = elemento('span', 'marca marca--tipo', guion(fila.tipo_identificacion));
        var estado = document.createElement('div');
        var acciones = elemento('div', 'acciones');
        var protegido = fila.protegido === true;
        var activo = fila.estado === true;

        estado.appendChild(elemento('span', activo ? 'estado estado--ok' : 'estado estado--inactivo', activo ? 'Activo' : 'Inactivo'));

        if (protegido) {
            estado.appendChild(elemento('span', 'marca marca--protegido', 'Protegido'));
            acciones.appendChild(elemento('span', 'nota', 'Registro protegido'));
            acciones.appendChild(botonAccion('ver', fila.id_cliente, 'Ver'));
        } else {
            acciones.appendChild(botonAccion('ver', fila.id_cliente, 'Ver'));
            acciones.appendChild(botonAccion('editar', fila.id_cliente, 'Editar'));
            acciones.appendChild(botonAccion('estado', fila.id_cliente, activo ? 'Desactivar' : 'Activar'));
        }

        tr.appendChild(celda('Identificación', guion(fila.identificacion)));
        tr.appendChild(celda('Razón social', nombre));
        tr.appendChild(celda('Tipo', tipo));
        tr.appendChild(celda('Teléfono', guion(fila.telefono)));
        tr.appendChild(celda('Correo', guion(fila.correo)));
        tr.appendChild(celda('Estado', estado));
        tr.appendChild(celda('Acciones', acciones));

        return tr;
    }

    function pintarListado(datos) {
        var filas = Array.isArray(datos.filas) ? datos.filas : [];

        pagina = Number(datos.pagina_actual) || pagina;
        paginas = Number(datos.total_paginas) || 0;
        cuerpo.replaceChildren.apply(cuerpo, filas.map(pintarFila));
        ocultar('aviso-clientes', true);
        ocultar('vacio-clientes', filas.length !== 0);
        ocultar('carga-clientes', true);
        pintarPaginacion(datos.total_registros);
    }

    function falloListado(datos) {
        var claro = mensajeClaro(datos && datos.mensaje);

        texto('texto-aviso', claro || 'No se pudo actualizar el listado de clientes.');
        ocultar('carga-clientes', true);
        ocultar('aviso-clientes', false);
    }

    function cargarListado() {
        if (typeof axios === 'undefined') {
            falloListado(null);
            return Promise.resolve(false);
        }

        if (enListado) {
            listadoPendiente = true;
            return Promise.resolve(false);
        }

        enListado = true;
        ocultar('carga-clientes', false);
        ocultar('exito-clientes', true);
        document.getElementById('carga-clientes').setAttribute('aria-busy', 'true');

        var peticion = {
            op: 'listarClientes',
            pagina: pagina
        };
        var busqueda = buscar ? buscar.value.trim() : '';

        if (busqueda !== '') {
            peticion.busqueda = busqueda.slice(0, 200);
        }

        if (filtroTipo && filtroTipo.value !== '') {
            peticion.tipo_identificacion = filtroTipo.value;
        }

        if (filtroEstado && filtroEstado.value === '1') {
            peticion.estado = true;
        } else if (filtroEstado && filtroEstado.value === '0') {
            peticion.estado = false;
        }

        return post(peticion).then(function (respuesta) {
            var datos = datosRespuesta(respuesta);

            if (datos.exito !== true || !Array.isArray(datos.filas)) {
                falloListado(datos);
                return false;
            }

            pintarKpis(datos.kpis || {});
            pintarListado(datos);
            return true;
        }).catch(function () {
            falloListado(null);
            return false;
        }).finally(function () {
            enListado = false;
            document.getElementById('carga-clientes').removeAttribute('aria-busy');

            if (listadoPendiente) {
                listadoPendiente = false;
                cargarListado();
            }
        });
    }

    function mostrarExito(mensaje) {
        var nodo = document.getElementById('exito-clientes');

        if (!nodo) {
            return;
        }

        nodo.textContent = mensaje;
        nodo.hidden = false;
    }

    function avisoFormulario(mensaje) {
        texto('aviso-formulario', mensaje);
        ocultar('aviso-formulario', mensaje === '');
    }

    function abrirVentana(dialogo) {
        if (dialogo && typeof dialogo.showModal === 'function' && !dialogo.open) {
            dialogo.showModal();
        }
    }

    function cerrarVentana(dialogo) {
        if (dialogo && dialogo.open) {
            dialogo.close();
        }
    }

    function lecturaCampos(soloLectura) {
        editables.forEach(function (campo) {
            campo.readOnly = soloLectura;
        });
        campoIdentificacion.readOnly = soloLectura || modo !== 'crear';
    }

    function limpiarFormulario() {
        modo = 'crear';
        idEdicion = null;
        formulario.reset();
        campoTipo.value = '';
        lecturaCampos(false);
        campoIdentificacion.readOnly = false;
        ocultar('bloque-tipo', false);
        ocultar('bloque-tipo-lectura', true);
        ocultar('bloque-fechas', true);
        ocultar('nota-protegido', true);
        ocultar('guardar-cliente', false);
        ocultar('aviso-formulario', true);
        ocultar('carga-formulario', true);
        texto('titulo-ventana', 'Nuevo cliente');
    }

    function aplicarCliente(cliente, editar) {
        var protegido = cliente.protegido === true;

        idEdicion = cliente.id_cliente;
        modo = editar && !protegido ? 'editar' : 'ver';
        texto('tipo-lectura', guion(cliente.tipo_identificacion));
        campoIdentificacion.value = cliente.identificacion || '';
        campoRazon.value = cliente.razon_social || '';
        campoDireccion.value = cliente.direccion || '';
        campoTelefono.value = cliente.telefono || '';
        campoCorreo.value = cliente.correo || '';
        texto('detalle-estado', cliente.estado === true ? 'Activo' : 'Inactivo');
        texto('detalle-creacion', fechaLegible(cliente.fecha_creacion));
        texto('detalle-actualizacion', fechaLegible(cliente.fecha_actualizacion));
        ocultar('bloque-tipo', true);
        ocultar('bloque-tipo-lectura', false);
        ocultar('bloque-fechas', false);
        ocultar('nota-protegido', !protegido);
        ocultar('carga-formulario', true);
        lecturaCampos(modo !== 'editar');
        campoIdentificacion.readOnly = true;
        ocultar('guardar-cliente', modo !== 'editar');
        texto('titulo-ventana', protegido ? 'Cliente' : (modo === 'editar' ? 'Editar cliente' : 'Detalle del cliente'));
    }

    function abrirCreacion() {
        tokenDetalle += 1;
        limpiarFormulario();
        abrirVentana(ventanaCliente);
        campoTipo.focus();
    }

    function abrirCliente(id, editar) {
        var token = ++tokenDetalle;

        limpiarFormulario();
        modo = editar ? 'editar' : 'ver';
        idEdicion = id;
        texto('titulo-ventana', editar ? 'Editar cliente' : 'Detalle del cliente');
        ocultar('guardar-cliente', true);
        ocultar('carga-formulario', false);
        lecturaCampos(true);
        abrirVentana(ventanaCliente);

        post({
            op: 'obtenerDetalleCliente',
            id_cliente: id
        }).then(function (respuesta) {
            var datos = datosRespuesta(respuesta);

            if (token !== tokenDetalle) {
                return;
            }

            if (datos.exito !== true || !datos.cliente) {
                avisoFormulario(mensajeClaro(datos.mensaje) || 'No se pudo consultar el cliente.');
                ocultar('carga-formulario', true);
                return;
            }

            aplicarCliente(datos.cliente, editar);
        }).catch(function () {
            if (token !== tokenDetalle) {
                return;
            }

            avisoFormulario('No se pudo consultar el cliente.');
            ocultar('carga-formulario', true);
        });
    }

    function identificacionValida(tipo, identificacion) {
        if (tipo === 'CEDULA') {
            return /^[0-9]{10}$/.test(identificacion);
        }

        if (tipo === 'RUC') {
            return /^[0-9]{13}$/.test(identificacion);
        }

        if (tipo === 'PASAPORTE') {
            return identificacion.length >= 1 && identificacion.length <= 20;
        }

        return false;
    }

    function validarFormulario() {
        var razon = campoRazon.value.trim();
        var direccion = campoDireccion.value.trim();
        var telefono = campoTelefono.value.trim();
        var correo = campoCorreo.value.trim();

        if (modo === 'crear') {
            if (campoTipo.value !== 'CEDULA' && campoTipo.value !== 'RUC' && campoTipo.value !== 'PASAPORTE') {
                avisoFormulario('Tipo de identificación no válido.');
                return null;
            }

            if (!identificacionValida(campoTipo.value, campoIdentificacion.value.trim())) {
                avisoFormulario('Identificación inválida');
                return null;
            }
        }

        if (razon === '' || razon.length > 200) {
            avisoFormulario('Razón social no válida.');
            return null;
        }

        if (direccion.length > 255) {
            avisoFormulario('Dirección no válida.');
            return null;
        }

        if (telefono.length > 30) {
            avisoFormulario('Teléfono no válido.');
            return null;
        }

        if (correo.length > 150) {
            avisoFormulario('Correo no válido.');
            return null;
        }

        if (modo === 'crear') {
            return {
                op: 'crearCliente',
                tipo_identificacion: campoTipo.value,
                identificacion: campoIdentificacion.value.trim(),
                razon_social: razon,
                direccion: opcional(direccion),
                telefono: opcional(telefono),
                correo: opcional(correo)
            };
        }

        return {
            op: 'actualizarCliente',
            id_cliente: idEdicion,
            razon_social: razon,
            direccion: opcional(direccion),
            telefono: opcional(telefono),
            correo: opcional(correo)
        };
    }

    function guardarCliente(evento) {
        var peticion;

        evento.preventDefault();

        if (modo === 'ver' || guardar.hidden || guardar.disabled) {
            return;
        }

        peticion = validarFormulario();

        if (!peticion) {
            return;
        }

        avisoFormulario('');
        guardar.disabled = true;
        guardar.setAttribute('aria-busy', 'true');

        post(peticion).then(function (respuesta) {
            var datos = datosRespuesta(respuesta);
            var mensaje = modo === 'crear' ? 'Cliente creado correctamente' : 'Cliente actualizado correctamente.';

            if (datos.exito !== true) {
                avisoFormulario(mensajeClaro(datos.mensaje) || 'No se pudo guardar el cliente.');
                return;
            }

            cerrarVentana(ventanaCliente);
            limpiarFormulario();
            pagina = 1;
            return cargarListado().then(function () {
                mostrarExito(mensaje);
            });
        }).catch(function () {
            avisoFormulario('No se pudo guardar el cliente.');
        }).finally(function () {
            guardar.disabled = false;
            guardar.removeAttribute('aria-busy');
        });
    }

    function prepararConfirma(pregunta) {
        texto('texto-confirma', pregunta);
        ocultar('cancelar-confirma', false);
        ocultar('aceptar-confirma', false);
        document.getElementById('aceptar-confirma').disabled = false;
    }

    function rechazarConfirma(mensaje) {
        texto('texto-confirma', mensaje);
        ocultar('cancelar-confirma', true);
        ocultar('aceptar-confirma', true);
    }

    function pedirEstado(id, activo) {
        prepararConfirma(activo ? '¿Desea desactivar este cliente?' : '¿Desea activar este cliente?');
        confirmarAccion = function () {
            return post({
                op: 'cambiarEstadoCliente',
                id_cliente: id,
                nuevo_estado: !activo
            }).then(function (respuesta) {
                var datos = datosRespuesta(respuesta);

                if (datos.exito !== true) {
                    rechazarConfirma(mensajeClaro(datos.mensaje) || 'No se pudo cambiar el estado del cliente.');
                    return false;
                }

                return cargarListado().then(function () {
                    mostrarExito(activo ? 'Cliente desactivado.' : 'Cliente activado.');
                    return true;
                });
            }).catch(function () {
                rechazarConfirma('No se pudo cambiar el estado del cliente.');
                return false;
            });
        };
        abrirVentana(ventanaConfirma);
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

    function mostrarMensaje(textoMensaje) {
        if (!mensajeSesion) {
            return;
        }

        mensajeSesion.textContent = textoMensaje;
        mensajeSesion.hidden = false;
    }

    document.getElementById('nuevo-cliente').addEventListener('click', abrirCreacion);
    formulario.addEventListener('submit', guardarCliente);
    document.getElementById('cerrar-cliente').addEventListener('click', function () {
        tokenDetalle += 1;
        cerrarVentana(ventanaCliente);
    });
    document.getElementById('filtros').addEventListener('submit', function (evento) {
        evento.preventDefault();
    });
    buscar.addEventListener('input', function () {
        window.clearTimeout(timerBusqueda);
        timerBusqueda = window.setTimeout(function () {
            pagina = 1;
            cargarListado();
        }, 400);
    });
    filtroTipo.addEventListener('change', function () {
        pagina = 1;
        cargarListado();
    });
    filtroEstado.addEventListener('change', function () {
        pagina = 1;
        cargarListado();
    });
    document.getElementById('pagina-anterior').addEventListener('click', function () {
        if (pagina > 1) {
            pagina -= 1;
            cargarListado();
        }
    });
    document.getElementById('pagina-siguiente').addEventListener('click', function () {
        if (pagina < paginas) {
            pagina += 1;
            cargarListado();
        }
    });
    document.getElementById('reintentar-clientes').addEventListener('click', cargarListado);
    cuerpo.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-accion]');
        var id;

        if (!boton || !cuerpo.contains(boton)) {
            return;
        }

        id = Number(boton.getAttribute('data-id'));

        if (!id) {
            return;
        }

        if (boton.getAttribute('data-accion') === 'ver') {
            abrirCliente(id, false);
        } else if (boton.getAttribute('data-accion') === 'editar') {
            abrirCliente(id, true);
        } else if (boton.getAttribute('data-accion') === 'estado') {
            pedirEstado(id, boton.textContent === 'Desactivar');
        }
    });
    document.getElementById('aceptar-confirma').addEventListener('click', function () {
        var boton = document.getElementById('aceptar-confirma');
        var accion = confirmarAccion;

        if (!accion || boton.disabled) {
            return;
        }

        boton.disabled = true;
        boton.setAttribute('aria-busy', 'true');
        accion().then(function (cerrar) {
            if (cerrar) {
                confirmarAccion = null;
                cerrarVentana(ventanaConfirma);
            }
        }).finally(function () {
            boton.disabled = false;
            boton.removeAttribute('aria-busy');
        });
    });
    document.getElementById('cancelar-confirma').addEventListener('click', function () {
        confirmarAccion = null;
        cerrarVentana(ventanaConfirma);
    });
    document.getElementById('cerrar-confirma').addEventListener('click', function () {
        confirmarAccion = null;
        cerrarVentana(ventanaConfirma);
    });

    [ventanaCliente, ventanaConfirma].forEach(function (dialogo) {
        dialogo.addEventListener('click', function (evento) {
            if (evento.target !== dialogo) {
                return;
            }

            if (dialogo === ventanaCliente) {
                tokenDetalle += 1;
            }

            confirmarAccion = null;
            dialogo.close();
        });
    });

    if (typeof axios === 'undefined') {
        falloListado(null);
    } else {
        cargarListado();
    }

    if (!botonSesion) {
        return;
    }

    botonSesion.addEventListener('click', function () {
        if (botonSesion.disabled || typeof axios === 'undefined') {
            if (typeof axios === 'undefined') {
                mostrarMensaje('No se pudo conectar. Inténtalo de nuevo.');
            }

            return;
        }

        if (mensajeSesion) {
            mensajeSesion.hidden = true;
        }

        botonSesion.disabled = true;
        botonSesion.setAttribute('aria-busy', 'true');
        var redirigio = false;

        axios.post('app/Controllers/AuthController.php', {
            op: 'cerrarSesion'
        }).then(function (respuesta) {
            var datos = datosRespuesta(respuesta);

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
                botonSesion.disabled = false;
                botonSesion.removeAttribute('aria-busy');
            }
        });
    });
}());
