(function() {
    'use strict';

    const HQ = { lat: 41.0082, lng: 28.9784, city: 'Istanbul', country: 'Turkey' };
    const locations = window.TRAVEL_LOCATIONS || [];
    const imageBase = window.TRAVEL_IMAGE_BASE || '';
    const staticImageBase = window.TRAVEL_STATIC_IMAGE_BASE || '';
    const galleryImageBase = window.TRAVEL_GALLERY_IMAGE_BASE || '';

    // UI Elements
    const mapEl = document.getElementById('travelMap');
    const globeEl = document.getElementById('travelGlobe');
    const toggleBtns = document.querySelectorAll('[data-view-toggle]');
    const countriesStat = document.getElementById('travelCountries');
    const milesStat = document.getElementById('travelMiles');

    let leafletMap = null;
    let threeGlobe = null;
    let activeView = '2d';

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
    function buildImageHtml(filename, alt) {
        var escapedAlt = (alt || '').replace(/['"]/g, '');
        var src1 = imageBase + filename;
        var src2 = staticImageBase + filename;
        var src3 = galleryImageBase + filename;
        return '<div class="ui-travel-image-wrap" data-img="' + escapedAlt + '">' +
            '<img src="' + src1 + '" alt="' + escapedAlt + '" loading="lazy" ' +
            'onerror="' +
            'if(this.src!==\'' + src2 + '\'){this.src=\'' + src2 + '\';this.onerror=null;}' +
            'else if(this.src!==\'' + src3 + '\'){this.src=\'' + src3 + '\';this.onerror=null;}' +
            'else{this.parentElement.style.display=\'none\';}' +
            '"></div>';
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
        lightboxImg.onerror = function() {
            if (this.src !== src2) { this.src = src2; return; }
            if (this.src !== src3) { this.src = src3; return; }
        };
        lightboxImg.src = src1;

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
                grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #8fa6a6;">No images for this location yet.</p>';
            } else {
                grid.innerHTML = images.map(function(img) {
                    return buildImageHtml(img, location.city);
                }).join('');
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
        closeEls.forEach(function(el) {
            el.addEventListener('click', function() {
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

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(leafletMap);

        const bounds = [];
        let totalMiles = 0;
        const countriesSet = new Set();

        locations.forEach(function(loc) {
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
            marker.on('click', function() { openModal(loc); });
        });

        if (bounds.length > 0) {
            leafletMap.fitBounds(bounds, { padding: [50, 50] });
        }

        if (countriesStat) countriesStat.textContent = countriesSet.size;
        if (milesStat) milesStat.textContent = Math.round(totalMiles).toLocaleString();
    }

    // ==================== 3D GLOBE ====================

    function init3DGlobe() {
        if (!window.THREE || threeGlobe) return;

        const container = document.getElementById('travelGlobeCanvas');
        if (!container) return;

        const width = container.clientWidth;
        const height = container.clientHeight;

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
        camera.position.z = 250;

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(width, height);
        renderer.setPixelRatio(window.devicePixelRatio);
        container.appendChild(renderer.domElement);

        const group = new THREE.Group();
        scene.add(group);

        const radius = 100;
        const segments = 64;

        const globeGeom = new THREE.SphereGeometry(radius, segments, segments);
        const globeMat = new THREE.PointsMaterial({
            color: 0x8490a4,
            size: 1.5,
            transparent: true,
            opacity: 0.4
        });
        const globePoints = new THREE.Points(globeGeom, globeMat);
        group.add(globePoints);

        const glowGeom = new THREE.SphereGeometry(radius * 1.02, segments, segments);
        const glowMat = new THREE.MeshBasicMaterial({
            color: 0x8490a4,
            transparent: true,
            opacity: 0.05,
            side: THREE.BackSide
        });
        const glow = new THREE.Mesh(glowGeom, glowMat);
        group.add(glow);

        const starGeom = new THREE.BufferGeometry();
        const starPos = [];
        for (var si = 0; si < 2000; si++) {
            starPos.push((Math.random() - 0.5) * 2000);
            starPos.push((Math.random() - 0.5) * 2000);
            starPos.push((Math.random() - 0.5) * 2000);
        }
        starGeom.setAttribute('position', new THREE.Float32BufferAttribute(starPos, 3));
        const starMat = new THREE.PointsMaterial({ color: 0xffffff, size: 1.5, transparent: true, opacity: 0.5 });
        const stars = new THREE.Points(starGeom, starMat);
        scene.add(stars);

        const markersGroup = new THREE.Group();
        group.add(markersGroup);

        function latLngToVector3(lat, lng, r) {
            const phi = (90 - lat) * Math.PI / 180;
            const theta = (lng + 180) * Math.PI / 180;
            return new THREE.Vector3(
                -(r * Math.sin(phi) * Math.cos(theta)),
                r * Math.cos(phi),
                r * Math.sin(phi) * Math.sin(theta)
            );
        }

        const markerObjects = [];

        locations.forEach(function(loc) {
            if (!loc.lat || !loc.lng) return;
            const pos = latLngToVector3(loc.lat, loc.lng, radius);

            const markerGeom = new THREE.SphereGeometry(2, 8, 8);
            const markerMat = new THREE.MeshBasicMaterial({ color: 0x8490a4 });
            const marker = new THREE.Mesh(markerGeom, markerMat);
            marker.position.copy(pos);
            marker.userData = { location: loc };
            markersGroup.add(marker);
            markerObjects.push(marker);

            const auraGeom = new THREE.SphereGeometry(4, 8, 8);
            const auraMat = new THREE.MeshBasicMaterial({ color: 0x8490a4, transparent: true, opacity: 0.2 });
            const aura = new THREE.Mesh(auraGeom, auraMat);
            aura.position.copy(pos);
            markersGroup.add(aura);
        });

        let isDragging = false;
        let hasDragged = false;
        let previousMousePosition = { x: 0, y: 0 };
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();

        container.addEventListener('mousedown', function(e) {
            isDragging = true;
            hasDragged = false;
        });

        window.addEventListener('mouseup', function(e) {
            if (isDragging && !hasDragged && e.target.closest('#travelGlobeCanvas')) {
                const rect = renderer.domElement.getBoundingClientRect();
                mouse.x = (e.clientX - rect.left) / rect.width * 2 - 1;
                mouse.y = -(e.clientY - rect.top) / rect.height * 2 + 1;
                raycaster.setFromCamera(mouse, camera);
                const intersects = raycaster.intersectObjects(markerObjects);
                if (intersects.length > 0) {
                    openModal(intersects[0].object.userData.location);
                }
            }
            isDragging = false;
        });

        window.addEventListener('mousemove', function(e) {
            if (isDragging) {
                hasDragged = true;
                const deltaMove = {
                    x: e.offsetX - previousMousePosition.x,
                    y: e.offsetY - previousMousePosition.y
                };
                const q = new THREE.Quaternion()
                    .setFromEuler(new THREE.Euler(
                        deltaMove.y * Math.PI / 180 * 0.5,
                        deltaMove.x * Math.PI / 180 * 0.5,
                        0, 'XYZ'
                    ));
                group.quaternion.multiplyQuaternions(q, group.quaternion);
            }
            previousMousePosition = { x: e.offsetX, y: e.offsetY };
        });

        function animate() {
            requestAnimationFrame(animate);
            if (!isDragging) group.rotation.y += 0.002;
            var time = Date.now() * 0.005;
            markersGroup.children.forEach(function(child, i) {
                if (child.geometry && child.geometry.type === 'SphereGeometry') {
                    var s = 1 + Math.sin(time + i) * 0.1;
                    child.scale.set(s, s, s);
                }
            });
            renderer.render(scene, camera);
        }
        animate();

        window.addEventListener('resize', function() {
            var w = container.clientWidth;
            var h = container.clientHeight;
            renderer.setSize(w, h);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
        });

        threeGlobe = { scene: scene, renderer: renderer, group: group };
    }

    // Toggle 2D / 3D
    function setupToggle() {
        toggleBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const view = btn.dataset.viewToggle;
                if (view === activeView) return;
                activeView = view;
                toggleBtns.forEach(function(b) { b.classList.toggle('is-active', b === btn); });
                if (view === '2d') {
                    mapEl.classList.remove('is-hidden');
                    globeEl.classList.add('is-hidden');
                    if (leafletMap) leafletMap.invalidateSize();
                } else {
                    globeEl.classList.remove('is-hidden');
                    mapEl.classList.add('is-hidden');
                    if (!threeGlobe) init3DGlobe();
                }
            });
        });
    }

    // ==================== LIGHTBOX EVENT BINDINGS ====================

    function setupLightboxEvents() {
        // Click on any image in the travel modal grid to open lightbox
        document.addEventListener('click', function(e) {
            var wrap = e.target.closest('.ui-travel-image-wrap');
            if (!wrap) return;
            var grid = document.getElementById('travelModalGrid');
            if (!grid || !grid.contains(wrap)) return;

            var allWraps = grid.querySelectorAll('.ui-travel-image-wrap');
            var images = [];
            var index = 0;
            allWraps.forEach(function(w, i) {
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
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-lightbox-close]');
            if (btn) { closeLightbox(); return; }
            btn = e.target.closest('[data-lightbox-prev]');
            if (btn) { lightboxPrev(); return; }
            btn = e.target.closest('[data-lightbox-next]');
            if (btn) { lightboxNext(); return; }
        });

        // Keyboard navigation for lightbox
        document.addEventListener('keydown', function(e) {
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
        setupToggle();
        initLightbox();
        setupLightboxEvents();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
