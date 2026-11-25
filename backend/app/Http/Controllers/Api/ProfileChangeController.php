<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pegawai meminta perubahan data profil (nama, NIP, jabatan, unit, nomor HP) dari menu Pengaturan aplikasi.
 * Data profil hanya boleh diubah admin, jadi permintaan dikirim ke setiap admin aktif sebagai notifikasi
 * (tampil di Dashboard Admin Web) dan, jika email server diatur, juga lewat email.
 */
class ProfileChangeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'message.required' => 'Tulis data apa yang perlu diubah.',
            'message.min' => 'Keterangan terlalu singkat.',
        ]);

        $user = $request->user();
        $admins = User::where('role', 'admin')->where('is_active', true)->get();
        foreach ($admins as $admin) {
            UserNotification::create([
                'user_id' => $admin->id,
                'type' => 'perubahan_data',
                'title' => 'Permintaan perubahan data: '.$user->name,
                'body' => $data['message'],
                'data' => ['employee_id' => $user->id, 'nip' => $user->nip],
            ]);
        }

        // Email hanya pelengkap; jika gagal, permintaan tetap tercatat di Dashboard admin.
        try {
            foreach ($admins as $admin) {
                Mail::raw("Pegawai {$user->name} (NIP {$user->nip}) meminta perubahan data profil:\n\n{$data['message']}\n\n"
                    .'Ubah datanya di Admin Web SIMPRES > Data Pegawai.', function ($mail) use ($admin, $user) {
                        $mail->to($admin->email)->subject('Permintaan perubahan data pegawai: '.$user->name);
                    });
            }
        } catch (\Throwable $e) {
            Log::warning('Email permintaan perubahan data gagal: '.$e->getMessage());
        }

        return response()->json(['message' => 'Permintaan perubahan data sudah dikirim ke admin.'], 201);
    }
}
