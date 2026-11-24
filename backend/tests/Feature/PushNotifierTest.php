<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotifierTest extends TestCase
{
    use DatabaseTransactions;

    private function fakeCredentials(): void
    {
        // Kunci RSA khusus test (tests/fixtures), bukan kunci Firebase asli.
        $pem = file_get_contents(base_path('tests/fixtures/fcm-kunci-uji.pem'));
        $file = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($file, json_encode([
            'project_id' => 'simpres-uji',
            'client_email' => 'uji@simpres-uji.iam.gserviceaccount.com',
            'private_key' => $pem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm.credentials' => $file]);
        Cache::forget('fcm_access_token');
    }

    private function notificationFor(User $user): UserNotification
    {
        return UserNotification::create([
            'user_id' => $user->id, 'type' => 'izin', 'title' => 'Izin diterima',
            'body' => 'Pengajuan cuti diterima oleh admin.', 'data' => ['status' => 'diterima'],
        ]);
    }

    public function test_notifikasi_dikirim_ke_semua_hp_pegawai(): void
    {
        $this->fakeCredentials();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'akses-uji']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/simpres-uji/messages/1']),
        ]);
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'fcm_token' => 'token-hp-1', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'fcm_token' => 'token-hp-2', 'platform' => 'android']);

        $sent = app(PushNotifier::class)->send($this->notificationFor($user));

        $this->assertSame(2, $sent);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'projects/simpres-uji/messages:send')
            && $request['message']['token'] === 'token-hp-1'
            && $request['message']['data']['title'] === 'Izin diterima'
            && $request->hasHeader('Authorization', 'Bearer akses-uji'));
    }

    public function test_token_hp_yang_sudah_tidak_berlaku_dihapus(): void
    {
        $this->fakeCredentials();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'akses-uji']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'fcm_token' => 'token-lama', 'platform' => 'android']);

        $this->assertSame(0, app(PushNotifier::class)->send($this->notificationFor($user)));
        $this->assertDatabaseMissing('device_tokens', ['fcm_token' => 'token-lama']);
    }

    public function test_tanpa_kunci_firebase_tidak_mengirim_apa_pun(): void
    {
        config(['services.fcm.credentials' => '']);
        Http::fake();
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'fcm_token' => 'token-hp', 'platform' => 'android']);

        $this->assertSame(0, app(PushNotifier::class)->send($this->notificationFor($user)));
        Http::assertNothingSent();
    }
}
