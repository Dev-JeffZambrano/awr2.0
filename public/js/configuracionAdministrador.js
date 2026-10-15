(function () {
    var controlador = 'app/Controllers/ConfiguracionAdministradorController.php';
    var botonSesion = document.getElementById('cerrar-sesion');
    var mensajeSesion = document.getElementById('mensaje-sesion');
    var botonActualizar = document.getElementById('actualizar-estado');
    var campoNombre = document.getElementById('campo-nombre');
    var campoRazon = document.getElementById('campo-razon');
    var campoComercial = document.getElementById('campo-comercial');
    var campoIdentificacion = document.getElementById('campo-identificacion');
    var campoDireccion = document.getElementById('campo-direccion');
    var campoTelefono = document.getElementById('campo-telefono');
    var campoCorreo = document.getElementById('campo-correo');
    var campoAmbiente = document.getElementById('campo-ambiente');
    var campoLeyenda = document.getElementById('campo-leyenda');
    var campoLatencia = document.getElementById('campo-latencia');
    var guardarEmpresa = document.getElementById('guardar-empresa');
    var guardarFacturacion = document.getElementById('guardar-facturacion');
    var aplicarLatencia = document.getElementById('aplicar-latencia');
    var tiposComprobante = [];
    var latenciaConfirmada = null;
    var cargado = false;
    var enConsulta = false;

    var mensajesClaros = {
        'Nombre no válido.': true,
        'Razón social no válida.': true,
        'Nombre comercial no válido.': true,
        'Identificación no válida.': true,
        'Dirección no válida.': true,
        'Teléfono no válido.': true,
        'Correo no válido.': true,
        'Ambiente no válido.': true,
        'Leyenda no válida.': true,
        'Latencia no válida.': true,
        'Datos incompletos.': true,
        'Toxiproxy no está disponible': true,
        'El proxy no está disponible': true,
        'No se pudo confirmar la latencia': true,
        'No disponible': true
    };

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

    function valorCampo(valor) {
        return valor === null || valor === undefined ? '' : String(valor);
    }

    function guion(valor) {
        if (valor === null || valor === undefined || String(valor).trim() === '') {
            return '—';
        }

        return String(valor);
    }

    function pintarEstado(id, activo) {
        var nodo = document.getElementById(id);

        if (!nodo) {
            return;
        }

        nodo.textContent = activo ? 'Activo' : 'Inactivo';
        nodo.className = activo ? 'estado estado--ok' : 'estado estado--inactivo';
    }

    function pintarMarca(id, estado) {
        var nodo = document.getElementById(id);
        var textoEstado = estado || 'No disponible';

        if (!nodo) {
            return;
        }

        nodo.textContent = textoEstado;
        nodo.className = textoEstado === 'Operativo' || textoEstado === 'Conectado'
            ? 'estado estado--ok'
            : 'estado estado--inactivo';
    }

    function aviso(id, mensaje) {
        texto(id, mensaje);
        ocultar(id, mensaje === '');
    }

    function exito(id, mensaje) {
        texto(id, mensaje);
        ocultar(id, mensaje === '');
    }

    function mostrarPaneles(visible) {
        ['panel-empresa', 'panel-facturacion', 'panel-referencias', 'panel-integraciones'].forEach(function (id) {
            ocultar(id, !visible);
        });
    }

    function porcentajeLegible(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '';
        }

        var textoPorcentaje = String(valor).trim();

        if (!/^\d+(?:\.\d+)?$/.test(textoPorcentaje)) {
            return textoPorcentaje;
        }

        var partes = textoPorcentaje.split('.');
        var decimales = (partes[1] || '').replace(/0+$/, '');

        return decimales === '' ? partes[0] + '%' : partes[0] + ',' + decimales + '%';
    }

    function etiquetaImpuesto(item) {
        var nombre = item && item.nombre ? String(item.nombre) : '—';
        var porcentaje = porcentajeLegible(item ? item.porcentaje : null);

        if (porcentaje === '' || nombre.indexOf('%') !== -1) {
            return nombre;
        }

        return nombre + ' ' + porcentaje;
    }

    function nombreTipo(codigo) {
        var i;

        for (i = 0; i < tiposComprobante.length; i += 1) {
            if (tiposComprobante[i].codigo === codigo && tiposComprobante[i].nombre) {
                return String(tiposComprobante[i].nombre);
            }
        }

        return codigo ? String(codigo) : '—';
    }

    function listaVacia(contenedor) {
        contenedor.replaceChildren(elemento('li', 'referencia__meta', 'Sin registros'));
    }

    function pintarTipos(lista) {
        var contenedor = document.getElementById('lista-tipos');

        tiposComprobante = Array.isArray(lista) ? lista : [];
        contenedor.replaceChildren();

        if (tiposComprobante.length === 0) {
            listaVacia(contenedor);
            return;
        }

        tiposComprobante.forEach(function (item) {
            var li = document.createElement('li');

            li.appendChild(elemento('span', 'marca marca--codigo', guion(item.codigo)));
            li.appendChild(elemento('strong', '', guion(item.nombre)));

            if (item.estado === false) {
                li.appendChild(elemento('span', 'estado estado--inactivo', 'Inactivo'));
            }

            contenedor.appendChild(li);
        });
    }

    function pintarImpuestos(lista) {
        var contenedor = document.getElementById('lista-impuestos');
        var impuestos = Array.isArray(lista) ? lista : [];

        contenedor.replaceChildren();

        if (impuestos.length === 0) {
            listaVacia(contenedor);
            return;
        }

        impuestos.forEach(function (item) {
            var li = document.createElement('li');

            li.appendChild(elemento('strong', '', etiquetaImpuesto(item)));
            contenedor.appendChild(li);
        });
    }

    function pintarPuntos(lista) {
        var contenedor = document.getElementById('lista-puntos');
        var puntos = Array.isArray(lista) ? lista : [];

        contenedor.replaceChildren();

        if (puntos.length === 0) {
            listaVacia(contenedor);
            return;
        }

        puntos.forEach(function (item) {
            var li = document.createElement('li');
            var etiqueta = guion(item.codigo);

            if (item.sucursal) {
                etiqueta += ' · ' + item.sucursal;
            }

            li.appendChild(elemento('strong', '', etiqueta));

            if (item.estado === false) {
                li.appendChild(elemento('span', 'estado estado--inactivo', 'Inactivo'));
            }

            contenedor.appendChild(li);
        });
    }

    function pintarSecuenciales(lista) {
        var contenedor = document.getElementById('lista-secuenciales');
        var secuenciales = Array.isArray(lista) ? lista : [];

        contenedor.replaceChildren();

        if (secuenciales.length === 0) {
            listaVacia(contenedor);
            return;
        }

        secuenciales.forEach(function (item) {
            var li = document.createElement('li');
            var punto = 'Punto ' + guion(item.punto);

            if (item.sucursal) {
                punto += ' · ' + item.sucursal;
            }

            li.appendChild(elemento('strong', '', punto));
            li.appendChild(elemento('span', '', nombreTipo(item.tipo)));
            li.appendChild(elemento('small', '', 'Último número: ' + String(item.ultimo_numero)));
            contenedor.appendChild(li);
        });
    }

    function pintarEmpresa(empresa) {
        var datos = empresa || {};

        campoNombre.value = valorCampo(datos.nombre);
        campoRazon.value = valorCampo(datos.razon_social);
        campoComercial.value = valorCampo(datos.nombre_comercial);
        campoIdentificacion.value = valorCampo(datos.identificacion);
        campoDireccion.value = valorCampo(datos.direccion);
        campoTelefono.value = valorCampo(datos.telefono);
        campoCorreo.value = valorCampo(datos.correo);
        pintarEstado('empresa-estado', datos.estado === true);
    }

    function sincronizarProduccion() {
        ocultar('aviso-produccion', campoAmbiente.value !== 'PRODUCCION');
    }

    function pintarFacturacion(configuracion) {
        var datos = configuracion || {};
        var ambiente = datos.ambiente === 'PRODUCCION' ? 'PRODUCCION' : 'PRUEBAS';

        campoAmbiente.value = ambiente;
        campoLeyenda.value = valorCampo(datos.leyenda);
        texto('facturacion-moneda', guion(datos.moneda));
        texto('facturacion-emision', guion(datos.tipo_emision));
        pintarEstado('facturacion-estado', datos.estado === true);
        sincronizarProduccion();
    }

    function textoDegradacion(valor) {
        if (valor === null) {
            return 'No disponible';
        }

        return valor === 0 ? 'Sin degradación artificial' : 'Degradación simulada activa';
    }

    function pintarLatencia(valor) {
        if (!Number.isInteger(valor) || valor < 0 || valor > 3000) {
            latenciaConfirmada = null;
            campoLatencia.value = '';
            texto('degradacion', 'No disponible');
            return;
        }

        latenciaConfirmada = valor;
        campoLatencia.value = String(valor);
        texto('degradacion', valor === 0 ? 'Sin degradación artificial' : valor + ' ms · Degradación simulada activa');
    }

    function pintarIntegraciones(integraciones) {
        var datos = integraciones || {};
        var toxiproxy = datos.toxiproxy || {};
        var redpanda = datos.redpanda || {};
        var clickhouse = datos.clickhouse || {};
        var proxy = document.getElementById('toxiproxy-proxy');

        pintarMarca('toxiproxy-estado', toxiproxy.estado);
        pintarLatencia(Number.isInteger(toxiproxy.latencia_configurada_ms) ? toxiproxy.latencia_configurada_ms : null);

        if (proxy) {
            if (typeof toxiproxy.proxy === 'string' && toxiproxy.proxy !== '') {
                proxy.textContent = 'Proxy: ' + toxiproxy.proxy;
                proxy.hidden = false;
            } else {
                proxy.textContent = '';
                proxy.hidden = true;
            }
        }

        pintarMarca('redpanda-estado', redpanda.estado || 'No integrado');
        texto('redpanda-texto', (redpanda.estado || 'No integrado') === 'No integrado' ? 'Integración pendiente.' : '');
        pintarMarca('clickhouse-estado', clickhouse.estado || 'No integrado');
        texto('clickhouse-texto', (clickhouse.estado || 'No integrado') === 'No integrado'
            ? 'Motor analítico pendiente de integración.'
            : '');
    }

    function pintarReferencias(referencias) {
        var datos = referencias || {};

        pintarTipos(datos.tipo_comprobante);
        pintarImpuestos(datos.impuesto);
        pintarPuntos(datos.punto_emision);
        pintarSecuenciales(datos.secuencial_comprobante);
    }

    function falloConsulta(datos) {
        var claro = mensajeClaro(datos && datos.mensaje);

        texto('texto-aviso', claro || 'No se pudo consultar la configuración.');
        ocultar('carga-configuracion', true);
        ocultar('aviso-configuracion', false);

        if (!cargado) {
            mostrarPaneles(false);
        }
    }

    function consultar() {
        if (typeof axios === 'undefined') {
            falloConsulta(null);
            return Promise.resolve(false);
        }

        if (enConsulta) {
            return Promise.resolve(false);
        }

        enConsulta = true;
        ocultar('carga-configuracion', false);
        document.getElementById('carga-configuracion').setAttribute('aria-busy', 'true');
        botonActualizar.disabled = true;
        botonActualizar.setAttribute('aria-busy', 'true');

        return post({
            op: 'obtenerConfiguracion'
        }).then(function (respuesta) {
            var datos = datosRespuesta(respuesta);

            if (datos.exito !== true || !datos.empresa || !datos.configuracion_facturacion) {
                falloConsulta(datos);
                return false;
            }

            pintarEmpresa(datos.empresa);
            pintarFacturacion(datos.configuracion_facturacion);
            pintarReferencias(datos.referencias_facturacion || {});
            pintarIntegraciones(datos.integraciones || {});
            cargado = true;
            mostrarPaneles(true);
            ocultar('aviso-configuracion', true);
            ocultar('carga-configuracion', true);
            return true;
        }).catch(function () {
            falloConsulta(null);
            return false;
        }).finally(function () {
            enConsulta = false;
            document.getElementById('carga-configuracion').removeAttribute('aria-busy');
            botonActualizar.disabled = false;
            botonActualizar.removeAttribute('aria-busy');
        });
    }

    function validarEmpresa() {
        var nombre = campoNombre.value.trim();
        var razon = campoRazon.value.trim();
        var comercial = campoComercial.value.trim();
        var identificacion = campoIdentificacion.value.trim();
        var direccion = campoDireccion.value.trim();
        var telefono = campoTelefono.value.trim();
        var correo = campoCorreo.value.trim();

        if (nombre === '' || nombre.length > 150) {
            aviso('aviso-empresa', 'Nombre no válido.');
            return null;
        }

        if (razon.length > 200) {
            aviso('aviso-empresa', 'Razón social no válida.');
            return null;
        }

        if (comercial.length > 200) {
            aviso('aviso-empresa', 'Nombre comercial no válido.');
            return null;
        }

        if (identificacion === '' || identificacion.length > 20) {
            aviso('aviso-empresa', 'Identificación no válida.');
            return null;
        }

        if (direccion.length > 255) {
            aviso('aviso-empresa', 'Dirección no válida.');
            return null;
        }

        if (telefono.length > 30) {
            aviso('aviso-empresa', 'Teléfono no válido.');
            return null;
        }

        if (correo.length > 150) {
            aviso('aviso-empresa', 'Correo no válido.');
            return null;
        }

        return {
            op: 'actualizarEmpresa',
            nombre: nombre,
            razon_social: opcional(razon),
            nombre_comercial: opcional(comercial),
            identificacion: identificacion,
            direccion: opcional(direccion),
            telefono: opcional(telefono),
            correo: opcional(correo)
        };
    }

    function guardarDatosEmpresa(evento) {
        var peticion;

        evento.preventDefault();

        if (guardarEmpresa.disabled) {
            return;
        }

        peticion = validarEmpresa();

        if (!peticion) {
            return;
        }

        aviso('aviso-empresa', '');
        exito('exito-empresa', '');
        guardarEmpresa.disabled = true;
        guardarEmpresa.setAttribute('aria-busy', 'true');

        post(peticion).then(function (respuesta) {
            var datos = datosRespuesta(respuesta);

            if (datos.exito !== true || !datos.empresa) {
                aviso('aviso-empresa', mensajeClaro(datos.mensaje) || 'No se pudo guardar los datos generales.');
                return;
            }

            pintarEmpresa(datos.empresa);
            exito('exito-empresa', 'Datos generales actualizados correctamente');
        }).catch(function () {
            aviso('aviso-empresa', 'No se pudo guardar los datos generales.');
        }).finally(function () {
            guardarEmpresa.disabled = false;
            guardarEmpresa.removeAttribute('aria-busy');
        });
    }

    function validarFacturacion() {
        var ambiente = campoAmbiente.value;
        var leyenda = campoLeyenda.value.trim();

        if (ambiente !== 'PRUEBAS' && ambiente !== 'PRODUCCION') {
            aviso('aviso-facturacion', 'Ambiente no válido.');
            return null;
        }

        if (leyenda.length > 255) {
            aviso('aviso-facturacion', 'Leyenda no válida.');
            return null;
        }

        return {
            op: 'actualizarFacturacion',
            ambiente: ambiente,
            leyenda: opcional(leyenda)
        };
    }

    function guardarDatosFacturacion(evento) {
        var peticion;

        evento.preventDefault();

        if (guardarFacturacion.disabled) {
            return;
        }

        peticion = validarFacturacion();

        if (!peticion) {
            return;
        }

        aviso('aviso-facturacion', '');
        exito('exito-facturacion', '');
        guardarFacturacion.disabled = true;
        guardarFacturacion.setAttribute('aria-busy', 'true');

        post(peticion).then(function (respuesta) {
            var datos = datosRespuesta(respuesta);

            if (datos.exito !== true || !datos.configuracion_facturacion) {
                aviso('aviso-facturacion', mensajeClaro(datos.mensaje) || 'No se pudo guardar la configuración de facturación.');
                return;
            }

            pintarFacturacion(datos.configuracion_facturacion);
            exito('exito-facturacion', 'Configuración de facturación actualizada correctamente.');
        }).catch(function () {
            aviso('aviso-facturacion', 'No se pudo guardar la configuración de facturación.');
        }).finally(function () {
            guardarFacturacion.disabled = false;
            guardarFacturacion.removeAttribute('aria-busy');
        });
    }

    function latenciaEscrita() {
        var valor = campoLatencia.value.trim();

        if (!/^(?:0|[1-9]\d{0,3})$/.test(valor)) {
            return null;
        }

        var numero = Number(valor);

        if (!Number.isInteger(numero) || numero < 0 || numero > 3000) {
            return null;
        }

        return numero;
    }

    function aplicar() {
        var latencia;

        if (aplicarLatencia.disabled) {
            return;
        }

        latencia = latenciaEscrita();

        if (latencia === null) {
            aviso('aviso-latencia', 'Latencia no válida.');
            exito('exito-latencia', '');
            return;
        }

        aviso('aviso-latencia', '');
        exito('exito-latencia', '');
        aplicarLatencia.disabled = true;
        aplicarLatencia.setAttribute('aria-busy', 'true');

        post({
            op: 'actualizarLatenciaToxiproxy',
            latencia_ms: latencia
        }).then(function (respuesta) {
            var datos = datosRespuesta(respuesta);
            var confirmada = datos.latencia_configurada_ms;

            if (datos.exito !== true || !Number.isInteger(confirmada) || confirmada < 0 || confirmada > 3000) {
                if (latenciaConfirmada !== null) {
                    campoLatencia.value = String(latenciaConfirmada);
                }

                aviso('aviso-latencia', mensajeClaro(datos.mensaje) || 'No se pudo aplicar la latencia.');
                return;
            }

            pintarLatencia(confirmada);
            exito('exito-latencia', 'Latencia artificial aplicada: ' + confirmada + ' ms');
        }).catch(function () {
            if (latenciaConfirmada !== null) {
                campoLatencia.value = String(latenciaConfirmada);
            }

            aviso('aviso-latencia', 'No se pudo aplicar la latencia.');
        }).finally(function () {
            aplicarLatencia.disabled = false;
            aplicarLatencia.removeAttribute('aria-busy');
        });
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

    document.getElementById('formulario-empresa').addEventListener('submit', guardarDatosEmpresa);
    document.getElementById('formulario-facturacion').addEventListener('submit', guardarDatosFacturacion);
    campoAmbiente.addEventListener('change', sincronizarProduccion);
    aplicarLatencia.addEventListener('click', aplicar);
    botonActualizar.addEventListener('click', consultar);
    document.getElementById('reintentar-configuracion').addEventListener('click', consultar);
    document.querySelectorAll('[data-preset]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            campoLatencia.value = boton.getAttribute('data-preset') || '';
        });
    });

    if (typeof axios === 'undefined') {
        falloConsulta(null);
    } else {
        consultar();
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
