import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:intl/intl.dart';
import 'package:absensi_mobile/services/api_service.dart';
import 'package:absensi_mobile/helpers/date_helper.dart';

class HistoryPage extends StatefulWidget {
  const HistoryPage({super.key});

  @override
  State<HistoryPage> createState() => _HistoryPageState();
}

class _HistoryPageState extends State<HistoryPage> {
  List<dynamic> _attendanceHistory = [];
  bool _isLoading = false;
  String? _errorMessage;
  final storage = const FlutterSecureStorage();
  DateTime _selectedDate = DateTime.now();

  @override
  void initState() {
    super.initState();
    Intl.defaultLocale = 'id_ID';
    _fetchAttendanceHistory();
  }

  Future<void> _fetchAttendanceHistory() async {
    if (!mounted) return;
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final token = await storage.read(key: 'token');
      if (token == null) {
        if (!mounted) return;
        setState(() {
          _errorMessage = "Token otentikasi tidak ditemukan. Harap login ulang.";
          _isLoading = false;
        });
        return;
      }

      final uri = ApiService.buildUri(
        '/api/history',
        {
          'bulan': _selectedDate.month.toString(),
          'tahun': _selectedDate.year.toString(),
        },
      );

      final response = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      if (!mounted) return;
      if (response.statusCode == 200) {
        setState(() {
          _attendanceHistory = jsonDecode(response.body);
          // debugPrint("Attendance History: $_attendanceHistory"); // Aktifkan untuk debugging data API
        });
      } else if (response.statusCode == 401) {
        setState(() {
          _errorMessage = "Sesi Anda habis. Harap login ulang.";
        });
      } else {
        setState(() {
          _errorMessage =
              "Gagal memuat data absensi: ${response.statusCode} ${response.reasonPhrase}";
          debugPrint("API Error: ${response.body}");
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = "Terjadi kesalahan jaringan: $e";
        debugPrint("Network Error: $e");
      });
    } finally {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _selectMonthYear(BuildContext context) async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2000),
      lastDate: DateTime.now(),
      builder: (BuildContext context, Widget? child) {
        return Theme(
          data: ThemeData.light().copyWith(
            colorScheme: ColorScheme.light(
              primary: Theme.of(context).primaryColor,
              onPrimary: Colors.white,
              surface: Colors.white,
              onSurface: Colors.black,
            ),
            dialogBackgroundColor: Colors.white,
          ),
          child: child!,
        );
      },
    );

    if (picked != null && picked != _selectedDate) {
      setState(() {
        _selectedDate = picked;
      });
      _fetchAttendanceHistory();
    }
  }

  // --- REVISI UTAMA PADA FUNGSI INI ---
  String formatTelat(String? telatRaw) {
    if (telatRaw == null || telatRaw.trim().isEmpty) { // Jangan cek '00:00:00' di sini, biarkan isWaktuLebihDariNol yang menentukan
      return '-';
    }
    try {
      final parts = telatRaw.split(':');
      if (parts.length < 2) return '-'; // Pastikan format valid

      final int jam = int.tryParse(parts[0]) ?? 0;
      final int menit = int.tryParse(parts[1]) ?? 0;
      // Detik tidak perlu untuk format HH:mm, tapi pastikan ada jika kita cek durasi > 0
      final int detik = parts.length > 2 ? (int.tryParse(parts[2]) ?? 0) : 0;

      // Jika durasinya benar-benar nol, baru kembalikan '-'
      if (jam == 0 && menit == 0 && detik == 0) {
        return '-';
      }

      return '${jam.toString().padLeft(2, '0')}:${menit.toString().padLeft(2, '0')}';
    } catch (e) {
      debugPrint('Error parsing telat duration: $telatRaw - $e'); // Gunakan debugPrint
      return '-'; // Fallback jika parsing gagal
    }
  }

  // --- REVISI UTAMA PADA FUNGSI INI ---
  bool isWaktuLebihDariNol(String? raw) {
    if (raw == null || raw.trim().isEmpty) {
      return false;
    }
    try {
      final parts = raw.split(':');
      // Pastikan ada setidaknya 3 bagian (HH, mm, ss) untuk validasi waktu penuh
      if (parts.length != 3) {
        debugPrint('Invalid time format for isWaktuLebihDariNol: $raw');
        return false;
      }

      final int jam = int.tryParse(parts[0]) ?? 0;
      final int menit = int.tryParse(parts[1]) ?? 0;
      final int detik = int.tryParse(parts[2]) ?? 0;

      // Cek apakah ada komponen waktu yang lebih besar dari nol
      return (jam > 0 || menit > 0 || detik > 0);
    } catch (e) {
      debugPrint('Error evaluating time duration: $raw - $e'); // Gunakan debugPrint
      return false; // Jika ada error parsing, anggap sebagai nol
    }
  }

  // Ini adalah fungsi formatTanggalIndo yang Anda miliki (seperti di kode terakhir yang Anda kirimkan)
  // Saya mengasumsikan ini adalah fungsi lokal di _HistoryPageState, BUKAN dari DateHelper
  // Jika Anda memang ingin menggunakan DateHelper, maka ubah ini menjadi `return DateHelper.formatTanggalIndo(tanggal);`
  String formatTanggalIndo(String tanggal) {
    // Implementasi lokal jika Anda punya
    try {
      final DateTime date = DateTime.parse(tanggal);
      final DateFormat formatter = DateFormat('d MMMM yyyy', 'id_ID');
      return formatter.format(date);
    } catch (e) {
      print('Error parsing date locally in HistoryPage: $tanggal - $e');
      return tanggal; // Kembalikan string asli sebagai fallback
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Riwayat Absensi'),
        actions: [
          IconButton(
            icon: const Icon(Icons.calendar_month),
            onPressed: () => _selectMonthYear(context),
            tooltip: 'Pilih Bulan & Tahun',
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: Card(
              color: Theme.of(context).primaryColor.withOpacity(0.1),
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              child: Padding(
                padding: const EdgeInsets.all(12.0),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'Riwayat Absensi Bulan: ${DateFormat('MMMM yyyy', 'id_ID').format(_selectedDate)}',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Theme.of(context).primaryColor,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          _isLoading
              ? const Expanded(child: Center(child: CircularProgressIndicator()))
              : _errorMessage != null
                  ? Expanded(
                      child: Center(
                        child: Text(
                          _errorMessage!,
                          style: const TextStyle(color: Colors.red, fontSize: 16),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    )
                  : _attendanceHistory.isEmpty
                      ? const Expanded(
                          child: Center(
                            child: Text(
                              'Tidak ada data absensi untuk bulan ini.',
                              style: TextStyle(fontSize: 16, color: Colors.grey),
                            ),
                          ),
                        )
                      : Expanded(
                          child: ListView.builder(
                            itemCount: _attendanceHistory.length,
                            itemBuilder: (context, index) {
                              final attendance = _attendanceHistory[index];

                              final String tanggal = attendance['tanggal'] ?? 'N/A';
                              final String jamMasuk = attendance['jam_masuk'] ?? '-';
                              final String jamPulang = attendance['jam_pulang'] ?? '-';
                              final String status = attendance['status'] ?? '-';

                              // Pastikan data diambil sebagai String?, ini penting
                              final String? telatRaw = attendance['telat']?.toString();
                              final String? pulangCepatRaw = attendance['pulang_cepat']?.toString();

                              // --- Logika untuk Terlambat ---
                              final bool isTelat = isWaktuLebihDariNol(telatRaw);
                              final String telatDisplay = formatTelat(telatRaw); // Gunakan formatTelat
                              final Color telatColor = isTelat ? Colors.red.shade700 : Colors.green.shade700;

                              // --- Logika untuk Pulang Cepat ---
                              final bool isPulangCepat = isWaktuLebihDariNol(pulangCepatRaw);
                              final String pulangCepatDisplay = isPulangCepat ? 'Ya' : 'Tidak';
                              final Color pulangCepatColor = isPulangCepat ? Colors.orange.shade700 : Colors.green.shade700;

                              return Card(
                                margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                elevation: 2,
                                child: Padding(
                                  padding: const EdgeInsets.all(16),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'Tanggal: ${formatTanggalIndo(tanggal)}', // Menggunakan fungsi lokal Anda
                                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                                      ),
                                      const Divider(height: 16),
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                const Text('Jam Masuk:', style: TextStyle(color: Colors.grey)),
                                                Text(
                                                  jamMasuk.length >= 5 ? jamMasuk.substring(0, 5) : jamMasuk,
                                                  style: const TextStyle(fontWeight: FontWeight.w500),
                                                ),
                                              ],
                                            ),
                                          ),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.end,
                                              children: [
                                                const Text('Jam Pulang:', style: TextStyle(color: Colors.grey)),
                                                Text(
                                                  jamPulang.length >= 5 ? jamPulang.substring(0, 5) : jamPulang,
                                                  style: const TextStyle(fontWeight: FontWeight.w500),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 8),
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                const Text('Telat:', style: TextStyle(color: Colors.grey)),
                                                Text(
                                                  telatDisplay,
                                                  style: TextStyle(
                                                    color: telatColor,
                                                    fontWeight: isTelat ? FontWeight.bold : FontWeight.normal,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.end,
                                              children: [
                                                const Text('Pulang Cepat:', style: TextStyle(color: Colors.grey)),
                                                Text(
                                                  pulangCepatDisplay,
                                                  style: TextStyle(
                                                    color: pulangCepatColor,
                                                    fontWeight: isPulangCepat ? FontWeight.bold : FontWeight.normal,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                        'Status: ${status.toUpperCase()}',
                                        style: TextStyle(
                                          fontStyle: FontStyle.italic,
                                          color: Colors.blueGrey.shade700,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
        ],
      ),
    );
  }
}