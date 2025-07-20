import 'package:flutter/material.dart';
import 'package:absensi_mobile/services/lapkin_service.dart';
import 'package:absensi_mobile/pages/add_lapkin_page.dart';
import 'package:cool_alert/cool_alert.dart';
import 'package:absensi_mobile/services/api_service.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

// Import helper date
import 'package:absensi_mobile/helpers/date_helper.dart';

class LapkinPage extends StatefulWidget {
  const LapkinPage({super.key});

  @override
  State<LapkinPage> createState() => _LapkinPageState();
}

class _LapkinPageState extends State<LapkinPage> {
  List<dynamic> lapkins = [];
  bool isLoading = true;
  String errorMessage = '';

  int _selectedMonth = DateTime.now().month;
  int _selectedYear = DateTime.now().year;

  @override
  void initState() {
    super.initState();
    _fetchLapkins();
  }

  Future<void> _fetchLapkins() async {
    setState(() {
      isLoading = true;
      errorMessage = '';
    });
    try {
      final data = await LapkinService.getLapkins(
          month: _selectedMonth, year: _selectedYear);
      setState(() {
        lapkins = data;
        isLoading = false;
      });
    } catch (e) {
      setState(() {
        errorMessage = 'Gagal memuat data Lapkin: $e';
        isLoading = false;
      });
    }
  }

  Future<void> _deleteLapkin(int id) async {
    CoolAlert.show(
      context: context,
      type: CoolAlertType.confirm,
      title: 'Konfirmasi Hapus',
      text: 'Anda yakin ingin menghapus Lapkin ini?',
      onConfirmBtnTap: () async {
        Navigator.pop(context);
        CoolAlert.show(
          context: context,
          type: CoolAlertType.loading,
          text: 'Menghapus...',
          barrierDismissible: false,
        );
        try {
          await LapkinService.deleteLapkin(id);
          if (context.mounted) {
            Navigator.pop(context);
            CoolAlert.show(
              context: context,
              type: CoolAlertType.success,
              title: 'Berhasil!',
              text: 'Laporan Kinerja berhasil dihapus.',
            );
          }
          _fetchLapkins();
        } catch (e) {
          if (context.mounted) {
            Navigator.pop(context);
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
        Navigator.pop(context);
      },
    );
  }

  String _getMonthName(int month) {
    return DateFormat.MMMM('id').format(DateTime(2000, month));
  }

  String _formatLapkinDate(String hari, String tanggal) {
    final formattedDate = formatTanggalIndo(tanggal, format: 'dd MMMM yyyy');
    return '$hari, $formattedDate';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Laporan Kinerja'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  flex: 5,
                  child: DropdownButtonFormField<int>(
                    value: _selectedMonth,
                    decoration: const InputDecoration(
                      labelText: 'Bulan',
                      border: OutlineInputBorder(),
                    ),
                    items: List.generate(12, (index) {
                      final month = index + 1;
                      return DropdownMenuItem(
                        value: month,
                        child: Text(_getMonthName(month)),
                      );
                    }),
                    onChanged: (value) {
                      setState(() {
                        _selectedMonth = value!;
                      });
                    },
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  flex: 5,
                  child: DropdownButtonFormField<int>(
                    value: _selectedYear,
                    decoration: const InputDecoration(
                      labelText: 'Tahun',
                      border: OutlineInputBorder(),
                    ),
                    items: List.generate(5, (index) {
                      final year = DateTime.now().year - index;
                      return DropdownMenuItem(
                        value: year,
                        child: Text(year.toString()),
                      );
                    }),
                    onChanged: (value) {
                      setState(() {
                        _selectedYear = value!;
                      });
                    },
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  height: 56,
                  child: ElevatedButton(
                    onPressed: _fetchLapkins,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Theme.of(context).primaryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(4.0)),
                      minimumSize: Size.zero,
                    ),
                    child: const Icon(Icons.search),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: isLoading
                ? const Center(child: CircularProgressIndicator())
                : errorMessage.isNotEmpty
                    ? Center(child: Text(errorMessage))
                    : lapkins.isEmpty
                        ? const Center(
                            child: Text(
                                'Belum ada data Lapkin untuk periode ini.'))
                        : ListView.builder(
                            padding: const EdgeInsets.all(8.0),
                            itemCount: lapkins.length,
                            itemBuilder: (context, index) {
                              final lapkin = lapkins[index];
                              final String formattedDateDisplay =
                                  _formatLapkinDate(
                                      lapkin['hari'], lapkin['tanggal']);

                              return Card(
                                margin:
                                    const EdgeInsets.symmetric(vertical: 8.0),
                                elevation: 2,
                                child: Padding(
                                  padding: const EdgeInsets.all(16.0),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment.spaceBetween,
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              children: [
                                                Text(
                                                  formattedDateDisplay,
                                                  style: const TextStyle(
                                                    fontWeight: FontWeight.bold,
                                                    fontSize: 16,
                                                  ),
                                                ),
                                                Text(
                                                  lapkin['nama_kegiatan'] ?? '',
                                                  style: const TextStyle(
                                                      fontSize: 14),
                                                ),
                                              ],
                                            ),
                                          ),
                                          const SizedBox(width: 8),
                                          Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.end,
                                            children: [
                                              const Text(
                                                'Kualitas Hasil',
                                                style: TextStyle(
                                                    fontSize: 10,
                                                    color: Colors.grey),
                                              ),
                                              Text(
                                                '${lapkin['kualitas_hasil'] ?? 0} Pts',
                                                style: const TextStyle(
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 18,
                                                  color: Colors.green,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                          'Tempat: ${lapkin['tempat'] ?? '-'}'),
                                      if (lapkin['target'] != null &&
                                          lapkin['target'].isNotEmpty)
                                        Text('Target: ${lapkin['target']}'),
                                      if (lapkin['output'] != null &&
                                          lapkin['output'].isNotEmpty)
                                        Text('Output: ${lapkin['output']}'),
                                      if (lapkin['lampiran'] != null)
                                        Padding(
                                          padding:
                                              const EdgeInsets.only(top: 8.0),
                                          child: ElevatedButton.icon(
                                            onPressed: () async {
                                              final fileUrl =
                                                  ApiService.storageUrl(
                                                      lapkin['lampiran']);
                                              if (await canLaunchUrl(
                                                  Uri.parse(fileUrl))) {
                                                await launchUrl(
                                                    Uri.parse(fileUrl),
                                                    mode: LaunchMode
                                                        .externalApplication);
                                              } else {
                                                if (context.mounted) {
                                                  CoolAlert.show(
                                                    context: context,
                                                    type: CoolAlertType.error,
                                                    title: 'Error!',
                                                    text:
                                                        'Tidak dapat membuka lampiran.',
                                                  );
                                                }
                                              }
                                            },
                                            icon:
                                                const Icon(Icons.attach_file),
                                            label: const Text(
                                                'Lihat Lampiran'),
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor:
                                                  Colors.blueGrey,
                                              foregroundColor: Colors.white,
                                            ),
                                          ),
                                        ),
                                      Align(
                                        alignment: Alignment.bottomRight,
                                        child: IconButton(
                                          icon: const Icon(Icons.delete,
                                              color: Colors.red),
                                          onPressed: () =>
                                              _deleteLapkin(lapkin['id']),
                                          tooltip: 'Hapus Lapkin',
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(
                builder: (context) => const AddLapkinPage()),
          );
          if (result == true) {
            _fetchLapkins();
          }
        },
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
        child: const Icon(Icons.add),
        tooltip: 'Tambah Laporan Kinerja',
      ),
    );
  }
}
