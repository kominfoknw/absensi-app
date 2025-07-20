import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:absensi_mobile/services/api_service.dart';
import 'package:intl/intl.dart'; // Import untuk format tanggal

class BeritaDetailPage extends StatefulWidget {
  final int beritaId;

  const BeritaDetailPage({super.key, required this.beritaId});

  @override
  State<BeritaDetailPage> createState() => _BeritaDetailPageState();
}

class _BeritaDetailPageState extends State<BeritaDetailPage> {
  Map<String, dynamic>? _berita;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _fetchBeritaDetail();
  }

  Future<void> _fetchBeritaDetail() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final uri = ApiService.buildUri('/api/berita/${widget.beritaId}');
      final response = await http.get(uri);

      if (response.statusCode == 200) {
        setState(() {
          _berita = jsonDecode(response.body);
        });
      } else {
        setState(() {
          _errorMessage = "Gagal memuat detail berita: ${response.statusCode} ${response.reasonPhrase}";
        });
        print("API Error (BeritaDetail): ${response.body}");
      }
    } catch (e) {
      setState(() {
        _errorMessage = "Terjadi kesalahan jaringan: $e";
      });
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Detail Berita'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Text(
                      _errorMessage!,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.red, fontSize: 16),
                    ),
                  ),
                )
              : _berita == null
                  ? const Center(child: Text('Berita tidak ditemukan.'))
                  : SingleChildScrollView(
                      padding: const EdgeInsets.all(16.0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (_berita!['gambar_url'] != null)
                            ClipRRect(
                              borderRadius: BorderRadius.circular(8.0),
                              child: Image.network(
                                _berita!['gambar_url'],
                                width: double.infinity,
                                height: 200,
                                fit: BoxFit.cover,
                                errorBuilder: (context, error, stackTrace) => Container(
                                  height: 200,
                                  color: Colors.grey[300],
                                  child: const Center(child: Icon(Icons.image_not_supported, size: 50, color: Colors.grey)),
                                ),
                              ),
                            ),
                          const SizedBox(height: 16),
                          Text(
                            _berita!['judul'] ?? 'Judul Tidak Tersedia',
                            style: const TextStyle(
                              fontSize: 24,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            _berita!['created_at'] != null
                                ? DateFormat('d MMMM yyyy HH:mm', 'id_ID').format(DateTime.parse(_berita!['created_at']))
                                : 'Tanggal Tidak Tersedia',
                            style: const TextStyle(
                              fontSize: 14,
                              color: Colors.grey,
                            ),
                          ),
                          const Divider(height: 32, thickness: 1),
                          // Menampilkan konten HTML
                          Text(
                            _berita!['konten'] ?? 'Konten Tidak Tersedia',
                            style: const TextStyle(fontSize: 16),
                          ),
                        ],
                      ),
                    ),
    );
  }
}