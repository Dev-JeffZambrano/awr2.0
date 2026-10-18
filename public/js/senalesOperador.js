(function () {
    var modo = document.getElementById('modo-resiliente');

    if (!modo || typeof axios === 'undefined') {
        return;
    }

    axios.post('app/Controllers/DashboardOperadorController.php', {
        op: 'obtenerSenalesOperador'
    }).then(function (respuesta) {
        var datos = respuesta.data || {};

        if (datos.exito !== true) {
            modo.textContent = 'No disponible';
            return;
        }

        modo.textContent = typeof datos.modoResiliente === 'string' && datos.modoResiliente !== ''
            ? datos.modoResiliente
            : 'No disponible';
    }).catch(function () {
        modo.textContent = 'No disponible';
    });
})();
