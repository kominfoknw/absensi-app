import 'package:flutter/material.dart';
import 'package:absensi_mobile/pages/splash_page.dart';
import 'package:flutter_localizations/flutter_localizations.dart'; // Impor ini

void main() async {
  // Pastikan Flutter binding sudah diinisialisasi sebelum memanggil fungsi async lainnya
  WidgetsFlutterBinding.ensureInitialized();

  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'EKERJA MOBILE',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        primarySwatch: Colors.blue,
        visualDensity: VisualDensity.adaptivePlatformDensity,
      ),
      // --- START: Konfigurasi Localizations ---
      supportedLocales: const [
        Locale('en', ''), // Locale default: English
        Locale('id', ''), // Locale untuk Bahasa Indonesia
        // Jika Anda ingin lebih spesifik ke Indonesia, Anda bisa pakai:
        // Locale('id', 'ID'),
      ],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate, // Opsional, jika Anda menggunakan widget gaya iOS
      ],
      // --- END: Konfigurasi Localizations ---
      home: const SplashPage(), // Halaman awal aplikasi Anda
    );
  }
}