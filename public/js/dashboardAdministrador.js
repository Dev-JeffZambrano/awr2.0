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
        'tiempo-postgres'
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
            escribir('arquitectura-resumen', 'PostgreSQL conectado · ClickHouse pendiente de integración · Redpanda pendiente de integración.');
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
