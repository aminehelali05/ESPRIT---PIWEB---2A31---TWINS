window.GlobeExplorer = (function () {
    let map = null;
    let marker = null;
    let picked = null;

    function init(mapElementId, options = {}) {
        if (!window.L) {
            throw new Error('Leaflet is not available.');
        }

        if (!map) {
            map = L.map(mapElementId, {
                center: options.center || [20, 0],
                zoom: options.zoom || 2,
                minZoom: 2,
                maxZoom: 18,
                worldCopyJump: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
        }

        return map;
    }

    async function pick(latlng, callbacks = {}) {
        const { lat, lng } = latlng;

        if (!marker) {
            marker = L.marker([lat, lng]).addTo(map);
        } else {
            marker.setLatLng([lat, lng]);
        }

        const basePicked = {
            lat: Number(lat.toFixed(6)),
            lng: Number(lng.toFixed(6)),
            country: '',
            city: '',
            display: `${lat.toFixed(4)}, ${lng.toFixed(4)}`
        };

        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`);
            const data = await response.json();

            basePicked.country = data?.address?.country || '';
            basePicked.city = data?.address?.city || data?.address?.town || data?.address?.village || '';
            basePicked.display = data?.display_name || basePicked.display;
        } catch (error) {
        }

        picked = basePicked;

        if (typeof callbacks.onPick === 'function') {
            callbacks.onPick(picked);
        }

        return picked;
    }

    function getPicked() {
        return picked;
    }

    function invalidateSize() {
        if (map) {
            map.invalidateSize();
        }
    }

    function setView(center, zoom = 5) {
        if (!map || !Array.isArray(center) || center.length < 2) {
            return;
        }

        const lat = Number(center[0]);
        const lng = Number(center[1]);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        map.setView([lat, lng], zoom, { animate: true });

        if (!marker) {
            marker = L.marker([lat, lng]).addTo(map);
        } else {
            marker.setLatLng([lat, lng]);
        }
    }

    function onMapClick(callbacks = {}) {
        if (!map) return;

        map.off('click');
        map.on('click', async (event) => {
            await pick(event.latlng, callbacks);
        });
    }

    return {
        init,
        onMapClick,
        getPicked,
        invalidateSize,
        setView
    };
})();
