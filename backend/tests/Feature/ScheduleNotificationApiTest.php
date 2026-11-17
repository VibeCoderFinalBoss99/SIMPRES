<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\DeviceToken;
use App\Models\FieldDuty;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleNotificationApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $siti;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(9, 0));
        $this->siti = $this->makePegawai('2026-11-17');
        Sanctum::actingAs($this->siti);
    }

    public function test_jadwal_bulan_ini_beserta_jam_shift(): void
    {
        Schedule::create(['user_id' => $this->siti->id, 'date' => '2026-11-18', 'is_day_off' => true]);
        Schedule::create(['user_id' => $this->siti->id, 'shift_id' => $this->makeShift()->id, 'date' => '2026-12-01']);

        $this->getJson('/api/schedules?month=2026-11')
            ->assertOk()
            ->assertJsonPath('month', '2026-11')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-11-17')
            ->assertJsonPath('data.0.shift.name', 'Pagi')
            ->assertJsonPath('data.0.shift.start_time', '07:30:00')
            ->assertJsonPath('data.0.shift.end_time', '14:00:00')
            ->assertJsonPath('data.1.is_day_off', true)
            ->assertJsonPath('data.1.shift', null);
    }

    public function test_jadwal_tanpa_bulan_memakai_bulan_ini_dan_tidak_menampilkan_jadwal_orang_lain(): void
    {
        $this->makePegawai('2026-11-17');

        $this->getJson('/api/schedules')->assertOk()->assertJsonPath('month', '2026-11')->assertJsonCount(1, 'data');
    }

    public function test_lokasi_kerja_berisi_titik_dan_radius(): void
    {
        $this->getJson('/api/work-location')
            ->assertOk()
            ->assertJsonPath('data.latitude', self::PUSKESMAS_LAT)
            ->assertJsonPath('data.longitude', self::PUSKESMAS_LNG)
            ->assertJsonPath('data.radius_m', 150);
    }

    public function test_lokasi_kerja_tanpa_token_ditolak(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/work-location')->assertUnauthorized();
    }

    public function test_izin_diterima_admin_membuat_notifikasi_untuk_pegawai(): void
    {
        $leave = LeaveRequest::create([
            'user_id' => $this->siti->id, 'type' => 'cuti', 'start_date' => '2026-11-20', 'end_date' => '2026-11-21',
            'reason' => 'Acara keluarga', 'status' => 'menunggu',
        ]);

        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 0);

        Sanctum::actingAs($this->makeAdmin());
        $this->patchJson("/api/admin/leaves/{$leave->id}", ['status' => 'diterima', 'admin_note' => 'Selamat'])->assertOk();

        Sanctum::actingAs($this->siti);
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.type', 'izin')
            ->assertJsonPath('data.0.title', 'Izin diterima')
            ->assertJsonPath('data.0.body', 'Pengajuan cuti tanggal 20/11/2026 s.d. 21/11/2026 diterima oleh admin. Catatan: Selamat')
            ->assertJsonPath('data.0.data.submission_id', $leave->id)
            ->assertJsonPath('data.0.is_read', false);
    }

    public function test_koreksi_dan_tugas_lapangan_yang_diputuskan_juga_membuat_notifikasi(): void
    {
        $correction = AttendanceCorrection::create([
            'user_id' => $this->siti->id, 'date' => '2026-11-17', 'corrected_check_out' => '14:00:00', 'reason' => 'x', 'status' => 'menunggu',
        ]);
        $duty = FieldDuty::create([
            'user_id' => $this->siti->id, 'date' => '2026-11-17', 'location_name' => 'Posyandu Melati',
            'description' => 'x', 'photo_path' => 'tugas-lapangan/a.jpg', 'status' => 'menunggu',
        ]);

        Sanctum::actingAs($this->makeAdmin());
        $this->patchJson("/api/admin/corrections/{$correction->id}", ['status' => 'ditolak'])->assertOk();
        $this->patchJson("/api/admin/field-duties/{$duty->id}", ['status' => 'diterima'])->assertOk();

        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->siti->id, 'type' => 'koreksi', 'title' => 'Koreksi absensi ditolak']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->siti->id, 'type' => 'tugas_lapangan', 'title' => 'Tugas lapangan diterima']);
    }

    public function test_notifikasi_terbaru_dulu(): void
    {
        $this->notification('Lama', now()->subDay());
        $this->notification('Baru', now());

        $this->getJson('/api/notifications')->assertJsonPath('data.0.title', 'Baru')->assertJsonPath('data.1.title', 'Lama');
    }

    public function test_tandai_dibaca_mengisi_read_at_dan_mengurangi_jumlah_belum_dibaca(): void
    {
        $first = $this->notification('Satu');
        $this->notification('Dua');

        $this->postJson("/api/notifications/{$first->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('unread_count', 1);

        $this->assertNotNull($first->fresh()->read_at);
        $this->getJson('/api/notifications')->assertJsonPath('unread_count', 1);

        $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->getJson('/api/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_tidak_bisa_membaca_notifikasi_milik_pegawai_lain(): void
    {
        $budi = $this->makePegawai();
        $notification = UserNotification::create(['user_id' => $budi->id, 'type' => 'izin', 'title' => 'Milik Budi', 'body' => 'x']);

        $this->postJson("/api/notifications/{$notification->id}/read")->assertNotFound();
        $this->getJson('/api/notifications')->assertJsonCount(0, 'data');
        $this->postJson('/api/notifications/read-all')->assertOk();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_simpan_token_fcm_tanpa_duplikat(): void
    {
        $this->postJson('/api/device-token', ['fcm_token' => 'token-hp-siti'])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android');

        $this->postJson('/api/device-token', ['fcm_token' => 'token-hp-siti'])->assertOk();
        $this->assertSame(1, DeviceToken::where('fcm_token', 'token-hp-siti')->count());

        // HP yang sama dipakai login akun lain: token pindah ke akun baru.
        $budi = $this->makePegawai();
        Sanctum::actingAs($budi);
        $this->postJson('/api/device-token', ['fcm_token' => 'token-hp-siti'])->assertOk();
        $this->assertSame($budi->id, DeviceToken::where('fcm_token', 'token-hp-siti')->value('user_id'));

        $this->postJson('/api/device-token', [])->assertStatus(422)->assertJsonPath('errors.fcm_token.0', 'Token FCM wajib diisi.');
    }

    private function notification(string $title, $at = null): UserNotification
    {
        $notification = UserNotification::create(['user_id' => $this->siti->id, 'type' => 'izin', 'title' => $title, 'body' => 'x']);

        if ($at) {
            $notification->forceFill(['created_at' => $at])->save();
        }

        return $notification;
    }
}
