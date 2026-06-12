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
    
    <!-- Admin Search Script -->
    <script src="<?php echo FULL_BASE_PATH; ?>assets/js/admin/admin-search.js"></script>
    
    <!-- Admin Modular Scripts -->
    <?php 
    require_once __DIR__ . '/admin-scripts.php';
    outputAdminScripts(); 
    ?>
    
    <?php if (isset($pageScripts)): ?>
        <?php echo $pageScripts; ?>
    <?php endif; ?>
    <script>
    (function () {
        var menu = document.getElementById('adminUiMobileMenu');
        if (!menu) return;
        var openBtn = document.querySelector('[data-admin-menu-open]');
        var closeEls = menu.querySelectorAll('[data-admin-menu-close]');
        var prevOverflow = '';
        function openMenu() {
            prevOverflow = document.body.style.overflow;
            menu.classList.add('is-open');
            menu.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
        function closeMenu() {
            menu.classList.remove('is-open');
            menu.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = prevOverflow || '';
        }
        if (openBtn) openBtn.addEventListener('click', openMenu);
        closeEls.forEach(function (el) { el.addEventListener('click', closeMenu); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && menu.classList.contains('is-open')) closeMenu();
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 980 && menu.classList.contains('is-open')) closeMenu();
        });
    })();
    </script>
</body>
</html> 
