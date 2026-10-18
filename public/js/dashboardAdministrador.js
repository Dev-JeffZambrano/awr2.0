(function () {
    var boton = document.getElementById('cerrar-sesion');
    var mensaje = document.getElementById('mensaje-sesion');

    var camposReales = [
        'sello-fuente',
        'usuarios-insignia',
        'usuarios-total',
        'usuarios-nota',
        'arquitectura-resumen',
        'pg-detalle',
        'pg-nota',
        'servicio-postgres-estado',
        'servicio-php-estado',
        'usuarios-registrados',
        'usuarios-habilitados',
        'usuarios-inhabilitados',
        'usuarios-roles',
        'usuarios-fallidos',
        'acceso-usuarios',
        'acceso-roles',
        'latencia-valor',
        'latencia-artificial-valor',
        'latencia-artificial-nota',
        'tiempo-postgres',
        'tiempo-api',
        'tiempo-rtt',
        'rendimiento-insignia',
        'rendimiento-texto',
        'clickhouse-detalle',
        'clickhouse-nota',
        'redpanda-detalle',
        'redpanda-nota',
        'servicio-redpanda-estado',
        'servicio-clickhouse-estado',
        'rendimiento-clickhouse',
        'facturas-pendientes-valor',
        'facturas-pendientes-nota',
        'facturas-pendientes-insignia'
    ];

    function milisegundos(valor) {
        if (typeof valor === 'number' && isFinite(valor) && valor >= 0) {
            return Math.round(valor) + ' ms';
        }

        return 'No disponible';
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

    function milisegundosEnteros(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return null;
        }

        if (typeof valor === 'number' && isFinite(valor) && valor >= 0) {
            return Math.round(valor);
        }

        if (typeof valor === 'string' && /^\d+$/.test(valor.trim())) {
            return Number(valor);
        }

        return null;
    }

    function escribir(id, valor) {
        var nodo = document.getElementById(id);
        if (nodo) {
            nodo.textContent = valor;
        }
    }

    function estadoRecibido(valor) {
        if (valor === 'Operativo' || valor === 'Conectado' || valor === 'No disponible') {
            return valor;
        }

        return 'No disponible';
    }

    function detalleClickHouse(clickhouse, estado) {
        if (estado !== 'Conectado' || typeof clickhouse.version !== 'string' || typeof clickhouse.base !== 'string') {
            return 'No disponible';
        }

        if (typeof clickhouse.tablas !== 'number' || !isFinite(clickhouse.tablas) || clickhouse.tablas < 0) {
            return 'No disponible';
        }

        return 'ClickHouse ' + clickhouse.version + ' · ' + clickhouse.base + ' · ' + clickhouse.tablas + ' tablas';
    }

    function detalleRedpanda(redpanda, estado) {
        if (estado !== 'Operativo' || typeof redpanda.broker !== 'string') {
            return 'No disponible';
        }

        if (typeof redpanda.puerto_kafka !== 'number' || !isFinite(redpanda.puerto_kafka)) {
            return 'No disponible';
        }

        return redpanda.broker + ':' + redpanda.puerto_kafka;
    }

    function marcarNoDisponible() {
        camposReales.forEach(function (id) {
            escribir(id, 'No disponible');
        });
    }

    function cargarResumen() {
        if (typeof axios === 'undefined') {
            marcarNoDisponible();
            return;
        }

        axios.post('app/Controllers/DashboardAdministradorController.php', {
            op: 'obtenerResumenAdministrador'
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true || !datos.usuarios || !datos.postgresql) {
                marcarNoDisponible();
                return;
            }

            var usuarios = datos.usuarios;
            var motor = datos.postgresql;

            escribir('sello-fuente', 'PostgreSQL');
            escribir('usuarios-insignia', usuarios.insignia);
            escribir('usuarios-total', String(usuarios.total));
            escribir('usuarios-nota', usuarios.nota);
            escribir('usuarios-registrados', String(usuarios.total));
            escribir('usuarios-habilitados', String(usuarios.habilitados));
            escribir('usuarios-inhabilitados', String(usuarios.inhabilitados));
            escribir('usuarios-roles', usuarios.rolesTexto);
            escribir('usuarios-fallidos', String(datos.intentosFallidos));
            escribir('acceso-usuarios', String(usuarios.total));
            escribir('acceso-roles', String(datos.roles));
            var redpanda = datos.redpanda || {};
            var clickhouse = datos.clickhouse || {};
            var estadoRedpanda = estadoRecibido(redpanda.estado);
            var estadoClickhouse = estadoRecibido(clickhouse.estado);

            escribir('arquitectura-resumen', 'PostgreSQL ' + (motor.conectado === true ? 'conectado' : 'no disponible')
                + ' · ClickHouse ' + estadoClickhouse.toLowerCase()
                + ' · Redpanda ' + estadoRedpanda.toLowerCase() + '.');
            escribir('clickhouse-nota', estadoClickhouse);
            escribir('clickhouse-detalle', detalleClickHouse(clickhouse, estadoClickhouse));
            escribir('servicio-clickhouse-estado', estadoClickhouse);
            escribir('rendimiento-clickhouse', milisegundos(clickhouse.tiempo_respuesta_ms));
            escribir('rendimiento-insignia', 'Observado');
            escribir('rendimiento-texto', 'Tiempos de esta consulta.');
            escribir('redpanda-nota', estadoRedpanda);
            escribir('redpanda-detalle', detalleRedpanda(redpanda, estadoRedpanda));
            escribir('servicio-redpanda-estado', estadoRedpanda);
            escribir('pg-detalle', motor.detalle);
            escribir('pg-nota', motor.nota);
            escribir('servicio-postgres', motor.servicio);
            escribir('servicio-postgres-estado', motor.conectado === true ? 'Conectado' : 'No disponible');

            if (datos.aplicacion) {
                escribir('servicio-php', datos.aplicacion.servicio || 'Aplicación PHP');
                escribir('servicio-php-estado', datos.aplicacion.estado || 'No disponible');
            }

            escribir('latencia-valor', milisegundosDecimales(datos.latencia_red_ms));

            var artificial = milisegundosEnteros(datos.latenciaRedMs);

            if (artificial === null) {
                escribir('latencia-artificial-valor', 'No disponible');
                escribir('latencia-artificial-nota', 'Retardo configurado');
            } else if (artificial === 0) {
                escribir('latencia-artificial-valor', '0 ms');
                escribir('latencia-artificial-nota', 'Sin degradación artificial');
            } else {
                escribir('latencia-artificial-valor', artificial + ' ms');
                escribir('latencia-artificial-nota', 'Degradación simulada activa');
            }

            escribir('tiempo-postgres', milisegundos(datos.tiempoPostgresMs));
            escribir('tiempo-api', milisegundos(datos.tiempoApiMs));
            escribir('tiempo-rtt', milisegundosDecimales(datos.latencia_red_ms));

            var facturasPendientes = datos.facturasPendientes || {};

            if (facturasPendientes.disponible === true && typeof facturasPendientes.valor === 'string') {
                escribir('facturas-pendientes-valor', facturasPendientes.valor);
                escribir(
                    'facturas-pendientes-nota',
                    typeof facturasPendientes.nota === 'string'
                        ? facturasPendientes.nota
                        : 'Operaciones esperando reproceso'
                );
                escribir('facturas-pendientes-insignia', 'Resiliencia');
            } else {
                escribir('facturas-pendientes-valor', 'No disponible');
                escribir('facturas-pendientes-nota', 'No disponible');
                escribir('facturas-pendientes-insignia', 'Resiliencia');
            }
        }).catch(function () {
            marcarNoDisponible();
        });
    }

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

    cargarResumen();

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
}());
