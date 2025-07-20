import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart'; // Import ini
import 'package:absensi_mobile/services/api_service.dart';

class AuthService {
  static final _storage = FlutterSecureStorage();

  static Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final url = ApiService.buildUri('/api/login');

      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'email': email,
          'password': password,
        }),
      );

      final data = jsonDecode(response.body);

      if (response.statusCode == 200) {
        await _storage.write(key: 'token', value: data['token']);

        // --- BAGIAN BARU: Simpan user_id ke SharedPreferences ---
        final SharedPreferences prefs = await SharedPreferences.getInstance();
        // Asumsi data['user'] adalah Map dan memiliki kunci 'id' atau 'user_id'
        // Anda perlu memastikan kunci yang benar dari respons API Anda.
        // Contoh: jika responsnya { "user": { "id": 123, "name": "..." } }
        int? userId = data['user']['id']; // Ganti 'id' jika kunci di API Anda berbeda (misal 'user_id')
        if (userId != null) {
          await prefs.setInt('user_id', userId);
          print('DEBUG: User ID berhasil disimpan ke SharedPreferences: $userId'); // Untuk debugging
        } else {
          print('WARNING: User ID tidak ditemukan dalam data user dari API login.');
        }
        // --------------------------------------------------------

        return {
          'success': true,
          'message': data['message'],
          'user': data['user'], // Anda masih bisa mengembalikan data user lengkap
        };
      } else {
        return {
          'success': false,
          'message': data['message'] ?? 'Login gagal',
        };
      }
    } catch (e) {
      print('ERROR di AuthService.login: $e'); // Tambahkan logging error
      return {
        'success': false,
        'message': 'Terjadi kesalahan jaringan: $e', // Tampilkan error jaringan
      };
    }
  }

  static Future<void> logout() async {
    await _storage.delete(key: 'token');
    // --- BAGIAN BARU: Hapus user_id dari SharedPreferences saat logout ---
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.remove('user_id');
    print('DEBUG: Token dan User ID dihapus dari penyimpanan.'); // Untuk debugging
    // ------------------------------------------------------------------
  }

  static Future<String?> getToken() async {
    return await _storage.read(key: 'token');
  }

  // Anda mungkin juga ingin menambahkan metode untuk mendapatkan user_id
  static Future<int?> getUserId() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    return prefs.getInt('user_id');
  }
}