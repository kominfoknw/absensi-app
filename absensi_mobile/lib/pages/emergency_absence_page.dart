// lib/pages/emergency_absence_page.dart
import 'package:flutter/material.dart';
import 'package:qr_code_scanner/qr_code_scanner.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:absensi_mobile/services/emergency_absence_api_service.dart'; // Import service API absen darurat

class EmergencyAbsencePage extends StatefulWidget {
  const EmergencyAbsencePage({Key? key}) : super(key: key);

  @override
  State<EmergencyAbsencePage> createState() => _EmergencyAbsencePageState();
}

class _EmergencyAbsencePageState extends State<EmergencyAbsencePage> {
  final GlobalKey qrKey = GlobalKey(debugLabel: 'QR');
  QRViewController? controller;
  Barcode? result;
  bool _isScanning = false;
  bool _cameraPermissionGranted = false;
  String? _selectedAbsenceType; // Untuk menyimpan pilihan 'masuk' atau 'pulang'

  final EmergencyAbsenceApiService _apiService = EmergencyAbsenceApiService();

  @override
  void initState() {
    super.initState();
    _checkCameraPermission();
  }

  Future<void> _checkCameraPermission() async {
    var status = await Permission.camera.status;
    if (!status.isGranted) {
      status = await Permission.camera.request();
    }
    setState(() {
      _cameraPermissionGranted = status.isGranted;
    });
    if (!_cameraPermissionGranted) {
      _showPermissionDeniedDialog();
    }
  }

  void _showPermissionDeniedDialog() {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Izin Kamera Dibutuhkan'),
          content: const Text('Untuk menggunakan fitur absen darurat, Anda perlu memberikan izin akses kamera.'),
          actions: <Widget>[
            TextButton(
              child: const Text('Batal'),
              onPressed: () {
                Navigator.of(context).pop();
                // Opsional: Jika ingin langsung kembali ke halaman sebelumnya
                // Navigator.of(context).pop();
              },
            ),
            TextButton(
              child: const Text('Buka Pengaturan'),
              onPressed: () {
                openAppSettings();
                Navigator.of(context).pop();
                // Opsional: Jika ingin langsung kembali ke halaman sebelumnya
                // Navigator.of(context).pop();
              },
            ),
          ],
        );
      },
    );
  }

  void _onQRViewCreated(QRViewController controller) {
    this.controller = controller;
    controller.scannedDataStream.listen((scanResult) async {
      // Pastikan hanya memproses satu scan pada satu waktu
      if (!_isScanning && _selectedAbsenceType != null) {
        setState(() {
          _isScanning = true; // Set _isScanning ke true segera
          result = scanResult;
        });

        if (result != null && result!.code != null) {
          controller.pauseCamera(); // Jeda kamera setelah scan pertama

          _showLoadingDialog();

          // Panggil fungsi dari service API yang terpisah
          final response = await _apiService.recordEmergencyAbsence(result!.code!, _selectedAbsenceType!);

          Navigator.of(context).pop(); // Tutup dialog loading

          if (response['status'] == 'success' || response['status'] == 'warning') {
            _showSuccessDialog(response['message']);
          } else {
            _showErrorDialog(response['message'] ?? 'Terjadi kesalahan saat mencatat absen.');
          }
        } else {
          // Jika QR code kosong atau tidak valid, izinkan scan berikutnya
          setState(() {
            _isScanning = false;
          });
          controller?.resumeCamera();
        }
      } else if (_selectedAbsenceType == null) {
        // Jika jenis absen belum dipilih, jangan lakukan apa-apa, tapi biarkan kamera aktif
        // dan berikan umpan balik visual atau pesan kepada pengguna di UI.
      }
    });
  }

  void _showLoadingDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext context) {
        return const Dialog(
          child: Padding(
            padding: EdgeInsets.all(20.0),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircularProgressIndicator(),
                SizedBox(width: 20),
                Text("Memproses absen..."),
              ],
            ),
          ),
        );
      },
    );
  }

  void _showSuccessDialog(String message) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Absen Berhasil'),
          content: Text(message),
          actions: <Widget>[
            TextButton(
              child: const Text('OK'),
              onPressed: () {
                Navigator.of(context).pop(); // Tutup dialog sukses
                Navigator.of(context).pop(); // Kembali ke halaman sebelumnya (misal: dashboard)
              },
            ),
          ],
        );
      },
    );
  }

  void _showErrorDialog(String message) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Absen Gagal'),
          content: Text(message),
          actions: <Widget>[
            TextButton(
              child: const Text('OK'),
              onPressed: () {
                Navigator.of(context).pop(); // Tutup dialog error
                controller?.resumeCamera(); // Lanjutkan pemindaian
                setState(() {
                  _isScanning = false; // Reset status scanning
                });
              },
            ),
          ],
        );
      },
    );
  }

  @override
  void dispose() {
    controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Absen Darurat')),
      body: _cameraPermissionGranted
          ? Column(
              children: <Widget>[
                Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Column(
                    children: [
                      const Text(
                        'Pilih Jenis Absen:',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 10),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceAround,
                        children: [
                          ChoiceChip(
                            label: const Text('Absen Masuk'),
                            selected: _selectedAbsenceType == 'masuk',
                            onSelected: (bool selected) {
                              setState(() {
                                _selectedAbsenceType = selected ? 'masuk' : null;
                                // Jika ada controller, resume kamera dan reset scanning
                                if (controller != null && _isScanning) {
                                  controller!.resumeCamera();
                                  _isScanning = false;
                                } else if (controller != null && _selectedAbsenceType != null) {
                                  // Jika belum scan dan baru pilih tipe, pastikan kamera aktif
                                  controller!.resumeCamera();
                                }
                              });
                            },
                          ),
                          ChoiceChip(
                            label: const Text('Absen Pulang'),
                            selected: _selectedAbsenceType == 'pulang',
                            onSelected: (bool selected) {
                              setState(() {
                                _selectedAbsenceType = selected ? 'pulang' : null;
                                // Jika ada controller, resume kamera dan reset scanning
                                if (controller != null && _isScanning) {
                                  controller!.resumeCamera();
                                  _isScanning = false;
                                } else if (controller != null && _selectedAbsenceType != null) {
                                  // Jika belum scan dan baru pilih tipe, pastikan kamera aktif
                                  controller!.resumeCamera();
                                }
                              });
                            },
                          ),
                        ],
                      ),
                      if (_selectedAbsenceType == null)
                        const Padding(
                          padding: EdgeInsets.only(top: 8.0),
                          child: Text(
                            'Harap pilih jenis absen terlebih dahulu untuk mengaktifkan pemindai.',
                            style: TextStyle(color: Colors.red),
                            textAlign: TextAlign.center,
                          ),
                        ),
                    ],
                  ),
                ),
                Expanded(
                  flex: 4,
                  child: _selectedAbsenceType != null
                      ? QRView(
                          key: qrKey,
                          onQRViewCreated: _onQRViewCreated,
                          overlay: QrScannerOverlayShape(
                            borderColor: Colors.red,
                            borderRadius: 10,
                            borderLength: 30,
                            borderWidth: 10,
                            cutOutSize: MediaQuery.of(context).size.width * 0.8,
                          ),
                        )
                      : const Center(
                          child: Text('Pilih jenis absen untuk mengaktifkan pemindai QR.'),
                        ),
                ),
                Expanded(
                  flex: 1,
                  child: Center(
                    child: (result != null && _selectedAbsenceType != null)
                        ? Text('QR Code Terbaca: ${result!.code}', style: const TextStyle(fontSize: 16))
                        : const Text('Scan QR Code untuk Absen Darurat', style: TextStyle(fontSize: 16)),
                  ),
                )
              ],
            )
          : Center(
              child: Padding(
                padding: const EdgeInsets.all(20.0),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.camera_alt_outlined, size: 80, color: Colors.grey),
                    const SizedBox(height: 20),
                    const Text(
                      'Akses kamera ditolak. Silakan berikan izin kamera di pengaturan aplikasi Anda untuk menggunakan fitur ini.',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 16),
                    ),
                    const SizedBox(height: 20),
                    ElevatedButton(
                      onPressed: () {
                        openAppSettings();
                      },
                      child: const Text('Buka Pengaturan Aplikasi'),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}