(function () {
    var boton = document.getElementById('cerrar-sesion');
    var mensaje = document.getElementById('mensaje-sesion');
    var carga = document.getElementById('carga-monitoreo');
    var aviso = document.getElementById('aviso-monitoreo');
    var panel = document.getElementById('panel-monitoreo');
    var enCurso = false;
    var intervaloMs = 5000;

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
        if (valor === 'Operativo' || valor === 'Conectado') {
            return 'estado estado--ok';
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
        pintarEstado('clickhouse-estado', clickhouse.estado);

        escribir('impacto-red', milisegundosDecimales(latencias.latencia_red_ms));
        escribir('impacto-artificial', latencia === null ? 'No disponible' : latencia + ' ms');
        escribir('impacto-respuesta', milisegundos(latencias.tiempo_respuesta_postgresql_ms));

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
