(function () {
    var controlador = 'app/Controllers/AnaliticaAdministrativoController.php';
    var boton = document.getElementById('cerrar-sesion');
    var mensaje = document.getElementById('mensaje-sesion');
    var copiar = document.getElementById('copiar-ruta');
    var aviso = document.getElementById('aviso-copia');
    var btnPrevia = document.getElementById('btn-vista-previa');
    var btnCsv = document.getElementById('btn-exportar-csv');
    var modal = document.getElementById('modal-previa');
    var chipClickhouse = document.getElementById('chip-clickhouse');
    var chipCsv = document.getElementById('chip-csv-titulo');
    var estadoActual = null;

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

    function mostrarAviso(texto) {
        if (!aviso) {
            return;
        }

        aviso.textContent = texto;
        aviso.hidden = false;
    }

    function escribir(id, valor) {
        var nodo = document.getElementById(id);
        if (nodo) {
            nodo.textContent = valor == null ? '—' : String(valor);
        }
    }

    function abrirModal() {
        if (!modal) {
            return;
        }

        var kpis = (estadoActual && estadoActual.kpis) || {};
        escribir('kpi-ventas', kpis.ventas_totales || 'No disponible');
        escribir('kpi-facturas', kpis.facturas || 'No disponible');
        escribir('kpi-ticket', kpis.ticket_promedio || 'No disponible');
        escribir('kpi-margen', kpis.margen_bruto || 'No disponible');
        escribir('kpi-porcentaje', kpis.porcentaje_margen || 'No disponible');
        escribir('kpi-lineas', kpis.lineas || 'No disponible');

        modal.hidden = false;
        document.body.classList.add('bi-modal-abierto');
    }

    function cerrarModal() {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('bi-modal-abierto');
    }

    function tituloPagina(id) {
        if (id === 'kpis') {
            return 'Indicadores KPI · Resumen ejecutivo';
        }
        if (id === 'graficos') {
            return 'Gráficos · Resumen ejecutivo';
        }
        return 'Vista completa · Resumen ejecutivo';
    }

    function mostrarEnModal(ruta, titulo) {
        var modalImg = document.getElementById('modal-previa-img');
        var caption = document.getElementById('modal-previa-caption');
        if (modalImg && ruta) {
            modalImg.src = ruta;
            modalImg.alt = 'Vista previa ' + titulo;
        }
        if (caption) {
            caption.textContent = titulo + ' · Power BI Desktop';
        }
        abrirModal();
    }

    function aplicarEstado(datos) {
        estadoActual = datos || {};
        var kpis = estadoActual.kpis || {};
        var informe = estadoActual.informe || {};
        var previa = estadoActual.vista_previa || {};
        var csv = estadoActual.csv || {};

        if (chipClickhouse) {
            chipClickhouse.textContent = kpis.disponible ? (kpis.lineas || 'Listo') + ' líneas' : 'No disponible';
        }

        if (chipCsv) {
            chipCsv.textContent = csv.disponible ? 'Listo' : 'Pendiente';
        }

        if (btnCsv) {
            btnCsv.disabled = !csv.disponible;
        }

        if (btnPrevia) {
            btnPrevia.disabled = !previa.disponible;
        }

        if (informe.disponible && informe.actualizado) {
            var ayuda = document.getElementById('chip-pbix-ayuda');
            if (ayuda) {
                ayuda.textContent = 'Actualizado: ' + informe.actualizado;
            }
        }

    }

    function cargarEstado() {
        if (typeof axios === 'undefined') {
            if (chipClickhouse) {
                chipClickhouse.textContent = 'No disponible';
            }
            if (chipCsv) {
                chipCsv.textContent = 'Pendiente';
            }
            return;
        }

        axios.post(controlador, {
            op: 'obtenerEstadoAnalitica'
        }).then(function (respuesta) {
            var datos = respuesta.data || {};
            if (datos.exito === true) {
                aplicarEstado(datos);
                return;
            }

            if (chipClickhouse) {
                chipClickhouse.textContent = 'No disponible';
            }
            if (chipCsv) {
                chipCsv.textContent = 'Pendiente';
            }
        }).catch(function () {
            if (chipClickhouse) {
                chipClickhouse.textContent = 'No disponible';
            }
            if (chipCsv) {
                chipCsv.textContent = 'Pendiente';
            }
        });
    }

    function exportarCsv() {
        if (!btnCsv || btnCsv.disabled || typeof axios === 'undefined') {
            return;
        }

        btnCsv.disabled = true;
        btnCsv.setAttribute('aria-busy', 'true');
        mostrarAviso('Generando CSV…');

        axios.post(controlador, {
            op: 'exportarCsvAnalitica'
        }, {
            responseType: 'blob'
        }).then(function (respuesta) {
            var tipo = (respuesta.headers && respuesta.headers['content-type']) || '';

            if (tipo.indexOf('application/json') !== -1) {
                mostrarAviso('No se pudo exportar el CSV.');
                return;
            }

            var blob = new Blob([respuesta.data], { type: 'text/csv;charset=utf-8' });
            var url = window.URL.createObjectURL(blob);
            var enlace = document.createElement('a');
            enlace.href = url;
            enlace.download = 'awr_ventas_analiticas.csv';
            document.body.appendChild(enlace);
            enlace.click();
            enlace.remove();
            window.URL.revokeObjectURL(url);
            mostrarAviso('CSV descargado.');
        }).catch(function () {
            mostrarAviso('No se pudo exportar el CSV.');
        }).finally(function () {
            btnCsv.disabled = !(estadoActual && estadoActual.csv && estadoActual.csv.disponible);
            btnCsv.removeAttribute('aria-busy');
        });
    }

    if (copiar) {
        copiar.addEventListener('click', function () {
            var ruta = copiar.getAttribute('data-ruta') || '';

            if (!ruta) {
                mostrarAviso('No hay ruta para copiar.');
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(ruta).then(function () {
                    mostrarAviso('Ruta copiada: ' + ruta);
                }).catch(function () {
                    mostrarAviso('Copia manualmente: ' + ruta);
                });
                return;
            }

            mostrarAviso('Copia manualmente: ' + ruta);
        });
    }

    if (btnPrevia) {
        btnPrevia.addEventListener('click', abrirModal);
    }

    if (btnCsv) {
        btnCsv.addEventListener('click', exportarCsv);
    }

    document.querySelectorAll('[data-cerrar-modal]').forEach(function (nodo) {
        nodo.addEventListener('click', cerrarModal);
    });

    document.querySelectorAll('.bi-galeria__foto').forEach(function (nodo) {
        nodo.addEventListener('click', function () {
            var img = nodo.querySelector('img');
            var pagina = nodo.getAttribute('data-pagina') || 'resumen';
            var ruta = img ? (img.getAttribute('src') || '') : '';
            mostrarEnModal(ruta, tituloPagina(pagina));
        });
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && modal && !modal.hidden) {
            cerrarModal();
        }
    });

    if (boton) {
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
    }

    cargarEstado();
}());
