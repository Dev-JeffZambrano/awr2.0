(function () {
    var botonSesion = document.getElementById('cerrar-sesion');
    var mensaje = document.getElementById('mensaje-sesion');
    var boton = document.getElementById('actualizar');
    var carga = document.getElementById('carga-servicios');
    var aviso = document.getElementById('aviso-servicios');
    var panel = document.getElementById('panel-servicios');
    var lista = document.getElementById('lista-servicios');
    var enCurso = false;

    function escribir(id, valor) {
        var nodo = document.getElementById(id);

        if (nodo) {
            nodo.textContent = valor;
        }
    }

    function entero(valor) {
        if (typeof valor === 'number' && isFinite(valor) && valor >= 0) {
            return Math.round(valor);
        }

        if (typeof valor === 'string' && /^\d+$/.test(valor.trim())) {
            return Number(valor);
        }

        return null;
    }

    function megabytes(bytes) {
        var cantidad = entero(bytes);

        if (cantidad === null) {
            return 'No disponible';
        }

        var texto = (cantidad / 1048576).toFixed(1).replace('.', ',');

        if (texto.slice(-2) === ',0') {
            texto = texto.slice(0, -2);
        }

        return texto + ' MB';
    }

    function milisegundos(valor) {
        var cantidad = entero(valor);

        return cantidad === null ? 'No disponible' : cantidad + ' ms';
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

        var texto = valor.toFixed(2).replace('.', ',');
        texto = texto.replace(/,00$/, '').replace(/(\,\d)0$/, '$1');

        return texto + ' ms';
    }

    function fecha(marca) {
        var coincidencia = typeof marca === 'string'
            ? marca.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}:\d{2}:\d{2})/)
            : null;

        if (!coincidencia) {
            return 'No disponible';
        }

        return coincidencia[3] + '/' + coincidencia[2] + '/' + coincidencia[1] + ' ' + coincidencia[4];
    }

    function grupo(estado) {
        if (estado === 'Operativo' || estado === 'Conectado') {
            return 'ok';
        }

        if (estado === 'No integrado') {
            return 'pendiente';
        }

        if (estado === 'No disponible') {
            return 'error';
        }

        return 'pendiente';
    }

    function tiene(objeto, clave) {
        return Object.prototype.hasOwnProperty.call(objeto, clave);
    }

    function fila(titulo, valor, nota) {
        var bloque = document.createElement('div');
        var termino = document.createElement('dt');
        var dato = document.createElement('dd');

        termino.textContent = titulo;
        dato.textContent = valor;

        if (nota) {
            var extra = document.createElement('small');
            extra.textContent = nota;
            dato.appendChild(extra);
        }

        bloque.appendChild(termino);
        bloque.appendChild(dato);

        return bloque;
    }

    function pintarServicio(servicio) {
        var estado = typeof servicio.estado === 'string' && servicio.estado !== ''
            ? servicio.estado
            : 'No disponible';
        var tipo = grupo(estado);
        var article = document.createElement('article');
        var identidad = document.createElement('div');
        var cabeza = document.createElement('div');
        var titulo = document.createElement('h2');
        var badge = document.createElement('p');
        var detalle = document.createElement('p');
        var metricas = document.createElement('dl');
        var notaArtificial = '';

        article.className = 'servicio servicio--' + tipo;
        cabeza.className = 'servicio__cabeza';
        titulo.textContent = servicio.nombre || 'Servicio';
        badge.className = 'estado estado--' + (tipo === 'error' ? 'error' : tipo === 'ok' ? 'ok' : 'pendiente');
        badge.textContent = estado;
        detalle.className = 'servicio__detalle';
        detalle.textContent = servicio.detalle || 'No disponible';
        metricas.className = 'metricas';

        cabeza.appendChild(titulo);
        cabeza.appendChild(badge);
        identidad.appendChild(cabeza);
        identidad.appendChild(detalle);
        article.appendChild(identidad);

        if (tiene(servicio, 'memoria_usada_peticion')) {
            metricas.appendChild(fila('Memoria de petición', megabytes(servicio.memoria_usada_peticion)));
        }

        if (tiene(servicio, 'tiempo_respuesta_ms')) {
            metricas.appendChild(fila('Respuesta PostgreSQL', milisegundos(servicio.tiempo_respuesta_ms)));
        }

        if (tiene(servicio, 'latencia_red_ms')) {
            metricas.appendChild(fila('Latencia de Red', milisegundosDecimales(servicio.latencia_red_ms)));
        }

        if (tiene(servicio, 'latencia_artificial_ms')) {
            var artificial = entero(servicio.latencia_artificial_ms);

            if (artificial === 0) {
                notaArtificial = 'Sin degradación artificial';
            } else if (artificial !== null && artificial > 0) {
                notaArtificial = 'Degradación simulada activa';
            }

            metricas.appendChild(fila(
                'Latencia Artificial',
                artificial === null ? 'No disponible' : artificial + ' ms',
                notaArtificial
            ));
        }

        if (tiene(servicio, 'puerto_admin')) {
            var admin = entero(servicio.puerto_admin);
            metricas.appendChild(fila('Admin API', admin === null ? 'No disponible' : String(admin)));
        }

        if (tiene(servicio, 'tablas')) {
            var tablas = entero(servicio.tablas);
            metricas.appendChild(fila('Tablas', tablas === null ? 'No disponible' : tablas + ' tablas'));
        }

        if (tiene(servicio, 'respuesta_ms')) {
            metricas.appendChild(fila('Respuesta observada', milisegundos(servicio.respuesta_ms)));
        }

        if (metricas.childElementCount > 0) {
            article.appendChild(metricas);
        }

        return article;
    }

    function pintar(datos) {
        var servicios = Array.isArray(datos.servicios) ? datos.servicios : [];
        var operativos = 0;
        var pendientes = 0;
        var caidos = 0;

        if (lista) {
            lista.replaceChildren();
        }

        servicios.forEach(function (servicio) {
            var tipo = grupo(servicio && servicio.estado);

            if (tipo === 'ok') {
                operativos += 1;
            } else if (tipo === 'error') {
                caidos += 1;
            } else if (servicio && servicio.estado === 'No integrado') {
                pendientes += 1;
            }

            if (lista && servicio) {
                lista.appendChild(pintarServicio(servicio));
            }
        });

        escribir('conteo-operativos', String(operativos));
        escribir('conteo-pendientes', String(pendientes));
        escribir('conteo-caidos', String(caidos));
        escribir('marca-valor', fecha(datos.timestamp_consulta));
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

        if (boton) {
            boton.disabled = true;
            boton.setAttribute('aria-busy', 'true');
        }

        axios.post('app/Controllers/ServiciosAdministradorController.php', {
            op: 'listarServicios'
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true || !Array.isArray(datos.servicios)) {
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

            if (boton) {
                boton.disabled = false;
                boton.removeAttribute('aria-busy');
            }
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

    if (boton) {
        boton.addEventListener('click', consultar);
    }

    consultar();

    if (!botonSesion) {
        return;
    }

    botonSesion.addEventListener('click', function () {
        if (botonSesion.disabled) {
            return;
        }

        if (mensaje) {
            mensaje.hidden = true;
        }

        botonSesion.disabled = true;
        botonSesion.setAttribute('aria-busy', 'true');

        if (typeof axios === 'undefined') {
            mostrarMensaje('No se pudo conectar. Inténtalo de nuevo.');
            botonSesion.disabled = false;
            botonSesion.removeAttribute('aria-busy');
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
                botonSesion.disabled = false;
                botonSesion.removeAttribute('aria-busy');
            }
        });
    });
}());
