(function () {
    var boton = document.getElementById('cerrar-sesion');
    var mensaje = document.getElementById('mensaje-sesion');
    var filtros = document.querySelector('.filtros');
    var estadoCarga = document.getElementById('estado-carga');
    var periodo = document.getElementById('filtro-periodo');
    var sucursal = document.getElementById('filtro-sucursal');
    var anteriorCategorias = document.getElementById('categorias-anterior');
    var siguienteCategorias = document.getElementById('categorias-siguiente');
    var paginaCategorias = document.getElementById('categorias-pagina');
    var vacio = 'Sin datos para el período seleccionado';
    var colores = ['#1664f5', '#12b6c8', '#0d4ecc', '#9aafc4'];
    var puntos = ['punto--azul', 'punto--cian', 'punto--marino', 'punto--suave'];
    var categoriasActuales = [];
    var paginaCategoria = 0;
    var mensajeCategoria = vacio;
    var porPagina = 5;
    var enCurso = false;
    var repetir = false;

    function mostrarMensaje(texto) {
        mensaje.textContent = texto;
        mensaje.hidden = false;
    }

    function escribir(id, valor) {
        var nodo = document.getElementById(id);
        if (nodo) {
            nodo.textContent = valor == null ? '' : String(valor);
        }
    }

    function limpiar(nodo) {
        while (nodo.firstChild) {
            nodo.removeChild(nodo.firstChild);
        }
    }

    function mensajeLista(nodo, texto) {
        var item = document.createElement('li');
        item.textContent = texto;
        nodo.appendChild(item);
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

    function marcarNoDisponible() {
        ['delta-ventas', 'cifra-ventas', 'ayuda-ventas', 'delta-facturas', 'cifra-facturas', 'ayuda-facturas', 'delta-ganancia', 'cifra-ganancia', 'ayuda-ganancia', 'delta-productos', 'cifra-productos', 'ayuda-productos', 'promedio-dia', 'dia-mayor', 'grafico-marca'].forEach(function (id) {
            escribir(id, 'No disponible');
        });
        ['lista-sucursales', 'lista-pagos', 'lista-stock'].forEach(function (id) {
            var nodo = document.getElementById(id);
            if (nodo) {
                limpiar(nodo);
                mensajeLista(nodo, 'No disponible');
            }
        });
        pintarCategorias([], 'No disponible');
        var cuerpo = document.getElementById('cuerpo-productos');
        if (cuerpo) {
            limpiar(cuerpo);
            var fila = document.createElement('tr');
            var celda = document.createElement('td');
            celda.colSpan = 4;
            celda.textContent = 'No disponible';
            fila.appendChild(celda);
            cuerpo.appendChild(fila);
        }
    }

    function llenarSucursales(lista) {
        var seleccion = sucursal.value;
        limpiar(sucursal);
        var todas = document.createElement('option');
        todas.value = '';
        todas.textContent = 'Todas las sucursales';
        sucursal.appendChild(todas);
        (lista || []).forEach(function (item) {
            var opcion = document.createElement('option');
            opcion.value = String(item.id);
            opcion.textContent = item.nombre;
            sucursal.appendChild(opcion);
        });
        sucursal.value = seleccion;
        if (sucursal.value !== seleccion) {
            sucursal.value = '';
        }
    }

    function pintarRanking(id, filas, chipId) {
        var nodo = document.getElementById(id);
        limpiar(nodo);
        if (!filas || !filas.length) {
            mensajeLista(nodo, vacio);
            if (chipId) {
                escribir(chipId, '0');
            }
            return;
        }
        filas.forEach(function (fila) {
            var item = document.createElement('li');
            var bloque = document.createElement('div');
            var puesto = document.createElement('b');
            var nombre = document.createElement('span');
            var parte = document.createElement('strong');
            var barra = document.createElement('i');
            var monto = document.createElement('em');
            puesto.textContent = String(fila.puesto);
            nombre.textContent = fila.nombre;
            parte.textContent = fila.participacion;
            barra.style.width = fila.ancho + '%';
            monto.textContent = fila.total;
            bloque.appendChild(puesto);
            bloque.appendChild(nombre);
            bloque.appendChild(parte);
            item.appendChild(bloque);
            item.appendChild(barra);
            item.appendChild(monto);
            nodo.appendChild(item);
        });
        if (chipId) {
            escribir(chipId, filas.length + (filas.length === 1 ? ' local' : ' locales'));
        }
    }

    function pintarCategorias(filas, aviso) {
        categoriasActuales = filas || [];
        paginaCategoria = 0;
        mensajeCategoria = aviso || vacio;
        mostrarPaginaCategorias();
    }

    function mostrarPaginaCategorias() {
        var cuerpo = document.getElementById('cuerpo-categorias');
        var total = categoriasActuales.length;
        var paginas = total === 0 ? 1 : Math.ceil(total / porPagina);
        if (paginaCategoria > paginas - 1) {
            paginaCategoria = paginas - 1;
        }
        if (paginaCategoria < 0) {
            paginaCategoria = 0;
        }
        var inicio = paginaCategoria * porPagina;
        limpiar(cuerpo);
        if (total === 0) {
            var vacia = document.createElement('tr');
            var celda = document.createElement('td');
            celda.colSpan = 3;
            celda.textContent = mensajeCategoria;
            vacia.appendChild(celda);
            cuerpo.appendChild(vacia);
        } else {
            categoriasActuales.slice(inicio, inicio + porPagina).forEach(function (fila) {
                var tr = document.createElement('tr');
                var nombre = document.createElement('td');
                var ventas = document.createElement('td');
                var parte = document.createElement('td');
                nombre.textContent = fila.nombre;
                ventas.textContent = fila.total;
                parte.textContent = fila.participacion;
                tr.appendChild(nombre);
                tr.appendChild(ventas);
                tr.appendChild(parte);
                cuerpo.appendChild(tr);
            });
        }
        anteriorCategorias.disabled = paginaCategoria <= 0;
        siguienteCategorias.disabled = paginaCategoria >= paginas - 1;
        paginaCategorias.textContent = 'Página ' + (paginaCategoria + 1) + ' de ' + paginas;
    }

    function pintarPagos(filas) {
        var lista = document.getElementById('lista-pagos');
        var mixto = document.getElementById('mixto-pagos');
        limpiar(lista);
        limpiar(mixto);
        if (!filas || !filas.length) {
            mensajeLista(lista, vacio);
            escribir('chip-pagos', '0');
            return;
        }
        var suma = 0;
        filas.forEach(function (fila) {
            suma += Number(String(fila.participacion).replace('%', '').replace(',', '.')) || 0;
        });
        filas.forEach(function (fila, indice) {
            var franja = document.createElement('span');
            franja.style.width = fila.participacion;
            franja.style.background = colores[indice % colores.length];
            mixto.appendChild(franja);

            var item = document.createElement('li');
            var lado = document.createElement('span');
            var punto = document.createElement('i');
            var monto = document.createElement('strong');
            var parte = document.createElement('em');
            punto.className = 'punto ' + puntos[indice % puntos.length];
            lado.appendChild(punto);
            lado.appendChild(document.createTextNode(fila.nombre));
            monto.textContent = fila.total;
            parte.textContent = fila.participacion;
            item.appendChild(lado);
            item.appendChild(monto);
            item.appendChild(parte);
            lista.appendChild(item);
        });
        escribir('chip-pagos', filas.length + (filas.length === 1 ? ' método' : ' métodos'));
        if (suma <= 0) {
            escribir('chip-pagos', 'Pendiente');
        }
    }

    function pintarProductos(filas) {
        var cuerpo = document.getElementById('cuerpo-productos');
        limpiar(cuerpo);
        if (!filas || !filas.length) {
            var fila = document.createElement('tr');
            var celda = document.createElement('td');
            celda.colSpan = 4;
            celda.textContent = vacio;
            fila.appendChild(celda);
            cuerpo.appendChild(fila);
            return;
        }
        filas.forEach(function (fila) {
            var tr = document.createElement('tr');
            var producto = document.createElement('td');
            var nombre = document.createElement('strong');
            var categoria = document.createElement('small');
            var cantidad = document.createElement('td');
            var margen = document.createElement('td');
            var ventas = document.createElement('td');
            nombre.textContent = fila.producto;
            categoria.textContent = fila.categoria;
            producto.appendChild(nombre);
            producto.appendChild(categoria);
            cantidad.textContent = fila.cantidad;
            margen.textContent = fila.margen;
            ventas.textContent = fila.ventas;
            tr.appendChild(producto);
            tr.appendChild(cantidad);
            tr.appendChild(margen);
            tr.appendChild(ventas);
            cuerpo.appendChild(tr);
        });
    }

    function pintarStock(filas) {
        var nodo = document.getElementById('lista-stock');
        limpiar(nodo);
        if (!filas || !filas.length) {
            mensajeLista(nodo, 'Sin productos bajo el mínimo');
            return;
        }
        filas.forEach(function (fila) {
            var item = document.createElement('li');
            var bloque = document.createElement('div');
            var nombre = document.createElement('strong');
            var lugar = document.createElement('small');
            var stock = document.createElement('span');
            nombre.textContent = fila.producto;
            lugar.textContent = fila.sucursal;
            stock.textContent = fila.stock;
            bloque.appendChild(nombre);
            bloque.appendChild(lugar);
            item.appendChild(bloque);
            item.appendChild(stock);
            nodo.appendChild(item);
        });
    }

    function pintarGrafico(datos) {
        var serie = datos.serie || [];
        var svg = document.getElementById('grafico-ventas');
        var area = document.getElementById('grafico-area');
        var linea = document.getElementById('grafico-linea');
        var punto = document.getElementById('grafico-punto');
        var eje = document.getElementById('grafico-eje');
        var aviso = document.getElementById('grafico-vacio');
        var maximo = 0;
        var indiceMax = 0;
        limpiar(eje);
        serie.forEach(function (puntoSerie, indice) {
            if (puntoSerie.total > maximo) {
                maximo = puntoSerie.total;
                indiceMax = indice;
            }
        });
        if (!serie.length || maximo <= 0) {
            svg.hidden = true;
            aviso.hidden = false;
            area.setAttribute('d', '');
            linea.setAttribute('d', '');
            punto.setAttribute('cx', '0');
            punto.setAttribute('cy', '0');
            escribir('grafico-marca', '');
            return;
        }
        svg.hidden = false;
        aviso.hidden = true;
        var coordenadas = serie.map(function (puntoSerie, indice) {
            var x = serie.length === 1 ? 320 : 20 + (indice * 600 / (serie.length - 1));
            var y = 170 - (puntoSerie.total / maximo) * 150;
            return { x: x, y: y };
        });
        var trazo = coordenadas.map(function (coord, indice) {
            return (indice === 0 ? 'M' : 'L') + coord.x.toFixed(1) + ' ' + coord.y.toFixed(1);
        }).join(' ');
        var ultimo = coordenadas[coordenadas.length - 1];
        var primero = coordenadas[0];
        area.setAttribute('d', trazo + ' L' + ultimo.x.toFixed(1) + ' 170 L' + primero.x.toFixed(1) + ' 170 Z');
        linea.setAttribute('d', trazo);
        punto.setAttribute('cx', coordenadas[indiceMax].x.toFixed(1));
        punto.setAttribute('cy', coordenadas[indiceMax].y.toFixed(1));
        var pasos = Math.min(7, serie.length);
        for (var i = 0; i < pasos; i += 1) {
            var indice = pasos === 1 ? 0 : Math.round(i * (serie.length - 1) / (pasos - 1));
            var etiqueta = document.createElement('span');
            etiqueta.textContent = serie[indice].etiqueta;
            eje.appendChild(etiqueta);
        }
    }

    function aplicar(datos) {
        escribir('delta-ventas', datos.ventas.crecimiento);
        escribir('cifra-ventas', datos.ventas.total);
        escribir('ayuda-ventas', datos.ventas.ayuda);
        escribir('delta-facturas', datos.facturas.crecimiento);
        escribir('cifra-facturas', datos.facturas.total);
        escribir('ayuda-facturas', datos.facturas.ayuda);
        escribir('delta-ganancia', datos.ganancia.crecimiento);
        escribir('cifra-ganancia', datos.ganancia.total);
        escribir('ayuda-ganancia', datos.ganancia.ayuda);
        escribir('delta-productos', datos.productosVendidos.crecimiento);
        escribir('cifra-productos', datos.productosVendidos.total);
        escribir('ayuda-productos', datos.productosVendidos.ayuda);
        escribir('promedio-dia', datos.promedioDia);
        escribir('dia-mayor', datos.diaMayor);
        escribir('grafico-marca', datos.marca);
        escribir('texto-tiempo', datos.periodo && datos.periodo.etiqueta
            ? 'Facturación diaria. ' + datos.periodo.etiqueta + '.'
            : 'Facturación diaria del período.');
        llenarSucursales(datos.sucursales);
        pintarGrafico(datos);
        pintarRanking('lista-sucursales', datos.sucursalesVenta, 'chip-sucursales');
        pintarCategorias(datos.categorias);
        pintarPagos(datos.pagos);
        pintarProductos(datos.topProductos);
        pintarStock(datos.stock);
    }

    function fijarCarga(activa) {
        periodo.disabled = activa;
        sucursal.disabled = activa;
        estadoCarga.hidden = !activa;
        if (filtros) {
            if (activa) {
                filtros.setAttribute('aria-busy', 'true');
            } else {
                filtros.removeAttribute('aria-busy');
            }
        }
    }

    function cargarResumen() {
        if (typeof axios === 'undefined') {
            marcarNoDisponible();
            return;
        }

        if (enCurso) {
            repetir = true;
            return;
        }

        enCurso = true;
        fijarCarga(true);
        var cuerpo = {
            op: 'obtenerResumenAdministrativo',
            periodo: periodo.value
        };
        if (sucursal.value !== '') {
            cuerpo.id_sucursal = Number(sucursal.value);
        }

        axios.post('app/Controllers/DashboardAdministrativoController.php', cuerpo).then(function (respuesta) {
            var datos = respuesta.data || {};
            if (datos.exito !== true || !datos.ventas) {
                marcarNoDisponible();
                return;
            }
            aplicar(datos);
        }).catch(function () {
            marcarNoDisponible();
        }).finally(function () {
            enCurso = false;
            fijarCarga(false);
            if (repetir) {
                repetir = false;
                cargarResumen();
            }
        });
    }

    cargarResumen();
    periodo.addEventListener('change', cargarResumen);
    sucursal.addEventListener('change', cargarResumen);
    anteriorCategorias.addEventListener('click', function () {
        if (paginaCategoria > 0) {
            paginaCategoria -= 1;
            mostrarPaginaCategorias();
        }
    });
    siguienteCategorias.addEventListener('click', function () {
        var paginas = categoriasActuales.length === 0 ? 1 : Math.ceil(categoriasActuales.length / porPagina);
        if (paginaCategoria < paginas - 1) {
            paginaCategoria += 1;
            mostrarPaginaCategorias();
        }
    });

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
