// lib/services/emergency_absence_api_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:absensi_mobile/services/api_service.dart';
import 'package:absensi_mobile/services/auth_service.dart'; // Import AuthService untuk getToken()

class EmergencyAbsenceApiService {
  /// Mencatat absen darurat (masuk atau pulang)
  /// Mengirimkan QR Code Secret, User ID, Jenis Absen, dan Token Otentikasi ke API.
  Future<Map<String, dynamic>> recordEmergencyAbsence(String qrCodeSecret, String absenceType) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    int? userId = prefs.getInt('user_id');
    // Ambil token dari AuthService yang mengakses FlutterSecureStorage
    String? authToken = await AuthService.getToken();

    print('DEBUG EmergencyAbsenceApiService: userId yang diambil: $userId');
    print('DEBUG EmergencyAbsenceApiService: authToken yang diambil: $authToken');

    if (userId == null) {
      return {'status': 'error', 'message': 'User ID tidak ditemukan. Harap login kembali.'};
    }

    if (authToken == null || authToken.isEmpty) {
      return {'status': 'error', 'message': 'Token otentikasi tidak ditemukan. Harap login kembali.'};
    }

    final uri = ApiService.buildUri('/api/emergency-absence');
    print('DEBUG EmergencyAbsenceApiService: URL API: $uri');
    print('DEBUG EmergencyAbsenceApiService: QR Secret: $qrCodeSecret, Absence Type: $absenceType');

    try {
      final bodyData = jsonEncode(<String, dynamic>{
        'qr_code_secret': qrCodeSecret,
        'user_id': userId,
        'absence_type': absenceType,
      });
      print('DEBUG EmergencyAbsenceApiService: Request Body: $bodyData');

      final response = await http.post(
        uri,
        headers: <String, String>{
          'Content-Type': 'application/json; charset=UTF-8',
          'Authorization': 'Bearer $authToken', // **INI SUDAH AKTIF SEKARANG**
          'Accept': 'application/json',
        },
        body: bodyData,
      );

      print('DEBUG EmergencyAbsenceApiService: Response Status Code: ${response.statusCode}');
      print('DEBUG EmergencyAbsenceApiService: Response Body: ${response.body}');

      if (response.statusCode == 200 || response.statusCode == 201) {
        return jsonDecode(response.body);
      } else {
        try {
          final errorBody = jsonDecode(response.body);
          return {'status': 'error', 'message': errorBody['message'] ?? 'Gagal mencatat absen: ${response.statusCode}'};
        } catch (e) {
          return {'status': 'error', 'message': 'Gagal mencatat absen: ${response.statusCode} - ${response.body}'};
        }
      }
    } catch (e) {
      print('ERROR di recordEmergencyAbsence: $e');
      return {'status': 'error', 'message': 'Terjadi kesalahan koneksi: $e'};
    }
  }
}