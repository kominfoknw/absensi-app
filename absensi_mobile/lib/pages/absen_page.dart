import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:location/location.dart'; // Pastikan package ini ada di pubspec.yaml
import 'package:cool_alert/cool_alert.dart';
import '../services/attendance_service.dart';
import 'package:flutter/services.dart'; // <--- Pastikan ini diimpor untuk SystemNavigator.pop()

class AbsenPage extends StatefulWidget {
  final bool isMasuk; // true = masuk, false = false
  const AbsenPage({super.key, required this.isMasuk});

  @override
  State<AbsenPage> createState() => _AbsenPageState();
}

class _AbsenPageState extends State<AbsenPage> with WidgetsBindingObserver {
  late CameraController _cameraController;
  bool _isCameraInitialized = false;
  bool _loading = false;
  final storage = const FlutterSecureStorage();
  final Location _location = Location(); // Inisialisasi Location service
  bool _fakeGpsAlertShown = false; // Flag untuk memastikan alert hanya tampil sekali

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    initializeCamera();
    _checkAndHandleFakeGPS(); // Lakukan pengecekan fake GPS saat page dimuat
  }

  @override
  void dispose() {
    _cameraController.dispose();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _checkAndHandleFakeGPS(); // Lakukan pengecekan lagi saat aplikasi di-resume
    }
  }

  Future<void> initializeCamera() async {
    final cameras = await availableCameras();
    final frontCamera = cameras.firstWhere(
        (camera) => camera.lensDirection == CameraLensDirection.front);
    _cameraController =
        CameraController(frontCamera, ResolutionPreset.medium, enableAudio: false);
    await _cameraController.initialize();
    setState(() {
      _isCameraInitialized = true;
    });
  }

  // Metode Pendeteksian dan Penanganan Fake GPS
  Future<void> _checkAndHandleFakeGPS() async {
    // Hanya proses jika alert sudah ditampilkan
    if (_fakeGpsAlertShown) {
      return;
    }

    bool serviceEnabled = await _location.serviceEnabled();
    if (!serviceEnabled) {
      serviceEnabled = await _location.requestService();
      if (!serviceEnabled) {
        return;
      }
    }

    PermissionStatus permissionGranted = await _location.hasPermission();
    if (permissionGranted == PermissionStatus.denied) {
      permissionGranted = await _location.requestPermission();
      if (permissionGranted != PermissionStatus.granted) {
        return;
      }
    }

    LocationData locationData;
    try {
      locationData = await _location.getLocation();
    } catch (e) {
      if (context.mounted) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.error,
          title: "Error Lokasi",
          text: "Tidak dapat mengambil lokasi: ${e.toString()}",
        );
      }
      return;
    }

    if (locationData.isMock ?? false) {
      setState(() {
        _fakeGpsAlertShown = true;
      });

      if (context.mounted) {
        await CoolAlert.show(
          context: context,
          type: CoolAlertType.error,
          title: 'Deteksi Lokasi Palsu!',
          text: 'Aplikasi mendeteksi penggunaan lokasi palsu. Aplikasi akan ditutup.',
          barrierDismissible: false,
          onConfirmBtnTap: () {
            Navigator.pop(context);
            SystemNavigator.pop();
          },
          // showConfirmBtn: true, // <--- BARIS INI DIHAPUS
        );

        SystemNavigator.pop();
      }
    } else {
      if (_fakeGpsAlertShown) {
        setState(() {
          _fakeGpsAlertShown = false;
        });
      }
    }
  }

  Future<void> performAttendance() async {
    try {
      setState(() => _loading = true);

      // Pengecekan ulang Fake GPS tepat sebelum absen
      LocationData currentLocData;
      try {
        currentLocData = await _location.getLocation();
      } catch (e) {
        if (context.mounted) {
          CoolAlert.show(
            context: context,
            type: CoolAlertType.error,
            title: "Error Lokasi",
            text: "Tidak dapat mengambil lokasi untuk absen: ${e.toString()}",
          );
        }
        setState(() => _loading = false);
        return;
      }

      if (currentLocData.isMock ?? false) {
          if (context.mounted) {
            await CoolAlert.show(
              context: context,
              type: CoolAlertType.error,
              title: 'Deteksi Lokasi Palsu!',
              text: 'Tidak dapat absen karena terdeteksi lokasi palsu. Aplikasi akan ditutup.',
              barrierDismissible: false,
              onConfirmBtnTap: () {
                Navigator.pop(context);
                SystemNavigator.pop();
              },
              // showConfirmBtn: true, // <--- BARIS INI DIHAPUS
            );
            SystemNavigator.pop();
          }
          setState(() => _loading = false);
          return;
      }

      final XFile photo = await _cameraController.takePicture();
      final bytes = await photo.readAsBytes();
      final base64Image = base64Encode(bytes);

      final token = await storage.read(key: 'token');
      final now = DateTime.now();

      final result = await AttendanceService.submitAttendance(
        token: token!,
        isMasuk: widget.isMasuk,
        tanggal: "${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}",
        jam: "${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}:${now.second.toString().padLeft(2, '0')}",
        lat: currentLocData.latitude!,
        long: currentLocData.longitude!,
        fotoBase64: base64Image,
      );

      if (context.mounted) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.success,
          text: result,
          onConfirmBtnTap: () => Navigator.pop(context),
        );
      }
    } catch (e) {
      if (context.mounted) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.error,
          text: e.toString(),
        );
      }
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final headerText = widget.isMasuk ? "Absen Masuk" : "Absen Pulang";

    return Scaffold(
      appBar: AppBar(title: Text(headerText)),
      body: _isCameraInitialized
          ? Stack(
              children: [
                Column(
                  children: [
                    Expanded(
                      child: CameraPreview(_cameraController),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: ElevatedButton.icon(
                        onPressed: _loading || _fakeGpsAlertShown ? null : performAttendance,
                        icon: const Icon(Icons.fingerprint),
                        label: Text("Absen Sekarang"),
                      ),
                    ),
                  ],
                ),
                if (_loading)
                  Container(
                    color: Colors.black45,
                    child: const Center(
                      child: CircularProgressIndicator(),
                    ),
                  ),
              ],
            )
          : const Center(child: CircularProgressIndicator()),
    );
  }
}

// ExitPage is no longer strictly needed if SystemNavigator.pop() is used for direct exit.
// You can remove it or keep it as a fallback page.
// class ExitPage extends StatelessWidget {
//   const ExitPage({super.key});

//   @override
//   Widget build(BuildContext context) {
//     return Scaffold(
//       body: Center(
//         child: Column(
//           mainAxisAlignment: MainAxisAlignment.center,
//           children: [
//             const Icon(Icons.error_outline, color: Colors.red, size: 80),
//             const SizedBox(height: 20),
//             const Text(
//               "Deteksi Fake GPS",
//               style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
//             ),
//             const SizedBox(height: 10),
//             const Text(
//               "Aplikasi telah mendeteksi penggunaan lokasi palsu dan tidak dapat dilanjutkan.",
//               textAlign: TextAlign.center,
//               style: TextStyle(fontSize: 16),
//             ),
//             const SizedBox(height: 30),
//             ElevatedButton(
//               onPressed: () {
//                 // SystemNavigator.pop();
//               },
//               child: const Text("Tutup Aplikasi"),
//             ),
//           ],
//         ),
//       ),
//     );
//   }
// }