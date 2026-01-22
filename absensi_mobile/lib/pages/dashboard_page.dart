import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:absensi_mobile/pages/login_page.dart';
import 'package:absensi_mobile/pages/absen_page.dart';
import 'package:location/location.dart';
import 'package:cool_alert/cool_alert.dart';
import 'package:absensi_mobile/services/api_service.dart';
import 'package:absensi_mobile/pages/izin_page.dart';
import 'package:absensi_mobile/pages/lapkin_page.dart';
import 'package:absensi_mobile/pages/tugas_luar_page.dart';
import 'package:absensi_mobile/pages/history_page.dart';
import 'package:absensi_mobile/pages/profile_page.dart';
import 'package:absensi_mobile/pages/berita_detail_page.dart';
import 'package:absensi_mobile/pages/emergency_absence_page.dart';
import 'package:intl/intl.dart'; // <<< PASTIKAN INI DIIMPOR
import 'package:shared_preferences/shared_preferences.dart'; // Import ini
import 'package:absensi_mobile/services/auth_service.dart';


class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> with WidgetsBindingObserver {
  Map<String, dynamic>? user;
  List<dynamic> _beritaList = [];
  bool _isLoadingBerita = true;
  String? _errorMessageBerita;
  final storage = const FlutterSecureStorage();
  bool fakeGpsDetected = false;

  final PageController _pageController = PageController(viewportFraction: 0.85);
  Timer? _timer;
  int _currentPage = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    fetchUserData();
    fetchBerita();
    checkFakeGPS();

    _timer = Timer.periodic(const Duration(seconds: 5), (Timer timer) {
      if (_beritaList.isNotEmpty && _pageController.hasClients) {
        int nextPage = (_currentPage + 1) % _beritaList.length;
        _pageController.animateToPage(
          nextPage,
          duration: const Duration(milliseconds: 600),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    _pageController.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      checkFakeGPS();
    }
  }

  Future<void> checkFakeGPS() async {
    Location location = Location();

    bool serviceEnabled = await location.serviceEnabled();
    if (!serviceEnabled) {
      serviceEnabled = await location.requestService();
      if (!serviceEnabled) return;
    }

    PermissionStatus permissionGranted = await location.hasPermission();
    if (permissionGranted == PermissionStatus.denied) {
      permissionGranted = await location.requestPermission();
      if (permissionGranted != PermissionStatus.granted) return;
    }

    LocationData locationData = await location.getLocation();

    if ((locationData.isMock ?? false) && !fakeGpsDetected) {
      setState(() {
        fakeGpsDetected = true;
      });

      if (context.mounted) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.error,
          title: 'Fake GPS Terdeteksi!',
          text: 'Matikan Fake GPS sebelum melanjutkan.',
          barrierDismissible: false,
          confirmBtnText: 'Keluar',
          onConfirmBtnTap: () {
            Navigator.pop(context);
            exit(0);
          },
        );
      }
    } else if (fakeGpsDetected && !(locationData.isMock ?? false)) {
      setState(() {
        fakeGpsDetected = false;
      });
      if (context.mounted) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.success,
          title: 'Fake GPS Nonaktif',
          text: 'Anda sekarang dapat melanjutkan penggunaan aplikasi.',
        );
      }
    }
  }

  Future<void> fetchUserData() async {
  // 1️⃣ Load dari LOCAL dulu (offline-safe)
  final localPegawai = await AuthService.getPegawaiLocal();
  if (localPegawai != null) {
    setState(() {
      user = localPegawai;
    });
  }

  // 2️⃣ Lalu coba update dari API (jika online)
  try {
    final token = await storage.read(key: 'token');
    final url = ApiService.buildUri('/api/user');

    final response = await http.get(
      url,
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final pegawai = jsonDecode(response.body)['pegawai'];

      // update UI
      setState(() {
        user = pegawai;
      });

      // update local cache
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('pegawai', jsonEncode(pegawai));
    }
  } catch (_) {
    // 🔕 DIAMKAN ERROR → OFFLINE MODE
  }
}


  Future<void> fetchBerita() async {
    setState(() {
      _isLoadingBerita = true;
      _errorMessageBerita = null;
    });

    try {
      final uri = ApiService.buildUri('/api/berita');
      final response = await http.get(uri);

      if (response.statusCode == 200) {
        final responseData = jsonDecode(response.body);
        if (responseData is Map && responseData.containsKey('data')) {
          setState(() {
            _beritaList = responseData['data'] ?? [];
            _currentPage = 0;
          });
        } else {
          setState(() {
            _errorMessageBerita = "Struktur respons berita tidak sesuai: 'data' kunci tidak ditemukan.";
          });
        }
      } else {
        setState(() {
          _errorMessageBerita = "Gagal memuat berita: ${response.statusCode} ${response.reasonPhrase}";
        });
      }
    } catch (e) {
      setState(() {
        _errorMessageBerita = "Terjadi kesalahan jaringan atau parsing saat memuat berita: $e";
      });
    } finally {
      setState(() {
        _isLoadingBerita = false;
      });
    }
  }

  Future<void> logout() async {
    final token = await storage.read(key: 'token');
    final url = ApiService.buildUri('/api/logout');

    try {
      final response = await http.post(
        url,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        await storage.delete(key: 'token');
        if (context.mounted) {
          Navigator.pushAndRemoveUntil(
            context,
            MaterialPageRoute(builder: (context) => const LoginPage()),
            (route) => false,
          );
        }
      } else {
        await storage.delete(key: 'token');
        if (context.mounted) {
          Navigator.pushAndRemoveUntil(
            context,
            MaterialPageRoute(builder: (context) => const LoginPage()),
            (route) => false,
          );
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Logout gagal di server, tapi Anda telah keluar dari aplikasi.')),
          );
        }
      }
    } catch (e) {
      await storage.delete(key: 'token');
      if (context.mounted) {
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (context) => const LoginPage()),
          (route) => false,
        );
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Terjadi kesalahan jaringan saat logout: $e')),
        );
      }
    }
  }

  void confirmLogout() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("Konfirmasi Logout"),
        content: const Text("Yakin ingin logout?"),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text("Tidak"),
          ),
          TextButton(
            onPressed: () async {
              Navigator.pop(context);
              await logout();
            },
            child: const Text("Ya"),
          ),
        ],
      ),
    );
  }

  Widget buildMenuItem(String title, IconData icon, {VoidCallback? onTap}) {
    return InkWell(
      onTap: fakeGpsDetected ? null : onTap,
      child: Column(
        children: [
          CircleAvatar(
            radius: 28,
            backgroundColor: fakeGpsDetected ? Colors.grey : const Color.fromARGB(255, 92, 30, 235),
            child: Icon(icon, color: Colors.white),
          ),
          const SizedBox(height: 8),
          Text(
            title,
            style: TextStyle(
              fontSize: 12,
              color: fakeGpsDetected ? Colors.grey : Colors.black,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPageIndicator() {
    List<Widget> indicators = [];
    for (int i = 0; i < _beritaList.length; i++) {
      indicators.add(
        Container(
          width: 8.0,
          height: 8.0,
          margin: const EdgeInsets.symmetric(horizontal: 4.0),
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: _currentPage == i ? Theme.of(context).primaryColor : Colors.grey[300],
          ),
        ),
      );
    }
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: indicators,
    );
  }

  @override
  Widget build(BuildContext context) {
    final avatarUrl = user?['foto_selfie'];
    final nama = user?['nama'] ?? 'Nama Pegawai';
    final jabatan = user?['jabatan'] ?? 'Jabatan';
    final jamMasuk = user?['shift']?['jam_masuk'];
    final jamPulang = user?['shift']?['jam_pulang'];
    final hasShift = jamMasuk != null && jamPulang != null;

    final imageProvider = avatarUrl != null && avatarUrl != ""
        ? NetworkImage(ApiService.storageUrl(avatarUrl))
        : const AssetImage('assets/images/default_avatar.png') as ImageProvider;

    return Scaffold(
      bottomNavigationBar: BottomNavigationBar(
        selectedItemColor: Theme.of(context).primaryColor,
        unselectedItemColor: Colors.grey,
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
          BottomNavigationBarItem(icon: Icon(Icons.history), label: 'History'),
          BottomNavigationBarItem(icon: Icon(Icons.file_copy), label: 'Lapkin'),
          BottomNavigationBarItem(icon: Icon(Icons.person), label: 'Profile'),
        ],
        onTap: (int index) {
          if (fakeGpsDetected) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Harap matikan Fake GPS untuk melanjutkan.')),
            );
            return;
          }
          switch (index) {
            case 0:
              // Home (current page)
              break;
            case 1:
              Navigator.push(context, MaterialPageRoute(builder: (context) => const HistoryPage()));
              break;
            case 2:
              Navigator.push(context, MaterialPageRoute(builder: (context) => const LapkinPage()));
              break;
            case 3:
              Navigator.push(context, MaterialPageRoute(builder: (context) => const ProfilePage()));
              break;
          }
        },
      ),
      drawer: Drawer(
        child: ListView(
          children: [
            const DrawerHeader(child: Text("Menu")),
            ListTile(
              leading: const Icon(Icons.history),
              title: const Text("Riwayat Absensi"),
              onTap: fakeGpsDetected ? null : () {
                Navigator.of(context).pop();
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (context) => const HistoryPage()),
                );
              },
            ),
            ListTile(
              leading: const Icon(Icons.event_note),
              title: const Text("Izin"),
              onTap: fakeGpsDetected ? null : () {
                Navigator.of(context).pop();
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (context) => const IzinPage()),
                );
              },
            ),
            ListTile(
              leading: const Icon(Icons.outbox),
              title: const Text("Tugas Luar"),
              onTap: fakeGpsDetected ? null : () {
                Navigator.of(context).pop();
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (context) => const TugasLuarPage()),
                );
              },
            ),
            ListTile(
              leading: const Icon(Icons.close),
              title: const Text("Cuti"),
              onTap: fakeGpsDetected ? null : () {
                 ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Halaman Cuti belum tersedia.')),
                 );
              },
            ),
            ListTile(
              leading: const Icon(Icons.insert_drive_file),
              title: const Text("Laporan Kinerja"),
              onTap: fakeGpsDetected ? null : () {
                Navigator.of(context).pop();
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (context) => const LapkinPage()),
                );
              },
            ),
            // --- Tambahkan link Absen Darurat di Drawer ---
            ListTile(
              leading: const Icon(Icons.qr_code),
              title: const Text("Absen Darurat"),
              onTap: fakeGpsDetected ? null : () {
                Navigator.of(context).pop(); // Tutup drawer
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (context) => const EmergencyAbsencePage()),
                );
              },
            ),
            // --- Akhir penambahan link Absen Darurat di Drawer ---
            const Divider(),
            ListTile(
              leading: const Icon(Icons.logout),
              title: const Text("Logout"),
              onTap: () {
                Navigator.of(context).pop();
                confirmLogout();
              },
            ),
          ],
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            children: [
              Stack(
                children: [
                  Image.asset(
                    'assets/images/banner_konawe.jpg',
                    width: double.infinity,
                    height: 150,
                    fit: BoxFit.cover,
                  ),
                  Positioned(
                    top: 10,
                    left: 10,
                    child: Builder(
                      builder: (context) => IconButton(
                        icon: const Icon(Icons.menu, color: Colors.white),
                        onPressed: fakeGpsDetected ? null : () => Scaffold.of(context).openDrawer(),
                      ),
                    ),
                  ),
                  Positioned(
                    top: 10,
                    left: 60,
                    child: Row(
                      children: [
                        CircleAvatar(
                          backgroundImage: imageProvider,
                          radius: 20,
                        ),
                        const SizedBox(width: 8),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(nama,
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: Colors.white)),
                            Text(jabatan,
                                style: const TextStyle(
                                    fontStyle: FontStyle.italic,
                                    fontSize: 12,
                                    color: Colors.white)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              const Text("Selamat Datang",
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              hasShift
                  ? Text(
                      "Waktu Kerja: $jamMasuk - $jamPulang",
                      style: const TextStyle(fontSize: 14),
                    )
                  : const Text(
                      "Anda Belum Memiliki Jam Kerja",
                      style: TextStyle(color: Colors.red),
                    ),
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: fakeGpsDetected ? Colors.grey : Colors.green,
                      minimumSize: const Size(130, 50),
                    ),
                    onPressed: fakeGpsDetected ? null : () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (context) => const AbsenPage(isMasuk: true)),
                      );
                    },
                    child: const Text("MASUK",
                        style: TextStyle(color: Colors.white)),
                  ),
                  ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: fakeGpsDetected ? Colors.grey : Colors.green,
                      minimumSize: const Size(130, 50),
                    ),
                    onPressed: fakeGpsDetected ? null : () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (context) => const AbsenPage(isMasuk: false)),
                      );
                    },
                    child: const Text("PULANG",
                        style: TextStyle(color: Colors.white)),
                  ),
                ],
              ),
              const SizedBox(height: 30),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    buildMenuItem('History', Icons.bar_chart, onTap: () {
                      Navigator.push(context, MaterialPageRoute(builder: (context) => const HistoryPage()));
                    }),
                    buildMenuItem('Izin', Icons.person_off, onTap: () {
                      Navigator.push(context, MaterialPageRoute(builder: (context) => const IzinPage()));
                    }),
                    buildMenuItem('Cuti', Icons.close, onTap: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Halaman Cuti belum tersedia.')),
                      );
                    }),
                    buildMenuItem('Lapkin', Icons.insert_drive_file, onTap: () {
                      Navigator.push(context, MaterialPageRoute(builder: (context) => const LapkinPage()));
                    }),
                  ],
                ),
              ),
              // --- Tambahkan QR Absen Darurat sebagai menu terpisah di sini ---
              const SizedBox(height: 20), // Spasi antar baris menu
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.start, // Atur alignment ke kiri
                  children: [
                    buildMenuItem('QR Absen Darurat', Icons.qr_code, onTap: () {
                      Navigator.push(context, MaterialPageRoute(builder: (context) => const EmergencyAbsencePage()));
                    }),
                  ],
                ),
              ),
              // --- Akhir penambahan QR Absen Darurat sebagai menu terpisah ---
              const SizedBox(height: 30),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16.0),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Berita Terbaru',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              _isLoadingBerita
                  ? const Center(child: CircularProgressIndicator())
                  : _errorMessageBerita != null
                      ? Center(
                          child: Padding(
                            padding: const EdgeInsets.all(16.0),
                            child: Text(
                              _errorMessageBerita!,
                              textAlign: TextAlign.center,
                              style: const TextStyle(color: Colors.red, fontSize: 16),
                            ),
                          ),
                        )
                      : _beritaList.isEmpty
                          ? const Center(
                              child: Padding(
                                padding: EdgeInsets.all(16.0),
                                child: Text('Belum ada berita terbaru saat ini.'),
                              ),
                            )
                          : SizedBox(
                              height: 190, // Tinggi PageView
                              child: PageView.builder(
                                controller: _pageController,
                                itemCount: _beritaList.length,
                                onPageChanged: (int index) {
                                  setState(() {
                                    _currentPage = index;
                                  });
                                },
                                itemBuilder: (BuildContext context, int index) {
                                  final berita = _beritaList[index];
                                  String imageUrl = berita['gambar_url'] ?? '';
                                  final String judul = berita['judul'] ?? 'Judul Tidak Tersedia';
                                  final int id = berita['id'];
                                  final String createdAtRaw = berita['created_at'] ?? ''; // Ambil string tanggal mentah

                                  // --- Perubahan di sini: Memformat tanggal ke format Indonesia ---
                                  String displayedDate = 'Tanggal Tidak Tersedia';
                                  if (createdAtRaw.isNotEmpty) {
                                    try {
                                      // Parse string tanggal dari API menjadi DateTime
                                      final DateTime dateTime = DateTime.parse(createdAtRaw);
                                      // Gunakan DateFormat untuk memformat ke Bahasa Indonesia
                                      // Contoh: "11 Juli 2025"
                                      displayedDate = DateFormat('dd MMMM yyyy', 'id').format(dateTime);
                                    } catch (e) {
                                      // Tangani jika parsing gagal, tampilkan saja string mentah atau pesan error
                                      print("Error parsing date: $e"); // Untuk debugging
                                      displayedDate = createdAtRaw; // Fallback ke string mentah
                                    }
                                  }
                                  // --- Akhir perubahan format tanggal ---

                                  String cleanedImageUrl = imageUrl;
                                  if (cleanedImageUrl.startsWith('/storage/')) {
                                    cleanedImageUrl = cleanedImageUrl.substring('/storage/'.length);
                                  } else if (cleanedImageUrl.startsWith('storage/')) {
                                    cleanedImageUrl = cleanedImageUrl.substring('storage/'.length);
                                  } else if (cleanedImageUrl.startsWith('/')) {
                                    cleanedImageUrl = cleanedImageUrl.substring(1);
                                  }

                                  final String finalImageUrl = ApiService.storageUrl(cleanedImageUrl);

                                  return GestureDetector(
                                    onTap: fakeGpsDetected ? null : () {
                                      Navigator.push(
                                        context,
                                        MaterialPageRoute(builder: (context) => BeritaDetailPage(beritaId: id)),
                                      );
                                    },
                                    child: Container(
                                      margin: const EdgeInsets.symmetric(horizontal: 8.0),
                                      decoration: BoxDecoration(
                                        color: Colors.white,
                                        borderRadius: BorderRadius.circular(10.0),
                                        boxShadow: [
                                          BoxShadow(
                                            color: Colors.grey.withOpacity(0.2),
                                            spreadRadius: 2,
                                            blurRadius: 5,
                                            offset: const Offset(0, 3),
                                          ),
                                        ],
                                      ),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          ClipRRect(
                                            borderRadius: const BorderRadius.vertical(top: Radius.circular(10.0)),
                                            child: finalImageUrl.isNotEmpty
                                                ? Image.network(
                                                    finalImageUrl,
                                                    height: 90,
                                                    width: double.infinity,
                                                    fit: BoxFit.cover,
                                                    errorBuilder: (context, error, stackTrace) => Container(
                                                      height: 90,
                                                      color: Colors.grey[300],
                                                      child: const Center(child: Icon(Icons.image_not_supported, size: 40, color: Colors.grey)),
                                                    ),
                                                  )
                                                : Container(
                                                    height: 90,
                                                    color: Colors.grey[300],
                                                    child: const Center(child: Icon(Icons.image_not_supported, size: 40, color: Colors.grey)),
                                                  ),
                                          ),
                                          Padding(
                                            padding: const EdgeInsets.all(8.0),
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Text(
                                                  judul,
                                                  style: const TextStyle(
                                                    fontSize: 14,
                                                    fontWeight: FontWeight.bold,
                                                  ),
                                                  maxLines: 2,
                                                  overflow: TextOverflow.ellipsis,
                                                ),
                                                const SizedBox(height: 2),
                                                Text(
                                                  displayedDate, // Tanggal sudah diformat di sini
                                                  style: const TextStyle(
                                                    fontSize: 11,
                                                    color: Colors.grey,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  );
                                },
                              ),
                            ),
              const SizedBox(height: 10),
              _buildPageIndicator(),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }
}