<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\FieldDuty;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(9, 0));
        // Database test kosong di awal; hapus sisa data supaya hitungan pasti.
        User::query()->delete();
    }

    public function test_kartu_ringkasan_dan_tabel_absensi_hari_ini(): void
    {
        $siti = $this->makePegawai(null, ['name' => 'Siti Aminah']);
        $budi = $this->makePegawai(null, ['name' => 'Budi Santoso']);
        $rina = $this->makePegawai(null, ['name' => 'Rina Wati']);
        $dodi = $this->makePegawai(null, ['name' => 'Dodi Libur']);
        $this->makePegawai(null, ['name' => 'Eko Belum']);
        $this->makePegawai(null, ['name' => 'Nonaktif', 'is_active' => false]);

        Attendance::create(['user_id' => $siti->id, 'date' => '2026-11-17', 'check_in_at' => '2026-11-17 07:20:00', 'check_in_distance_m' => 22.2, 'status' => 'hadir']);
        Attendance::create(['user_id' => $budi->id, 'date' => '2026-11-17', 'check_in_at' => '2026-11-17 08:05:00', 'status' => 'terlambat', 'late_minutes' => 35]);
        Attendance::create(['user_id' => $rina->id, 'date' => '2026-11-17', 'status' => 'izin']);
        Attendance::create(['user_id' => $siti->id, 'date' => '2026-11-16', 'status' => 'hadir']);
        Schedule::create(['user_id' => $dodi->id, 'date' => '2026-11-17', 'is_day_off' => true]);

        LeaveRequest::create(['user_id' => $siti->id, 'type' => 'cuti', 'start_date' => '2026-11-20', 'end_date' => '2026-11-20', 'reason' => 'x', 'status' => 'menunggu']);
        FieldDuty::create(['user_id' => $siti->id, 'date' => '2026-11-17', 'location_name' => 'x', 'description' => 'x', 'photo_path' => 'a.jpg', 'status' => 'menunggu']);

        $response = $this->actingAs($this->makeAdmin())->get('/admin')->assertOk();

        $counts = $response->viewData('counts');
        $this->assertSame(['hadir' => 1, 'terlambat' => 1, 'izin' => 1, 'belum' => 1], $counts);
        $this->assertSame(['izin' => 1, 'koreksi' => 0, 'tugas' => 1], $response->viewData('waiting'));

        $response->assertSeeInOrder(['Siti Aminah', '07:20', '22 m', 'Budi Santoso', '08:05', '35 menit'])
            ->assertSee('Eko Belum')
            ->assertDontSee('Nonaktif');
    }

    public function test_setelah_pegawai_check_in_angka_hadir_bertambah(): void
    {
        $this->travelTo(now()->setTime(7, 20)); // sebelum jam masuk shift, jadi berstatus hadir
        $siti = $this->makePegawai('2026-11-17', ['name' => 'Siti Aminah']);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertViewHas('counts', fn ($c) => $c['hadir'] === 0 && $c['belum'] === 1);

        Sanctum::actingAs($siti);
        $this->postJson('/api/attendance/check-in', [
            'latitude' => self::DEKAT_LAT, 'longitude' => self::PUSKESMAS_LNG, 'accuracy' => 10, 'is_mock' => false,
        ])->assertCreated();

        $this->app['auth']->shouldUse('web');
        $this->actingAs($admin)->get('/admin')
            ->assertViewHas('counts', fn ($c) => $c['hadir'] === 1 && $c['belum'] === 0)
            ->assertSee('Siti Aminah');
    }
}
