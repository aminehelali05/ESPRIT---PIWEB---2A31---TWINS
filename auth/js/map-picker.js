/**
 * Map Picker for Registration
 * Handle Leaflet map location selection
 */

let map = null;
let marker = null;
let selectedLocation = null;

function openMapPicker() {
    const modal = document.getElementById('locationPickerModal');
    if (!modal) return;

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // Initialize map if not already done
    if (!map) {
        initMap();
    } else {
        setTimeout(() => map.invalidateSize(), 100);
    }
}

function closeMapPicker() {
    const modal = document.getElementById('locationPickerModal');
    if (!modal) return;

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function initMap() {
    // Default to approximate center of world (or specific region if desired)
    map = L.map('map').setView([20, 0], 2);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // On Click Event
    map.on('click', async function (e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        handleLocationSelect(lat, lng);
    });

    // Try to get user location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            map.setView([lat, lng], 5); // Zoom into user area
        });
    }
}

async function handleLocationSelect(lat, lng) {
    // Update marker
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng]).addTo(map);
    }

    const display = document.getElementById('selectedLocationDisplay');
    const confirmBtn = document.getElementById('confirmLocationBtn');

    if (display) display.textContent = "Fetching address...";
    if (confirmBtn) confirmBtn.disabled = true;

    try {
        // Reverse Geocode (using PHP proxy to avoid CORS)
        const response = await fetch(`../api/geocode/reverse.php?lat=${lat}&lon=${lng}`);
        const data = await response.json();

        let country = data.address.country || "Unknown Country";
        let city = data.address.city || data.address.town || data.address.village || data.address.state || "";
        let fullAddress = data.display_name;

        selectedLocation = {
            lat: lat,
            lng: lng,
            country: country,
            city: city,
            fullAddress: fullAddress
        };

        if (display) display.textContent = `${city ? city + ', ' : ''}${country}`;
        if (confirmBtn) confirmBtn.disabled = false;

    } catch (error) {
        console.error("Geocoding error:", error);
        if (display) display.textContent = `Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)}`;

        // Allow confirming coordinates even if geocoding fails
        selectedLocation = {
            lat: lat,
            lng: lng,
            country: "Unknown", // User can manually select if needed, but for now we fallback
            city: "",
            fullAddress: `Lat: ${lat}, Lng: ${lng}`
        };
        if (confirmBtn) confirmBtn.disabled = false;
    }
}

function confirmLocation() {
    if (!selectedLocation) return;

    // Fill form inputs
    const countryInput = document.getElementById('country');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const cityInput = document.getElementById('city');
    const addressInput = document.getElementById('fullAddress');

    if (countryInput) countryInput.value = selectedLocation.country;
    if (latInput) latInput.value = selectedLocation.lat;
    if (lngInput) lngInput.value = selectedLocation.lng;
    if (cityInput) cityInput.value = selectedLocation.city;
    if (addressInput) addressInput.value = selectedLocation.fullAddress;

    closeMapPicker();
}

function openGlobe3DPicker() {
    if (!window.Globe3DPicker) {
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: '3D Globe unavailable',
                text: 'Could not load the 3D globe picker right now.'
            });
        }
        return;
    }

    const opened = window.Globe3DPicker.open({
        url: '../assets/globale_explore/index.html?picker=1',
        onPick: (selection) => {
            const country = String(selection?.country || '').trim();
            const address = String(selection?.fullAddress || country || '').trim();

            const countryInput = document.getElementById('country');
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const cityInput = document.getElementById('city');
            const addressInput = document.getElementById('fullAddress');
            const display = document.getElementById('selectedLocationDisplay');

            if (countryInput) countryInput.value = country || 'Unknown';
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';
            if (cityInput) cityInput.value = '';
            if (addressInput) addressInput.value = address;
            if (display) display.textContent = country || 'Unknown';

            if (window.Swal) {
                Swal.fire({
                    icon: 'success',
                    title: '3D location selected',
                    text: country || 'Location selected from 3D globe',
                    timer: 1400,
                    showConfirmButton: false
                });
            }
        }
    });

    if (!opened && window.Swal) {
        Swal.fire({
            icon: 'warning',
            title: 'Popup blocked',
            text: 'Please allow popups to use the 3D globe picker.'
        });
    }
}

window.openGlobe3DPicker = openGlobe3DPicker;
