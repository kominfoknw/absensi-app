import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:absensi_mobile/services/izin_service.dart';

class IzinFormPage extends StatefulWidget {
  const IzinFormPage({super.key});

  @override
  State<IzinFormPage> createState() => _IzinFormPageState();
}

class _IzinFormPageState extends State<IzinFormPage> {
  final _formKey = GlobalKey<FormState>();
  final IzinService _izinService = IzinService();

  String? _namaIzin;
  DateTime? _tanggalMulai;
  DateTime? _tanggalSelesai;
  File? _selectedFile;
  String? _keterangan;

  @override
  void initState() {
    super.initState();
  }

  Future<void> _pickFile() async {
    final picker = ImagePicker();
    final pickedFile = await picker.pickImage(source: ImageSource.gallery);

    if (pickedFile != null) {
      setState(() {
        _selectedFile = File(pickedFile.path);
      });
    }
  }

  Future<void> _selectDate(BuildContext context, bool isStartDate) async {
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
          if (_tanggalSelesai != null &&
              _tanggalSelesai!.isBefore(_tanggalMulai!)) {
            _tanggalSelesai = _tanggalMulai;
          }
        } else {
          _tanggalSelesai = picked;
        }
      });
    }
  }

  void _submitForm() async {
    if (_formKey.currentState!.validate()) {
      _formKey.currentState!.save();

      if (_tanggalMulai == null || _tanggalSelesai == null) {
        _showSnackBar('Pilih rentang tanggal izin.', Colors.red);
        return;
      }

      if (context.mounted) {
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (context) => const AlertDialog(
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircularProgressIndicator(),
                SizedBox(height: 16),
                Text('Mengajukan izin...'),
              ],
            ),
          ),
        );
      }

      final result = await _izinService.submitIzin(
        namaIzin: _namaIzin!,
        tanggalMulai: DateFormat('yyyy-MM-dd').format(_tanggalMulai!),
        tanggalSelesai: DateFormat('yyyy-MM-dd').format(_tanggalSelesai!),
        keterangan: _keterangan,
        file: _selectedFile,
      );

      if (context.mounted) Navigator.pop(context);

      if (result['success']) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(result['message'])),
          );
          Navigator.pop(context, true);
        }
      } else {
        String errorMessage =
            result['message'] ?? 'Terjadi kesalahan tidak diketahui.';
        if (result['errors'] != null) {
          result['errors'].forEach((key, value) {
            errorMessage += '\n- ${value.join(', ')}';
          });
        }
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(errorMessage), backgroundColor: Colors.red),
          );
        }
      }
    }
  }

  void _showSnackBar(String message, Color color) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: color,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Form Pengajuan Izin'),
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
                decoration: const InputDecoration(
                  labelText: 'Nama Izin',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Nama izin tidak boleh kosong';
                  }
                  return null;
                },
                onSaved: (value) {
                  _namaIzin = value;
                },
              ),
              const SizedBox(height: 16),
              InkWell(
                onTap: () => _selectDate(context, true),
                child: InputDecorator(
                  decoration: const InputDecoration(
                    labelText: 'Tanggal Mulai',
                    border: OutlineInputBorder(),
                  ),
                  child: Text(
                    _tanggalMulai == null
                        ? 'Pilih Tanggal'
                        : DateFormat('dd MMMM yyyy').format(_tanggalMulai!),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              InkWell(
                onTap: () => _selectDate(context, false),
                child: InputDecorator(
                  decoration: const InputDecoration(
                    labelText: 'Tanggal Selesai',
                    border: OutlineInputBorder(),
                  ),
                  child: Text(
                    _tanggalSelesai == null
                        ? 'Pilih Tanggal'
                        : DateFormat('dd MMMM yyyy').format(_tanggalSelesai!),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              GestureDetector(
                onTap: _pickFile,
                child: Container(
                  height: 50,
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  alignment: Alignment.centerLeft,
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  child: Text(
                    _selectedFile == null
                        ? 'Pilih File Pendukung (Opsional)'
                        : _selectedFile!.path.split('/').last,
                    style: TextStyle(
                      color: _selectedFile == null
                          ? Colors.grey[700]
                          : Colors.black,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                decoration: const InputDecoration(
                  labelText: 'Keterangan (Opsional)',
                  border: OutlineInputBorder(),
                ),
                maxLines: 3,
                onSaved: (value) {
                  _keterangan = value;
                },
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).primaryColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  onPressed: _submitForm,
                  child: const Text(
                    'Ajukan Izin',
                    style: TextStyle(fontSize: 18),
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
