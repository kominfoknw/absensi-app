import 'package:flutter/material.dart';
import 'package:absensi_mobile/services/tugas_luar_service.dart';
import 'package:absensi_mobile/pages/add_tugas_luar_page.dart';
import 'package:cool_alert/cool_alert.dart';
import 'package:absensi_mobile/services/api_service.dart'; // Untuk menampilkan file dari storage

class TugasLuarPage extends StatefulWidget {
  const TugasLuarPage({super.key});

  @override
  State<TugasLuarPage> createState() => _TugasLuarPageState();
}

class _TugasLuarPageState extends State<TugasLuarPage> {
  List<dynamic> tugasLuars = [];
  bool isLoading = true;
  String errorMessage = '';

  @override
  void initState() {
    super.initState();
    _fetchTugasLuars();
  }

  Future<void> _fetchTugasLuars() async {
    setState(() {
      isLoading = true;
      errorMessage = '';
    });
    try {
      final data = await TugasLuarService.getTugasLuars();
      setState(() {
        tugasLuars = data;
        isLoading = false;
      });
    } catch (e) {
      setState(() {
        errorMessage = 'Gagal memuat data tugas luar: $e';
        isLoading = false;
      });
      if (context.mounted) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.error,
          title: 'Error!',
          text: errorMessage,
        );
      }
    }
  }

  Future<void> _deleteTugasLuar(int id) async {
    CoolAlert.show(
      context: context,
      type: CoolAlertType.confirm,
      title: 'Konfirmasi Hapus',
      text: 'Anda yakin ingin menghapus tugas luar ini?',
      onConfirmBtnTap: () async {
        Navigator.pop(context); // Tutup dialog konfirmasi
        CoolAlert.show(
          context: context,
          type: CoolAlertType.loading,
          text: 'Menghapus...',
          barrierDismissible: false,
        );
        try {
          await TugasLuarService.deleteTugasLuar(id);
          if (context.mounted) {
            Navigator.pop(context); // Tutup dialog loading
            CoolAlert.show(
              context: context,
              type: CoolAlertType.success,
              title: 'Berhasil!',
              text: 'Tugas luar berhasil dihapus.',
            );
          }
          _fetchTugasLuars(); // Refresh list
        } catch (e) {
          if (context.mounted) {
            Navigator.pop(context); // Tutup dialog loading
            CoolAlert.show(
              context: context,
              type: CoolAlertType.error,
              title: 'Gagal Hapus!',
              text: e.toString(),
            );
          }
        }
      },
      onCancelBtnTap: () {
        Navigator.pop(context); // Tutup dialog konfirmasi
      },
    );
  }

  // Helper untuk mendapatkan warna berdasarkan status
  Color _getStatusColor(String status) {
    switch (status.toLowerCase()) {
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Daftar Tugas Luar'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: isLoading
          ? const Center(child: CircularProgressIndicator())
          : errorMessage.isNotEmpty
              ? Center(child: Text(errorMessage))
              : tugasLuars.isEmpty
                  ? const Center(child: Text('Belum ada data tugas luar.'))
                  : ListView.builder(
                      padding: const EdgeInsets.all(8.0),
                      itemCount: tugasLuars.length,
                      itemBuilder: (context, index) {
                        final tugasLuar = tugasLuars[index];
                        final isAcceptedOrRejected =
                            tugasLuar['status'] == 'diterima' ||
                                tugasLuar['status'] == 'ditolak';

                        return Card(
                          margin: const EdgeInsets.symmetric(vertical: 8.0),
                          elevation: 2,
                          child: Padding(
                            padding: const EdgeInsets.all(16.0),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      tugasLuar['nama_tugas'],
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 16,
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 8, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: _getStatusColor(
                                            tugasLuar['status']),
                                        borderRadius: BorderRadius.circular(5),
                                      ),
                                      child: Text(
                                        tugasLuar['status'].toUpperCase(),
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 12,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  'Tanggal: ${tugasLuar['tanggal_mulai']} s/d ${tugasLuar['tanggal_selesai']}',
                                  style: const TextStyle(fontSize: 14),
                                ),
                                if (tugasLuar['keterangan'] != null &&
                                    tugasLuar['keterangan'].isNotEmpty)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 4.0),
                                    child: Text(
                                      'Keterangan: ${tugasLuar['keterangan']}',
                                      style: const TextStyle(
                                          fontSize: 14, fontStyle: FontStyle.italic),
                                    ),
                                  ),
                                if (tugasLuar['file'] != null)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 8.0),
                                    child: ElevatedButton.icon(
                                      onPressed: () {
                                        // Arahkan ke URL file
                                        final fileUrl = ApiService.storageUrl(tugasLuar['file']);
                                        // TODO: Implement file viewer (e.g., using url_launcher for browser or a custom PDF/image viewer)
                                        print('Membuka file: $fileUrl');
                                        // Example with url_launcher (add to pubspec.yaml: url_launcher: ^x.x.x)
                                        // launchUrl(Uri.parse(fileUrl), mode: LaunchMode.externalApplication);
                                      },
                                      icon: const Icon(Icons.attach_file),
                                      label: const Text('Lihat File'),
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: Colors.blueGrey,
                                        foregroundColor: Colors.white,
                                      ),
                                    ),
                                  ),
                                Align(
                                  alignment: Alignment.bottomRight,
                                  child: IconButton(
                                    icon: Icon(Icons.delete,
                                        color: isAcceptedOrRejected
                                            ? Colors.grey
                                            : Colors.red),
                                    onPressed: isAcceptedOrRejected
                                        ? null // Nonaktifkan tombol
                                        : () =>
                                            _deleteTugasLuar(tugasLuar['id']),
                                    tooltip: isAcceptedOrRejected
                                        ? 'Tidak dapat dihapus karena status sudah ${tugasLuar['status']}'
                                        : 'Hapus Tugas Luar',
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
            MaterialPageRoute(builder: (context) => const AddTugasLuarPage()),
          );
          if (result == true) {
            _fetchTugasLuars(); // Refresh the list if a new tugas luar was added
          }
        },
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
        child: const Icon(Icons.add),
        tooltip: 'Tambah Tugas Luar',
      ),
    );
  }
}