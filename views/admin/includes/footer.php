<?php
require_once dirname(__DIR__, 3) . '/config/setPath.php';

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}
?>
        </div>
    </main>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha384-vtXRMe3mGCbOeY7l30aIg8H9p3GdeSe4IFlP6G8JMa7o7lXvnz3GFKzPxzJdPfGK" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <!-- Admin Modular Scripts -->
    <?php
    require_once __DIR__ . '/admin-scripts.php';
    outputAdminScripts();
    ?>

    <?php if (isset($pageScripts)): ?>
        <?php echo $pageScripts; ?>
    <?php endif; ?>
<script src="<?= FULL_BASE_PATH ?>assets/js/navigation.js?v=<?= filemtime(dirname(__DIR__, 3) . '/assets/js/navigation.js') ?>"></script>

<script src="<?= FULL_BASE_PATH ?>assets/js/admin/admin-dialogs.js?v=<?= filemtime(dirname(__DIR__, 3) . '/assets/js/admin/admin-dialogs.js') ?>"></script>
</body>
</html>
