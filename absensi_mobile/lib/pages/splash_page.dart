import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:location/location.dart';
import 'login_page.dart';
import 'dashboard_page.dart';

class SplashPage extends StatefulWidget {
  const SplashPage({super.key});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> {
  final storage = const FlutterSecureStorage();

  @override
  void initState() {
    super.initState();

    // Delay sedikit untuk animasi splash
    Future.delayed(const Duration(seconds: 1), () {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        checkLoginAndLocation();
      });
    });
  }

  Future<void> checkLoginAndLocation() async {
    String? token;

    try {
      print("🔍 Membaca token dari secure storage...");
      token = await storage.read(key: 'token');
      print("✅ Token dibaca: $token");
    } catch (e) {
      // BUG FIX untuk Android 15 Samsung:
      // Jika KeyStore rusak, error akan dilempar dari sini.
      print("⚠️ SecureStorage error: $e");
      await storage.deleteAll(); // reset penyimpanan rusak
      token = null;
    }

    bool isFake = false;
    try {
      isFake = await detectFakeGPS();
    } catch (e) {
      print("⚠️ Error deteksi lokasi: $e");
      isFake = false;
    }

    if (!mounted) return;

    if (isFake) {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (_) => AlertDialog(
          title: const Text('Fake GPS Terdeteksi!'),
          content: const Text('Matikan Fake GPS sebelum melanjutkan.'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('OK'),
            ),
          ],
        ),
      );
    } else {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => token == null
              ? const LoginPage()
              : const DashboardPage(),
        ),
      );
    }
  }

  Future<bool> detectFakeGPS() async {
    Location location = Location();

    bool serviceEnabled = await location.serviceEnabled();
    if (!serviceEnabled) {
      serviceEnabled = await location.requestService();
      if (!serviceEnabled) return false;
    }

    PermissionStatus permissionGranted = await location.hasPermission();
    if (permissionGranted == PermissionStatus.denied) {
      permissionGranted = await location.requestPermission();
      if (permissionGranted != PermissionStatus.granted) return false;
    }

    LocationData locationData = await location.getLocation();
    debugPrint("Mock status: ${locationData.isMock}");
    return locationData.isMock ?? false;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Animate(
              effects: [FadeEffect(duration: 1500.ms), ScaleEffect()],
              child: Image.asset(
                'assets/images/logo_konawe.png',
                width: 120,
              ),
            ),
            const SizedBox(height: 24),
            Text(
              'EKERJA MOBILE',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w900,
                color: Colors.green[700],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
