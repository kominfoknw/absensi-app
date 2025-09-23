<x-filament::page>
    <div class="min-h-screen flex flex-col items-center justify-center bg-white p-8">
        <h2 class="text-3xl font-bold mb-6 text-black">Rekam Wajah Pegawai: {{ $record->nama }}</h2>

        <!-- Video Preview -->
        <div class="border-4 border-gray-400 rounded-lg overflow-hidden mb-4">
            <video id="video" width="640" height="480" autoplay class="rounded"></video>
        </div>

        <!-- Canvas Preview -->
        <canvas id="canvas" width="640" height="480"
            class="hidden border-4 border-gray-400 rounded-lg mb-4"></canvas>

        <!-- Buttons -->
        <div class="flex gap-4">
            <!-- Tombol Ambil Foto -->
            <button id="capture" type="button"
                style="display: flex; align-items: center; gap: 8px; padding: 12px 24px; background-color: #22c55e; color: white; border: none; border-radius: 8px; cursor: pointer;">
                <!-- Icon Kamera -->
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 7h4l2-3h6l2 3h4v13H3V7z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 11a3 3 0 100 6 3 3 0 000-6z" />
                </svg>
                <span>Ambil Foto</span>
            </button>

            <!-- Tombol Simpan Foto -->
            <button id="saveButton" type="button"
                style="display: flex; align-items: center; gap: 8px; padding: 12px 24px; background-color: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer;">
                <!-- Icon Ceklis -->
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M5 13l4 4L19 7" />
                </svg>
                <span>Simpan Foto</span>
            </button>
        </div>
    </div>

    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const captureButton = document.getElementById('capture');
        const saveButton = document.getElementById('saveButton');

        let imageData = '';

        // Aktifkan Kamera
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                video.srcObject = stream;
            })
            .catch(error => {
                alert('Kamera tidak bisa diakses: ' + error.message);
            });

        // Ambil Foto
        captureButton.addEventListener('click', () => {
            const context = canvas.getContext('2d');
            canvas.classList.remove('hidden');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            imageData = canvas.toDataURL('image/png');
        });

        // Simpan Foto AJAX
        saveButton.addEventListener('click', () => {
            if (!imageData) {
                alert('Silakan ambil foto terlebih dahulu.');
                return;
            }

            fetch("{{ route('pegawai.save-face', ['pegawai' => $record->id]) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ image: imageData })
            })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                })
                .catch(error => {
                    alert('Periksa hasil rekam wajah di data pegawai');
                    console.error(error);
                });
        });
    </script>
</x-filament::page>
