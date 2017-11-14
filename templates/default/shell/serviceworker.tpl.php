<?php

if (Idno\Core\site()->isSecure()) { // Service worker only works on TLS
    ?>
    <script>
        if (navigator.serviceWorker) {
            navigator.serviceWorker.register('<?= \Idno\Core\Idno::site()->config()->getDisplayURL(); ?>/js/service-worker.min.js', {
                scope: '/'
            });
            window.addEventListener('load', function () {
                if (navigator.serviceWorker.controller) {

                }
            });
        }
    </script>
    <?php

} 