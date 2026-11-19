(function () {
    var botones = document.querySelectorAll('[data-cerrar-sesion]');
    var mensaje = document.getElementById('mensaje-sesion');

    function mostrarMensaje(texto) {
        mensaje.textContent = texto;
        mensaje.hidden = false;
    }

    function marcarEspera(activo) {
        botones.forEach(function (boton) {
            boton.disabled = activo;
            if (activo) {
                boton.setAttribute('aria-busy', 'true');
            } else {
                boton.removeAttribute('aria-busy');
            }
        });
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

    function texto(id, valor) {
        var nodo = document.getElementById(id);

        if (nodo) {
            nodo.textContent = valor;
        }
    }

    function limpiar(nodo) {
        while (nodo.firstChild) {
            nodo.removeChild(nodo.firstChild);
        }
    }

    function itemCobro(nombre, total, participacion) {
        var item = document.createElement('li');
        var etiqueta = document.createElement('span');
        var monto = document.createElement('strong');
        var parte = document.createElement('em');

        etiqueta.textContent = nombre;
        monto.textContent = total;
        parte.textContent = participacion;
        item.appendChild(etiqueta);
        item.appendChild(monto);
        item.appendChild(parte);

        return item;
    }

    function celda(fila, valor) {
        var columna = document.createElement('td');
        columna.textContent = valor;
        fila.appendChild(columna);
    }

    function dibujarHoras(serie) {
        var linea = document.getElementById('linea-horas');
        var pico = document.getElementById('punto-pico');
        var etiquetas = document.getElementById('etiquetas-horas');
        var vacio = document.getElementById('grafico-vacio');
        var maximo = 0;
        var puntos = [];
        var mejor = null;
        var espacio = 'http://www.w3.org/2000/svg';

        limpiar(etiquetas);
        linea.setAttribute('points', '');
        pico.setAttribute('hidden', '');

        if (!serie.length) {
            vacio.hidden = false;
            return;
        }

        vacio.hidden = true;
        serie.forEach(function (hora) {
            if (hora.total > maximo) {
                maximo = hora.total;
            }
        });

        serie.forEach(function (hora) {
            var x = 36 + (hora.hora / 23) * 568;
            var y = 150 - (maximo > 0 ? (hora.total / maximo) * 114 : 0);
            var marca = document.createElementNS(espacio, 'text');

            puntos.push(Math.round(x) + ',' + Math.round(y));
            marca.setAttribute('x', String(Math.round(x)));
            marca.setAttribute('y', '168');
            marca.setAttribute('fill', '#52657c');
            marca.setAttribute('font-size', '10');
            marca.setAttribute('text-anchor', 'middle');
            marca.textContent = hora.etiqueta;
            etiquetas.appendChild(marca);

            if (mejor === null || hora.total > mejor.total) {
                mejor = { x: x, y: y, total: hora.total };
            }
        });

        linea.setAttribute('points', puntos.join(' '));

        if (mejor) {
            pico.setAttribute('cx', String(Math.round(mejor.x)));
            pico.setAttribute('cy', String(Math.round(mejor.y)));
            pico.removeAttribute('hidden');
        }
    }

    function pintar(datos) {
        var sucursal = datos.sucursal || {};
        var cobros = document.getElementById('lista-cobros');
        var facturas = document.getElementById('facturas-recientes');
        var stock = document.getElementById('lista-stock');

        texto('jornada-meta', 'Sucursal: ' + (sucursal.nombre || 'No disponible') + ' · Punto de emisión: ' + (datos.puntoEmision || 'Pendiente'));
        texto('ventas-hoy', datos.ventasHoy || 'No disponible');
        texto('facturas-hoy', datos.facturas && datos.facturas.total ? datos.facturas.total : '0');
        texto('ticket-promedio', datos.facturas && datos.facturas.ticket ? datos.facturas.ticket : 'Ticket promedio: $ 0,00');
        texto('unidades-hoy', datos.unidades && datos.unidades.total ? datos.unidades.total : '0');
        texto('unidades-nota', datos.unidades && datos.unidades.nota ? datos.unidades.nota : 'En 0 facturas');
        texto('efectivo-hoy', datos.efectivo && datos.efectivo.total ? datos.efectivo.total : 'Pendiente');
        texto('efectivo-nota', datos.efectivo && datos.efectivo.porcentaje ? datos.efectivo.porcentaje : 'Pendiente');
        texto('stock-critico', datos.stockCritico || '0');
        texto('pico-hora', datos.pico || 'Sin ventas registradas hoy');
        texto('pico-monto', datos.picoMonto || '');
        texto('promedio-horario', datos.promedioHorario || 'Sin ventas registradas hoy');
        texto('promedio-nota', '');
        texto('tickets-emitidos', datos.tickets || '0 facturas');
        texto('tickets-nota', '');
        texto('total-cobrado', 'Total cobrado hoy: ' + (datos.totalCobrado || '$ 0,00'));
        texto('senal-sincronizacion', datos.sincronizacion || 'Pendiente');
        pintarOperacionesPendientes(datos.operacionesPendientes);
        dibujarHoras(Array.isArray(datos.serie) ? datos.serie : []);

        limpiar(cobros);

        if (!Array.isArray(datos.pagos) || datos.pagos.length === 0) {
            var sinCobros = document.createElement('li');
            sinCobros.className = 'vacio';
            sinCobros.textContent = 'Sin cobros registrados hoy';
            cobros.appendChild(sinCobros);
        } else {
            datos.pagos.forEach(function (pago) {
                cobros.appendChild(itemCobro(pago.nombre, pago.total, pago.participacion));
            });
        }

        limpiar(facturas);

        if (!Array.isArray(datos.facturasRecientes) || datos.facturasRecientes.length === 0) {
            var filaVacia = document.createElement('tr');
            var aviso = document.createElement('td');
            aviso.colSpan = 7;
            aviso.className = 'vacio';
            aviso.textContent = 'Sin facturas emitidas hoy';
            filaVacia.appendChild(aviso);
            facturas.appendChild(filaVacia);
        } else {
            datos.facturasRecientes.forEach(function (factura) {
                var fila = document.createElement('tr');
                celda(fila, factura.comprobante);
                celda(fila, factura.hora);
                celda(fila, factura.cliente);
                celda(fila, factura.pago);
                celda(fila, factura.total);
                celda(fila, factura.estado);
                celda(fila, '—');
                facturas.appendChild(fila);
            });
        }

        limpiar(stock);

        if (!Array.isArray(datos.stock) || datos.stock.length === 0) {
            var sinStock = document.createElement('li');
            sinStock.className = 'vacio';
            sinStock.textContent = 'Sin productos en stock crítico';
            stock.appendChild(sinStock);
        } else {
            datos.stock.forEach(function (producto) {
                var item = document.createElement('li');
                var nombre = document.createElement('span');
                var nivel = document.createElement('strong');
                var salto = document.createElement('br');

                nombre.appendChild(document.createTextNode(producto.producto));
                nombre.appendChild(salto);
                nombre.appendChild(document.createTextNode('SKU: ' + producto.codigo));
                nivel.textContent = producto.stock + ' · mín. ' + producto.minimo;
                item.appendChild(nombre);
                item.appendChild(nivel);
                stock.appendChild(item);
            });
        }
    }

    function pintarOperacionesPendientes(dato) {
        var nodo = document.getElementById('operaciones-pendientes');
        var valor = 'No disponible';
        var cantidad = null;

        if (dato && typeof dato === 'object') {
            if (dato.disponible === true && typeof dato.valor === 'string' && /^\d+$/.test(dato.valor)) {
                valor = dato.valor;
                cantidad = Number(dato.valor);
            } else if (typeof dato.valor === 'string' && dato.valor !== '') {
                valor = dato.valor;
            }
        } else if (typeof dato === 'string' && /^\d+$/.test(dato)) {
            valor = dato;
            cantidad = Number(dato);
        }

        texto('operaciones-pendientes', 'Operaciones pendientes: ' + valor);

        if (!nodo) {
            return;
        }

        nodo.classList.remove('barra__nota--alerta');

        if (cantidad !== null && cantidad > 0) {
            nodo.classList.add('barra__nota--alerta');
        }
    }

    function marcarNoDisponible() {
        ['ventas-hoy', 'facturas-hoy', 'unidades-hoy', 'efectivo-hoy', 'stock-critico'].forEach(function (id) {
            texto(id, 'No disponible');
        });
        texto('jornada-meta', 'Sucursal: No disponible · Punto de emisión: No disponible');
        texto('ticket-promedio', 'No disponible');
        texto('unidades-nota', 'No disponible');
        texto('efectivo-nota', 'No disponible');
        texto('pico-hora', 'No disponible');
        texto('promedio-horario', 'No disponible');
        texto('tickets-emitidos', 'No disponible');
        texto('total-cobrado', 'Total cobrado hoy: No disponible');
        pintarOperacionesPendientes({ disponible: false, valor: 'No disponible' });
        dibujarHoras([]);
        document.getElementById('grafico-vacio').textContent = 'No disponible';
    }

    if (typeof axios !== 'undefined') {
        axios.post('app/Controllers/DashboardOperadorController.php', {
            op: 'obtenerResumenOperador'
        }).then(function (respuesta) {
            var datos = respuesta.data || {};

            if (datos.exito !== true) {
                marcarNoDisponible();
                return;
            }

            pintar(datos);
        }).catch(function () {
            marcarNoDisponible();
        });
    } else {
        marcarNoDisponible();
    }

    botones.forEach(function (boton) {
        boton.addEventListener('click', function () {
            if (boton.disabled) {
                return;
            }

            mensaje.hidden = true;
            marcarEspera(true);

            if (typeof axios === 'undefined') {
                mostrarMensaje('No se pudo conectar. Inténtalo de nuevo.');
                marcarEspera(false);
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
                    marcarEspera(false);
                }
            });
        });
    });

    var atajos = {
        F1: document.querySelector('[data-atajo="F1"]'),
        F2: document.querySelector('[data-atajo="F2"]'),
        F3: document.querySelector('[data-atajo="F3"]')
    };
    var ayuda = document.getElementById('ayuda-turno');
    var pulso = null;

    function campoEditable(objetivo) {
        if (!objetivo || typeof objetivo.closest !== 'function') {
            return false;
        }

        var campo = objetivo.closest('input, textarea, select');

        if (!campo || campo.disabled || campo.readOnly) {
            return false;
        }

        return true;
    }

    function marcarAtajo(tecla) {
        var control = atajos[tecla];

        if (!control) {
            return;
        }

        control.classList.add('comandos__pulso');
        control.focus();
        window.clearTimeout(pulso);
        pulso = window.setTimeout(function () {
            control.classList.remove('comandos__pulso');
        }, 180);
    }

    function abrirAyuda() {
        if (!ayuda || ayuda.open) {
            return;
        }

        ayuda.showModal();
    }

    function cerrarAyuda() {
        if (ayuda && ayuda.open) {
            ayuda.close();
        }
    }

    function irAlAcceso(tecla) {
        var control = atajos[tecla];
        var ruta = control ? control.getAttribute('href') : '';

        if (ruta) {
            window.location.href = ruta;
        }
    }

    if (atajos.F1) {
        atajos.F1.addEventListener('click', abrirAyuda);
    }

    if (ayuda) {
        ayuda.querySelector('[data-cerrar-ayuda]').addEventListener('click', cerrarAyuda);
        ayuda.addEventListener('cancel', function (event) {
            event.preventDefault();
            cerrarAyuda();
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (ayuda && ayuda.open) {
                event.preventDefault();
                cerrarAyuda();
            }
            return;
        }

        if (event.key !== 'F1' && event.key !== 'F2' && event.key !== 'F3') {
            return;
        }

        if (event.repeat || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }

        if (campoEditable(event.target)) {
            return;
        }

        event.preventDefault();
        marcarAtajo(event.key);

        if (event.key === 'F1') {
            abrirAyuda();
            return;
        }

        irAlAcceso(event.key);
    });
}());
