<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $siti;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(7, 25));
        $this->siti = $this->makePegawai('2026-11-17');
        Sanctum::actingAs($this->siti);
    }

    private function gps(float $lat = self::DEKAT_LAT, array $extra = []): array
    {
        return array_merge(['latitude' => $lat, 'longitude' => self::PUSKESMAS_LNG, 'accuracy' => 10, 'is_mock' => false], $extra);
    }

    public function test_today_sebelum_absen_menunjukkan_belum_check_in(): void
    {
        $this->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-17')
            ->assertJsonPath('data.has_checked_in', false)
            ->assertJsonPath('data.attendance', null)
            ->assertJsonPath('data.schedule.shift', 'Pagi')
            ->assertJsonPath('data.schedule.start_time', '07:30:00');
    }

    public function test_check_in_jauh_ditolak_dengan_jarak(): void
    {
        $response = $this->postJson('/api/attendance/check-in', $this->gps(self::JAUH_LAT))
            ->assertStatus(422);

        $this->assertStringContainsString('di luar radius', $response->json('message'));
        $this->assertEqualsWithDelta(222, $response->json('distance_m'), 1);
        $this->assertSame(0, Attendance::where('user_id', $this->siti->id)->count());
    }

    public function test_check_in_akurasi_buruk_dan_lokasi_palsu_ditolak(): void
    {
        $this->postJson('/api/attendance/check-in', $this->gps(extra: ['accuracy' => 500]))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Akurasi GPS'));

        $this->postJson('/api/attendance/check-in', $this->gps(extra: ['is_mock' => true]))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'lokasi palsu'));
    }

    public function test_check_in_dekat_berhasil_dan_tersimpan(): void
    {
        $response = $this->postJson('/api/attendance/check-in', $this->gps(extra: ['device_info' => 'Samsung A15']))
            ->assertCreated()
            ->assertJsonPath('message', 'Check-in berhasil.')
            ->assertJsonPath('data.status', 'hadir')
            ->assertJsonPath('data.source', 'online')
            ->assertJsonPath('data.check_in_at', '2026-11-17 07:25:00');

        $this->assertEqualsWithDelta(22.2, $response->json('data.check_in_distance_m'), 0.5);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->siti->id,
            'date' => '2026-11-17',
            'check_in_at' => '2026-11-17 07:25:00',
            'check_in_lat' => self::DEKAT_LAT,
            'status' => 'hadir',
            'device_info' => 'Samsung A15',
        ]);

        $this->getJson('/api/attendance/today')->assertJsonPath('data.has_checked_in', true);
    }

    public function test_check_in_terlambat(): void
    {
        $this->travelTo(now()->setTime(8, 0));

        $this->postJson('/api/attendance/check-in', $this->gps())
            ->assertCreated()
            ->assertJsonPath('data.status', 'terlambat')
            ->assertJsonPath('data.late_minutes', 30)
            ->assertJsonPath('message', 'Check-in berhasil. Anda terlambat 30 menit.');
    }

    public function test_check_in_kedua_ditolak(): void
    {
        $this->postJson('/api/attendance/check-in', $this->gps())->assertCreated();

        $this->postJson('/api/attendance/check-in', $this->gps())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Anda sudah check-in hari ini.');
    }

    public function test_client_uuid_sama_tidak_membuat_data_baru(): void
    {
        $uuid = '6f1c2a5e-8f3b-4c1d-9a2e-1b2c3d4e5f60';

        $first = $this->postJson('/api/attendance/check-in', $this->gps(extra: ['client_uuid' => $uuid]))->assertCreated();
        $second = $this->postJson('/api/attendance/check-in', $this->gps(extra: ['client_uuid' => $uuid]))->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Attendance::where('user_id', $this->siti->id)->count());
    }

    public function test_client_uuid_milik_pegawai_lain_ditolak(): void
    {
        $uuid = '6f1c2a5e-8f3b-4c1d-9a2e-1b2c3d4e5f61';
        $this->postJson('/api/attendance/check-in', $this->gps(extra: ['client_uuid' => $uuid]))->assertCreated();

        Sanctum::actingAs($this->makePegawai('2026-11-17'));
        $this->postJson('/api/attendance/check-in', $this->gps(extra: ['client_uuid' => $uuid]))->assertStatus(422);
    }

    public function test_check_out_berhasil_lalu_check_out_kedua_ditolak(): void
    {
        $this->postJson('/api/attendance/check-in', $this->gps())->assertCreated();
        $this->travelTo(now()->setTime(14, 5));

        $this->postJson('/api/attendance/check-out', $this->gps())
            ->assertOk()
            ->assertJsonPath('message', 'Check-out berhasil.')
            ->assertJsonPath('data.check_out_at', '2026-11-17 14:05:00');

        $this->postJson('/api/attendance/check-out', $this->gps())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Anda sudah check-out hari ini.');
    }

    public function test_check_out_tanpa_check_in_ditolak(): void
    {
        $this->postJson('/api/attendance/check-out', $this->gps())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Anda belum check-in hari ini.');
    }

    public function test_riwayat_bulan_ini_berisi_absensi_hari_ini(): void
    {
        $id = $this->postJson('/api/attendance/check-in', $this->gps())->json('data.id');
        Attendance::create(['user_id' => $this->siti->id, 'date' => '2026-10-31', 'status' => 'hadir']);

        $this->getJson('/api/attendance?month=2026-11')
            ->assertOk()
            ->assertJsonPath('month', '2026-11')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $this->getJson('/api/attendance?month=11-2026')
            ->assertStatus(422)
            ->assertJsonPath('errors.month.0', 'Bulan harus berformat Y-m.');
    }

    public function test_detail_milik_sendiri_bisa_dibuka_milik_orang_lain_tidak(): void
    {
        $id = $this->postJson('/api/attendance/check-in', $this->gps())->json('data.id');
        $this->getJson("/api/attendance/{$id}")->assertOk()->assertJsonPath('data.id', $id);

        Sanctum::actingAs($this->makePegawai());
        $this->getJson("/api/attendance/{$id}")->assertNotFound();
    }

    public function test_input_tidak_lengkap_mendapat_pesan_bahasa_indonesia(): void
    {
        $this->postJson('/api/attendance/check-in', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.latitude.0', 'Latitude wajib diisi.')
            ->assertJsonPath('errors.accuracy.0', 'Akurasi GPS wajib diisi.')
            ->assertJsonPath('errors.is_mock.0', 'Penanda lokasi palsu (is_mock) wajib diisi.');
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/attendance/today')->assertUnauthorized();
    }
}
