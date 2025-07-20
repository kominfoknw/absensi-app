import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:absensi_mobile/services/api_service.dart';

class IzinService {
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  Future<String?> _getToken() async {
    return await _storage.read(key: 'token');
  }

  // --- Mengambil Daftar Izin (Hanya Milik Sendiri) ---
  Future<List<dynamic>> fetchIzinList() async {
    final token = await _getToken();
    if (token == null) return [];

    final url = ApiService.buildUri('/api/izin-pegawai');
    try {
      final response = await http.get(
        url,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );
      if (response.statusCode == 200) {
        return jsonDecode(response.body)['data'];
      } else {
        print('Gagal mengambil daftar izin: ${response.statusCode} ${response.body}');
        return [];
      }
    } catch (e) {
      print('Error fetchIzinList: $e');
      return [];
    }
  }

  // --- Mengajukan Izin Baru ---
  Future<Map<String, dynamic>> submitIzin({
    required String namaIzin,
    required String tanggalMulai,
    required String tanggalSelesai,
    String? keterangan,
    File? file,
  }) async {
    final token = await _getToken();
    if (token == null) return {'success': false, 'message': 'Token tidak ditemukan'};

    final url = ApiService.buildUri('/api/izin-pegawai');
    var request = http.MultipartRequest('POST', url);

    request.headers.addAll({
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
    });

    request.fields['nama_izin'] = namaIzin;
    request.fields['tanggal_mulai'] = tanggalMulai;
    request.fields['tanggal_selesai'] = tanggalSelesai;
    if (keterangan != null) request.fields['keterangan'] = keterangan;

    if (file != null) {
      request.files.add(
        await http.MultipartFile.fromPath(
          'file',
          file.path,
          filename: file.path.split('/').last,
        ),
      );
    }

    try {
      final streamedResponse = await request.send();
      final response = await http.Response.fromStream(streamedResponse);

      if (response.statusCode == 201) {
        return {'success': true, 'message': 'Izin berhasil diajukan'};
      } else if (response.statusCode == 422) {
        final Map<String, dynamic> errorData = jsonDecode(response.body);
        final String errorMessage = errorData['message'] ?? 'Validasi gagal';
        return {'success': false, 'message': errorMessage, 'errors': errorData['errors']};
      } else if (response.statusCode == 403) { // Tambahkan penanganan 403 Forbidden
        final Map<String, dynamic> errorData = jsonDecode(response.body);
        return {'success': false, 'message': errorData['message'] ?? 'Anda tidak memiliki akses untuk mengajukan izin.'};
      }
      else {
        return {'success': false, 'message': 'Gagal mengajukan izin: ${response.body}'};
      }
    } catch (e) {
      print('Error submitIzin: $e');
      return {'success': false, 'message': 'Terjadi kesalahan jaringan: $e'};
    }
  }

  // --- Menghapus Izin ---
  Future<Map<String, dynamic>> deleteIzin(int izinId) async {
    final token = await _getToken();
    if (token == null) return {'success': false, 'message': 'Token tidak ditemukan'};

    final url = ApiService.buildUri('/api/izin-pegawai/$izinId');
    try {
      final response = await http.delete(
        url,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );
      if (response.statusCode == 200 || response.statusCode == 204) {
        return {'success': true, 'message': 'Izin berhasil dihapus'};
      } else if (response.statusCode == 403) {
        final Map<String, dynamic> errorData = jsonDecode(response.body);
        return {'success': false, 'message': errorData['message'] ?? 'Tidak diizinkan untuk menghapus izin ini.'};
      } else {
        return {'success': false, 'message': 'Gagal menghapus izin: ${response.body}'};
      }
    } catch (e) {
      print('Error deleteIzin: $e');
      return {'success': false, 'message': 'Terjadi kesalahan jaringan: $e'};
    }
  }
}