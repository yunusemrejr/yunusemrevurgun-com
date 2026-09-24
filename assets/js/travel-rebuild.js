(() => {
    

    const HQ = { lat: 41.0082, lng: 28.9784, city: 'Istanbul', country: 'Turkey' };
    const locations = window.TRAVEL_LOCATIONS || [];
    const imageBase = window.TRAVEL_IMAGE_BASE || '';
    const staticImageBase = window.TRAVEL_STATIC_IMAGE_BASE || '';
    const galleryImageBase = window.TRAVEL_GALLERY_IMAGE_BASE || '';

    // UI Elements
    const mapEl = document.getElementById('travelMap');
    const countriesStat = document.getElementById('travelCountries');
    const milesStat = document.getElementById('travelMiles');

    let leafletMap = null;

    // Helper: Haversine distance in miles
    function haversineMiles(lat1, lon1, lat2, lon2) {
        const R = 3958.8; // Earth radius in miles
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    // Build fallback image src chain: uploads/travel/ -> assets/images/travel/ -> uploads/gallery/ -> hide
    // DOM-API build (no innerHTML): keeps lightbox filename extraction via src working
    function buildImageEl(filename, alt) {
        var wrap = document.createElement('div');
        wrap.className = 'ui-travel-image-wrap';
        wrap.setAttribute('data-img', alt || '');
        var img = document.createElement('img');
        var src2 = staticImageBase + filename;
        var src3 = galleryImageBase + filename;
        img.loading = 'lazy';
        img.alt = alt || '';
        const sources = [...new Set([imageBase + filename, src2, src3])];
        let sourceIndex = 0;
        img.onerror = function() {
            sourceIndex++;
            if (sourceIndex < sources.length) this.src = sources[sourceIndex];
            else { this.onerror = null; wrap.hidden = true; }
        };
        img.src = sources[0];
        wrap.appendChild(img);
        return wrap;
    }

    // ==================== LIGHTBOX (Feature 2) ====================

    var lightboxEl = null;

    function initLightbox() {
        if (document.getElementById('travelLightbox')) return;
        lightboxEl = document.createElement('div');
        lightboxEl.id = 'travelLightbox';
        lightboxEl.className = 'ui-travel-lightbox';
        lightboxEl.setAttribute('aria-hidden', 'true');
        lightboxEl.innerHTML =
            '<div class="ui-travel-lightbox-backdrop" data-lightbox-close></div>' +
            '<div class="ui-travel-lightbox-panel">' +
            '<button type="button" class="ui-travel-lightbox-close" data-lightbox-close aria-label="Close lightbox">×</button>' +
            '<button type="button" class="ui-travel-lightbox-nav ui-travel-lightbox-prev" data-lightbox-prev aria-label="Previous image"><span>&lsaquo;</span></button>' +
            '<div class="ui-travel-lightbox-image-wrap"><img id="travelLightboxImg" src="" alt=""></div>' +
            '<button type="button" class="ui-travel-lightbox-nav ui-travel-lightbox-next" data-lightbox-next aria-label="Next image"><span>&rsaquo;</span></button>' +
            '<div class="ui-travel-lightbox-counter" id="travelLightboxCounter"></div>' +
            '</div>';
        document.body.appendChild(lightboxEl);
    }

    var lightboxImages = [];
    var lightboxIndex = 0;

    function openLightbox(images, index) {
        initLightbox();
        if (!lightboxEl || !images || images.length === 0) return;
        lightboxImages = images;
        lightboxIndex = Math.max(0, Math.min(index, images.length - 1));
        showLightboxImage();
        lightboxEl.setAttribute('aria-hidden', 'false');
        lightboxEl.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (!lightboxEl) return;
        lightboxEl.setAttribute('aria-hidden', 'true');
        lightboxEl.style.display = 'none';
        document.body.style.overflow = '';
    }

    function showLightboxImage() {
        if (!lightboxEl || lightboxImages.length === 0) return;
        var img = lightboxImages[lightboxIndex];
        var src1 = imageBase + img;
        var src2 = staticImageBase + img;
        var src3 = galleryImageBase + img;
        var lightboxImg = document.getElementById('travelLightboxImg');
        if (!lightboxImg) return;
        lightboxImg.alt = 'Travel photo ' + (lightboxIndex + 1);
        // Reset onerror chain
        const sources = [...new Set([src1, src2, src3])];
        let sourceIndex = 0;
        lightboxImg.onerror = function() {
            sourceIndex++;
            if (sourceIndex < sources.length) this.src = sources[sourceIndex];
            else { this.onerror = null; this.alt = 'This travel photo is unavailable.'; }
        };
        lightboxImg.src = sources[0];

        var counter = document.getElementById('travelLightboxCounter');
        if (counter) counter.textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;

        // Update nav visibility
        var prevBtn = lightboxEl.querySelector('[data-lightbox-prev]');
        var nextBtn = lightboxEl.querySelector('[data-lightbox-next]');
        if (prevBtn) prevBtn.style.display = lightboxIndex > 0 ? '' : 'none';
        if (nextBtn) nextBtn.style.display = lightboxIndex < lightboxImages.length - 1 ? '' : 'none';
    }

    function lightboxPrev() {
        if (lightboxIndex > 0) { lightboxIndex--; showLightboxImage(); }
    }

    function lightboxNext() {
        if (lightboxIndex < lightboxImages.length - 1) { lightboxIndex++; showLightboxImage(); }
    }

    // ==================== LOCATION MODAL ====================

    function openModal(location) {
        const modal = document.getElementById('travelImageModal');
        if (!modal) return;

        const title = document.getElementById('travelModalTitle');
        const meta = document.getElementById('travelModalMeta');
        const grid = document.getElementById('travelModalGrid');

        if (title) title.textContent = location.city + ', ' + location.country;
        const images = Array.isArray(location.images) ? location.images : [];
        if (meta) meta.textContent = images.length + ' image(s)';

        if (grid) {
            if (images.length === 0) {
                var empty = document.createElement('p');
                empty.style.gridColumn = '1/-1';
                empty.style.textAlign = 'center';
                empty.style.padding = '2rem';
                empty.style.color = '#8fa6a6';
                empty.textContent = 'No images for this location yet.';
                grid.replaceChildren(empty);
            } else {
                grid.replaceChildren.apply(grid, images.map((img) => buildImageEl(img, location.city)));
            }
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function initModalClose() {
        const modal = document.getElementById('travelImageModal');
        if (!modal) return;
        const closeEls = modal.querySelectorAll('[data-travel-modal-close]');
        closeEls.forEach((el) => {
            el.addEventListener('click', () => {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            });
        });
    }

    // ==================== 2D MAP ====================

    function init2DMap() {
        if (!mapEl || leafletMap) return;

        leafletMap = L.map('travelMap', {
            center: [20, 10],
            zoom: 2,
            minZoom: 2,
            worldCopyJump: true,
            scrollWheelZoom: false
        });

        // WHY: CARTO light_all began requiring API keys for some clients; OSM standard tiles are keyless and already CSP-whitelisted
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(leafletMap);

        const bounds = [];
        let totalMiles = 0;
        const countriesSet = new Set();

        locations.forEach((loc) => {
            if (!loc.lat || !loc.lng) return;
            countriesSet.add(loc.country);
            totalMiles += haversineMiles(HQ.lat, HQ.lng, loc.lat, loc.lng);
            bounds.push([loc.lat, loc.lng]);

            const marker = L.marker([loc.lat, loc.lng], {
                icon: L.divIcon({
                    className: 'ui-map-dot',
                    html: '<span class="ui-map-dot-core"></span>',
                    iconSize: [14, 14],
                    iconAnchor: [7, 7]
                })
            }).addTo(leafletMap);

            marker.bindPopup('<strong>' + loc.city + '</strong><br>' + loc.country);
            marker.on('click', () => { openModal(loc); });
        });

        if (bounds.length > 0) {
            leafletMap.fitBounds(bounds, { padding: [50, 50] });
        }

        if (countriesStat) countriesStat.textContent = countriesSet.size;
        if (milesStat) milesStat.textContent = Math.round(totalMiles).toLocaleString();
    }


    // ==================== LIGHTBOX EVENT BINDINGS ====================

    function setupLightboxEvents() {
        // Click on any image in the travel modal grid to open lightbox
        document.addEventListener('click', (e) => {
            var wrap = e.target.closest('.ui-travel-image-wrap');
            if (!wrap) return;
            var grid = document.getElementById('travelModalGrid');
            if (!grid || !grid.contains(wrap)) return;

            var allWraps = grid.querySelectorAll('.ui-travel-image-wrap');
            var images = [];
            var index = 0;
            allWraps.forEach((w, i) => {
                var imgEl = w.querySelector('img');
                if (imgEl) {
                    // Extract filename from src — grab the last path segment
                    var src = imgEl.getAttribute('src') || '';
                    var filename = src.split('/').pop();
                    if (filename && !filename.startsWith('http')) {
                        images.push(filename);
                        if (w === wrap) index = i;
                    }
                }
            });
            if (images.length > 0) {
                openLightbox(images, index);
                e.preventDefault();
            }
        });

        // Lightbox close / nav events (delegated)
        document.addEventListener('click', (e) => {
            var btn = e.target.closest('[data-lightbox-close]');
            if (btn) { closeLightbox(); return; }
            btn = e.target.closest('[data-lightbox-prev]');
            if (btn) { lightboxPrev(); return; }
            btn = e.target.closest('[data-lightbox-next]');
            if (btn) { lightboxNext(); return; }
        });

        // Keyboard navigation for lightbox
        document.addEventListener('keydown', (e) => {
            if (!lightboxEl || lightboxEl.getAttribute('aria-hidden') !== 'false') return;
            if (e.key === 'Escape') { closeLightbox(); return; }
            if (e.key === 'ArrowLeft') { lightboxPrev(); return; }
            if (e.key === 'ArrowRight') { lightboxNext(); return; }
        });
    }

    // ==================== INIT ====================

    function init() {
        initModalClose();
        init2DMap();
        initLightbox();
        setupLightboxEvents();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
