(() => {
    const openButton = document.getElementById('open-location-picker');
    const modal = document.getElementById('location-picker-modal');
    const addressInput = document.getElementById('home-address');
    const status = document.getElementById('map-selection-status');
    const useButton = document.getElementById('use-selected-location');
    const mapElement = document.getElementById('address-map');
    if (!openButton || !modal || !addressInput || !mapElement || typeof L === 'undefined') return;

    let map = null;
    let marker = null;
    let selectedAddress = '';
    let selectedLatLng = null;

    const setStatus = (message) => { status.textContent = message; };
    const reverseGeocode = async (latlng) => {
        setStatus('Finding the address…');
        useButton.disabled = true;
        try {
            const params = new URLSearchParams({ format: 'jsonv2', lat: latlng.lat, lon: latlng.lng });
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) throw new Error('Address lookup failed');
            const data = await response.json();
            selectedAddress = data.display_name || `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
            setStatus(selectedAddress);
        } catch (error) {
            selectedAddress = `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`;
            setStatus('Address lookup unavailable. You can use the coordinates or choose another point.');
        }
        useButton.disabled = false;
    };

    const selectPoint = (latlng) => {
        selectedLatLng = latlng;
        if (marker) marker.setLatLng(latlng);
        else marker = L.marker(latlng).addTo(map);
        reverseGeocode(latlng);
    };

    const initMap = () => {
        if (map) {
            map.invalidateSize();
            return;
        }
        map = L.map(mapElement).setView([16.043, 120.333], 13); // Dagupan-area default; users can pan anywhere.
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
        }).addTo(map);
        map.on('click', (event) => selectPoint(event.latlng));
    };

    openButton.addEventListener('click', () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('map-open');
        initMap();
        window.setTimeout(() => map.invalidateSize(), 100);
        if (navigator.geolocation && !selectedLatLng && !addressInput.value.trim()) {
            navigator.geolocation.getCurrentPosition((position) => {
                if (!map || selectedLatLng) return;
                map.setView([position.coords.latitude, position.coords.longitude], 16);
            }, () => {}, { timeout: 4000, maximumAge: 60000 });
        }
    });

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('map-open');
    };
    modal.querySelectorAll('[data-close-map]').forEach((button) => button.addEventListener('click', closeModal));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
    useButton.addEventListener('click', () => {
        if (!selectedLatLng) return;
        addressInput.value = selectedAddress || `${selectedLatLng.lat.toFixed(6)}, ${selectedLatLng.lng.toFixed(6)}`;
        addressInput.dispatchEvent(new Event('input', { bubbles: true }));
        closeModal();
    });
})();
