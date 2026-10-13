(function () {
    var controlador = 'app/Controllers/FicheroClientesController.php';
    var busqueda = document.getElementById('filtro-busqueda');
    var aviso = document.getElementById('estado-consulta');
    var avisoAlta = document.getElementById('aviso-alta');
    var marco = document.getElementById('marco-clientes');
    var cuerpo = document.getElementById('cuerpo-clientes');
    var anterior = document.getElementById('pagina-anterior');
    var siguiente = document.getElementById('pagina-siguiente');
    var indicador = document.getElementById('pagina-indicador');
    var resumen = document.getElementById('resumen-pagina');
    var dialogo = document.getElementById('alta-cliente');
    var formAlta = document.getElementById('form-alta');
    var tipo = document.getElementById('alta-tipo');
    var identificacion = document.getElementById('alta-identificacion');
    var razon = document.getElementById('alta-razon');
    var direccion = document.getElementById('alta-direccion');
    var telefono = document.getElementById('alta-telefono');
    var correo = document.getElementById('alta-correo');
    var ayuda = document.getElementById('ayuda-identificacion');
    var errorAlta = document.getElementById('error-alta');
    var registrar = document.getElementById('registrar-cliente');
    var pagina = 1;
    var totalPaginas = 0;
    var enCurso = false;
    var pendiente = false;
    var guardando = false;
    var esperaBusqueda = 0;
    var ayudas = {
        CEDULA: '10 dígitos',
        RUC: '13 dígitos',
        PASAPORTE: 'Máximo 20 caracteres'
    };
    var tipos = {
        CEDULA: 'Cédula',
        RUC: 'RUC',
        PASAPORTE: 'Pasaporte',
        CONSUMIDOR_FINAL: 'Consumidor final'
    };

    function limpiarNodo(nodo) {
        while (nodo.firstChild) {
            nodo.removeChild(nodo.firstChild);
        }
    }

    function elemento(etiqueta, clase, texto) {
        var nodo = document.createElement(etiqueta);
        if (clase) {
            nodo.className = clase;
        }
        if (texto != null) {
            nodo.textContent = texto;
        }
        return nodo;
    }

    function textoPresente(valor) {
        return valor != null && String(valor).trim() !== '';
    }

    function mostrarAviso(mensaje, error) {
        if (!mensaje) {
            aviso.hidden = true;
            aviso.textContent = '';
            aviso.classList.remove('consulta__estado--error');
            return;
        }
        aviso.hidden = false;
        aviso.textContent = mensaje;
        aviso.classList.toggle('consulta__estado--error', error === true);
    }

    function mostrarExito(mensaje) {
        if (!mensaje) {
            avisoAlta.hidden = true;
            avisoAlta.textContent = '';
            return;
        }
        avisoAlta.hidden = false;
        avisoAlta.textContent = mensaje;
    }

    function mostrarErrorAlta(mensaje) {
        if (!mensaje) {
            errorAlta.hidden = true;
            errorAlta.textContent = '';
            return;
        }
        errorAlta.hidden = false;
        errorAlta.textContent = mensaje;
    }

    function mensajeError(error) {
        if (!error || !error.response) {
            return '';
        }
        if (error.response.status === 403) {
            return 'Sesión no disponible. Vuelva a ingresar.';
        }
        var datos = error.response.data || {};
        return typeof datos.mensaje === 'string' ? datos.mensaje : '';
    }

    function marcarEspera(activo) {
        enCurso = activo;
        marco.classList.toggle('listado--espera', activo && cuerpo.querySelector('.persona'));
        marco.setAttribute('aria-busy', activo ? 'true' : 'false');
        anterior.disabled = activo || pagina <= 1;
        siguiente.disabled = activo || totalPaginas < 1 || pagina >= totalPaginas;
        if (activo) {
            mostrarAviso('Cargando clientes...');
        }
    }

    function tipoVisible(valor) {
        return tipos[valor] || valor || '—';
    }

    function badgeEstado(activo) {
        if (activo === true) {
            return elemento('span', 'badge badge--activo', 'Activo');
        }
        if (activo === false) {
            return elemento('span', 'badge badge--inactivo', 'Inactivo');
        }
        return elemento('span', 'badge', '—');
    }

    function pintarVacio(mensaje) {
        limpiarNodo(cuerpo);
        var fila = document.createElement('tr');
        var celda = elemento('td', null, mensaje);
        fila.className = 'vacio-fila';
        celda.colSpan = 5;
        fila.appendChild(celda);
        cuerpo.appendChild(fila);
    }

    function pintarFilas(filas) {
        limpiarNodo(cuerpo);
        if (!filas.length) {
            pintarVacio('No se encontraron clientes con la búsqueda indicada.');
            return;
        }
        filas.forEach(function (fila) {
            var tr = document.createElement('tr');
            var cliente = elemento('td', 'persona');
            var estadoCelda = document.createElement('td');
            var direccionCelda = textoPresente(fila.direccion)
                ? elemento('td', null, String(fila.direccion).trim())
                : elemento('td', 'direccion--vacia', 'Sin dirección');
            cliente.appendChild(elemento('strong', null, fila.razon_social || '—'));
            if (textoPresente(fila.telefono)) {
                cliente.appendChild(elemento('small', null, String(fila.telefono).trim()));
            }
            if (textoPresente(fila.correo)) {
                cliente.appendChild(elemento('small', null, String(fila.correo).trim()));
            }
            estadoCelda.appendChild(badgeEstado(fila.estado));
            tr.appendChild(elemento('td', 'identificacion', fila.identificacion || '—'));
            tr.appendChild(elemento('td', null, tipoVisible(fila.tipo_identificacion)));
            tr.appendChild(cliente);
            tr.appendChild(direccionCelda);
            tr.appendChild(estadoCelda);
            cuerpo.appendChild(tr);
        });
    }

    function pintarPagina(datos, cantidad) {
        var total = Number(datos.total_registros) || 0;
        var porPagina = Number(datos.por_pagina) || 20;
        var inicio = total === 0 ? 0 : ((pagina - 1) * porPagina) + 1;
        var fin = total === 0 ? 0 : inicio + cantidad - 1;
        totalPaginas = Number(datos.total_paginas) || 0;
        resumen.textContent = total === 0
            ? 'Mostrando 0 de 0 clientes'
            : 'Mostrando ' + inicio + '–' + fin + ' de ' + total + ' clientes';
        indicador.textContent = 'Página ' + (totalPaginas < 1 ? 1 : datos.pagina_actual) + ' de ' + (totalPaginas < 1 ? 1 : totalPaginas);
        anterior.disabled = pagina <= 1;
        siguiente.disabled = totalPaginas < 1 || pagina >= totalPaginas;
    }

    function consultar() {
        if (typeof axios === 'undefined') {
            mostrarAviso('No se pudo conectar. Inténtalo de nuevo.', true);
            return;
        }
        enCurso = true;
        pendiente = false;
        marcarEspera(true);
        axios.post(controlador, {
            op: 'listarClientes',
            busqueda: busqueda.value.trim(),
            pagina: pagina
        }, { timeout: 25000 }).then(function (respuesta) {
            if (pendiente) {
                return;
            }
            var datos = respuesta.data || {};
            if (datos.exito !== true || !Array.isArray(datos.filas)) {
                mostrarAviso(datos.mensaje || 'No se pudo consultar los clientes.', true);
                if (!cuerpo.querySelector('.persona')) {
                    pintarVacio(datos.mensaje || 'No se pudo consultar los clientes.');
                }
                return;
            }
            mostrarAviso('');
            pintarFilas(datos.filas);
            pintarPagina(datos, datos.filas.length);
        }).catch(function (error) {
            if (pendiente) {
                return;
            }
            var texto = mensajeError(error) || 'No se pudo consultar los clientes.';
            mostrarAviso(texto, true);
            if (!cuerpo.querySelector('.persona')) {
                pintarVacio(texto);
            }
        }).finally(function () {
            marcarEspera(false);
            if (pendiente) {
                consultar();
            }
        });
    }

    function solicitar(reiniciar) {
        if (reiniciar) {
            pagina = 1;
        }
        if (enCurso) {
            pendiente = true;
            return;
        }
        consultar();
    }

    function identificacionValida(tipoValor, valor) {
        if (valor === '9999999999999') {
            return false;
        }
        if (tipoValor === 'CEDULA') {
            return /^[0-9]{10}$/.test(valor);
        }
        if (tipoValor === 'RUC') {
            return /^[0-9]{13}$/.test(valor);
        }
        if (tipoValor === 'PASAPORTE') {
            return valor.length >= 1 && valor.length <= 20;
        }
        return false;
    }

    function opcional(valor) {
        var texto = valor.trim();
        return texto === '' ? null : texto;
    }

    function limpiarFormulario() {
        formAlta.reset();
        tipo.value = 'CEDULA';
        ayuda.textContent = ayudas.CEDULA;
        mostrarErrorAlta('');
        registrar.disabled = false;
        registrar.textContent = 'Registrar cliente';
        document.getElementById('cancelar-alta').disabled = false;
        document.getElementById('cerrar-alta').disabled = false;
    }

    function abrirAlta() {
        if (guardando) {
            return;
        }
        limpiarFormulario();
        document.querySelector('.turno').inert = true;
        if (!dialogo.open) {
            dialogo.showModal();
        }
        identificacion.focus();
    }

    function cerrarAlta() {
        if (guardando) {
            return;
        }
        limpiarFormulario();
        document.querySelector('.turno').inert = false;
        if (dialogo.open) {
            dialogo.close();
        }
    }

    function registrarCliente(evento) {
        evento.preventDefault();
        if (guardando || typeof axios === 'undefined') {
            return;
        }
        var tipoValor = tipo.value;
        var identificacionValor = identificacion.value.trim();
        var razonValor = razon.value.trim();
        if (razonValor === '') {
            mostrarErrorAlta('Razón social obligatoria');
            razon.focus();
            return;
        }
        if (!identificacionValida(tipoValor, identificacionValor)) {
            mostrarErrorAlta('Identificación inválida');
            identificacion.focus();
            return;
        }
        guardando = true;
        mostrarErrorAlta('');
        registrar.disabled = true;
        registrar.textContent = 'Registrando...';
        document.getElementById('cancelar-alta').disabled = true;
        document.getElementById('cerrar-alta').disabled = true;
        axios.post(controlador, {
            op: 'crearCliente',
            tipo_identificacion: tipoValor,
            identificacion: identificacionValor,
            razon_social: razonValor,
            direccion: opcional(direccion.value),
            telefono: opcional(telefono.value),
            correo: opcional(correo.value)
        }, { timeout: 25000 }).then(function (respuesta) {
            var datos = respuesta.data || {};
            if (datos.exito !== true) {
                mostrarErrorAlta(datos.mensaje || 'No se pudo registrar el cliente.');
                return;
            }
            guardando = false;
            cerrarAlta();
            mostrarExito('El cliente ya está disponible para facturación.');
            busqueda.value = identificacionValor;
            window.clearTimeout(esperaBusqueda);
            solicitar(true);
        }).catch(function (error) {
            mostrarErrorAlta(mensajeError(error) || 'No se pudo registrar el cliente.');
        }).finally(function () {
            guardando = false;
            registrar.disabled = false;
            registrar.textContent = 'Registrar cliente';
            document.getElementById('cancelar-alta').disabled = false;
            document.getElementById('cerrar-alta').disabled = false;
        });
    }

    busqueda.addEventListener('input', function () {
        mostrarExito('');
        window.clearTimeout(esperaBusqueda);
        esperaBusqueda = window.setTimeout(function () {
            solicitar(true);
        }, 320);
    });
    document.getElementById('filtros').addEventListener('submit', function (evento) {
        evento.preventDefault();
        window.clearTimeout(esperaBusqueda);
        solicitar(true);
    });
    anterior.addEventListener('click', function () {
        if (pagina > 1) {
            pagina -= 1;
            solicitar(false);
        }
    });
    siguiente.addEventListener('click', function () {
        if (totalPaginas > 0 && pagina < totalPaginas) {
            pagina += 1;
            solicitar(false);
        }
    });
    document.getElementById('nuevo-cliente').addEventListener('click', abrirAlta);
    document.getElementById('cancelar-alta').addEventListener('click', cerrarAlta);
    document.getElementById('cerrar-alta').addEventListener('click', cerrarAlta);
    tipo.addEventListener('change', function () {
        ayuda.textContent = ayudas[tipo.value] || '';
    });
    formAlta.addEventListener('submit', registrarCliente);
    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && (dialogo.open || document.querySelector('.turno').inert)) {
            cerrarAlta();
        }
    });

    var botonSesion = document.querySelector('[data-cerrar-sesion]');
    var mensajeSesion = document.getElementById('mensaje-sesion');
    botonSesion.addEventListener('click', function () {
        if (botonSesion.disabled) {
            return;
        }
        mensajeSesion.hidden = true;
        botonSesion.disabled = true;
        if (typeof axios === 'undefined') {
            mensajeSesion.hidden = false;
            mensajeSesion.textContent = 'No se pudo conectar. Inténtalo de nuevo.';
            botonSesion.disabled = false;
            return;
        }
        var redirigio = false;
        axios.post('app/Controllers/AuthController.php', { op: 'cerrarSesion' }).then(function (respuesta) {
            var datos = respuesta.data || {};
            var ruta = typeof datos.redireccion === 'string' ? datos.redireccion.trim() : '';
            var local = ruta !== '' && ruta.length <= 200 && !/^[a-z][a-z0-9+.-]*:/i.test(ruta) && ruta.indexOf('//') !== 0 && ruta.indexOf('\\') === -1;
            if (datos.exito === true && local) {
                redirigio = true;
                window.location.href = ruta;
                return;
            }
            mensajeSesion.hidden = false;
            mensajeSesion.textContent = 'No se pudo cerrar la sesión.';
        }).catch(function () {
            mensajeSesion.hidden = false;
            mensajeSesion.textContent = 'No se pudo conectar. Inténtalo de nuevo.';
        }).finally(function () {
            if (!redirigio) {
                botonSesion.disabled = false;
            }
        });
    });

    solicitar(true);
}());
