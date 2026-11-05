(function () {
    if (typeof axios === 'undefined') {
        return;
    }

    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : '';

    if (!token) {
        return;
    }

    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
})();
