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
        'usuarios-registrados',
        'usuarios-habilitados',
        'usuarios-sesiones',
        'usuarios-roles',
        'usuarios-ultimo-acceso',
        'usuarios-fallidos',
        'acceso-usuarios',
        'acceso-roles',
        'acceso-auditoria',
        'latencia-valor',
        'tiempo-postgres'
    ];

    function milisegundos(valor) {
        if (typeof valor === 'number' && isFinite(valor) && valor >= 0) {
            return Math.round(valor) + ' ms';
        }

        return 'No disponible';
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
            escribir('usuarios-sesiones', String(usuarios.sesionesActivas));
            escribir('usuarios-roles', usuarios.rolesTexto);
            escribir('usuarios-ultimo-acceso', datos.ultimoAcceso);
            escribir('usuarios-fallidos', String(datos.intentosFallidos));
            escribir('acceso-usuarios', String(usuarios.total));
            escribir('acceso-roles', String(datos.roles));
            escribir('acceso-auditoria', String(datos.auditoria));
            escribir('arquitectura-resumen', 'PostgreSQL conectado · ClickHouse pendiente de integración · Redpanda pendiente de integración.');
            escribir('pg-detalle', motor.detalle);
            escribir('pg-nota', motor.nota);
            escribir('servicio-postgres', motor.servicio);
            escribir('servicio-postgres-estado', motor.conectado === true ? 'Conectado' : 'No disponible');

            escribir('latencia-valor', milisegundos(datos.latenciaRedMs));
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
