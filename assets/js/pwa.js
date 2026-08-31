(function () {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('service-worker.js', { scope: './' })
            .then(function (registration) {
                // Pick up new SW versions quickly after deploy
                if (registration.update) {
                    registration.update().catch(function () {});
                }
                // Optional: reload once when a new worker takes control
                var refreshing = false;
                navigator.serviceWorker.addEventListener('controllerchange', function () {
                    if (refreshing) {
                        return;
                    }
                    refreshing = true;
                    // Soft reload so the new offline shell is used
                    // window.location.reload();
                });
            })
            .catch(function (error) {
                console.warn('PWA service worker registration failed.', error);
            });
    });
}());
