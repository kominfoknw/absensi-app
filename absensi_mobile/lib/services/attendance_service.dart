import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:absensi_mobile/services/api_service.dart';

class AttendanceService {
  static Future<String> submitAttendance({
    required String token,
    required bool isMasuk,
    required String tanggal,
    required String jam,
    required double lat,
    required double long,
    required String fotoBase64,
  }) async {
    final endpoint = '/api/absensi/${isMasuk ? 'checkin' : 'checkout'}';
    final url = ApiService.buildUri(endpoint);

    final response = await http.post(
      url,
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'tanggal': tanggal,
        isMasuk ? 'jam_masuk' : 'jam_pulang': jam,
        isMasuk ? 'lat_masuk' : 'lat_pulang': lat,
        isMasuk ? 'long_masuk' : 'long_pulang': long,
        'foto_wajah': fotoBase64,
      }),
    );

    final data = jsonDecode(response.body);

    if (response.statusCode == 200) {
      return data['message'];
    } else {
      throw Exception(data['message'] ?? 'Gagal melakukan absen.');
    }
  }
}
