import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:absensi_mobile/services/api_service.dart';
import 'package:cool_alert/cool_alert.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'dart:io';


class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  final storage = const FlutterSecureStorage();
  Map<String, dynamic>? _userProfile; // Ini akan berisi data 'pegawai'
  // Tambahkan variabel untuk data kantor di level user
  String? _namaKantor; // Untuk menyimpan nama kantor dari level root response

  bool _isLoading = true;
  String? _errorMessage;

  final TextEditingController _currentPasswordController = TextEditingController();
  final TextEditingController _newPasswordController = TextEditingController();
  final TextEditingController _confirmNewPasswordController = TextEditingController();

  final _formKey = GlobalKey<FormState>();

  @override
  void initState() {
    super.initState();
    _fetchUserProfile();
  }

  @override
  void dispose() {
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmNewPasswordController.dispose();
    super.dispose();
  }

  Future<void> _fetchUserProfile() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final token = await storage.read(key: 'token');
      if (token == null) {
        _errorMessage = "Token otentikasi tidak ditemukan. Harap login ulang.";
        return;
      }

      final uri = ApiService.buildUri('/api/user');

      final response = await http.get(
        uri,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        final responseData = jsonDecode(response.body);
        setState(() {
          _userProfile = responseData['pegawai']; // Data pegawai tetap di sini
          _namaKantor = responseData['kantor']; // Ambil nama kantor dari level root
        });
      } else if (response.statusCode == 401) {
        _errorMessage = "Sesi Anda habis. Harap login ulang.";
      } else {
        _errorMessage = "Gagal memuat profil: ${response.statusCode} ${response.reasonPhrase}";
        print("API Error (fetchUserProfile): ${response.body}");
      }
    } catch (e) {
      _errorMessage = "Terjadi kesalahan jaringan: $e";
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _changePassword() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (_userProfile == null) {
      if (context.mounted) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.error,
          title: "Error",
          text: "Data profil tidak ditemukan. Coba muat ulang halaman.",
        );
      }
      return;
    }

    if (_newPasswordController.text != _confirmNewPasswordController.text) {
      if (context.mounted) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.warning,
          title: "Gagal",
          text: "Konfirmasi password baru tidak cocok.",
        );
      }
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final token = await storage.read(key: 'token');
      if (token == null) {
        if (context.mounted) {
          CoolAlert.show(
            context: context,
            type: CoolAlertType.error,
            title: "Error",
            text: "Token otentikasi tidak ditemukan. Harap login ulang.",
          );
        }
        return;
      }

      final uri = ApiService.buildUri('/api/change-password');

      final response = await http.post(
        uri,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({
          'current_password': _currentPasswordController.text,
          'new_password': _newPasswordController.text,
          'new_password_confirmation': _confirmNewPasswordController.text,
        }),
      );

      if (context.mounted) {
        final responseBody = jsonDecode(response.body);
        if (response.statusCode == 200) {
          CoolAlert.show(
            context: context,
            type: CoolAlertType.success,
            title: "Berhasil!",
            text: responseBody['message'] ?? "Password berhasil diperbarui.",
          );
          _currentPasswordController.clear();
          _newPasswordController.clear();
          _confirmNewPasswordController.clear();
        } else {
          CoolAlert.show(
            context: context,
            type: CoolAlertType.error,
            title: "Gagal!",
            text: responseBody['message'] ?? "Terjadi kesalahan saat memperbarui password.",
          );
          print("API Error (changePassword): ${response.body}");
        }
      }
    } catch (e) {
      if (context.mounted) {
        CoolAlert.show(
          context: context,
          type: CoolAlertType.error,
          title: "Error",
          text: "Terjadi kesalahan jaringan: $e",
        );
      }
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

 Future<void> _downloadFaceRecognition() async {
  try {
    final facePath = _userProfile?['foto_face_recognition'];

    if (facePath == null || facePath.isEmpty) {
      CoolAlert.show(
        context: context,
        type: CoolAlertType.warning,
        title: "Tidak Ditemukan",
        text: "Data foto face recognition tidak tersedia.",
      );
      return;
    }

    final url = ApiService.storageUrl(facePath);
    final response = await http.get(Uri.parse(url));

    if (response.statusCode != 200) {
      throw Exception("Gagal mengunduh foto");
    }

    // ✅ TIDAK PERLU IZIN STORAGE
    final directory = await getApplicationDocumentsDirectory();
    final filePath =
        '${directory.path}/face_recognition_${_userProfile?['nip']}.jpg';

    final file = File(filePath);
    await file.writeAsBytes(response.bodyBytes);

    if (context.mounted) {
      CoolAlert.show(
        context: context,
        type: CoolAlertType.success,
        title: "Berhasil",
        text: "Foto face recognition berhasil disimpan.\n$filePath",
      );
    }
  } catch (e) {
    if (context.mounted) {
      CoolAlert.show(
        context: context,
        type: CoolAlertType.error,
        title: "Error",
        text: "Gagal mengunduh foto: $e",
      );
    }
  }
}



  @override
  Widget build(BuildContext context) {
    if (_isLoading && _userProfile == null && _errorMessage == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Profil Pengguna')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_errorMessage != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Profil Pengguna')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.error, color: Colors.red, size: 50),
                const SizedBox(height: 10),
                Text(
                  _errorMessage!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.red, fontSize: 16),
                ),
                const SizedBox(height: 20),
                ElevatedButton(
                  onPressed: _fetchUserProfile,
                  child: const Text('Coba Lagi'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final avatarUrl = _userProfile?['foto_selfie'];
    final nama = _userProfile?['nama'] ?? 'N/A';
    final nip = _userProfile?['nip'] ?? 'N/A';
    final jabatan = _userProfile?['jabatan'] ?? 'N/A';

    final unit = _userProfile?['unit'] ?? 'N/A';
    final kantor = _namaKantor ?? 'N/A';
    final kelasJabatan = _userProfile?['kelas_jabatan'] ?? 'N/A';

    final imageProvider = avatarUrl != null && avatarUrl != ""
        ? NetworkImage(
            avatarUrl.startsWith('http')
                ? avatarUrl
                : ApiService.storageUrl(avatarUrl))
        : const AssetImage('assets/images/default_avatar.png') as ImageProvider;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profil Pengguna'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            CircleAvatar(
              radius: 60,
              backgroundImage: imageProvider,
            ),
            const SizedBox(height: 16),
            Text(
              nama,
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              jabatan,
              style: const TextStyle(fontSize: 18, color: Colors.grey),
              textAlign: TextAlign.center, // <-- Corrected line
            ),
            const SizedBox(height: 4),
            Text(
              'NIP: $nip',
              style: const TextStyle(fontSize: 16, color: Colors.grey),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 4),
            Text(
              'Unit: $unit',
              style: const TextStyle(fontSize: 16, color: Colors.grey),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 4),
            Text(
              'Kantor: $kantor',
              style: const TextStyle(fontSize: 16, color: Colors.grey),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 4),
            Text(
              'Kelas Jabatan: $kelasJabatan',
              style: const TextStyle(fontSize: 16, color: Colors.grey),
              textAlign: TextAlign.center,
            ),
            const Divider(height: 32, thickness: 1),
            const Text(
              'Ganti Password',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            Form(
              key: _formKey,
              child: Column(
                children: [
                  TextFormField(
                    controller: _currentPasswordController,
                    decoration: const InputDecoration(
                      labelText: 'Password Lama',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.lock),
                    ),
                    obscureText: true,
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Password lama tidak boleh kosong';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _newPasswordController,
                    decoration: const InputDecoration(
                      labelText: 'Password Baru',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.lock_outline),
                    ),
                    obscureText: true,
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Password baru tidak boleh kosong';
                      }
                      if (value.length < 6) {
                        return 'Password minimal 6 karakter';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _confirmNewPasswordController,
                    decoration: const InputDecoration(
                      labelText: 'Konfirmasi Password Baru',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.lock_reset),
                    ),
                    obscureText: true,
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Konfirmasi password tidak boleh kosong';
                      }
                      if (value != _newPasswordController.text) {
                        return 'Konfirmasi password tidak cocok';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 24),
                  _isLoading
                      ? const CircularProgressIndicator()
                      : ElevatedButton.icon(
                          onPressed: _changePassword,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Theme.of(context).primaryColor,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 15),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          icon: const Icon(Icons.update),
                          label: const Text(
                            'PERBAHARUI PASSWORD',
                            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                          ),
                        ),
                        const SizedBox(height: 16),

ElevatedButton.icon(
  onPressed: _downloadFaceRecognition,
  style: ElevatedButton.styleFrom(
    backgroundColor: Colors.blueGrey,
    foregroundColor: Colors.white,
    padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 15),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(10),
    ),
  ),
  icon: const Icon(Icons.download),
  label: const Text(
    'DOWNLOAD REKAM WAJAH',
    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
  ),
),

                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}