(function() {
    'use strict';

    const HQ = { lat: 41.0082, lng: 28.9784, city: 'Istanbul', country: 'Turkey' };
    const locations = window.TRAVEL_LOCATIONS || [];
    const imageBase = window.TRAVEL_IMAGE_BASE || '';
    const staticImageBase = window.TRAVEL_STATIC_IMAGE_BASE || '';

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

    // Modal logic
    function openModal(location) {
        const modal = document.getElementById('travelImageModal');
        if (!modal) return;

        const title = document.getElementById('travelModalTitle');
        const meta = document.getElementById('travelModalMeta');
        const grid = document.getElementById('travelModalGrid');

        if (title) title.textContent = `${location.city}, ${location.country}`;
        const images = Array.isArray(location.images) ? location.images : [];
        if (meta) meta.textContent = `${images.length} image(s)`;

        if (grid) {
            if (images.length === 0) {
                grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--color-text-muted, rgba(255,255,255,0.5));">No images for this location yet.</p>';
            } else {
                grid.innerHTML = images.map(img => `
                    <div class="ui-travel-image-wrap">
                        <img src="${imageBase}${img}" alt="${location.city}" loading="lazy" onerror="if(this.src!=='${staticImageBase}${img}'){this.src='${staticImageBase}${img}';this.onerror=null;}else{this.parentElement.style.display='none';}">
                    </div>
                `).join('');
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
        closeEls.forEach(el => el.addEventListener('click', () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }));
    }

    // 2D Map Initialization
    function init2DMap() {
        if (!mapEl || leafletMap) return;

        leafletMap = L.map('travelMap', {
            center: [20, 10],
            zoom: 2,
            minZoom: 2,
            worldCopyJump: true,
            scrollWheelZoom: false
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(leafletMap);

        const bounds = [];
        let totalMiles = 0;
        const countriesSet = new Set();

        locations.forEach(loc => {
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

            marker.bindPopup(`<strong>${loc.city}</strong><br>${loc.country}`);
            marker.on('click', () => openModal(loc));
        });

        if (bounds.length > 0) {
            leafletMap.fitBounds(bounds, { padding: [50, 50] });
        }

        if (countriesStat) countriesStat.textContent = countriesSet.size;
        if (milesStat) milesStat.textContent = Math.round(totalMiles).toLocaleString();
    }

    // 3D Globe Initialization
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

        // Group to hold everything
        const group = new THREE.Group();
        scene.add(group);

        // 1. The Globe (Dotted)
        const radius = 100;
        const segments = 64;
        
        // Procedural dot pattern for globe
        const globeGeom = new THREE.SphereGeometry(radius, segments, segments);
        const globeMat = new THREE.PointsMaterial({
            color: 0x4A90E2,
            size: 1.5,
            transparent: true,
            opacity: 0.4
        });
        const globePoints = new THREE.Points(globeGeom, globeMat);
        group.add(globePoints);

        // 2. Atmosphere / Glow
        const glowGeom = new THREE.SphereGeometry(radius * 1.02, segments, segments);
        const glowMat = new THREE.MeshBasicMaterial({
            color: 0x4A90E2,
            transparent: true,
            opacity: 0.05,
            side: THREE.BackSide
        });
        const glow = new THREE.Mesh(glowGeom, glowMat);
        group.add(glow);

        // 2.5 Starfield
        const starGeom = new THREE.BufferGeometry();
        const starPos = [];
        for (let i = 0; i < 2000; i++) {
            const x = (Math.random() - 0.5) * 2000;
            const y = (Math.random() - 0.5) * 2000;
            const z = (Math.random() - 0.5) * 2000;
            starPos.push(x, y, z);
        }
        starGeom.setAttribute('position', new THREE.Float32BufferAttribute(starPos, 3));
        const starMat = new THREE.PointsMaterial({ color: 0xffffff, size: 1.5, transparent: true, opacity: 0.5 });
        const stars = new THREE.Points(starGeom, starMat);
        scene.add(stars);

        // 3. Markers
        const markersGroup = new THREE.Group();
        group.add(markersGroup);

        function latLngToVector3(lat, lng, radius) {
            const phi = (90 - lat) * (Math.PI / 180);
            const theta = (lng + 180) * (Math.PI / 180);
            const x = -(radius * Math.sin(phi) * Math.cos(theta));
            const z = radius * Math.sin(phi) * Math.sin(theta);
            const y = radius * Math.cos(phi);
            return new THREE.Vector3(x, y, z);
        }

        const markerObjects = [];

        locations.forEach(loc => {
            if (!loc.lat || !loc.lng) return;
            const pos = latLngToVector3(loc.lat, loc.lng, radius);
            
            // Pulsing marker
            const markerGeom = new THREE.SphereGeometry(2, 8, 8);
            const markerMat = new THREE.MeshBasicMaterial({ color: 0x4A90E2 });
            const marker = new THREE.Mesh(markerGeom, markerMat);
            marker.position.copy(pos);
            marker.userData = { location: loc };
            markersGroup.add(marker);
            markerObjects.push(marker);

            // Add a small aura
            const auraGeom = new THREE.SphereGeometry(4, 8, 8);
            const auraMat = new THREE.MeshBasicMaterial({ color: 0x4A90E2, transparent: true, opacity: 0.2 });
            const aura = new THREE.Mesh(auraGeom, auraMat);
            aura.position.copy(pos);
            markersGroup.add(aura);
        });

        // 4. Interaction (Simple Rotate + Raycasting)
        let isDragging = false;
        let hasDragged = false;
        let previousMousePosition = { x: 0, y: 0 };
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();

        container.addEventListener('mousedown', e => { 
            isDragging = true; 
            hasDragged = false;
        });

        window.addEventListener('mouseup', e => { 
            if (isDragging && !hasDragged && e.target.closest('#travelGlobeCanvas')) {
                // Click (not drag)
                const rect = renderer.domElement.getBoundingClientRect();
                mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
                mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

                raycaster.setFromCamera(mouse, camera);
                const intersects = raycaster.intersectObjects(markerObjects);
                if (intersects.length > 0) {
                    openModal(intersects[0].object.userData.location);
                }
            }
            isDragging = false; 
        });

        window.addEventListener('mousemove', e => {
            if (isDragging) {
                hasDragged = true;
                const deltaMove = {
                    x: e.offsetX - previousMousePosition.x,
                    y: e.offsetY - previousMousePosition.y
                };

                const deltaRotationQuaternion = new THREE.Quaternion()
                    .setFromEuler(new THREE.Euler(
                        deltaMove.y * (Math.PI / 180) * 0.5,
                        deltaMove.x * (Math.PI / 180) * 0.5,
                        0,
                        'XYZ'
                    ));
                group.quaternion.multiplyQuaternions(deltaRotationQuaternion, group.quaternion);
            }
            previousMousePosition = { x: e.offsetX, y: e.offsetY };
        });

        // 5. Animation Loop
        function animate() {
            requestAnimationFrame(animate);
            if (!isDragging) {
                group.rotation.y += 0.002;
            }
            
            // Pulse markers
            const time = Date.now() * 0.005;
            markersGroup.children.forEach((child, i) => {
                if (child.geometry.type === 'SphereGeometry') {
                    const s = 1 + Math.sin(time + i) * 0.1;
                    child.scale.set(s, s, s);
                }
            });

            renderer.render(scene, camera);
        }
        animate();

        // 6. Handle Resize
        window.addEventListener('resize', () => {
            const w = container.clientWidth;
            const h = container.clientHeight;
            renderer.setSize(w, h);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
        });

        threeGlobe = { scene, renderer, group };
    }

    // Toggle logic
    function setupToggle() {
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const view = btn.dataset.viewToggle;
                if (view === activeView) return;

                activeView = view;
                toggleBtns.forEach(b => b.classList.toggle('is-active', b === btn));

                if (view === '2d') {
                    mapEl.classList.remove('is-hidden');
                    globeEl.classList.add('is-hidden');
                    if (leafletMap) leafletMap.invalidateSize();
                } else {
                    globeEl.classList.remove('is-hidden');
                    mapEl.classList.add('is-hidden');
                    if (!threeGlobe) {
                        init3DGlobe();
                    }
                }
            });
        });
    }

    // Initialize everything
    function init() {
        initModalClose();
        init2DMap();
        setupToggle();
        
        // Auto-initialize 3D if pre-selected or just to be ready
        // But better to lazy load when clicked to save resources
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
