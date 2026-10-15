(function () {
    var controlador = 'app/Controllers/UsuariosAccesosAdministradorController.php';
    var idSesion = Number(document.body.getAttribute('data-usuario')) || 0;
    var botonSesion = document.getElementById('cerrar-sesion');
    var mensajeSesion = document.getElementById('mensaje-sesion');
    var cuerpo = document.getElementById('cuerpo-usuarios');
    var buscar = document.getElementById('buscar-usuario');
    var filtroRol = document.getElementById('filtro-rol');
    var filtroEstado = document.getElementById('filtro-estado');
    var filtroSucursal = document.getElementById('filtro-sucursal');
    var campoRol = document.getElementById('campo-rol');
    var campoSucursal = document.getElementById('campo-sucursal');
    var campoUsername = document.getElementById('campo-username');
    var campoPassword = document.getElementById('campo-password');
    var ventanaUsuario = document.getElementById('ventana-usuario');
    var ventanaConfirma = document.getElementById('ventana-confirma');
    var ventanaClave = document.getElementById('ventana-clave');
    var catalogoRoles = [];
    var catalogoSucursales = [];
    var kpis = {};
    var usuariosVisibles = [];
    var pagina = 1;
    var paginas = 1;
    var idEdicion = null;
    var ultimoAdminEdicion = false;
    var usernameManual = false;
    var ultimaSugerencia = '';
    var timerBusqueda = null;
    var timerUsername = null;
    var passwordTemporal = '';
    var confirmarAccion = null;
    var rolesCargados = false;
    var enListado = false;
    var listadoPendiente = false;
    var tokenUsername = 0;

    var mensajesClaros = {
        'No puede dejar el sistema sin Administrador': true,
        'No puede desactivar su propia cuenta': true,
        'Username ya existe': true,
        'El Operador requiere una sucursal': true,
        'Nombres obligatorios': true,
        'Apellidos obligatorios': true,
        'Rol no disponible': true,
        'Sucursal no disponible': true,
        'Username obligatorio': true,
        'Contraseña obligatoria': true,
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

    function post(cuerpoPeticion) {
        return axios.post(controlador, cuerpoPeticion);
    }

    function rolPorId(idRol) {
        var id = Number(idRol);

        for (var i = 0; i < catalogoRoles.length; i += 1) {
            if (catalogoRoles[i].id_rol === id) {
                return catalogoRoles[i];
            }
        }

        return null;
    }

    function sucursalPorId(idSucursal) {
        var id = Number(idSucursal);

        for (var i = 0; i < catalogoSucursales.length; i += 1) {
            if (catalogoSucursales[i].id_sucursal === id) {
                return catalogoSucursales[i];
            }
        }

        return null;
    }

    function etiquetaSucursal(item) {
        if (!item) {
            return '';
        }

        return item.ciudad ? item.nombre + ' · ' + item.ciudad : item.nombre;
    }

    function textoSucursal(usuario) {
        if (!usuario || usuario.id_sucursal === null || usuario.id_sucursal === undefined) {
            return usuario && usuario.sucursal ? usuario.sucursal : '—';
        }

        var item = sucursalPorId(usuario.id_sucursal);

        return item ? etiquetaSucursal(item) : (usuario.sucursal || '—');
    }

    function esUltimoAdministrador(usuario) {
        return !!usuario
            && usuario.rol === 'Administrador'
            && usuario.estado === true
            && Number(kpis.administradores_activos) === 1;
    }

    function llenarOpciones(select, items, textoVacio, valor, etiqueta) {
        var actual = select.value;

        select.replaceChildren();
        select.appendChild(new Option(textoVacio, ''));
        items.forEach(function (item) {
            select.appendChild(new Option(etiqueta(item), String(valor(item))));
        });

        if (actual && select.querySelector('option[value="' + actual + '"]')) {
            select.value = actual;
        }
    }

    function pintarCatalogos() {
        llenarOpciones(filtroRol, catalogoRoles, 'Todos', function (item) {
            return item.id_rol;
        }, function (item) {
            return item.nombre;
        });
        llenarOpciones(filtroSucursal, catalogoSucursales, 'Todas', function (item) {
            return item.id_sucursal;
        }, etiquetaSucursal);
        llenarOpciones(campoRol, catalogoRoles, 'Seleccione', function (item) {
            return item.id_rol;
        }, function (item) {
            return item.nombre;
        });
        llenarOpciones(campoSucursal, catalogoSucursales, 'Seleccione', function (item) {
            return item.id_sucursal;
        }, etiquetaSucursal);
    }

    function pintarKpis(datos) {
        kpis = datos || {};
        texto('kpi-activos', String(kpis.usuarios_activos ?? '—'));
        texto('kpi-inactivos', String(kpis.usuarios_inactivos ?? '—'));
        texto('kpi-administradores', String(kpis.administradores_activos ?? '—'));
        texto('kpi-administrativos', String(kpis.administrativos_activos ?? '—'));
        texto('kpi-operadores', String(kpis.operadores_activos ?? '—'));
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

    function botonAccion(accion, id, rotulo, titulo, deshabilitado) {
        var boton = elemento('button', 'accion', rotulo);

        boton.type = 'button';
        boton.setAttribute('data-accion', accion);
        boton.setAttribute('data-id', String(id));

        if (titulo) {
            boton.title = titulo;
        }

        if (deshabilitado) {
            boton.disabled = true;
        }

        return boton;
    }

    function pintarFila(usuario) {
        var fila = document.createElement('tr');
        var identidad = document.createElement('div');
        var meta = elemento('div', 'usuario__meta');
        var acciones = elemento('div', 'acciones');
        var propio = Number(usuario.id_usuario) === idSesion;
        var ultimo = esUltimoAdministrador(usuario);
        var tituloEstado = '';

        identidad.appendChild(elemento('p', 'usuario__nombre', usuario.username || '—'));

        if (usuario.inconsistencia === true) {
            meta.appendChild(elemento('span', 'marca marca--revisar', 'Revisar'));
            identidad.appendChild(meta);
        }

        acciones.appendChild(botonAccion('editar', usuario.id_usuario, 'Editar'));

        if (usuario.estado === true && propio) {
            tituloEstado = 'No puede desactivar su propia cuenta';
        } else if (usuario.estado === true && ultimo) {
            tituloEstado = 'No puede dejar el sistema sin Administrador';
        }

        acciones.appendChild(botonAccion(
            'estado',
            usuario.id_usuario,
            usuario.estado === true ? 'Desactivar' : 'Activar',
            tituloEstado,
            tituloEstado !== ''
        ));
        acciones.appendChild(botonAccion('clave', usuario.id_usuario, 'Restablecer'));

        fila.appendChild(celda('Usuario', identidad));
        fila.appendChild(celda('Nombre completo', ((usuario.nombres || '') + ' ' + (usuario.apellidos || '')).trim() || '—'));
        fila.appendChild(celda('Rol', usuario.rol || '—'));
        fila.appendChild(celda('Sucursal', textoSucursal(usuario)));
        fila.appendChild(celda('Estado', elemento(
            'span',
            'estado ' + (usuario.estado === true ? 'estado--ok' : 'estado--inactivo'),
            usuario.estado === true ? 'Activo' : 'Inactivo'
        )));
        fila.appendChild(celda('Acciones', acciones));

        return fila;
    }

    function pintarListado(datos) {
        var todos = Array.isArray(datos.usuarios) ? datos.usuarios : [];
        var sucursal = filtroSucursal ? filtroSucursal.value : '';

        usuariosVisibles = sucursal === '' ? todos : todos.filter(function (usuario) {
            return String(usuario.id_sucursal) === sucursal;
        });
        paginas = Number(datos.paginas) || 0;
        pagina = Number(datos.pagina) || 1;
        ocultar('nota-sucursal', !(sucursal !== '' && paginas > 1));
        ocultar('carga-usuarios', true);
        ocultar('aviso-usuarios', true);
        ocultar('vacio-usuarios', usuariosVisibles.length !== 0);

        if (cuerpo) {
            cuerpo.replaceChildren();
            usuariosVisibles.forEach(function (usuario) {
                cuerpo.appendChild(pintarFila(usuario));
            });
        }

        texto('pagina-texto', paginas === 0 ? 'Página 0 de 0' : 'Página ' + pagina + ' de ' + paginas);
        ocultar('paginacion', paginas <= 1);
        var anterior = document.getElementById('pagina-anterior');
        var siguiente = document.getElementById('pagina-siguiente');

        if (anterior) {
            anterior.disabled = pagina <= 1;
        }

        if (siguiente) {
            siguiente.disabled = paginas === 0 || pagina >= paginas;
        }
    }

    function cargarListado() {
        if (typeof axios === 'undefined') {
            ocultar('carga-usuarios', true);
            ocultar('aviso-usuarios', false);
            return Promise.resolve();
        }

        if (enListado) {
            listadoPendiente = true;
            return Promise.resolve();
        }

        enListado = true;
        ocultar('carga-usuarios', false);
        var peticion = {
            op: 'listarUsuarios',
            pagina: pagina
        };
        var busqueda = buscar ? buscar.value.trim() : '';

        if (busqueda !== '') {
            peticion.busqueda = busqueda;
        }

        if (filtroEstado && filtroEstado.value === '1') {
            peticion.estado = true;
        } else if (filtroEstado && filtroEstado.value === '0') {
            peticion.estado = false;
        }

        if (filtroRol && filtroRol.value !== '') {
            peticion.id_rol = Number(filtroRol.value);
        }

        return post(peticion).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true || !Array.isArray(datos.usuarios)) {
                ocultar('carga-usuarios', true);
                ocultar('aviso-usuarios', false);
                return;
            }

            catalogoRoles = Array.isArray(datos.roles) ? datos.roles : [];
            catalogoSucursales = Array.isArray(datos.sucursales) ? datos.sucursales : [];
            pintarCatalogos();
            pintarKpis(datos.kpis || {});
            pintarListado(datos);
        }).catch(function () {
            ocultar('carga-usuarios', true);
            ocultar('aviso-usuarios', false);
        }).finally(function () {
            enListado = false;

            if (listadoPendiente) {
                listadoPendiente = false;
                cargarListado();
            }
        });
    }

    function mostrarExito(mensaje) {
        var nodo = document.getElementById('exito-usuarios');

        if (!nodo) {
            return;
        }

        nodo.textContent = mensaje;
        nodo.hidden = false;
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

    function limpiarFormulario() {
        idEdicion = null;
        ultimoAdminEdicion = false;
        usernameManual = false;
        ultimaSugerencia = '';
        document.getElementById('formulario-usuario').reset();
        campoUsername.readOnly = false;
        ocultar('bloque-password', false);
        ocultar('bloque-sucursal', true);
        ocultar('username-sugerido', true);
        ocultar('aviso-formulario', true);
        ocultar('carga-formulario', true);
        campoPassword.value = '';
        texto('titulo-ventana', 'Nuevo usuario');
    }

    function sincronizarSucursal() {
        var rol = rolPorId(campoRol.value);
        var operador = rol && rol.nombre === 'Operador';

        ocultar('bloque-sucursal', !operador);

        if (!operador && campoSucursal) {
            campoSucursal.value = '';
        }

        if (campoRol) {
            Array.prototype.forEach.call(campoRol.options, function (opcion) {
                opcion.disabled = ultimoAdminEdicion && opcion.value !== '' && opcion.textContent !== 'Administrador';
            });
        }
    }

    function proponer() {
        if (idEdicion !== null || usernameManual || typeof axios === 'undefined') {
            return;
        }

        var nombres = document.getElementById('campo-nombres').value.trim();
        var apellidos = document.getElementById('campo-apellidos').value.trim();

        if (nombres === '' || apellidos === '') {
            return;
        }

        var token = ++tokenUsername;

        post({
            op: 'proponerUsername',
            nombres: nombres,
            apellidos: apellidos
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (token !== tokenUsername || idEdicion !== null || usernameManual || datos.exito !== true) {
                return;
            }

            ultimaSugerencia = datos.username || '';
            campoUsername.value = ultimaSugerencia;
            texto('username-sugerido', 'Username sugerido: ' + ultimaSugerencia);
            ocultar('username-sugerido', ultimaSugerencia === '');
        }).catch(function () {
            ocultar('username-sugerido', true);
        });
    }

    function programarUsername() {
        window.clearTimeout(timerUsername);
        timerUsername = window.setTimeout(proponer, 500);
    }

    function abrirCreacion() {
        limpiarFormulario();
        abrirVentana(ventanaUsuario);
    }

    function abrirEdicion(id) {
        limpiarFormulario();
        idEdicion = id;
        campoUsername.readOnly = true;
        ocultar('bloque-password', true);
        ocultar('username-sugerido', true);
        texto('titulo-ventana', 'Editar usuario');
        ocultar('carga-formulario', false);
        abrirVentana(ventanaUsuario);

        post({
            op: 'obtenerDetalleUsuario',
            id_usuario: id
        }).then(function (respuesta) {
            var datos = respuesta.data || {};
            var usuario = datos.usuario || {};

            if (datos.exito !== true) {
                texto('aviso-formulario', mensajeClaro(datos.mensaje) || 'No se pudo consultar el usuario.');
                ocultar('aviso-formulario', false);
                return;
            }

            document.getElementById('campo-nombres').value = usuario.nombres || '';
            document.getElementById('campo-apellidos').value = usuario.apellidos || '';
            campoUsername.value = usuario.username || '';
            ultimoAdminEdicion = esUltimoAdministrador(usuario);

            if (usuario.id_rol) {
                campoRol.value = String(usuario.id_rol);
            }

            sincronizarSucursal();

            if (usuario.id_sucursal) {
                campoSucursal.value = String(usuario.id_sucursal);
            }
        }).catch(function () {
            texto('aviso-formulario', 'No se pudo consultar el usuario.');
            ocultar('aviso-formulario', false);
        }).finally(function () {
            ocultar('carga-formulario', true);
        });
    }

    function guardarUsuario(evento) {
        evento.preventDefault();
        var nombres = document.getElementById('campo-nombres').value.trim();
        var apellidos = document.getElementById('campo-apellidos').value.trim();
        var rol = rolPorId(campoRol.value);
        var peticion = {
            nombres: nombres,
            apellidos: apellidos,
            id_rol: rol ? rol.id_rol : null
        };

        if (!rol) {
            texto('aviso-formulario', 'Rol no disponible');
            ocultar('aviso-formulario', false);
            return;
        }

        if (rol.nombre === 'Operador') {
            if (!campoSucursal.value) {
                texto('aviso-formulario', 'El Operador requiere una sucursal');
                ocultar('aviso-formulario', false);
                return;
            }

            peticion.id_sucursal = Number(campoSucursal.value);
        }

        if (idEdicion === null) {
            peticion.op = 'crearUsuario';
            peticion.username = campoUsername.value.trim();
            peticion.password = campoPassword.value;

            if (peticion.username === '') {
                texto('aviso-formulario', 'Username obligatorio');
                ocultar('aviso-formulario', false);
                return;
            }

            if (peticion.password === '') {
                texto('aviso-formulario', 'Contraseña obligatoria');
                ocultar('aviso-formulario', false);
                return;
            }
        } else {
            peticion.op = 'actualizarUsuario';
            peticion.id_usuario = idEdicion;
        }

        ocultar('aviso-formulario', true);
        document.getElementById('guardar-usuario').disabled = true;

        post(peticion).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true) {
                texto('aviso-formulario', mensajeClaro(datos.mensaje) || 'No se pudo guardar el usuario.');
                ocultar('aviso-formulario', false);
                return;
            }

            campoPassword.value = '';
            cerrarVentana(ventanaUsuario);
            mostrarExito(idEdicion === null ? 'Usuario creado.' : 'Usuario actualizado.');
            cargarListado();
        }).catch(function () {
            texto('aviso-formulario', 'No se pudo guardar el usuario.');
            ocultar('aviso-formulario', false);
        }).finally(function () {
            document.getElementById('guardar-usuario').disabled = false;
        });
    }

    function pedirConfirmacion(mensaje, accion) {
        confirmarAccion = accion;
        texto('texto-confirma', mensaje);
        texto('titulo-confirma', 'Confirmar');
        ocultar('aceptar-confirma', false);
        ocultar('cancelar-confirma', false);
        abrirVentana(ventanaConfirma);
    }

    function cambiarEstado(usuario) {
        var activar = usuario.estado !== true;

        pedirConfirmacion(activar ? '¿Desea activar este usuario?' : '¿Desea desactivar este usuario?', function () {
            return post({
                op: 'cambiarEstadoUsuario',
                id_usuario: usuario.id_usuario,
                nuevo_estado: activar
            }).then(function (respuesta) {
                var datos = respuesta.data || {};
                var claro = mensajeClaro(datos.mensaje);

                if (datos.exito !== true) {
                    texto('texto-confirma', claro || 'No se pudo cambiar el estado del usuario.');
                    texto('titulo-confirma', 'No se pudo cambiar el estado');
                    ocultar('aceptar-confirma', true);
                    ocultar('cancelar-confirma', true);
                    return;
                }

                cerrarVentana(ventanaConfirma);
                mostrarExito(activar ? 'Usuario activado.' : 'Usuario desactivado.');
                cargarListado();
            }).catch(function () {
                texto('texto-confirma', 'No se pudo cambiar el estado del usuario.');
                ocultar('aceptar-confirma', true);
            });
        });
    }

    function limpiarClave() {
        passwordTemporal = '';
        texto('clave-temporal', '');
        ocultar('aviso-copia', true);
    }

    function restablecer(usuario) {
        pedirConfirmacion('¿Desea restablecer la contraseña de este usuario?', function () {
            return post({
                op: 'restablecerPassword',
                id_usuario: usuario.id_usuario
            }).then(function (respuesta) {
                var datos = respuesta.data || {};

                if (datos.exito !== true || typeof datos.password_temporal !== 'string' || datos.password_temporal === '') {
                    texto('texto-confirma', mensajeClaro(datos.mensaje) || 'No se pudo restablecer la contraseña.');
                    ocultar('aceptar-confirma', true);
                    return;
                }

                passwordTemporal = datos.password_temporal;
                texto('clave-temporal', passwordTemporal);
                cerrarVentana(ventanaConfirma);
                abrirVentana(ventanaClave);
            }).catch(function () {
                texto('texto-confirma', 'No se pudo restablecer la contraseña.');
                ocultar('aceptar-confirma', true);
            });
        });
    }

    function usuarioVisible(id) {
        for (var i = 0; i < usuariosVisibles.length; i += 1) {
            if (Number(usuariosVisibles[i].id_usuario) === id) {
                return usuariosVisibles[i];
            }
        }

        return null;
    }

    function pintarRoles(roles) {
        var lista = document.getElementById('lista-roles');

        if (!lista) {
            return;
        }

        lista.replaceChildren();
        roles.forEach(function (rol) {
            var article = elemento('article', 'rol');
            var permisos = Array.isArray(rol.permisos) ? rol.permisos : [];
            var items = document.createElement('ul');

            article.appendChild(elemento('h3', '', rol.nombre || 'Rol'));
            article.appendChild(elemento('p', '', rol.descripcion || 'Sin descripción'));
            article.appendChild(elemento('p', 'rol__cuenta', permisos.length + (permisos.length === 1 ? ' permiso' : ' permisos')));
            permisos.forEach(function (permiso) {
                var item = document.createElement('li');
                item.appendChild(elemento('code', '', permiso.codigo || ''));
                item.appendChild(elemento('strong', '', ' ' + (permiso.nombre || '')));

                if (permiso.descripcion) {
                    item.appendChild(elemento('small', '', permiso.descripcion));
                }

                items.appendChild(item);
            });
            article.appendChild(items);
            lista.appendChild(article);
        });
    }

    function cargarRoles() {
        ocultar('carga-roles', false);
        ocultar('aviso-roles', true);

        return post({ op: 'obtenerRolesPermisos' }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true || !Array.isArray(datos.roles)) {
                ocultar('aviso-roles', false);
                return;
            }

            rolesCargados = true;
            pintarRoles(datos.roles);
        }).catch(function () {
            ocultar('aviso-roles', false);
        }).finally(function () {
            ocultar('carga-roles', true);
        });
    }

    function activarPestana(usuarios) {
        var tabUsuarios = document.getElementById('tab-usuarios');
        var tabRoles = document.getElementById('tab-roles');

        tabUsuarios.classList.toggle('pestanas__opcion--activa', usuarios);
        tabRoles.classList.toggle('pestanas__opcion--activa', !usuarios);
        tabUsuarios.setAttribute('aria-selected', usuarios ? 'true' : 'false');
        tabRoles.setAttribute('aria-selected', usuarios ? 'false' : 'true');
        ocultar('panel-usuarios', !usuarios);
        ocultar('panel-roles', usuarios);
        ocultar('nuevo-usuario', !usuarios);

        if (!usuarios && !rolesCargados) {
            cargarRoles();
        }
    }

    function mostrarMensaje(textoMensaje) {
        if (!mensajeSesion) {
            return;
        }

        mensajeSesion.textContent = textoMensaje;
        mensajeSesion.hidden = false;
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

    document.getElementById('tab-usuarios').addEventListener('click', function () {
        activarPestana(true);
    });
    document.getElementById('tab-roles').addEventListener('click', function () {
        activarPestana(false);
    });
    document.getElementById('nuevo-usuario').addEventListener('click', abrirCreacion);
    document.getElementById('formulario-usuario').addEventListener('submit', guardarUsuario);
    document.getElementById('cerrar-usuario').addEventListener('click', function () {
        campoPassword.value = '';
        cerrarVentana(ventanaUsuario);
    });
    document.getElementById('campo-nombres').addEventListener('input', programarUsername);
    document.getElementById('campo-apellidos').addEventListener('input', programarUsername);
    document.getElementById('campo-nombres').addEventListener('blur', proponer);
    document.getElementById('campo-apellidos').addEventListener('blur', proponer);
    campoUsername.addEventListener('input', function () {
        usernameManual = campoUsername.value !== '' && campoUsername.value !== ultimaSugerencia;
    });
    campoRol.addEventListener('change', sincronizarSucursal);
    document.getElementById('ver-password').addEventListener('click', function () {
        var visible = campoPassword.type === 'text';
        campoPassword.type = visible ? 'password' : 'text';
        this.textContent = visible ? 'Mostrar' : 'Ocultar';
    });
    document.getElementById('filtros').addEventListener('submit', function (evento) {
        evento.preventDefault();
    });

    if (buscar) {
        buscar.addEventListener('input', function () {
            window.clearTimeout(timerBusqueda);
            timerBusqueda = window.setTimeout(function () {
                pagina = 1;
                cargarListado();
            }, 400);
        });
    }

    [filtroRol, filtroEstado].forEach(function (filtro) {
        if (!filtro) {
            return;
        }

        filtro.addEventListener('change', function () {
            pagina = 1;
            cargarListado();
        });
    });

    if (filtroSucursal) {
        filtroSucursal.addEventListener('change', function () {
            cargarListado();
        });
    }

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

    if (cuerpo) {
        cuerpo.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-accion]');

            if (!boton || boton.disabled) {
                return;
            }

            var usuario = usuarioVisible(Number(boton.getAttribute('data-id')));

            if (!usuario) {
                return;
            }

            if (boton.getAttribute('data-accion') === 'editar') {
                abrirEdicion(usuario.id_usuario);
            } else if (boton.getAttribute('data-accion') === 'estado') {
                cambiarEstado(usuario);
            } else if (boton.getAttribute('data-accion') === 'clave') {
                restablecer(usuario);
            }
        });
    }

    document.getElementById('aceptar-confirma').addEventListener('click', function () {
        if (!confirmarAccion) {
            cerrarVentana(ventanaConfirma);
            return;
        }

        var accion = confirmarAccion;
        this.disabled = true;
        Promise.resolve(accion()).finally(function () {
            document.getElementById('aceptar-confirma').disabled = false;
        });
    });
    document.getElementById('cancelar-confirma').addEventListener('click', function () {
        cerrarVentana(ventanaConfirma);
    });
    document.getElementById('cerrar-confirma').addEventListener('click', function () {
        cerrarVentana(ventanaConfirma);
    });
    document.getElementById('copiar-clave').addEventListener('click', function () {
        var valor = passwordTemporal;

        if (valor === '') {
            return;
        }

        var copiar = navigator.clipboard && navigator.clipboard.writeText
            ? navigator.clipboard.writeText(valor)
            : Promise.reject();

        copiar.then(function () {
            texto('aviso-copia', 'Contraseña copiada.');
            ocultar('aviso-copia', false);
        }).catch(function () {
            var nodo = document.getElementById('clave-temporal');
            var seleccion = window.getSelection();

            if (nodo && seleccion) {
                var rango = document.createRange();
                rango.selectNodeContents(nodo);
                seleccion.removeAllRanges();
                seleccion.addRange(rango);
            }

            texto('aviso-copia', 'No se pudo copiar. Selecciónela manualmente.');
            ocultar('aviso-copia', false);
        });
    });
    function cerrarClave() {
        limpiarClave();
        cerrarVentana(ventanaClave);
    }

    document.getElementById('cerrar-clave').addEventListener('click', cerrarClave);

    if (ventanaClave) {
        ventanaClave.addEventListener('close', limpiarClave);
        ventanaClave.addEventListener('cancel', limpiarClave);
    }

    [ventanaUsuario, ventanaConfirma, ventanaClave].forEach(function (dialogo) {
        if (!dialogo) {
            return;
        }

        dialogo.addEventListener('click', function (evento) {
            if (evento.target !== dialogo) {
                return;
            }

            if (dialogo === ventanaClave) {
                limpiarClave();
            }

            dialogo.close();
        });
    });

    if (typeof axios === 'undefined') {
        ocultar('carga-usuarios', true);
        ocultar('aviso-usuarios', false);
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
