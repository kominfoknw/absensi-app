<div style="height: 400px;" id="lokasi-map"></div>

@once
    @push('scripts')
        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        />
        <script
            src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        ></script>
    @endpush
@endonce

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const map = L.map('lokasi-map').setView([-4.1461, 122.1746], 9); // Sulawesi Tenggara

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
        }).addTo(map);

        let marker;

        function updateInputs(lat, lng) {
            const latInput = document.querySelector('[name="data.latitude"]');
            const lngInput = document.querySelector('[name="data.longitude"]');

            if (latInput && lngInput) {
                latInput.value = lat;
                lngInput.value = lng;
            }
        }

        map.on('click', function(e) {
            const { lat, lng } = e.latlng;

            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }

            updateInputs(lat, lng);
        });

        // cek jika sudah ada koordinat saat edit
        const latInput = document.querySelector('[name="data.latitude"]');
        const lngInput = document.querySelector('[name="data.longitude"]');

        if (latInput?.value && lngInput?.value) {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);

            marker = L.marker([lat, lng]).addTo(map);
            map.setView([lat, lng], 12);
        }
    });
</script>
@endpush
