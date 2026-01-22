import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart'; 
import 'package:camera/camera.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:path_provider/path_provider.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:image/image.dart' as img;

import '../services/api_service.dart';
import '../services/attendance_service.dart';

class AbsenPage extends StatefulWidget {
  final bool isMasuk;
  const AbsenPage({super.key, required this.isMasuk});

  @override
  State<AbsenPage> createState() => _AbsenPageState();
}

class _AbsenPageState extends State<AbsenPage> {
  CameraController? _camera;
  late FaceDetector _faceDetector;
  final storage = const FlutterSecureStorage();

  bool _isLoading = true;
  bool _isScanning = true;
  bool _isVerified = false;
  String _statusMessage = "Menyiapkan kamera...";

  @override
  void initState() {
    super.initState();
    _faceDetector = FaceDetector(
      options: FaceDetectorOptions(
        enableClassification: true, 
        performanceMode: FaceDetectorMode.accurate,
      ),
    );
    _prepare();
  }

  Future<void> _prepare() async {
    await _ensureFaceDownloaded();
    await _initCamera();
    if (mounted) setState(() => _isLoading = false);
    _startLivenessDetection();
  }

  Future<File> _getFaceFile() async {
    final dir = await getApplicationDocumentsDirectory();
    return File('${dir.path}/face_reference.jpg');
  }

  Future<void> _ensureFaceDownloaded() async {
    final file = await _getFaceFile();
    if (await file.exists()) return;

    if (mounted) setState(() => _statusMessage = "Mengunduh data wajah...");
    try {
      final token = await storage.read(key: 'token');
      final res = await http.get(
        Uri.parse(ApiService.buildUri('/api/user').toString()),
        headers: {'Authorization': 'Bearer $token'},
      );

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        final facePath = data['pegawai']['foto_face_recognition'];
        final imgRes = await http.get(Uri.parse(ApiService.storageUrl(facePath)));
        await file.writeAsBytes(imgRes.bodyBytes);
      }
    } catch (e) {
      debugPrint("Download Error: $e");
    }
  }

  Future<void> _initCamera() async {
    final cameras = await availableCameras();
    final front = cameras.firstWhere((c) => c.lensDirection == CameraLensDirection.front);
    _camera = CameraController(front, ResolutionPreset.medium, enableAudio: false);
    await _camera!.initialize();
  }

  void _startLivenessDetection() async {
    if (_camera == null || !_camera!.value.isInitialized) return;

    _camera!.startImageStream((CameraImage image) async {
      if (!_isScanning || _isVerified) return;

      final inputImage = _processCameraImage(image);
      final faces = await _faceDetector.processImage(inputImage);

      if (faces.isEmpty) {
        if (mounted) setState(() => _statusMessage = "Wajah tidak terlihat");
        return;
      }

      for (Face face in faces) {
        if (face.leftEyeOpenProbability != null && face.rightEyeOpenProbability != null) {
          if (face.leftEyeOpenProbability! < 0.25 && face.rightEyeOpenProbability! < 0.25) {
            _isScanning = false;
            await _camera!.stopImageStream();
            _processRecognition();
            break;
          } else {
            if (mounted) setState(() => _statusMessage = "Silakan Berkedip...");
          }
        }
      }
    });
  }

  Future<void> _processRecognition() async {
    if (mounted) setState(() => _statusMessage = "Memverifikasi...");
    try {
      final XFile photo = await _camera!.takePicture();
      final File refFile = await _getFaceFile();

      final bool match = await _compareImages(refFile, File(photo.path));

      if (match) {
        if (mounted) {
          setState(() {
            _isVerified = true;
            _statusMessage = "Verifikasi Berhasil!";
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _isScanning = true;
            _statusMessage = "Wajah tidak cocok, ulangi!";
          });
          _startLivenessDetection();
        }
      }
    } catch (e) {
      debugPrint("Recognition Error: $e");
    }
  }

  Future<bool> _compareImages(File ref, File live) async {
    final img.Image? i1 = img.decodeImage(await ref.readAsBytes());
    final img.Image? i2 = img.decodeImage(await live.readAsBytes());
    if (i1 == null || i2 == null) return false;

    final img.Image s1 = img.copyResize(i1, width: 120);
    final img.Image s2 = img.copyResize(i2, width: 120);

    double diff = 0;
    for (int y = 0; y < s1.height; y++) {
      for (int x = 0; x < s1.width; x++) {
        final p1 = s1.getPixel(x, y);
        final p2 = s2.getPixel(x, y);
        diff += (p1.r - p2.r).abs() + (p1.g - p2.g).abs() + (p1.b - p2.b).abs();
      }
    }
    double score = diff / (s1.width * s1.height * 3);
    return score < 20; 
  }

  InputImage _processCameraImage(CameraImage image) {
    final WriteBuffer allBytes = WriteBuffer();
    for (final Plane plane in image.planes) {
      allBytes.putUint8List(plane.bytes);
    }
    final bytes = allBytes.done().buffer.asUint8List();

    final Size imageSize = Size(image.width.toDouble(), image.height.toDouble());
    const imageRotation = InputImageRotation.rotation270deg;
    const inputImageFormat = InputImageFormat.yuv420;

    final metadata = InputImageMetadata(
      size: imageSize,
      rotation: imageRotation,
      format: inputImageFormat,
      bytesPerRow: image.planes[0].bytesPerRow,
    );

    return InputImage.fromBytes(bytes: bytes, metadata: metadata);
  }

  Future<void> _submit() async {
    final token = await storage.read(key: 'token');
    await AttendanceService.submitAttendance(
      token: token!,
      isMasuk: widget.isMasuk,
      tanggal: DateTime.now().toString().substring(0, 10),
      jam: TimeOfDay.now().format(context),
      lat: 0, long: 0, fotoBase64: "",
    );
    if (mounted) Navigator.pop(context);
  }

  @override
  void dispose() {
    _camera?.dispose();
    _faceDetector.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) return const Scaffold(body: Center(child: CircularProgressIndicator()));

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          _camera != null && _camera!.value.isInitialized
              ? Center(child: CameraPreview(_camera!))
              : Container(),
          Center(
            child: Container(
              width: 260, height: 260,
              decoration: BoxDecoration(
                border: Border.all(color: _isVerified ? Colors.green : Colors.white, width: 3),
                borderRadius: BorderRadius.circular(200),
              ),
            ),
          ),
          Positioned(
            top: 70, left: 0, right: 0,
            child: Text(_statusMessage, textAlign: TextAlign.center, 
                style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
          ),
          if (_isVerified)
            Positioned(
              bottom: 60, left: 40, right: 40,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(backgroundColor: Colors.green, padding: const EdgeInsets.symmetric(vertical: 15)),
                onPressed: _submit,
                child: const Text("ABSEN SEKARANG", style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ),
        ],
      ),
    );
  }
}