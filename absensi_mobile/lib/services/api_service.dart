// lib/services/api_service.dart

class ApiService {
  // Ganti base URL sesuai IP/Domain server backend kamu
  static const String baseUrl = 'http://ekerja.konawekab.go.id';

  /// Build full Uri by providing the [path] and optional [queryParams].
  static Uri buildUri(String path, [Map<String, dynamic>? queryParams]) {
    // Pastikan path dimulai dengan slash
    final fullPath = path.startsWith('/') ? path : '/$path';
    return Uri.parse('$baseUrl$fullPath').replace(queryParameters: queryParams);
  }

  /// Digunakan untuk membangun URL ke file di folder storage Laravel
  static String storageUrl(String path) {
    if (path.startsWith('http')) {
      return path;
    }
    return '$baseUrl/storage/$path';
  }
}
