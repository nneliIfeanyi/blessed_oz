    <!-- Footer -->
    <footer class="footer bg-dark">
        <div class="container">
            <p class="m-0 text-center text-white">Copyright &copy; Inventory System <?php echo date('Y'); ?></p>
        </div>
    </footer>

    <!-- Bootstrap core JavaScript -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Datatables script -->
    <script type="text/javascript" charset="utf8" src="vendor/DataTables/datatables.js"></script>
    <script type="text/javascript" charset="utf8" src="vendor/DataTables/sumsum.js"></script>

    <!-- Chosen files for select boxes -->
    <script src="vendor/chosen/chosen.jquery.min.js"></script>
    <link rel="stylesheet" href="vendor/chosen/chosen.css" />

    <!-- Datepicker JS -->
    <script src="vendor/datepicker164/js/bootstrap-datepicker.min.js"></script>

    <!-- Bootbox JS -->
    <script src="vendor/bootbox/bootbox.min.js"></script>

    <!-- Session & Subscription context -->
    <script>
        window.userSession = {
            isProActive: <?php echo isset($_SESSION['isProActive']) && $_SESSION['isProActive'] === true ? 'true' : 'false'; ?>,
            subscriptionPlan: '<?php echo isset($_SESSION['subscription_plan']) ? htmlentities($_SESSION['subscription_plan'], ENT_QUOTES, 'UTF-8') : 'free'; ?>',
            subscriptionExpiresAt: '<?php echo isset($_SESSION['subscription_expires_at']) ? htmlentities($_SESSION['subscription_expires_at'], ENT_QUOTES, 'UTF-8') : ''; ?>'
        };
    </script>

    <!-- Custom scripts -->
    <script src="assets/js/scripts.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/scripts.js'); ?>"></script>
    <script src="assets/js/db-sync.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/db-sync.js'); ?>"></script>
    <script src="assets/js/login.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/login.js'); ?>"></script>
    <script src="assets/js/pwa.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/pwa.js'); ?>"></script>