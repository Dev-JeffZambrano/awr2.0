(function () {
    var controlador = 'app/Controllers/BasesDatosAdministradorController.php';
    var botonSesion = document.getElementById('cerrar-sesion');
    var mensaje = document.getElementById('mensaje-sesion');
    var boton = document.getElementById('actualizar');
    var motores = document.getElementById('motores');
    var dominios = document.getElementById('dominios');
    var listaTablas = document.getElementById('lista-tablas');
    var buscarTabla = document.getElementById('buscar-tabla');
    var motorDiccionario = document.getElementById('motor-diccionario');
    var dialogo = document.getElementById('modelo-completo');
    var motorModelo = 'postgresql';
    var tablaSeleccionada = '';
    var cacheModelo = {};
    var cacheDetalle = {};
    var detalleToken = 0;
    var enCurso = false;

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

    function aviso(id, visible) {
        ocultar(id, !visible);
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

    function cantidad(valor) {
        if (typeof valor === 'number' && isFinite(valor) && valor >= 0) {
            return valor;
        }

        if (typeof valor === 'string' && /^\d+(?:\.\d+)?$/.test(valor.trim())) {
            return Number(valor);
        }

        return null;
    }

    function decimal(valor) {
        var textoNumero = valor.toFixed(1).replace('.', ',');

        if (textoNumero.slice(-2) === ',0') {
            return textoNumero.slice(0, -2);
        }

        return textoNumero;
    }

    function tamano(valor) {
        var bytes = cantidad(valor);

        if (bytes === null) {
            return 'No disponible';
        }

        var cifra = bytes / 1024;
        var unidad = 'KB';

        if (bytes >= 1073741824) {
            cifra = bytes / 1073741824;
            unidad = 'GB';
        } else if (bytes >= 1048576) {
            cifra = bytes / 1048576;
            unidad = 'MB';
        }

        return decimal(cifra) + ' ' + unidad;
    }

    function milisegundos(valor) {
        var numero = entero(valor);

        return numero === null ? 'No disponible' : numero + ' ms';
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

    function plano(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return 'No disponible';
        }

        if (typeof valor === 'number' && isFinite(valor)) {
            return String(valor);
        }

        return typeof valor === 'string' ? valor : 'No disponible';
    }

    function grupo(estado) {
        if (estado === 'Operativo' || estado === 'Conectado') {
            return 'ok';
        }

        if (estado === 'No disponible') {
            return 'error';
        }

        return 'pendiente';
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

    function metrica(titulo, valor, nota) {
        var bloque = document.createElement('div');
        var termino = elemento('dt', '', titulo);
        var dato = elemento('dd', '', valor);

        bloque.appendChild(termino);
        bloque.appendChild(dato);

        if (nota) {
            dato.appendChild(elemento('small', '', nota));
        }

        return bloque;
    }

    function post(cuerpo) {
        return axios.post(controlador, cuerpo);
    }

    function pintarMotores(datos) {
        var lista = Array.isArray(datos.motores) ? datos.motores : [];

        if (!motores) {
            return;
        }

        motores.replaceChildren();
        lista.forEach(function (motor) {
            motores.appendChild(tarjetaMotor(motor || {}));
        });
        aviso('aviso-resumen', false);
        ocultar('carga-resumen', true);
    }

    function tarjetaMotor(motor) {
        var estado = typeof motor.estado === 'string' && motor.estado !== '' ? motor.estado : 'No disponible';
        var tipo = grupo(estado);
        var article = elemento('article', 'motor motor--' + tipo);
        var cabeza = elemento('div', 'motor__cabeza');
        var identidad = document.createElement('div');
        var nombre = elemento('h3', '', motor.nombre || 'Motor');
        var rol = elemento('p', 'motor__rol', motor.id === 'clickhouse' ? 'OLAP / Analítico' : 'OLTP / Transaccional');
        var badge = elemento('p', 'estado estado--' + tipo, estado);

        identidad.appendChild(nombre);
        identidad.appendChild(rol);
        cabeza.appendChild(identidad);
        cabeza.appendChild(badge);
        article.appendChild(cabeza);

        if (motor.id === 'clickhouse') {
            if (motor.disponible !== true) {
                article.appendChild(elemento('p', 'motor__rol', 'Motor analítico pendiente de integración.'));

                return article;
            }

            var analitico = elemento('dl', 'metricas');
            analitico.appendChild(metrica('Versión', plano(motor.version)));
            analitico.appendChild(metrica('Base', plano(motor.base)));
            analitico.appendChild(metrica('Tablas', plano(motor.tablas)));
            analitico.appendChild(metrica('Tamaño', tamano(motor.tamano_bytes)));
            analitico.appendChild(metrica('Respuesta observada', milisegundos(motor.tiempo_respuesta_ms)));
            analitico.appendChild(metrica('Puerto HTTP', plano(motor.puerto)));
            article.appendChild(analitico);

            return article;
        }

        var datos = elemento('dl', 'metricas');
        datos.appendChild(metrica('Versión', plano(motor.version)));
        datos.appendChild(metrica('Base', plano(motor.base)));
        datos.appendChild(metrica('Tablas', plano(motor.tablas)));
        datos.appendChild(metrica('Tamaño', tamano(motor.tamano_bytes)));
        datos.appendChild(metrica('Conexiones', plano(motor.conexiones)));
        datos.appendChild(metrica('Respuesta PostgreSQL', milisegundos(motor.tiempo_respuesta_ms)));
        datos.appendChild(metrica('Puerto', plano(motor.puerto)));
        datos.appendChild(metrica('Latencia de Red', milisegundosDecimales(motor.latencia_red_ms), 'AWR ↔ PostgreSQL'));
        article.appendChild(datos);

        return article;
    }

    function gruposModelo(datos) {
        if (datos && Array.isArray(datos.grupos) && datos.grupos.length) {
            return datos.grupos;
        }

        return datos && Array.isArray(datos.dominios) ? datos.dominios : [];
    }

    function listaTablasGrupo(grupo) {
        var lista = document.createElement('ul');

        (Array.isArray(grupo.tablas) ? grupo.tablas : []).forEach(function (tabla) {
            var item = document.createElement('li');
            var botonTabla = elemento('button', '', tabla);

            botonTabla.type = 'button';
            botonTabla.addEventListener('click', function () {
                elegirTabla(tabla);
            });
            item.appendChild(botonTabla);
            lista.appendChild(item);
        });

        return lista;
    }

    function textoRelaciones(total) {
        return total === 1 ? '1 relación' : total + ' relaciones';
    }

    function pintarDominios(datos) {
        if (!dominios) {
            return;
        }

        dominios.replaceChildren();
        gruposModelo(datos).forEach(function (grupo) {
            var seccion = elemento('section', 'familia');
            var caja = elemento('div', 'familia__chips');

            seccion.appendChild(elemento('h3', 'familia__titulo', grupo.nombre || 'Grupo'));
            caja.appendChild(listaTablasGrupo(grupo));
            seccion.appendChild(caja);
            dominios.appendChild(seccion);
        });
    }

    function pintarListaTablas(datos) {
        if (!listaTablas) {
            return;
        }

        listaTablas.replaceChildren();
        gruposModelo(datos).forEach(function (dominio) {
            var grupoTablas = elemento('div', 'picker__grupo');
            var titulo = elemento('p', '', dominio.nombre || 'Grupo');

            grupoTablas.appendChild(titulo);
            (Array.isArray(dominio.tablas) ? dominio.tablas : []).forEach(function (tabla) {
                var botonTabla = elemento('button', 'picker__tabla', tabla);

                botonTabla.type = 'button';
                botonTabla.setAttribute('data-tabla', tabla);
                botonTabla.addEventListener('click', function () {
                    elegirTabla(tabla);
                });
                grupoTablas.appendChild(botonTabla);
            });
            listaTablas.appendChild(grupoTablas);
        });
        filtrarTablas();
        marcarTabla();
        ocultar('carga-tablas', true);
    }

    function pintarModelo(datos) {
        var motor = datos.motor === 'clickhouse' ? 'clickhouse' : 'postgresql';
        var relaciones = Array.isArray(datos.relaciones) ? datos.relaciones : [];

        cacheModelo[motor] = datos;

        if (motor !== motorModelo) {
            if (motor === 'postgresql') {
                pintarListaTablas(datos);
            }

            return;
        }

        aviso('aviso-modelo', false);
        ocultar('carga-modelo', true);

        if (datos.disponible === false || motor === 'clickhouse') {
            ocultar('modelo-postgresql', true);
            ocultar('modelo-clickhouse', false);
            return;
        }

        ocultar('modelo-clickhouse', true);
        ocultar('modelo-postgresql', false);
        texto('conteo-relaciones', textoRelaciones(relaciones.length));
        pintarDominios(datos);
        pintarListaTablas(datos);
    }

    function badge(clase, contenido) {
        return elemento('span', 'marca ' + clase, contenido);
    }

    function pintarDetalle(datos) {
        var columnas = Array.isArray(datos.columnas) ? datos.columnas : [];
        var cuerpo = document.getElementById('detalle-columnas');
        var tipo = document.getElementById('detalle-tipo');

        cacheDetalle[datos.tabla] = datos;
        tablaSeleccionada = datos.tabla;
        texto('detalle-nombre', datos.tabla || '');
        texto('detalle-comentario', datos.comentario_postgresql || 'Sin comentario en PostgreSQL');
        texto('detalle-funcional', datos.descripcion_funcional || 'Sin descripción funcional');

        if (tipo) {
            tipo.hidden = false;
            tipo.className = 'estado ' + (datos.clase === 'tecnica' ? 'marca--tecnica' : 'marca--negocio');
            tipo.textContent = datos.clase_nombre || (datos.tipo_tabla === 'tecnica' ? 'Tabla técnica' : 'Tabla de negocio');
        }

        if (cuerpo) {
            cuerpo.replaceChildren();
            columnas.forEach(function (columna) {
                var fila = document.createElement('tr');
                var clave = elemento('td', 'clave');
                var descripcion = elemento('td', 'descripcion');
                var postgres = columna.comentario_postgresql || '';
                var funcional = columna.descripcion_funcional || '';

                fila.appendChild(elemento('td', 'campo', columna.nombre || ''));
                fila.appendChild(elemento('td', 'tipo', columna.tipo || 'No disponible'));

                if (columna.clave_primaria === true) {
                    clave.appendChild(badge('marca--pk', 'PK'));
                }

                if (columna.clave_foranea === true) {
                    clave.appendChild(badge('marca--fk', 'FK'));
                }

                if (clave.childElementCount === 0) {
                    clave.textContent = '—';
                    clave.classList.add('muted');
                }

                fila.appendChild(clave);
                fila.appendChild(elemento('td', 'nulo', columna.permite_nulo === true ? 'Sí' : 'No'));
                fila.appendChild(elemento('td', columna.predeterminado ? 'defecto' : 'defecto muted', columna.predeterminado || '—'));
                fila.appendChild(elemento('td', columna.referencia ? 'referencia' : 'referencia muted', columna.referencia || '—'));

                if (!postgres && !funcional) {
                    descripcion.textContent = '—';
                    descripcion.classList.add('muted');
                } else {
                    if (funcional) {
                        descripcion.appendChild(document.createTextNode(funcional));
                    }

                    if (postgres) {
                        descripcion.appendChild(elemento('span', 'descripcion__pg', postgres));
                    }
                }

                fila.appendChild(descripcion);
                cuerpo.appendChild(fila);
            });
        }

        aviso('aviso-diccionario', false);
        ocultar('carga-detalle', true);
        ocultar('detalle-vacio', true);
        ocultar('detalle', false);
        marcarTabla();
    }

    function marcarTabla() {
        document.querySelectorAll('.picker__tabla, .dominio button').forEach(function (botonTabla) {
            var activa = botonTabla.textContent === tablaSeleccionada;
            botonTabla.classList.toggle('picker__tabla--activa', activa && botonTabla.classList.contains('picker__tabla'));

            if (activa) {
                botonTabla.setAttribute('aria-current', 'true');
            } else {
                botonTabla.removeAttribute('aria-current');
            }
        });
    }

    function filtrarTablas() {
        var consulta = buscarTabla ? buscarTabla.value.trim().toLowerCase() : '';
        var visibles = 0;

        document.querySelectorAll('.picker__grupo').forEach(function (grupoTablas) {
            var alguno = false;

            grupoTablas.querySelectorAll('.picker__tabla').forEach(function (botonTabla) {
                var coincide = consulta === '' || (botonTabla.getAttribute('data-tabla') || '').toLowerCase().indexOf(consulta) !== -1;
                botonTabla.hidden = !coincide;

                if (coincide) {
                    alguno = true;
                    visibles += 1;
                }
            });
            grupoTablas.hidden = !alguno;
        });

        ocultar('tablas-vacias', visibles !== 0 || !cacheModelo.postgresql);
    }

    function mostrarDiccionario() {
        var clickhouse = motorDiccionario && motorDiccionario.value === 'clickhouse';

        ocultar('diccionario-clickhouse', !clickhouse);
        ocultar('diccionario-postgresql', clickhouse);
    }

    function cargarResumen() {
        return post({ op: 'obtenerResumenMotores' }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true || !Array.isArray(datos.motores)) {
                aviso('aviso-resumen', true);
                ocultar('carga-resumen', true);
                return;
            }

            pintarMotores(datos);
        }).catch(function () {
            aviso('aviso-resumen', true);
            ocultar('carga-resumen', true);
        });
    }

    function cargarModelo(motor) {
        return post({
            op: 'obtenerModeloRelacional',
            motor: motor
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true) {
                if (motor === motorModelo) {
                    aviso('aviso-modelo', true);
                    ocultar('carga-modelo', true);
                }

                return;
            }

            pintarModelo(datos);
        }).catch(function () {
            if (motor === motorModelo) {
                aviso('aviso-modelo', true);
                ocultar('carga-modelo', true);
            }
        });
    }

    function cargarDetalle(tabla) {
        var token = ++detalleToken;
        var carga = document.getElementById('carga-detalle');

        ocultar('carga-detalle', false);

        if (carga) {
            carga.setAttribute('aria-busy', 'true');
        }

        return post({
            op: 'obtenerDetalleTabla',
            motor: 'postgresql',
            tabla: tabla
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (token !== detalleToken) {
                return;
            }

            if (datos.exito !== true || !Array.isArray(datos.columnas)) {
                aviso('aviso-diccionario', true);
                ocultar('carga-detalle', true);

                if (!cacheDetalle[tabla]) {
                    tablaSeleccionada = document.getElementById('detalle-nombre').textContent || '';
                    marcarTabla();
                }

                return;
            }

            pintarDetalle(datos);
        }).catch(function () {
            if (token !== detalleToken) {
                return;
            }

            aviso('aviso-diccionario', true);
            ocultar('carga-detalle', true);

            if (!cacheDetalle[tabla]) {
                tablaSeleccionada = document.getElementById('detalle-nombre').textContent || '';
                marcarTabla();
            }
        }).finally(function () {
            var carga = document.getElementById('carga-detalle');

            if (carga) {
                carga.removeAttribute('aria-busy');
            }
        });
    }

    function elegirTabla(tabla) {
        if (!tabla) {
            return;
        }

        if (motorDiccionario) {
            motorDiccionario.value = 'postgresql';
        }

        mostrarDiccionario();
        tablaSeleccionada = tabla;
        marcarTabla();

        var destino = document.getElementById('diccionario');

        if (destino) {
            destino.scrollIntoView({ block: 'start' });
        }

        if (cacheDetalle[tabla]) {
            pintarDetalle(cacheDetalle[tabla]);
        }

        cargarDetalle(tabla);
    }

    function cambiarModelo(motor) {
        motorModelo = motor;
        document.querySelectorAll('.conmutador__opcion').forEach(function (opcion) {
            var activa = opcion.getAttribute('data-motor') === motor;
            opcion.classList.toggle('conmutador__opcion--activa', activa);
            opcion.setAttribute('aria-pressed', activa ? 'true' : 'false');
        });

        if (cacheModelo[motor]) {
            pintarModelo(cacheModelo[motor]);
            return;
        }

        ocultar('carga-modelo', false);
        cargarModelo(motor);
    }

    function abrirModelo() {
        if (!dialogo || typeof dialogo.showModal !== 'function' || dialogo.open) {
            return;
        }

        dialogo.showModal();
    }

    function actualizar() {
        if (enCurso || typeof axios === 'undefined') {
            if (typeof axios === 'undefined') {
                aviso('aviso-resumen', true);
            }

            return;
        }

        enCurso = true;

        if (boton) {
            boton.disabled = true;
            boton.setAttribute('aria-busy', 'true');
        }

        var tareas = [cargarResumen(), cargarModelo(motorModelo)];

        if (motorModelo !== 'postgresql' && motorDiccionario && motorDiccionario.value === 'postgresql') {
            tareas.push(cargarModelo('postgresql'));
        }

        if (tablaSeleccionada && motorDiccionario && motorDiccionario.value === 'postgresql') {
            tareas.push(cargarDetalle(tablaSeleccionada));
        }

        Promise.all(tareas).finally(function () {
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

    document.querySelectorAll('.conmutador__opcion').forEach(function (opcion) {
        opcion.addEventListener('click', function () {
            cambiarModelo(opcion.getAttribute('data-motor') || 'postgresql');
        });
    });

    if (motorDiccionario) {
        motorDiccionario.addEventListener('change', mostrarDiccionario);
    }

    if (buscarTabla) {
        buscarTabla.addEventListener('input', filtrarTablas);
    }

    if (document.getElementById('ver-modelo')) {
        document.getElementById('ver-modelo').addEventListener('click', abrirModelo);
    }

    if (document.getElementById('cerrar-modelo') && dialogo) {
        document.getElementById('cerrar-modelo').addEventListener('click', function () {
            dialogo.close();
        });
    }

    if (dialogo) {
        dialogo.addEventListener('click', function (evento) {
            if (evento.target === dialogo) {
                dialogo.close();
            }
        });
    }

    if (boton) {
        boton.addEventListener('click', actualizar);
    }

    if (typeof axios === 'undefined') {
        aviso('aviso-resumen', true);
        aviso('aviso-modelo', true);
        ocultar('carga-resumen', true);
        ocultar('carga-modelo', true);
    } else {
        cargarResumen();
        cargarModelo('postgresql');
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

        if (mensaje) {
            mensaje.hidden = true;
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
