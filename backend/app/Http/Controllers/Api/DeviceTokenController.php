<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Simpan token FCM dari HP. Token yang sama tidak dibuat dua kali;
     * jika HP dipakai login akun lain, token dipindah ke akun yang baru.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:android'],
        ]);

        $token = DeviceToken::updateOrCreate(
            ['fcm_token' => $data['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? 'android',
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Token perangkat berhasil disimpan.',
            'data' => [
                'id' => $token->id,
                'platform' => $token->platform,
                'last_used_at' => $token->last_used_at->format('Y-m-d H:i:s'),
            ],
        ], $token->wasRecentlyCreated ? 201 : 200);
    }
}
