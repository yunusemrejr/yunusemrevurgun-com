/**
 * Post-Code Page — Category Filtering
 */

(function() {
    'use strict';

    function initFilters() {
        var filterButtons = document.querySelectorAll('[data-filter]');
        var cards = document.querySelectorAll('.ui-feed-card');

        if (!filterButtons.length || !cards.length) return;

        filterButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var filter = button.getAttribute('data-filter');

                // Update active button state
                filterButtons.forEach(function(btn) {
                    btn.classList.remove('is-active');
                });
                button.classList.add('is-active');

                // Filter cards
                cards.forEach(function(card) {
                    var category = card.getAttribute('data-category');
                    if (filter === 'all' || category === filter) {
                        card.classList.remove('is-hidden');
                        // Small stagger re-trigger for visual polish
                        card.style.animation = 'none';
                        void card.offsetWidth;
                        card.style.animation = '';
                    } else {
                        card.classList.add('is-hidden');
                    }
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFilters);
    } else {
        initFilters();
    }
})();
