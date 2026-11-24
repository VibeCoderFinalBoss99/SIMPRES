<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim notifikasi ke HP pegawai lewat Firebase Cloud Messaging (FCM HTTP v1).
 * Kunci akun layanan Firebase dibaca dari config('services.fcm.credentials') (file JSON di storage private).
 * Jika kunci belum ada atau pengiriman gagal, keputusan admin tetap tersimpan; HP masih mengambil
 * notifikasi lewat pengecekan berkala di aplikasi.
 */
class PushNotifier
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Kirim ke semua HP milik pegawai. Mengembalikan jumlah HP yang berhasil dikirimi. */
    public function send(UserNotification $notification): int
    {
        $file = config('services.fcm.credentials');
        if (! $file || ! is_file($file)) {
            return 0;
        }

        $tokens = DeviceToken::where('user_id', $notification->user_id)->pluck('fcm_token');
        if ($tokens->isEmpty()) {
            return 0;
        }

        try {
            $credentials = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $accessToken = $this->accessToken($credentials);
        } catch (\Throwable $e) {
            Log::warning('FCM: gagal mendapatkan akses token. '.$e->getMessage());

            return 0;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send";
        $sent = 0;

        foreach ($tokens as $token) {
            try {
                // Pesan data (bukan notification) supaya aplikasi yang menampilkan, dengan format yang sama
                // seperti notifikasi dari pengecekan berkala, dan tidak muncul dua kali.
                $response = Http::withToken($accessToken)->timeout(8)->post($url, [
                    'message' => [
                        'token' => $token,
                        'data' => [
                            'id' => (string) $notification->id,
                            'type' => $notification->type,
                            'title' => $notification->title,
                            'body' => $notification->body,
                        ],
                        'android' => ['priority' => 'high'],
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('FCM: pengiriman gagal. '.$e->getMessage());

                continue;
            }

            if ($response->successful()) {
                $sent++;
            } elseif ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                // Aplikasi sudah dihapus dari HP itu: token tidak berlaku lagi.
                DeviceToken::where('fcm_token', $token)->delete();
            } else {
                Log::warning('FCM: ditolak '.$response->status().' '.$response->body());
            }
        }

        return $sent;
    }

    /** Akses token Google OAuth dari JWT yang ditandatangani kunci akun layanan, disimpan 50 menit. */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm_access_token', 3000, function () use ($credentials) {
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = "{$header}.{$claims}.".$this->base64Url($signature);

            return Http::asForm()->timeout(10)->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])->throw()->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
