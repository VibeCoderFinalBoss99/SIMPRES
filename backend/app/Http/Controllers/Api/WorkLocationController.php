<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkLocationController extends Controller
{
    /** Titik dan radius absensi milik pegawai yang login (untuk peta di aplikasi). */
    public function show(Request $request): JsonResponse
    {
        $location = $request->user()->workLocation;

        if (! $location) {
            return response()->json(['message' => 'Lokasi kerja Anda belum diatur. Hubungi admin Puskesmas.'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'radius_m' => $location->radius_m,
                'is_active' => $location->is_active,
            ],
        ]);
    }
}
