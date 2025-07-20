<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lokasi;
use App\Models\Shift;
use Illuminate\Http\Request;

class DataController extends Controller
{
    public function getLokasi($id)
    {
        $lokasi = Lokasi::find($id);
        if (!$lokasi) {
            return response()->json(['message' => 'Lokasi tidak ditemukan.'], 404);
        }
        return response()->json($lokasi);
    }

    public function getShift($id)
    {
        $shift = Shift::find($id);
        if (!$shift) {
            return response()->json(['message' => 'Shift tidak ditemukan.'], 404);
        }
        return response()->json($shift);
    }
}