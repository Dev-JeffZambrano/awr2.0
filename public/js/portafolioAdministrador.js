(function () {
    var modal = document.getElementById('portafolio-modal');
    var titulo = document.getElementById('portafolio-modal-titulo');
    var estado = document.getElementById('portafolio-modal-estado');
    var detalle = document.getElementById('portafolio-modal-detalle');
    var cerrarBoton = document.getElementById('portafolio-cerrar');
    var mando = document.querySelector('.mando');
    var origen = null;
    var botonSesion = document.getElementById('cerrar-sesion');
    var mensajeSesion = document.getElementById('mensaje-sesion');

    function textoPlano(nodo) {
        return nodo ? nodo.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    function fondoInerte(activo) {
        if (!mando) {
            return;
        }

        if (activo) {
            mando.setAttribute('inert', '');
            document.body.style.overflow = 'hidden';
            return;
        }

        mando.removeAttribute('inert');
        document.body.style.overflow = '';
    }

    function llenar(tarjeta) {
        var insignia = tarjeta.querySelector('.portafolio-insignia');
        var estadoTexto = insignia ? textoPlano(insignia) : textoPlano(tarjeta.querySelector('.portafolio-proyecto__estado'));

        titulo.textContent = textoPlano(tarjeta.querySelector('h3'));
        estado.textContent = estadoTexto;
        estado.hidden = estadoTexto === '';
        estado.className = insignia ? 'portafolio-insignia' : 'portafolio-proyecto__estado';
        detalle.replaceChildren();

        ['.portafolio-proyecto__completo', '.portafolio-proyecto__texto'].forEach(function (selector) {
            var nodo = tarjeta.querySelector(selector);

            if (nodo) {
                detalle.appendChild(nodo.cloneNode(true));
            }
        });

        tarjeta.querySelectorAll(':scope > div').forEach(function (bloque) {
            var copia = bloque.cloneNode(true);

            copia.removeAttribute('hidden');
            detalle.appendChild(copia);
        });
    }

    function abrir(tarjeta, boton) {
        origen = boton;
        llenar(tarjeta);
        fondoInerte(true);

        if (!modal.open) {
            modal.showModal();
        }

        cerrarBoton.focus();
    }

    function cerrar() {
        if (modal.open) {
            modal.close();
        }
    }

    function focos() {
        return Array.prototype.filter.call(
            modal.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'),
            function (nodo) {
                return !nodo.closest('[hidden]') && nodo.getClientRects().length > 0;
            }
        );
    }

    document.querySelectorAll('[data-detalle]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var tarjeta = boton.closest('.portafolio-proyecto');

            if (tarjeta) {
                abrir(tarjeta, boton);
            }
        });
    });

    cerrarBoton.addEventListener('click', cerrar);
    modal.addEventListener('click', function (evento) {
        if (evento.target === modal) {
            cerrar();
        }
    });
    modal.addEventListener('close', function () {
        fondoInerte(false);

        if (origen && typeof origen.focus === 'function') {
            origen.focus();
        }
    });
    modal.addEventListener('cancel', function () {
        fondoInerte(false);
    });
    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && modal.open) {
            evento.preventDefault();
            cerrar();
        }
    });
    modal.addEventListener('keydown', function (evento) {
        var lista;
        var primero;
        var ultimo;

        if (!modal.open || evento.key !== 'Tab') {
            return;
        }

        lista = focos();

        if (!lista.length) {
            evento.preventDefault();
            cerrarBoton.focus();
            return;
        }

        primero = lista[0];
        ultimo = lista[lista.length - 1];

        if (evento.shiftKey && (document.activeElement === primero || !modal.contains(document.activeElement))) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && (document.activeElement === ultimo || !modal.contains(document.activeElement))) {
            evento.preventDefault();
            primero.focus();
        }
    });

    document.querySelectorAll('.portafolio a[href^="#"]').forEach(function (enlace) {
        enlace.addEventListener('click', function (evento) {
            var destino = document.getElementById(enlace.getAttribute('href').slice(1));

            if (!destino) {
                return;
            }

            evento.preventDefault();
            destino.scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.querySelectorAll('.portafolio-anclas a').forEach(function (item) {
                item.removeAttribute('aria-current');
            });

            if (enlace.parentElement && enlace.parentElement.classList.contains('portafolio-anclas')) {
                enlace.setAttribute('aria-current', 'true');
            }
        });
    });

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

    if (!botonSesion || typeof axios === 'undefined') {
        return;
    }

    botonSesion.addEventListener('click', function () {
        if (botonSesion.disabled) {
            return;
        }

        if (mensajeSesion) {
            mensajeSesion.hidden = true;
        }

        botonSesion.disabled = true;
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

            if (mensajeSesion) {
                mensajeSesion.textContent = 'No se pudo cerrar la sesión.';
                mensajeSesion.hidden = false;
            }
        }).catch(function () {
            if (mensajeSesion) {
                mensajeSesion.textContent = 'No se pudo conectar. Inténtalo de nuevo.';
                mensajeSesion.hidden = false;
            }
        }).finally(function () {
            if (!redirigio) {
                botonSesion.disabled = false;
            }
        });
    });
}());
