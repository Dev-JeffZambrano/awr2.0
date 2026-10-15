(function () {
    var disponibilidad = document.getElementById('senal-disponibilidad');
    var latencia = document.getElementById('senal-latencia');
    var sincronizacion = document.getElementById('senal-sincronizacion');

    if (!disponibilidad || !latencia || !sincronizacion || typeof axios === 'undefined') {
        return;
    }

    function marcarNoDisponible() {
        disponibilidad.textContent = 'No disponible';
        latencia.textContent = 'No disponible';
        sincronizacion.textContent = 'No disponible';
    }

    axios.post('app/Controllers/DashboardAdministradorController.php', {
        op: 'obtenerSenales'
    }).then(function (respuesta) {
        var datos = respuesta.data || {};

        if (datos.exito !== true) {
            marcarNoDisponible();
            return;
        }

        disponibilidad.textContent = datos.disponibilidad || 'No disponible';
        latencia.textContent = datos.latencia || 'No disponible';
        sincronizacion.textContent = datos.sincronizacion || 'No disponible';
    }).catch(marcarNoDisponible);
})();
