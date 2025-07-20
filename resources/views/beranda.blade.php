<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda - Pegawai Terbaik</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f3f4f6; /* bg-gray-100 */
            line-height: 1.5;
            color: #374151; /* text-gray-700 */
        }
        .header {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .header-logo {
            height: 50px;
            width: auto;
        }
        .container {
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            /* padding-left: 1rem; /* Biarkan ini jika ingin padding umum */
            /* padding-right: 1rem; /* Biarkan ini jika ingin padding umum */
        }
        @media (min-width: 640px) { /* sm */
            .container { max-width: 640px; }
        }
        @media (min-width: 768px) { /* md */
            .container { max-width: 768px; }
        }
        @media (min-width: 1024px) { /* lg */
            .container { max-width: 1024px; }
        }
        @media (min-width: 1280px) { /* xl */
            .container { max-width: 1280px; }
        }

        .card-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 2rem;
            padding: 2rem;
        }
        .employee-card {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            text-align: center;
            width: 300px;
            transition: transform 0.2s ease-in-out;
            position: relative;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }
        .employee-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            border: 4px solid #3b82f6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .percentage-box {
            background-color: #eff6ff;
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-top: 1rem;
            font-weight: bold;
            color: #1e40af;
            border: 1px dashed #93c5fd;
        }
        .percentage-box p {
            margin: 0.25rem 0;
            font-size: 0.95rem;
        }
        .percentage-box span {
            font-size: 1.5rem;
            color: #1c64f2;
        }

        /* --- Animasi Bintang Emas Membesar-Mengecil & Bersinar --- */
        .star-rating {
            display: flex;
            justify-content: center;
            margin-top: 1rem;
            position: relative;
            z-index: 10;
        }
        .star-rating .fa-star {
            color: #FFD700;
            font-size: 1.5rem;
            animation: pulseStar 1.5s infinite alternate ease-in-out,
                       glowStar 2s infinite alternate ease-in-out;
        }
        .star-rating .fa-star:nth-child(1) { animation-delay: 0s, 0.2s; }
        .star-rating .fa-star:nth-child(2) { animation-delay: 0.1s, 0.4s; }
        .star-rating .fa-star:nth-child(3) { animation-delay: 0.2s, 0.6s; }
        .star-rating .fa-star:nth-child(4) { animation-delay: 0.3s, 0.8s; }
        .star-rating .fa-star:nth-child(5) { animation-delay: 0.4s, 1s; }

        @keyframes pulseStar {
            from { transform: scale(1); }
            to { transform: scale(1.2); }
        }
        @keyframes glowStar {
            from { text-shadow: 0 0 5px rgba(255, 215, 0, 0.5); }
            to { text-shadow: 0 0 15px rgba(255, 215, 0, 1), 0 0 25px rgba(255, 215, 0, 0.8); }
        }

        /* Styling untuk bagian bawah: Chart and Statistics Section */
        .dashboard-section {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2rem;
            /* padding: 2rem; <--- Ubah baris ini */
            padding: 1rem; /* Mengurangi padding dari 2rem menjadi 1rem */
            max-width: 1200px;
            margin: 2rem auto;
            align-items: flex-start;
        }
        .dashboard-card {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            flex-grow: 1;
            flex-shrink: 1;
            box-sizing: border-box;
            border: 1px solid #e5e7eb;
        }
        .chart-card {
            flex-basis: calc(70% - 1rem);
            min-width: 500px;
        }
        .stats-card {
            flex-basis: calc(30% - 1rem);
            min-width: 250px;
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 1023px) { /* Adjust for smaller than desktop screens */
            .chart-card, .stats-card {
                flex-basis: 100%; /* Full width on smaller screens */
                max-width: 100%; /* Ensure they take full width */
            }
            .dashboard-section {
                padding: 1rem; /* Pastikan padding tetap kecil di layar kecil */
            }
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .stat-item span:first-child {
            font-weight: 500;
            color: #4a5568;
        }
        .stat-item span:last-child {
            font-weight: bold;
            color: #2b6cb0;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <img src="{{ asset('images/logo_konawe.png') }}" alt="Logo Konawe" class="header-logo">
        </div>
        <div class="header-right">
            <img src="{{ asset('images/logo_berakhlak.png') }}" alt="Logo Berakhlak" class="header-logo">
        </div>
    </header>

    <div class="container mx-auto mt-10">
        <h1 class="text-4xl font-bold text-center text-gray-800 mb-8">Pegawai Terbaik Bulan Lalu</h1>

        @if($top3Pegawai->isEmpty())
            <p class="text-center text-gray-600 text-xl">Belum ada data pegawai atau perhitungan performa.</p>
        @else
            <div class="card-container">
                @foreach($top3Pegawai as $data)
                    <div class="employee-card">
                        @php
                            $photoPath = $data['pegawai']->foto_selfie ? asset('storage/' . $data['pegawai']->foto_selfie) : asset('images/default_avatar.png');
                        @endphp
                        <img src="{{ $photoPath }}" alt="{{ $data['pegawai']->nama }}" class="employee-photo">
                        <h2 class="text-xl font-semibold text-gray-900">{{ $data['pegawai']->nama }}</h2>
                        <p class="text-gray-600 text-sm">{{ $data['pegawai']->jabatan }}</p>
                        <p class="text-gray-700 font-medium">{{ $data['pegawai']->kantor->nama_kantor ?? 'N/A' }}</p>

                        <div class="percentage-box">
                            <p>Kehadiran: <span>{{ $data['persentase_kehadiran'] }}%</span></p>
                            <p>Kualitas Lapkin: <span>{{ $data['rata_rata_kualitas_lapkin'] }}</span></p>
                        </div>
                        <div class="star-rating">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="container mx-auto mt-20 mb-10">
        <div class="dashboard-section">
            <div class="dashboard-card chart-card">
                <h2 class="text-2xl font-bold text-center text-gray-800 mb-4">Persentase Kehadiran per Kantor Bulan Lalu</h2>
                <div style="position: relative; height:400px; width:100%;">
                    <canvas id="kehadiranChart"></canvas>
                </div>
            </div>
            <div class="dashboard-card stats-card">
                <h2 class="text-2xl font-bold text-center text-gray-800 mb-4">Statistik Umum</h2>
                <div class="space-y-2">
                    <div class="stat-item"><span>Total Pegawai:</span><span>{{ $statistikData['total_pegawai'] }}</span></div>
                    <div class="stat-item"><span>Total Kantor:</span><span>{{ $statistikData['total_kantor'] }}</span></div>
                    <div class="stat-item"><span>Total Unit:</span><span>{{ $statistikData['total_unit'] }}</span></div>
                    <div class="stat-item"><span>Hari Libur Bulan Lalu:</span><span>{{ $statistikData['total_hari_libur_bln_lalu'] }}</span></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const kantorLabels = @json($kantorLabels);
            const kantorKehadiranData = @json($kantorKehadiranData);

            console.log('Kantor Labels (JS):', kantorLabels);
            console.log('Kantor Kehadiran Data (JS):', kantorKehadiranData);

            if (!Array.isArray(kantorLabels) || kantorLabels.length === 0 ||
                !Array.isArray(kantorKehadiranData) || kantorKehadiranData.length === 0) {
                console.error('Data untuk grafik kosong atau tidak valid. Grafik tidak akan dibuat.');
                const chartContainer = document.getElementById('kehadiranChart').parentNode;
                chartContainer.innerHTML = '<p class="text-center text-gray-500 mt-4">Data grafik tidak tersedia.</p>';
                return;
            }

            const ctx = document.getElementById('kehadiranChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: kantorLabels,
                    datasets: [{
                        label: 'Persentase Kehadiran (%)',
                        data: kantorKehadiranData,
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.6)', 'rgba(153, 102, 255, 0.6)', 'rgba(255, 159, 64, 0.6)',
                            'rgba(255, 99, 132, 0.6)', 'rgba(54, 162, 235, 0.6)', 'rgba(201, 203, 207, 0.6)',
                            'rgba(255, 205, 86, 0.6)', 'rgba(192, 75, 192, 0.6)'
                        ],
                        borderColor: [
                            'rgba(75, 192, 192, 1)', 'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)',
                            'rgba(255, 99, 132, 1)', 'rgba(54, 162, 235, 1)', 'rgba(201, 203, 207, 1)',
                            'rgba(255, 205, 86, 1)', 'rgba(192, 75, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Persentase (%)', color: '#4a5568', font: { size: 14 } },
                            ticks: { callback: function(value) { return value + '%'; } }
                        },
                        x: {
                            title: { display: true, text: 'Kantor', color: '#4a5568', font: { size: 14 } }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed.y !== null) { label += context.parsed.y + '%'; }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>