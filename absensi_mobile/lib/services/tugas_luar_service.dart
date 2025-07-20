import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:absensi_mobile/services/api_service.dart';

class TugasLuarService {
  static final FlutterSecureStorage _storage = FlutterSecureStorage();

  static Future<Map<String, String>> _getHeaders() async {
    final token = await _storage.read(key: 'token');
    return {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
      // 'Content-Type': 'application/json', // Untuk POST/PUT tanpa file
    };
  }

  static Future<List<dynamic>> getTugasLuars() async {
    final url = ApiService.buildUri('/api/tugas_luar');
    final headers = await _getHeaders();

    final response = await http.get(url, headers: headers);

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      final errorData = jsonDecode(response.body);
      throw Exception(errorData['message'] ?? 'Gagal memuat tugas luar.');
    }
  }

  static Future<void> createTugasLuar({
    required String namaTugas,
    required String tanggalMulai,
    required String tanggalSelesai,
    File? file,
    String? keterangan,
  }) async {
    final url = ApiService.buildUri('/api/tugas_luar');
    final token = await _storage.read(key: 'token');

    var request = http.MultipartRequest('POST', url)
      ..headers.addAll({
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      })
      ..fields['nama_tugas'] = namaTugas
      ..fields['tanggal_mulai'] = tanggalMulai
      ..fields['tanggal_selesai'] = tanggalSelesai;

    if (keterangan != null && keterangan.isNotEmpty) {
      request.fields['keterangan'] = keterangan;
    }

    if (file != null) {
      request.files.add(await http.MultipartFile.fromPath(
        'file',
        file.path,
        filename: file.path.split('/').last,
      ));
    }

    final response = await request.send();
    final responseBody = await response.stream.bytesToString();

    if (response.statusCode == 201) {
      // Created successfully
      return;
    } else if (response.statusCode == 409) { // Conflict for overlapping dates
      final errorData = jsonDecode(responseBody);
      throw Exception(errorData['message'] ?? 'Anda sudah memiliki tugas luar pada tanggal tersebut.');
    } else {
      final errorData = jsonDecode(responseBody);
      throw Exception(errorData['message'] ?? 'Gagal mengajukan tugas luar.');
    }
  }

  static Future<void> deleteTugasLuar(int id) async {
    final url = ApiService.buildUri('/api/tugas_luar/$id');
    final headers = await _getHeaders();

    final response = await http.delete(url, headers: headers);

    if (response.statusCode == 200) {
      // Deleted successfully
      return;
    } else if (response.statusCode == 403) { // Forbidden
       final errorData = jsonDecode(response.body);
       throw Exception(errorData['message'] ?? 'Anda tidak diizinkan menghapus tugas luar ini.');
    }
    else {
      final errorData = jsonDecode(response.body);
      throw Exception(errorData['message'] ?? 'Gagal menghapus tugas luar.');
    }
  }
}