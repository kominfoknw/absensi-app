import 'package:flutter/material.dart';
import 'package:absensi_mobile/services/izin_service.dart';
import 'package:absensi_mobile/pages/izin_form_page.dart';
import 'package:absensi_mobile/services/api_service.dart';
import 'package:absensi_mobile/helpers/date_helper.dart'; // ✅ sudah diimpor helper baru

class IzinPage extends StatefulWidget {
  const IzinPage({super.key});

  @override
  State<IzinPage> createState() => _IzinPageState();
}

class _IzinPageState extends State<IzinPage> {
  final IzinService _izinService = IzinService();
  List<dynamic> _izinList = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _fetchIzinList();
  }

  Future<void> _fetchIzinList() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });
    try {
      final data = await _izinService.fetchIzinList();
      setState(() {
        _izinList = data;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _errorMessage = 'Gagal memuat data izin: $e';
        _isLoading = false;
      });
    }
  }

  Future<void> _confirmDelete(int izinId, String status) async {
    if (status == 'diterima') {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Izin dengan status DITERIMA tidak dapat dihapus.')),
        );
      }
      return;
    }

    final bool? confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Konfirmasi Hapus'),
        content: const Text('Apakah Anda yakin ingin menghapus izin ini?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Hapus', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );

    if (confirm == true) {
      final result = await _izinService.deleteIzin(izinId);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result['message'])),
        );
      }
      if (result['success']) {
        _fetchIzinList();
      }
    }
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'pending':
        return Colors.orange;
      case 'diterima':
        return Colors.green;
      case 'ditolak':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  String _formatStatus(String status) {
    switch (status) {
      case 'pending':
        return 'Menunggu Persetujuan';
      case 'diterima':
        return 'Diterima';
      case 'ditolak':
        return 'Ditolak';
      default:
        return status;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Daftar Izin Saya'),
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
              : _izinList.isEmpty
                  ? const Center(
                      child: Text('Belum ada data izin yang Anda ajukan.'),
                    )
                  : ListView.builder(
                      itemCount: _izinList.length,
                      itemBuilder: (context, index) {
                        final izin = _izinList[index];
                        final fileUrl = izin['file'];
                        return Card(
                          margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          elevation: 2,
                          child: Padding(
                            padding: const EdgeInsets.all(16.0),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Expanded(
                                      child: Text(
                                        '${izin['pegawai']['nama']} (${izin['pegawai']['nip']})',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                          fontSize: 16,
                                        ),
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: _getStatusColor(izin['status']),
                                        borderRadius: BorderRadius.circular(5),
                                      ),
                                      child: Text(
                                        _formatStatus(izin['status']),
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 12,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                Text('Kantor: ${izin['kantor']['nama_kantor']}'),
                                Text('Jenis Izin: ${izin['nama_izin']}'),
                                // ✅ Ganti ke helper global
                                Text(
                                  'Tanggal: ${formatTanggalIndo(izin['tanggal_mulai'])} - ${formatTanggalIndo(izin['tanggal_selesai'])}',
                                ),
                                if (izin['keterangan'] != null && izin['keterangan'].isNotEmpty)
                                  Text('Keterangan: ${izin['keterangan']}'),
                                if (fileUrl != null && fileUrl.isNotEmpty)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 8.0),
                                    child: InkWell(
                                      onTap: () {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(content: Text('Membuka file: ${ApiService.storageUrl(fileUrl)}'))
                                        );
                                        // Anda bisa menggunakan package url_launcher untuk membuka URL
                                        // launchUrl(Uri.parse(ApiService.storageUrl(fileUrl)));
                                      },
                                      child: const Text(
                                        'Lihat File Pendukung',
                                        style: TextStyle(color: Colors.blue, decoration: TextDecoration.underline),
                                      ),
                                    ),
                                  ),
                                if (izin['status'] != 'diterima')
                                  Align(
                                    alignment: Alignment.bottomRight,
                                    child: IconButton(
                                      icon: const Icon(Icons.delete, color: Colors.red),
                                      onPressed: () => _confirmDelete(izin['id'], izin['status']),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (context) => const IzinFormPage()),
          );
          if (result == true) {
            _fetchIzinList();
          }
        },
        backgroundColor: Theme.of(context).primaryColor,
        child: const Icon(Icons.add, color: Colors.white),
      ),
    );
  }
}
