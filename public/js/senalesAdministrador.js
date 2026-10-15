(function () {
    var disponibilidad = document.getElementById('senal-disponibilidad');
    var latencia = document.getElementById('senal-latencia');

    if (!disponibilidad || !latencia || typeof axios === 'undefined') {
        return;
    }

    function marcarNoDisponible() {
        disponibilidad.textContent = 'No disponible';
        latencia.textContent = 'No disponible';
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
    }).catch(marcarNoDisponible);
})();
