import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:absensi_mobile/services/api_service.dart'; // Pastikan ini mengacu pada ApiService Anda

class LapkinService {
  static final FlutterSecureStorage _storage = FlutterSecureStorage();

  static Future<Map<String, String>> _getHeaders() async {
    final token = await _storage.read(key: 'token');
    return {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
    };
  }

  static Future<List<dynamic>> getLapkins({int? month, int? year}) async {
    final Map<String, String> queryParams = {};
    if (month != null) {
      queryParams['month'] = month.toString();
    }
    if (year != null) {
      queryParams['year'] = year.toString();
    }

    // Perubahan di sini: 'queryParams' sekarang menjadi argumen posisi kedua
    final url = ApiService.buildUri('/api/lapkin', queryParams);
    final headers = await _getHeaders();

    final response = await http.get(url, headers: headers);

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else {
      final errorData = jsonDecode(response.body);
      throw Exception(errorData['message'] ?? 'Gagal memuat Laporan Kinerja.');
    }
  }

  static Future<void> createLapkin({
    required String tanggal,
    required String namaKegiatan,
    required String tempat,
    String? target,
    String? output,
    File? lampiran,
  }) async {
    final url = ApiService.buildUri('/api/lapkin'); // Tidak ada queryParams di sini
    final token = await _storage.read(key: 'token');

    var request = http.MultipartRequest('POST', url)
      ..headers.addAll({
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      })
      ..fields['tanggal'] = tanggal
      ..fields['nama_kegiatan'] = namaKegiatan
      ..fields['tempat'] = tempat;

    if (target != null && target.isNotEmpty) {
      request.fields['target'] = target;
    }
    if (output != null && output.isNotEmpty) {
      request.fields['output'] = output;
    }

    if (lampiran != null) {
      request.files.add(await http.MultipartFile.fromPath(
        'lampiran',
        lampiran.path,
        filename: lampiran.path.split('/').last,
      ));
    }

    final response = await request.send();
    final responseBody = await response.stream.bytesToString();

    if (response.statusCode == 201) {
      // Created successfully
      return;
    } else if (response.statusCode == 409) { // Conflict for existing Lapkin on same date
      final errorData = jsonDecode(responseBody);
      throw Exception(errorData['message'] ?? 'Anda sudah memiliki Laporan Kinerja pada tanggal tersebut.');
    } else {
      final errorData = jsonDecode(responseBody);
      throw Exception(errorData['message'] ?? 'Gagal mengajukan Laporan Kinerja.');
    }
  }

  static Future<void> deleteLapkin(int id) async {
    final url = ApiService.buildUri('/api/lapkin/$id'); // Tidak ada queryParams di sini
    final headers = await _getHeaders();

    final response = await http.delete(url, headers: headers);

    if (response.statusCode == 200) {
      // Deleted successfully
      return;
    } else {
      final errorData = jsonDecode(response.body);
      throw Exception(errorData['message'] ?? 'Gagal menghapus Laporan Kinerja.');
    }
  }
}