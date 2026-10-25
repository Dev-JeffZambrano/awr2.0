(function () {
    var controlador = 'app/Controllers/PuntoVentaController.php';
    var aviso = document.getElementById('aviso-venta');
    var estado = document.getElementById('estado-caja');
    var buscarProducto = document.getElementById('buscar-producto');
    var formBuscar = document.getElementById('form-buscar');
    var ayudaBusqueda = document.getElementById('ayuda-busqueda');
    var resultados = document.getElementById('resultados-productos');
    var lineas = document.getElementById('lineas-carrito');
    var campoPunto = document.getElementById('campo-punto');
    var puntosCaja = document.getElementById('puntos-emision');
    var clienteActual = document.getElementById('cliente-actual');
    var clienteDetalle = document.getElementById('cliente-detalle');
    var usarConsumidor = document.getElementById('usar-consumidor');
    var formCliente = document.getElementById('form-cliente');
    var buscarCliente = document.getElementById('buscar-cliente');
    var resultadosClientes = document.getElementById('resultados-clientes');
    var metodosCaja = document.getElementById('metodos-pago');
    var confirmar = document.getElementById('confirmar-venta');
    var dialogo = document.getElementById('resultado-venta');
    var dialogoTransferencia = document.getElementById('cuentas-transferencia');
    var listaCuentasTransferencia = document.getElementById('lista-cuentas-transferencia');
    var cerrarCuentasTransferencia = document.getElementById('cerrar-cuentas-transferencia');
    var dialogoValidarTransferencia = document.getElementById('validar-transferencia');
    var resumenCuentaTransferencia = document.getElementById('resumen-cuenta-transferencia');
    var formValidarTransferencia = document.getElementById('form-validar-transferencia');
    var inputComprobanteTransferencia = document.getElementById('comprobante-transferencia');
    var avisoComprobanteTransferencia = document.getElementById('aviso-comprobante-transferencia');
    var cancelarValidarTransferencia = document.getElementById('cancelar-validar-transferencia');
    var pagoTransferenciaResumen = document.getElementById('pago-transferencia-resumen');
    var nuevaVenta = document.getElementById('nueva-venta');
    var cuentasTransferencia = [
        {
            banco: 'Banco Pichincha',
            tipo: 'Ahorros',
            numero: '2204517893',
            titular: 'Comercial AWR S.A.',
            identificacion: '1792564839001'
        },
        {
            banco: 'Banco Guayaquil',
            tipo: 'Corriente',
            numero: '0034589127',
            titular: 'Comercial AWR S.A.',
            identificacion: '1792564839001'
        },
        {
            banco: 'Produbanco',
            tipo: 'Ahorros',
            numero: '1209876543',
            titular: 'Comercial AWR S.A.',
            identificacion: '1792564839001'
        },
        {
            banco: 'Banco del Pacífico',
            tipo: 'Corriente',
            numero: '7456123098',
            titular: 'Comercial AWR S.A.',
            identificacion: '1792564839001'
        },
        {
            banco: 'Banco Bolivariano',
            tipo: 'Ahorros',
            numero: '0987654321',
            titular: 'Comercial AWR S.A.',
            identificacion: '1792564839001'
        }
    ];
    var descuentoOpcion = document.getElementById('descuento-opcion');
    var descuentoOtroCaja = document.getElementById('descuento-otro-caja');
    var descuentoOtro = document.getElementById('descuento-otro');
    var ayudaDescuento = document.getElementById('ayuda-descuento');
    var descuentoAviso = document.getElementById('descuento-aviso');
    var contexto = null;
    var cliente = null;
    var metodoId = 0;
    var metodoEsTransferencia = false;
    var transferenciaValidada = null;
    var cuentaTransferenciaPendiente = null;
    var puntoId = 0;
    var carrito = [];
    var cotizacion = null;
    var uuidVenta = null;
    var enProceso = false;
    var confirmando = false;
    var reintento = false;
    var ultimoAgregado = 0;
    var tokenBusqueda = 0;
    var tokenClientes = 0;
    var tokenCotizacion = 0;
    var esperaBusqueda = 0;
    var esperaClientes = 0;
    var esperaCotizacion = 0;

    function limpiar(nodo) {
        while (nodo.firstChild) {
            nodo.removeChild(nodo.firstChild);
        }
    }

    function texto(id, valor) {
        var nodo = document.getElementById(id);
        if (nodo) {
            nodo.textContent = valor == null ? '' : String(valor);
        }
    }

    function marcarEstado(valor) {
        var tono = 'info';
        if (valor === 'Listo' || valor === 'Emitida') {
            tono = 'ok';
        } else if (valor === 'Revisar stock' || valor === 'No disponible') {
            tono = 'alerta';
        }
        estado.className = 'estado estado--' + tono;
        estado.textContent = valor;
    }

    function mostrarAviso(mensaje, proceso) {
        if (!mensaje) {
            aviso.hidden = true;
            aviso.textContent = '';
            aviso.classList.remove('aviso--proceso');
            if (!confirmando) {
                marcarEstado(cotizacion && cotizacion.stockSuficiente === false ? 'Revisar stock' : 'Listo');
            }
            return;
        }
        aviso.hidden = false;
        aviso.textContent = mensaje;
        aviso.classList.toggle('aviso--proceso', proceso === true);
        if (proceso) {
            marcarEstado('Procesando');
        }
    }

    function dinero(valor) {
        if (valor == null || valor === '') {
            return '—';
        }
        var textoValor = String(valor);
        var negativo = textoValor.charAt(0) === '-';
        var partes = textoValor.replace('-', '').split('.');
        var entera = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        var decimal = (partes[1] || '00').padEnd(2, '0').slice(0, 2);
        return (negativo ? '-' : '') + '$ ' + entera + ',' + decimal;
    }

    function porcentaje(valor) {
        if (valor == null || valor === '') {
            return '—';
        }
        return String(valor).replace(/\.?0+$/, '') + '%';
    }

    function redondearDinero(valor) {
        return (Math.round((Number(valor) + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    function sincronizarDescuentoOtro() {
        var esOtro = descuentoOpcion && descuentoOpcion.value === 'otro';
        if (!descuentoOtroCaja || !descuentoOtro) {
            return;
        }
        descuentoOtroCaja.hidden = !esOtro;
        descuentoOtro.disabled = !esOtro || confirmando;
        if (!esOtro) {
            descuentoOtro.value = '';
            descuentoOtro.setCustomValidity('');
        }
        if (descuentoOpcion) {
            descuentoOpcion.disabled = confirmando;
        }
    }

    function porcentajeDescuentoActual() {
        if (!descuentoOpcion) {
            return { valido: true, porcentaje: 0 };
        }
        var opcion = descuentoOpcion.value;
        if (opcion === '10' || opcion === '20' || opcion === '0') {
            return { valido: true, porcentaje: Number(opcion) };
        }
        if (opcion === 'otro') {
            var crudo = descuentoOtro ? String(descuentoOtro.value).trim() : '';
            if (crudo === '') {
                if (descuentoOtro) {
                    descuentoOtro.setCustomValidity('Ingrese un porcentaje entre 0 y 100.');
                }
                return { valido: false, porcentaje: 0 };
            }
            var numero = Number(crudo);
            if (!Number.isFinite(numero) || numero < 0 || numero > 100) {
                if (descuentoOtro) {
                    descuentoOtro.setCustomValidity('El descuento debe estar entre 0 y 100.');
                }
                return { valido: false, porcentaje: 0 };
            }
            if (descuentoOtro) {
                descuentoOtro.setCustomValidity('');
            }
            return { valido: true, porcentaje: numero };
        }
        return { valido: true, porcentaje: 0 };
    }

    /**
     * Descuento sobre subtotal (sin IVA).
     * IVA proporcional al impuesto cotizado por línea:
     * iva = ivaOriginal * (base / subtotal), equivalente a repartir el %
     * sobre cada línea y recalcular su impuesto.
     */
    function totalesConDescuento() {
        if (!cotizacion) {
            return null;
        }
        var descuentoInfo = porcentajeDescuentoActual();
        var subtotal = Number(cotizacion.subtotal);
        var ivaOriginal = Number(cotizacion.valorImpuesto);
        if (!Number.isFinite(subtotal) || !Number.isFinite(ivaOriginal)) {
            return null;
        }
        if (!descuentoInfo.valido) {
            return {
                subtotal: redondearDinero(subtotal),
                descuento: null,
                valorImpuesto: null,
                total: null,
                porcentajeDescuento: null,
                descuentoValido: false
            };
        }
        var porcentajeDesc = descuentoInfo.porcentaje;
        var descuento = Number(redondearDinero(subtotal * porcentajeDesc / 100));
        var base = Number(redondearDinero(subtotal - descuento));
        var iva = subtotal > 0
            ? Number(redondearDinero(ivaOriginal * (base / subtotal)))
            : 0;
        var total = Number(redondearDinero(base + iva));
        return {
            subtotal: redondearDinero(subtotal),
            descuento: redondearDinero(descuento),
            valorImpuesto: redondearDinero(iva),
            total: redondearDinero(total),
            porcentajeDescuento: porcentajeDesc,
            descuentoValido: true
        };
    }

    function post(cuerpo) {
        return axios.post(controlador, cuerpo, { timeout: 25000 });
    }

    function volverAlDashboard() {
        if (confirmando) {
            return;
        }
        window.location.href = 'dashboardOperador.php';
    }

    function nuevoUuid() {
        if (window.crypto && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        var bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        var hex = '';
        bytes.forEach(function (byte) {
            hex += byte.toString(16).padStart(2, '0');
        });
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }

    function asegurarUuid() {
        if (!uuidVenta) {
            uuidVenta = nuevoUuid();
        }
        return uuidVenta;
    }

    function puedeConfirmar() {
        var descuentoInfo = porcentajeDescuentoActual();
        var transferenciaOk = !metodoEsTransferencia
            || (transferenciaValidada
                && String(transferenciaValidada.comprobante || '').trim() !== '');
        return !confirmando
            && !enProceso
            && carrito.length > 0
            && cliente
            && cliente.id > 0
            && metodoId > 0
            && puntoId > 0
            && descuentoInfo.valido
            && transferenciaOk;
    }

    function actualizarConfirmar() {
        confirmar.disabled = !puedeConfirmar();
        if (!confirmando) {
            confirmar.textContent = reintento ? 'Reintentar' : 'Emitir factura';
        }
    }

    function cuentaCarrito() {
        var unidades = 0;
        carrito.forEach(function (item) {
            unidades += item.cantidad;
        });
        texto('cuenta-carrito', unidades + (unidades === 1 ? ' ítem' : ' ítems'));
    }

    function mostrarAvisoDescuento(visible) {
        if (descuentoAviso) {
            descuentoAviso.hidden = !visible;
        }
        if (ayudaDescuento) {
            ayudaDescuento.textContent = visible
                ? 'Ingrese un porcentaje entre 0 y 100.'
                : 'El descuento se aplica sobre el subtotal.';
        }
    }

    function pintarTotales() {
        var totales = totalesConDescuento();
        sincronizarDescuentoOtro();
        if (!totales) {
            texto('total-subtotal', '—');
            texto('total-impuesto', '—');
            texto('total-descuento', '—');
            texto('total-pagar', '—');
            mostrarAvisoDescuento(false);
            return;
        }
        texto('total-subtotal', dinero(totales.subtotal));
        if (!totales.descuentoValido) {
            texto('total-impuesto', '—');
            texto('total-descuento', '—');
            texto('total-pagar', '—');
            mostrarAvisoDescuento(true);
            actualizarConfirmar();
            return;
        }
        texto('total-impuesto', dinero(totales.valorImpuesto));
        texto('total-descuento', dinero(totales.descuento));
        texto('total-pagar', dinero(totales.total));
        mostrarAvisoDescuento(false);
        actualizarConfirmar();
    }

    function lineaCotizada(idProducto) {
        var encontrada = null;
        if (!cotizacion || !cotizacion.lineas) {
            return null;
        }
        cotizacion.lineas.forEach(function (linea) {
            if (linea.idProducto === idProducto) {
                encontrada = linea;
            }
        });
        return encontrada;
    }

    function avisoStock() {
        if (!cotizacion || cotizacion.stockSuficiente !== false) {
            return;
        }
        var nombres = [];
        cotizacion.lineas.forEach(function (linea) {
            if (linea.stockSuficiente === false) {
                nombres.push(linea.nombre);
            }
        });
        mostrarAviso(nombres.length
            ? 'Stock insuficiente para ' + nombres.join(', ')
            : 'Stock insuficiente.');
    }

    function celda(fila, clase) {
        var td = document.createElement('td');
        if (clase) {
            td.className = clase;
        }
        fila.appendChild(td);
        return td;
    }

    function pintarCarrito() {
        limpiar(lineas);
        cuentaCarrito();
        if (carrito.length === 0) {
            var vacio = document.createElement('tr');
            var td = document.createElement('td');
            vacio.className = 'vacio-tabla';
            td.colSpan = 7;
            td.textContent = 'Agrega productos para comenzar la venta';
            vacio.appendChild(td);
            lineas.appendChild(vacio);
            actualizarConfirmar();
            return;
        }
        carrito.forEach(function (item) {
            var cotizada = lineaCotizada(item.idProducto);
            var fila = document.createElement('tr');
            var codigo = null;
            var descripcion = null;
            var nombre = document.createElement('strong');
            var cantidades = null;
            var menos = document.createElement('button');
            var cantidad = document.createElement('span');
            var mas = document.createElement('button');
            var grupo = document.createElement('div');
            var quitar = document.createElement('button');
            fila.className = 'linea' + (cotizada && cotizada.stockSuficiente === false ? ' linea--alerta' : '') + (item.idProducto === ultimoAgregado ? ' linea--nueva' : '');
            codigo = celda(fila, 'codigo');
            codigo.textContent = cotizada ? cotizada.codigo : item.codigo;
            descripcion = celda(fila, 'descripcion');
            nombre.textContent = cotizada ? cotizada.nombre : item.nombre;
            descripcion.appendChild(nombre);
            if (cotizada && cotizada.stockSuficiente === false) {
                var nota = document.createElement('span');
                nota.className = 'nota';
                nota.textContent = 'Stock insuficiente';
                descripcion.appendChild(nota);
            }
            cantidades = celda(fila);
            grupo.className = 'cant';
            menos.type = 'button';
            mas.type = 'button';
            menos.textContent = '−';
            mas.textContent = '+';
            menos.setAttribute('aria-label', 'Disminuir cantidad');
            mas.setAttribute('aria-label', 'Aumentar cantidad');
            cantidad.textContent = String(item.cantidad);
            menos.disabled = confirmando || item.cantidad <= 1;
            mas.disabled = confirmando || item.cantidad >= 9999;
            menos.addEventListener('click', function () {
                cambiarCantidad(item.idProducto, item.cantidad - 1);
            });
            mas.addEventListener('click', function () {
                cambiarCantidad(item.idProducto, item.cantidad + 1);
            });
            grupo.appendChild(menos);
            grupo.appendChild(cantidad);
            grupo.appendChild(mas);
            cantidades.appendChild(grupo);
            celda(fila, 'num').textContent = dinero(cotizada ? cotizada.precioUnitario : item.precio);
            var impuesto = document.createElement('span');
            impuesto.className = 'iva';
            impuesto.textContent = cotizada ? porcentaje(cotizada.porcentajeImpuesto) : '…';
            if (cotizada && cotizada.nombreImpuesto) {
                impuesto.title = cotizada.nombreImpuesto;
            }
            celda(fila).appendChild(impuesto);
            celda(fila, 'subtotal').textContent = cotizada ? dinero(cotizada.subtotal) : '…';
            quitar.type = 'button';
            quitar.className = 'quitar';
            quitar.textContent = '×';
            quitar.setAttribute('aria-label', 'Quitar producto');
            quitar.disabled = confirmando;
            quitar.addEventListener('click', function () {
                quitarProducto(item.idProducto);
            });
            celda(fila).appendChild(quitar);
            lineas.appendChild(fila);
        });
        actualizarConfirmar();
    }

    function programarCotizacion() {
        window.clearTimeout(esperaCotizacion);
        marcarEstado('Cotizando');
        esperaCotizacion = window.setTimeout(cotizar, 220);
    }

    function cotizar() {
        if (carrito.length === 0) {
            tokenCotizacion += 1;
            cotizacion = null;
            pintarTotales();
            pintarCarrito();
            if (!confirmando) {
                marcarEstado('Listo');
            }
            return;
        }
        var token = tokenCotizacion + 1;
        tokenCotizacion = token;
        post({
            op: 'cotizarVenta',
            productos: carrito.map(function (item) {
                return { id_producto: item.idProducto, cantidad: item.cantidad };
            })
        }).then(function (respuesta) {
            if (token !== tokenCotizacion) {
                return;
            }
            var datos = respuesta.data || {};
            if (datos.exito !== true) {
                cotizacion = null;
                pintarTotales();
                mostrarAviso(datos.mensaje || 'No se pudo cotizar la venta.');
                return;
            }
            cotizacion = datos;
            pintarTotales();
            pintarCarrito();
            if (datos.stockSuficiente === false) {
                avisoStock();
            } else if (!confirmando) {
                mostrarAviso('');
                marcarEstado('Listo');
            }
        }).catch(function (error) {
            if (token !== tokenCotizacion) {
                return;
            }
            cotizacion = null;
            pintarTotales();
            mostrarAviso(mensajeError(error) || 'No se pudo cotizar la venta.');
        });
    }

    function cambiarCantidad(idProducto, cantidad) {
        if (confirmando || cantidad > 9999) {
            return;
        }
        if (cantidad < 1) {
            quitarProducto(idProducto);
            return;
        }
        carrito.forEach(function (item) {
            if (item.idProducto === idProducto) {
                item.cantidad = cantidad;
            }
        });
        ultimoAgregado = 0;
        reintento = false;
        pintarCarrito();
        programarCotizacion();
    }

    function quitarProducto(idProducto) {
        if (confirmando) {
            return;
        }
        carrito = carrito.filter(function (item) {
            return item.idProducto !== idProducto;
        });
        if (carrito.length === 0) {
            uuidVenta = null;
            reintento = false;
            mostrarAviso('');
        }
        ultimoAgregado = 0;
        pintarCarrito();
        programarCotizacion();
    }

    function agregarProducto(producto) {
        if (confirmando) {
            return;
        }
        var existente = null;
        carrito.forEach(function (item) {
            if (item.idProducto === producto.idProducto) {
                existente = item;
            }
        });
        if (existente) {
            if (existente.cantidad < 9999) {
                existente.cantidad += 1;
            }
        } else {
            carrito.push({
                idProducto: producto.idProducto,
                cantidad: 1,
                nombre: producto.nombre,
                codigo: producto.codigo,
                precio: producto.precio
            });
        }
        ultimoAgregado = producto.idProducto;
        reintento = false;
        buscarProducto.value = '';
        tokenBusqueda += 1;
        pintarProductos([]);
        pintarCarrito();
        programarCotizacion();
        buscarProducto.focus();
    }

    function pintarProductos(lista, buscando) {
        limpiar(resultados);
        if (buscando) {
            ayudaBusqueda.textContent = 'Buscando…';
            return;
        }
        if (!lista.length) {
            ayudaBusqueda.textContent = buscarProducto.value.trim() === ''
                ? 'Escriba para buscar en la sucursal.'
                : 'Sin resultados.';
            return;
        }
        ayudaBusqueda.textContent = lista.length + (lista.length === 1 ? ' resultado' : ' resultados') + '. Enter agrega el primero.';
        lista.forEach(function (producto) {
            var item = document.createElement('li');
            var datos = document.createElement('div');
            var nombre = document.createElement('strong');
            var detalle = document.createElement('small');
            var stock = document.createElement('small');
            var boton = document.createElement('button');
            var disponible = Number(producto.stockActual) > 0;
            item.className = 'resultado';
            nombre.textContent = producto.nombre;
            detalle.textContent = 'SKU: ' + producto.codigo + ' · ' + dinero(producto.precio);
            stock.className = disponible ? 'stock--ok' : 'stock--no';
            stock.textContent = 'Stock: ' + producto.stockActual + (disponible ? ' · Disponible' : ' · Sin stock');
            boton.type = 'button';
            boton.textContent = '+ Agregar';
            boton.disabled = confirmando;
            boton.addEventListener('click', function () {
                agregarProducto(producto);
            });
            datos.appendChild(nombre);
            datos.appendChild(detalle);
            datos.appendChild(stock);
            item.appendChild(datos);
            item.appendChild(boton);
            resultados.appendChild(item);
        });
    }

    function buscarProductos() {
        var textoBusqueda = buscarProducto.value.trim();
        var token = tokenBusqueda + 1;
        tokenBusqueda = token;
        if (textoBusqueda.length < 2) {
            pintarProductos([]);
            return;
        }
        pintarProductos([], true);
        post({ op: 'buscarProductos', texto: textoBusqueda }).then(function (respuesta) {
            if (token !== tokenBusqueda) {
                return;
            }
            var datos = respuesta.data || {};
            if (datos.exito !== true) {
                pintarProductos([]);
                mostrarAviso(datos.mensaje || 'No se pudo buscar productos.');
                return;
            }
            pintarProductos(datos.productos || []);
        }).catch(function (error) {
            if (token !== tokenBusqueda) {
                return;
            }
            pintarProductos([]);
            mostrarAviso(mensajeError(error) || 'No se pudo buscar productos.');
        });
    }

    function etiquetaIdentificacion(item) {
        var tipo = item.tipoIdentificacion === 'CEDULA' ? 'Cédula' : (item.tipoIdentificacion === 'RUC' ? 'RUC' : '');
        return (tipo ? tipo + ' ' : '') + (item.identificacion || '—');
    }

    function mostrarCliente() {
        var esFinal = false;
        if (!cliente) {
            clienteActual.textContent = '…';
            clienteDetalle.textContent = '…';
        } else {
            esFinal = !!(contexto && cliente.id === contexto.consumidorFinal.id);
            clienteActual.textContent = cliente.razonSocial || '—';
            clienteDetalle.textContent = etiquetaIdentificacion(cliente);
        }
        usarConsumidor.disabled = confirmando || !contexto || esFinal;
        usarConsumidor.classList.toggle('chip--activo', esFinal);
        usarConsumidor.setAttribute('aria-pressed', esFinal ? 'true' : 'false');
    }

    function elegirCliente(item) {
        cliente = item;
        mostrarCliente();
        actualizarConfirmar();
        buscarCliente.value = '';
        limpiar(resultadosClientes);
        buscarProducto.focus();
    }

    function pintarClientes(lista) {
        limpiar(resultadosClientes);
        if (!lista.length) {
            var vacio = document.createElement('li');
            vacio.className = 'vacio';
            vacio.textContent = 'Sin clientes para esa búsqueda.';
            resultadosClientes.appendChild(vacio);
            return;
        }
        lista.forEach(function (item) {
            var fila = document.createElement('li');
            var datos = document.createElement('div');
            var nombre = document.createElement('strong');
            var detalle = document.createElement('small');
            var boton = document.createElement('button');
            fila.className = 'resultado';
            nombre.textContent = item.razonSocial;
            detalle.textContent = etiquetaIdentificacion(item);
            boton.type = 'button';
            boton.textContent = 'Seleccionar';
            boton.addEventListener('click', function () {
                elegirCliente(item);
            });
            datos.appendChild(nombre);
            datos.appendChild(detalle);
            fila.appendChild(datos);
            fila.appendChild(boton);
            resultadosClientes.appendChild(fila);
        });
    }

    function buscarClientes() {
        var textoBusqueda = buscarCliente.value.trim();
        var token = tokenClientes + 1;
        tokenClientes = token;
        if (textoBusqueda.length < 2) {
            limpiar(resultadosClientes);
            return;
        }
        post({ op: 'buscarClientes', texto: textoBusqueda }).then(function (respuesta) {
            if (token !== tokenClientes) {
                return;
            }
            var datos = respuesta.data || {};
            if (datos.exito !== true) {
                pintarClientes([]);
                mostrarAviso(datos.mensaje || 'No se pudo buscar clientes.');
                return;
            }
            pintarClientes(datos.clientes || []);
        }).catch(function (error) {
            if (token !== tokenClientes) {
                return;
            }
            pintarClientes([]);
            mostrarAviso(mensajeError(error) || 'No se pudo buscar clientes.');
        });
    }

    function etiquetaPunto(punto) {
        return punto.codigo + ' · ' + punto.descripcion;
    }

    function elegirPunto(id, etiqueta) {
        puntoId = id;
        texto('contexto-punto', etiqueta);
        puntosCaja.querySelectorAll('button').forEach(function (boton) {
            var activo = Number(boton.getAttribute('data-id')) === id;
            boton.classList.toggle('opcion--activa', activo);
            boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
        });
        actualizarConfirmar();
    }

    function llenarPuntos() {
        var puntos = contexto.puntosEmision || [];
        limpiar(puntosCaja);
        if (puntos.length <= 1) {
            campoPunto.hidden = true;
            if (puntos.length === 1) {
                elegirPunto(puntos[0].id, etiquetaPunto(puntos[0]));
            } else {
                puntoId = 0;
                texto('contexto-punto', '—');
            }
            return;
        }
        campoPunto.hidden = false;
        puntoId = 0;
        texto('contexto-punto', 'Pendiente');
        puntos.forEach(function (punto) {
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'opcion';
            boton.setAttribute('data-id', String(punto.id));
            boton.textContent = etiquetaPunto(punto);
            boton.addEventListener('click', function () {
                if (!confirmando) {
                    elegirPunto(punto.id, etiquetaPunto(punto));
                }
            });
            puntosCaja.appendChild(boton);
        });
    }

    function esTransferencia(metodo) {
        var codigo = String(metodo.codigo || '').toUpperCase();
        var nombre = String(metodo.nombre || '').toLowerCase();
        return codigo === 'TRANSFERENCIA' || nombre.indexOf('transferencia') !== -1;
    }

    function limpiarTransferenciaValidada() {
        transferenciaValidada = null;
        cuentaTransferenciaPendiente = null;
        if (inputComprobanteTransferencia) {
            inputComprobanteTransferencia.value = '';
        }
        if (avisoComprobanteTransferencia) {
            avisoComprobanteTransferencia.hidden = true;
        }
        mostrarResumenTransferencia();
    }

    function mostrarResumenTransferencia() {
        if (!pagoTransferenciaResumen) {
            return;
        }
        if (!metodoEsTransferencia) {
            pagoTransferenciaResumen.hidden = true;
            pagoTransferenciaResumen.textContent = '';
            pagoTransferenciaResumen.classList.remove('pago-validacion--pendiente');
            return;
        }
        pagoTransferenciaResumen.hidden = false;
        if (transferenciaValidada) {
            pagoTransferenciaResumen.classList.remove('pago-validacion--pendiente');
            pagoTransferenciaResumen.textContent = 'Transferencia validada: '
                + transferenciaValidada.banco
                + ' · comprobante '
                + transferenciaValidada.comprobante;
            return;
        }
        pagoTransferenciaResumen.classList.add('pago-validacion--pendiente');
        pagoTransferenciaResumen.textContent = 'Elija una cuenta e ingrese el número de comprobante para validar el pago.';
    }

    function pintarFilasCuenta(contenedor, pares) {
        limpiar(contenedor);
        pares.forEach(function (par) {
            var fila = document.createElement('div');
            var dt = document.createElement('dt');
            var dd = document.createElement('dd');
            dt.textContent = par[0];
            dd.textContent = par[1];
            fila.appendChild(dt);
            fila.appendChild(dd);
            contenedor.appendChild(fila);
        });
    }

    function pintarCuentasTransferencia() {
        if (!listaCuentasTransferencia) {
            return;
        }
        if (listaCuentasTransferencia.childElementCount > 0) {
            return;
        }
        cuentasTransferencia.forEach(function (cuenta, indice) {
            var item = document.createElement('li');
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'cuentas-transferencia__item';
            boton.setAttribute('data-indice', String(indice));
            boton.setAttribute('aria-label', 'Elegir cuenta ' + cuenta.banco);

            var banco = document.createElement('p');
            banco.className = 'cuentas-transferencia__banco';
            banco.textContent = cuenta.banco;
            boton.appendChild(banco);

            var datos = document.createElement('dl');
            pintarFilasCuenta(datos, [
                ['Tipo', cuenta.tipo],
                ['Número', cuenta.numero],
                ['Titular', cuenta.titular],
                ['RUC', cuenta.identificacion]
            ]);
            boton.appendChild(datos);
            boton.addEventListener('click', function () {
                abrirValidarTransferencia(cuenta);
            });
            item.appendChild(boton);
            listaCuentasTransferencia.appendChild(item);
        });
    }

    function mostrarCuentasTransferencia() {
        if (!dialogoTransferencia) {
            return;
        }
        pintarCuentasTransferencia();
        if (!dialogoTransferencia.open) {
            dialogoTransferencia.showModal();
        }
    }

    function cerrarDialogoTransferencia() {
        if (dialogoTransferencia && dialogoTransferencia.open) {
            dialogoTransferencia.close();
        }
    }

    function cerrarDialogoValidarTransferencia() {
        if (dialogoValidarTransferencia && dialogoValidarTransferencia.open) {
            dialogoValidarTransferencia.close();
        }
    }

    function abrirValidarTransferencia(cuenta) {
        if (!dialogoValidarTransferencia || !resumenCuentaTransferencia) {
            return;
        }
        cuentaTransferenciaPendiente = cuenta;
        pintarFilasCuenta(resumenCuentaTransferencia, [
            ['Banco', cuenta.banco],
            ['Tipo', cuenta.tipo],
            ['Número', cuenta.numero],
            ['Titular', cuenta.titular]
        ]);
        if (inputComprobanteTransferencia) {
            inputComprobanteTransferencia.value = transferenciaValidada
                && transferenciaValidada.numero === cuenta.numero
                ? String(transferenciaValidada.comprobante || '')
                : '';
            inputComprobanteTransferencia.setCustomValidity('');
        }
        if (avisoComprobanteTransferencia) {
            avisoComprobanteTransferencia.hidden = true;
        }
        if (!dialogoValidarTransferencia.open) {
            dialogoValidarTransferencia.showModal();
        }
        if (inputComprobanteTransferencia) {
            window.setTimeout(function () {
                inputComprobanteTransferencia.focus();
                inputComprobanteTransferencia.select();
            }, 0);
        }
    }

    function confirmarValidacionTransferencia() {
        if (!cuentaTransferenciaPendiente || !inputComprobanteTransferencia) {
            return false;
        }
        var comprobante = String(inputComprobanteTransferencia.value || '').trim();
        if (comprobante === '') {
            if (avisoComprobanteTransferencia) {
                avisoComprobanteTransferencia.hidden = false;
            }
            inputComprobanteTransferencia.focus();
            return false;
        }
        if (avisoComprobanteTransferencia) {
            avisoComprobanteTransferencia.hidden = true;
        }
        transferenciaValidada = {
            banco: cuentaTransferenciaPendiente.banco,
            tipo: cuentaTransferenciaPendiente.tipo,
            numero: cuentaTransferenciaPendiente.numero,
            titular: cuentaTransferenciaPendiente.titular,
            comprobante: comprobante.slice(0, 50)
        };
        cuentaTransferenciaPendiente = null;
        cerrarDialogoValidarTransferencia();
        cerrarDialogoTransferencia();
        mostrarResumenTransferencia();
        actualizarConfirmar();
        return true;
    }

    function llenarMetodos() {
        limpiar(metodosCaja);
        metodoId = 0;
        metodoEsTransferencia = false;
        limpiarTransferenciaValidada();
        var metodos = (contexto.metodosPago || []).filter(function (metodo) {
            return String(metodo.codigo || '').toUpperCase() !== 'OTRO';
        });
        metodos.forEach(function (metodo) {
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'opcion';
            boton.setAttribute('role', 'radio');
            boton.setAttribute('aria-checked', 'false');
            boton.textContent = metodo.nombre;
            boton.addEventListener('click', function () {
                if (confirmando) {
                    return;
                }
                var eraTransferencia = metodoEsTransferencia;
                var ahoraTransferencia = esTransferencia(metodo);
                metodoId = metodo.id;
                metodoEsTransferencia = ahoraTransferencia;
                metodosCaja.querySelectorAll('button').forEach(function (item) {
                    var activo = item === boton;
                    item.classList.toggle('opcion--activa', activo);
                    item.setAttribute('aria-checked', activo ? 'true' : 'false');
                });
                if (!ahoraTransferencia) {
                    limpiarTransferenciaValidada();
                    cerrarDialogoValidarTransferencia();
                    cerrarDialogoTransferencia();
                } else {
                    if (!eraTransferencia) {
                        limpiarTransferenciaValidada();
                    }
                    mostrarResumenTransferencia();
                    mostrarCuentasTransferencia();
                }
                actualizarConfirmar();
            });
            metodosCaja.appendChild(boton);
        });
        if (metodos.length === 1) {
            metodosCaja.querySelector('button').click();
        }
    }

    function activarPos() {
        buscarProducto.disabled = false;
        buscarCliente.disabled = false;
        llenarPuntos();
        llenarMetodos();
        cliente = contexto.consumidorFinal;
        mostrarCliente();
        pintarProductos([]);
        pintarCarrito();
        pintarTotales();
        texto('sucursal-nombre', contexto.sucursal.nombre);
        marcarEstado('Listo');
        if ((contexto.puntosEmision || []).length === 0) {
            mostrarAviso('No hay un punto de emisión disponible.');
        }
        buscarProducto.focus();
    }

    function reiniciarVenta() {
        carrito = [];
        cotizacion = null;
        uuidVenta = null;
        enProceso = false;
        reintento = false;
        confirmando = false;
        ultimoAgregado = 0;
        buscarProducto.value = '';
        buscarCliente.value = '';
        limpiar(resultadosClientes);
        cliente = contexto ? contexto.consumidorFinal : null;
        metodoEsTransferencia = false;
        limpiarTransferenciaValidada();
        cerrarDialogoValidarTransferencia();
        cerrarDialogoTransferencia();
        if (descuentoOpcion) {
            descuentoOpcion.value = '0';
        }
        if (descuentoOtro) {
            descuentoOtro.value = '';
            descuentoOtro.setCustomValidity('');
        }
        sincronizarDescuentoOtro();
        mostrarAviso('');
        confirmar.textContent = 'Emitir factura';
        document.querySelectorAll('[data-volver]').forEach(function (enlace) {
            enlace.setAttribute('aria-disabled', 'false');
        });
        if (dialogo.open) {
            dialogo.close();
        }
        if (contexto) {
            llenarMetodos();
        }
        pintarProductos([]);
        pintarCarrito();
        pintarTotales();
        mostrarCliente();
        actualizarConfirmar();
        marcarEstado('Listo');
        buscarProducto.focus();
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

    function mostrarExito(datos) {
        var factura = datos.factura || {};
        dialogo.classList.remove('exito--proceso');
        texto('resultado-etiqueta', 'Comprobante');
        texto('resultado-titulo', datos.idempotente === true ? 'Venta confirmada correctamente' : 'Venta emitida correctamente');
        texto('resultado-numero', factura.numeroComprobante || '—');
        texto('resultado-total', dinero(factura.total));
        texto('resultado-estado', factura.estado || '—');
        var nota = document.getElementById('resultado-nota');
        if (datos.idempotente === true) {
            nota.hidden = false;
            nota.textContent = 'Operación recuperada sin duplicar la venta';
        } else {
            nota.hidden = true;
            nota.textContent = '';
        }
        marcarEstado('Emitida');
        if (!dialogo.open) {
            dialogo.showModal();
        }
    }

    function mostrarEnProceso(datos) {
        enProceso = true;
        dialogo.classList.add('exito--proceso');
        texto('resultado-etiqueta', 'Operación');
        texto('resultado-titulo', 'Venta en proceso');
        texto('resultado-numero', datos.id_operacion || asegurarUuid());
        texto('resultado-total', '—');
        texto('resultado-estado', 'EN_PROCESO');
        var nota = document.getElementById('resultado-nota');
        nota.hidden = false;
        nota.textContent = datos.mensaje
            || 'La operación fue recibida y será procesada automáticamente cuando el servicio se encuentre disponible.';
        marcarEstado('En proceso');
        if (!dialogo.open) {
            dialogo.showModal();
        }
    }

    function enviarVenta() {
        if (confirmando) {
            return;
        }
        var totales = totalesConDescuento();
        if (!puedeConfirmar() || !totales || !totales.descuentoValido) {
            if (metodoEsTransferencia && !transferenciaValidada) {
                mostrarAviso('Valide la transferencia: elija una cuenta e ingrese el número de comprobante.');
            } else {
                mostrarAviso(!totales || !totales.descuentoValido
                    ? 'Revise el porcentaje de descuento (0 a 100).'
                    : 'Revise el carrito, el cliente, el método de pago y el punto de emisión.');
            }
            actualizarConfirmar();
            return;
        }
        confirmando = true;
        reintento = false;
        confirmar.disabled = true;
        confirmar.textContent = 'Procesando venta...';
        mostrarAviso('Procesando venta...', true);
        document.querySelectorAll('[data-volver]').forEach(function (enlace) {
            enlace.setAttribute('aria-disabled', 'true');
        });
        sincronizarDescuentoOtro();
        pintarCarrito();
        var payload = {
            op: 'confirmarVenta',
            id_operacion: asegurarUuid(),
            id_cliente: cliente.id,
            id_metodo_pago: metodoId,
            id_punto_emision: puntoId,
            porcentaje_descuento: totales.porcentajeDescuento,
            productos: carrito.map(function (item) {
                return { id_producto: item.idProducto, cantidad: item.cantidad };
            })
        };
        if (metodoEsTransferencia && transferenciaValidada) {
            payload.transferencia = {
                banco: transferenciaValidada.banco,
                tipo: transferenciaValidada.tipo,
                numero_cuenta: transferenciaValidada.numero,
                titular: transferenciaValidada.titular,
                comprobante: transferenciaValidada.comprobante
            };
            payload.referencia_pago = transferenciaValidada.banco
                + ' | '
                + transferenciaValidada.numero
                + ' | '
                + transferenciaValidada.comprobante;
        }
        post(payload).then(function (respuesta) {
            var datos = respuesta.data || {};
            confirmando = false;
            sincronizarDescuentoOtro();
            document.querySelectorAll('[data-volver]').forEach(function (enlace) {
                enlace.setAttribute('aria-disabled', 'false');
            });
            if (datos.exito === true && datos.estado === 'EN_PROCESO') {
                mostrarAviso('');
                mostrarEnProceso(datos);
                actualizarConfirmar();
                pintarCarrito();
                return;
            }
            if (datos.exito === true && datos.factura) {
                mostrarAviso('');
                mostrarExito(datos);
                actualizarConfirmar();
                pintarCarrito();
                return;
            }
            mostrarAviso(datos.mensaje || 'No se pudo confirmar la venta.');
            programarCotizacion();
            actualizarConfirmar();
        }).catch(function (error) {
            confirmando = false;
            sincronizarDescuentoOtro();
            document.querySelectorAll('[data-volver]').forEach(function (enlace) {
                enlace.setAttribute('aria-disabled', 'false');
            });
            var claro = mensajeError(error);
            if (claro) {
                mostrarAviso(claro);
                if (error.response && error.response.status !== 403) {
                    programarCotizacion();
                }
            } else {
                reintento = true;
                mostrarAviso('No se pudo confirmar el resultado de la operación. Puede reintentar.');
            }
            actualizarConfirmar();
            pintarCarrito();
        });
    }

    buscarProducto.addEventListener('input', function () {
        window.clearTimeout(esperaBusqueda);
        esperaBusqueda = window.setTimeout(buscarProductos, 280);
    });
    formBuscar.addEventListener('submit', function (evento) {
        evento.preventDefault();
        var primero = resultados.querySelector('button');
        if (primero && buscarProducto.value.trim().length >= 2) {
            primero.click();
            return;
        }
        window.clearTimeout(esperaBusqueda);
        buscarProductos();
    });
    buscarCliente.addEventListener('input', function () {
        window.clearTimeout(esperaClientes);
        esperaClientes = window.setTimeout(buscarClientes, 280);
    });
    formCliente.addEventListener('submit', function (evento) {
        evento.preventDefault();
        window.clearTimeout(esperaClientes);
        buscarClientes();
    });
    usarConsumidor.addEventListener('click', function () {
        if (contexto && !confirmando) {
            elegirCliente(contexto.consumidorFinal);
        }
    });
    if (descuentoOpcion) {
        descuentoOpcion.addEventListener('change', function () {
            sincronizarDescuentoOtro();
            pintarTotales();
            if (descuentoOpcion.value === 'otro' && descuentoOtro) {
                descuentoOtro.focus();
            }
        });
    }
    if (descuentoOtro) {
        descuentoOtro.addEventListener('input', function () {
            pintarTotales();
        });
    }
    sincronizarDescuentoOtro();
    confirmar.addEventListener('click', enviarVenta);
    nuevaVenta.addEventListener('click', reiniciarVenta);
    if (cerrarCuentasTransferencia) {
        cerrarCuentasTransferencia.addEventListener('click', function () {
            cerrarDialogoTransferencia();
        });
    }
    if (cancelarValidarTransferencia) {
        cancelarValidarTransferencia.addEventListener('click', function () {
            cuentaTransferenciaPendiente = null;
            cerrarDialogoValidarTransferencia();
        });
    }
    if (formValidarTransferencia) {
        formValidarTransferencia.addEventListener('submit', function (evento) {
            evento.preventDefault();
            confirmarValidacionTransferencia();
        });
    }
    if (inputComprobanteTransferencia) {
        inputComprobanteTransferencia.addEventListener('input', function () {
            if (avisoComprobanteTransferencia) {
                avisoComprobanteTransferencia.hidden = String(inputComprobanteTransferencia.value || '').trim() !== '';
            }
        });
    }
    if (dialogoValidarTransferencia) {
        dialogoValidarTransferencia.addEventListener('cancel', function (evento) {
            evento.preventDefault();
            cuentaTransferenciaPendiente = null;
            cerrarDialogoValidarTransferencia();
        });
    }
    if (dialogoTransferencia) {
        dialogoTransferencia.addEventListener('cancel', function (evento) {
            evento.preventDefault();
            if (dialogoValidarTransferencia && dialogoValidarTransferencia.open) {
                return;
            }
            cerrarDialogoTransferencia();
        });
    }
    dialogo.addEventListener('cancel', function (evento) {
        evento.preventDefault();
        volverAlDashboard();
    });
    document.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Escape') {
            return;
        }
        if (dialogoValidarTransferencia && dialogoValidarTransferencia.open) {
            cuentaTransferenciaPendiente = null;
            cerrarDialogoValidarTransferencia();
            return;
        }
        if (dialogoTransferencia && dialogoTransferencia.open) {
            cerrarDialogoTransferencia();
            return;
        }
        evento.preventDefault();
        volverAlDashboard();
    });
    document.querySelectorAll('[data-volver]').forEach(function (enlace) {
        enlace.addEventListener('click', function (evento) {
            if (confirmando) {
                evento.preventDefault();
            }
        });
    });

    if (typeof axios === 'undefined') {
        mostrarAviso('No se pudo conectar. Inténtalo de nuevo.');
        return;
    }

    post({ op: 'obtenerDatosPos' }).then(function (respuesta) {
        var datos = respuesta.data || {};
        if (datos.exito !== true || !datos.sucursal || !datos.consumidorFinal) {
            mostrarAviso(datos.mensaje || 'No se pudo abrir el punto de venta.');
            marcarEstado('No disponible');
            return;
        }
        contexto = datos;
        activarPos();
    }).catch(function (error) {
        mostrarAviso(mensajeError(error) || 'No se pudo abrir el punto de venta.');
        marcarEstado('No disponible');
    });
}());
