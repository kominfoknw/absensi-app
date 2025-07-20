import 'dart:io';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:intl/intl.dart';
import 'package:cool_alert/cool_alert.dart';
import 'package:absensi_mobile/services/tugas_luar_service.dart';

class AddTugasLuarPage extends StatefulWidget {
  const AddTugasLuarPage({super.key});

  @override
  State<AddTugasLuarPage> createState() => _AddTugasLuarPageState();
}

class _AddTugasLuarPageState extends State<AddTugasLuarPage> {
  final _formKey = GlobalKey<FormState>();
  final TextEditingController _namaTugasController = TextEditingController();
  final TextEditingController _tanggalMulaiController = TextEditingController();
  final TextEditingController _tanggalSelesaiController = TextEditingController();
  final TextEditingController _keteranganController = TextEditingController();

  File? _selectedFile;
  DateTime? _tanggalMulai;
  DateTime? _tanggalSelesai;

  @override
  void dispose() {
    _namaTugasController.dispose();
    _tanggalMulaiController.dispose();
    _tanggalSelesaiController.dispose();
    _keteranganController.dispose();
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

  Future<void> _selectDate(BuildContext context, TextEditingController controller, bool isStartDate) async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime(2101),
    );
    if (picked != null) {
      setState(() {
        if (isStartDate) {
          _tanggalMulai = picked;
          controller.text = DateFormat('yyyy-MM-dd').format(picked);
          // Jika tanggal mulai lebih besar dari tanggal selesai yang sudah ada, reset tanggal selesai
          if (_tanggalSelesai != null && picked.isAfter(_tanggalSelesai!)) {
            _tanggalSelesai = null;
            _tanggalSelesaiController.text = '';
          }
        } else {
          _tanggalSelesai = picked;
          controller.text = DateFormat('yyyy-MM-dd').format(picked);
        }
      });
    }
  }

  Future<void> _submitTugasLuar() async {
    if (_formKey.currentState!.validate()) {
      if (_tanggalMulai == null || _tanggalSelesai == null) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.error,
          title: 'Error!',
          text: 'Tanggal mulai dan tanggal selesai harus diisi.',
        );
        return;
      }
      if (_tanggalSelesai!.isBefore(_tanggalMulai!)) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.error,
          title: 'Error!',
          text: 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        );
        return;
      }

      CoolAlert.show(
        context: context,
        type: CoolAlertType.loading,
        text: 'Mengirim tugas luar...',
        barrierDismissible: false,
      );

      try {
        await TugasLuarService.createTugasLuar(
          namaTugas: _namaTugasController.text,
          tanggalMulai: _tanggalMulaiController.text,
          tanggalSelesai: _tanggalSelesaiController.text,
          file: _selectedFile,
          keterangan: _keteranganController.text.isEmpty ? null : _keteranganController.text,
        );

        if (context.mounted) {
          Navigator.pop(context); // Tutup dialog loading
          CoolAlert.show(
            context: context,
            type: CoolAlertType.success,
            title: 'Berhasil!',
            text: 'Pengajuan tugas luar berhasil.',
            onConfirmBtnTap: () {
              Navigator.pop(context); // Tutup dialog sukses
              Navigator.pop(context, true); // Kembali ke halaman sebelumnya dan berikan sinyal refresh
            },
          );
        }
      } catch (e) {
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
        title: const Text('Ajukan Tugas Luar'),
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
                controller: _namaTugasController,
                decoration: const InputDecoration(
                  labelText: 'Nama Tugas',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Nama tugas tidak boleh kosong';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _tanggalMulaiController,
                readOnly: true,
                onTap: () => _selectDate(context, _tanggalMulaiController, true),
                decoration: const InputDecoration(
                  labelText: 'Tanggal Mulai',
                  border: OutlineInputBorder(),
                  suffixIcon: Icon(Icons.calendar_today),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Tanggal mulai tidak boleh kosong';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _tanggalSelesaiController,
                readOnly: true,
                onTap: () => _selectDate(context, _tanggalSelesaiController, false),
                decoration: const InputDecoration(
                  labelText: 'Tanggal Selesai',
                  border: OutlineInputBorder(),
                  suffixIcon: Icon(Icons.calendar_today),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Tanggal selesai tidak boleh kosong';
                  }
                  return null;
                },
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
                              : 'Pilih File Pendukung (PDF/JPG/PNG, maks 2MB)',
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
              const SizedBox(height: 16),
              TextFormField(
                controller: _keteranganController,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Keterangan (Opsional)',
                  alignLabelWithHint: true,
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _submitTugasLuar,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).primaryColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  child: const Text(
                    'Ajukan Tugas Luar',
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