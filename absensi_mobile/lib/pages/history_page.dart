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
    _fetchAttendanceHistory();
  }

  Future<void> _fetchAttendanceHistory() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final token = await storage.read(key: 'token');
      if (token == null) {
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

      if (response.statusCode == 200) {
        setState(() {
          _attendanceHistory = jsonDecode(response.body);
        });
      } else if (response.statusCode == 401) {
        setState(() {
          _errorMessage = "Sesi Anda habis. Harap login ulang.";
        });
      } else {
        setState(() {
          _errorMessage =
              "Gagal memuat data absensi: ${response.statusCode} ${response.reasonPhrase}";
          print("API Error: ${response.body}");
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = "Terjadi kesalahan jaringan: $e";
      });
    } finally {
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
      initialEntryMode: DatePickerEntryMode.calendarOnly,
      builder: (BuildContext context, Widget? child) {
        return Theme(
          data: ThemeData.light().copyWith(
            primaryColor: Theme.of(context).primaryColor,
            colorScheme:
                ColorScheme.light(primary: Theme.of(context).primaryColor),
            buttonTheme:
                const ButtonThemeData(textTheme: ButtonTextTheme.primary),
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
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10)),
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
              ? const Expanded(
                  child: Center(child: CircularProgressIndicator()))
              : _errorMessage != null
                  ? Expanded(
                      child: Center(
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Text(
                            _errorMessage!,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                                color: Colors.red, fontSize: 16),
                          ),
                        ),
                      ),
                    )
                  : _attendanceHistory.isEmpty
                      ? const Expanded(
                          child: Center(
                            child: Text(
                              'Tidak ada data absensi untuk bulan ini.',
                              style:
                                  TextStyle(fontSize: 16, color: Colors.grey),
                            ),
                          ),
                        )
                      : Expanded(
                          child: ListView.builder(
                            itemCount: _attendanceHistory.length,
                            itemBuilder: (context, index) {
                              final attendance =
                                  _attendanceHistory[index];

                              final String tanggal =
                                  attendance['tanggal'] ?? 'N/A';
                              final String jamMasuk =
                                  attendance['jam_masuk'] ?? '-';
                              final String jamPulang =
                                  attendance['jam_pulang'] ?? '-';

                              /// --- LOGIC TELAT ---
                              String? telatRaw =
                                  attendance['telat']?.toString();
                              telatRaw = telatRaw?.trim();

                              bool isTelat = false;
                              String telatDisplay = '-';

                              if (telatRaw != null &&
                                  telatRaw != '' &&
                                  telatRaw != '00:00:00') {
                                isTelat = true;
                                telatDisplay = telatRaw;
                              }

                              /// --- LOGIC PULANG CEPAT ---
                              String? pulangCepatRaw =
                                  attendance['pulang_cepat']?.toString();
                              pulangCepatRaw = pulangCepatRaw?.trim();

                              bool isPulangCepat = false;
                              if (pulangCepatRaw != null &&
                                  pulangCepatRaw != '' &&
                                  pulangCepatRaw != '00:00:00') {
                                isPulangCepat = true;
                              }

                              final String status =
                                  attendance['status'] ?? 'N/A';

                              return Card(
                                margin: const EdgeInsets.symmetric(
                                    horizontal: 16, vertical: 8),
                                elevation: 2,
                                shape: RoundedRectangleBorder(
                                    borderRadius:
                                        BorderRadius.circular(10)),
                                child: Padding(
                                  padding: const EdgeInsets.all(16.0),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'Tanggal: ${formatTanggalIndo(tanggal)}',
                                        style: const TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 16),
                                      ),
                                      const Divider(height: 16),
                                      Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment
                                                .spaceBetween,
                                        children: [
                                          Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              const Text('Jam Masuk:',
                                                  style: TextStyle(
                                                      color:
                                                          Colors.grey)),
                                              Text(
                                                jamMasuk.length >= 5
                                                    ? jamMasuk.substring(
                                                        0, 5)
                                                    : jamMasuk,
                                                style: const TextStyle(
                                                    fontSize: 15),
                                              ),
                                            ],
                                          ),
                                          Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.end,
                                            children: [
                                              const Text('Jam Pulang:',
                                                  style: TextStyle(
                                                      color:
                                                          Colors.grey)),
                                              Text(
                                                jamPulang != '-' &&
                                                        jamPulang
                                                                .length >=
                                                            5
                                                    ? jamPulang
                                                        .substring(0, 5)
                                                    : jamPulang,
                                                style: const TextStyle(
                                                    fontSize: 15),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 8),
                                      Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment
                                                .spaceBetween,
                                        children: [
                                          Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              const Text('Telat:',
                                                  style: TextStyle(
                                                      color:
                                                          Colors.grey)),
                                              Text(
                                                isTelat
                                                    ? telatDisplay
                                                    : '-',
                                                style: TextStyle(
                                                    color: isTelat
                                                        ? Colors.red
                                                        : Colors.black,
                                                    fontSize: 15),
                                              ),
                                            ],
                                          ),
                                          Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.end,
                                            children: [
                                              const Text(
                                                  'Pulang Cepat:',
                                                  style: TextStyle(
                                                      color:
                                                          Colors.grey)),
                                              Text(
                                                isPulangCepat
                                                    ? 'Ya'
                                                    : 'Tidak',
                                                style: TextStyle(
                                                    color: isPulangCepat
                                                        ? Colors.orange
                                                        : Colors.black,
                                                    fontSize: 15),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                        'Status: $status',
                                        style: const TextStyle(
                                            fontStyle: FontStyle.italic),
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
