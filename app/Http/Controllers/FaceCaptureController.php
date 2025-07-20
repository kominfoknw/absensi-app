<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Storage;

class FaceCaptureController extends Controller
{
    public function store(Request $request, Pegawai $pegawai)
    {
        $data = $request->input('image');

        if (!$data) {
            return response()->json(['message' => 'No image data provided.'], 400);
        }

        // Decode base64 image
        list($type, $data) = explode(';', $data);
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);

        $fileName = 'face-recognition-photos/' . uniqid() . '.png';
        Storage::disk('public')->put($fileName, $data);

        // Simpan path foto ke database
        $pegawai->update([
            'foto_face_recognition' => $fileName
        ]);

        return response()->json([
            'message' => 'Foto berhasil disimpan.',
            'redirect' => route('filament.resources.pegawais.index')
        ]);
    }
}
