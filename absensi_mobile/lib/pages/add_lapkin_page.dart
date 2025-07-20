import 'dart:io';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:intl/intl.dart';
import 'package:cool_alert/cool_alert.dart';
import 'package:absensi_mobile/services/lapkin_service.dart';
// Import http package untuk menangani exception
import 'package:http/http.dart' as http; // <--- TAMBAHKAN INI

class AddLapkinPage extends StatefulWidget {
  const AddLapkinPage({super.key});

  @override
  State<AddLapkinPage> createState() => _AddLapkinPageState();
}

class _AddLapkinPageState extends State<AddLapkinPage> {
  final _formKey = GlobalKey<FormState>();
  final TextEditingController _tanggalController = TextEditingController();
  final TextEditingController _namaKegiatanController = TextEditingController();
  final TextEditingController _tempatController = TextEditingController();
  final TextEditingController _targetController = TextEditingController();
  final TextEditingController _outputController = TextEditingController();

  File? _selectedFile;

  @override
  void initState() {
    super.initState();
    _tanggalController.text = DateFormat('yyyy-MM-dd').format(DateTime.now());
  }

  @override
  void dispose() {
    _tanggalController.dispose();
    _namaKegiatanController.dispose();
    _tempatController.dispose();
    _targetController.dispose();
    _outputController.dispose();
    super.dispose();
  }

  Future<void> _pickFile() async {
    FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
    );

    if (result != null) {
      setState(() {
        _selectedFile = File(result.files.single.path!);
      });
    }
  }

  Future<void> _submitLapkin() async {
    if (_formKey.currentState!.validate()) {
      CoolAlert.show(
        context: context,
        type: CoolAlertType.loading,
        text: 'Mengirim Laporan Kinerja...',
        barrierDismissible: false,
      );

      try {
        await LapkinService.createLapkin(
          tanggal: _tanggalController.text,
          namaKegiatan: _namaKegiatanController.text,
          tempat: _tempatController.text,
          target: _targetController.text.isEmpty ? null : _targetController.text,
          output: _outputController.text.isEmpty ? null : _outputController.text,
          lampiran: _selectedFile,
        );

        if (context.mounted) {
          Navigator.pop(context); // Tutup dialog loading
          CoolAlert.show(
            context: context,
            type: CoolAlertType.success,
            title: 'Berhasil!',
            text: 'Laporan Kinerja berhasil diajukan.',
            onConfirmBtnTap: () {
              Navigator.pop(context); // Tutup dialog sukses
              Navigator.pop(context, true); // Kembali ke halaman sebelumnya dan berikan sinyal refresh
            },
          );
        }
      } on http.ClientException catch (e) { // <--- PERUBAHAN: Tangkap http.ClientException
        if (context.mounted) {
          Navigator.pop(context); // Tutup dialog loading
          String errorMessage = 'Terjadi kesalahan saat berkomunikasi dengan server.';
          if (e.message.contains('409')) { // <--- Cek jika pesan error mengandung 409
            errorMessage = 'Anda sudah memiliki Laporan Kinerja pada tanggal ini.';
          } else {
            errorMessage = e.message; // Tampilkan pesan error detail jika bukan 409
          }
          CoolAlert.show(
            context: context,
            type: CoolAlertType.error,
            title: 'Gagal!',
            text: errorMessage,
          );
        }
      } catch (e) { // <--- Tangkap error umum lainnya
        if (context.mounted) {
          Navigator.pop(context); // Tutup dialog loading
          CoolAlert.show(
            context: context,
            type: CoolAlertType.error,
            title: 'Gagal!',
            text: e.toString(),
          );
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Ajukan Laporan Kinerja'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextFormField(
                controller: _tanggalController,
                readOnly: true,
                decoration: const InputDecoration(
                  labelText: 'Tanggal Lapkin',
                  border: OutlineInputBorder(),
                  suffixIcon: Icon(Icons.calendar_today),
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _namaKegiatanController,
                decoration: const InputDecoration(
                  labelText: 'Nama Kegiatan',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Nama kegiatan tidak boleh kosong';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _tempatController,
                decoration: const InputDecoration(
                  labelText: 'Tempat',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Tempat tidak boleh kosong';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _targetController,
                decoration: const InputDecoration(
                  labelText: 'Target (Opsional)',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _outputController,
                decoration: const InputDecoration(
                  labelText: 'Output (Opsional)',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 16),
              GestureDetector(
                onTap: _pickFile,
                child: Container(
                  height: 50,
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey),
                    borderRadius: BorderRadius.circular(5),
                  ),
                  child: Row(
                    children: [
                      const SizedBox(width: 12),
                      const Icon(Icons.upload_file),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _selectedFile != null
                              ? _selectedFile!.path.split('/').last
                              : 'Pilih Lampiran (PDF/JPG/PNG, maks 2MB)',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (_selectedFile != null)
                        IconButton(
                          icon: const Icon(Icons.clear, color: Colors.red),
                          onPressed: () {
                            setState(() {
                              _selectedFile = null;
                            });
                          },
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _submitLapkin,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).primaryColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  child: const Text(
                    'Ajukan Laporan Kinerja',
                    style: TextStyle(fontSize: 16),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}