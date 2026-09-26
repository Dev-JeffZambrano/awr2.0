(function () {
    var formulario = document.getElementById('formulario-acceso');
    var campoUsuario = document.getElementById('username');
    var campoClave = document.getElementById('password');
    var botonEnviar = document.getElementById('boton-acceso');
    var botonAlternar = document.getElementById('alternar-clave');
    var mensaje = document.getElementById('mensaje-acceso');
    var textoBoton = 'Iniciar sesión';

    function mostrarMensaje(texto) {
        mensaje.textContent = texto;
        mensaje.hidden = false;
    }

    function ocultarMensaje() {
        mensaje.textContent = '';
        mensaje.hidden = true;
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

    botonAlternar.addEventListener('click', function () {
        var visible = campoClave.type === 'text';
        campoClave.type = visible ? 'password' : 'text';
        botonAlternar.setAttribute('aria-pressed', visible ? 'false' : 'true');
        botonAlternar.textContent = visible ? 'Mostrar' : 'Ocultar';
    });

    formulario.addEventListener('submit', function (evento) {
        evento.preventDefault();

        if (botonEnviar.disabled) {
            return;
        }

        var username = campoUsuario.value.trim();
        var password = campoClave.value;
        campoUsuario.value = username;

        if (username === '' || password === '') {
            mostrarMensaje('Ingresa el usuario y la contraseña.');
            if (username === '') {
                campoUsuario.focus();
            } else {
                campoClave.focus();
            }
            return;
        }

        if (typeof axios === 'undefined') {
            mostrarMensaje('No se pudo conectar. Inténtalo de nuevo.');
            return;
        }

        ocultarMensaje();
        botonEnviar.disabled = true;
        botonEnviar.setAttribute('aria-busy', 'true');
        botonEnviar.textContent = 'Ingresando…';

        var redirigio = false;

        axios.post('app/Controllers/AuthController.php', {
            op: 'iniciarSesion',
            username: username,
            password: password
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito === true && redireccionLocal(datos.redireccion)) {
                redirigio = true;
                window.location.href = datos.redireccion;
                return;
            }

            if (datos.exito === false && typeof datos.mensaje === 'string' && datos.mensaje !== '') {
                mostrarMensaje(datos.mensaje);
                return;
            }

            mostrarMensaje('No se pudo iniciar sesión.');
        }).catch(function (error) {
            var datos = error.response && error.response.data;

            if (datos && typeof datos.mensaje === 'string' && datos.mensaje !== '') {
                mostrarMensaje(datos.mensaje);
                return;
            }

            mostrarMensaje('No se pudo conectar. Inténtalo de nuevo.');
        }).finally(function () {
            if (!redirigio) {
                botonEnviar.disabled = false;
                botonEnviar.removeAttribute('aria-busy');
                botonEnviar.textContent = textoBoton;
            }
        });
    });
}());
