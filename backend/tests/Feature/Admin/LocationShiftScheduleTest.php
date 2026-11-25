<?php

namespace Tests\Feature\Admin;

use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationShiftScheduleTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(7, 20));
        WorkLocation::query()->delete(); // supaya WorkLocation::first() adalah lokasi milik test ini
        $this->admin = $this->makeAdmin();
        $this->actingAs($this->admin);
    }

    public function test_halaman_lokasi_dan_ubah_radius_berpengaruh_ke_check_in(): void
    {
        $siti = $this->makePegawai('2026-11-17');

        $this->get('/admin/lokasi')->assertOk()->assertSee('value="150"', false);

        $this->put('/admin/lokasi', [
            'name' => 'UPTD Puskesmas Simbang', 'address' => 'Simbang',
            'latitude' => self::PUSKESMAS_LAT, 'longitude' => self::PUSKESMAS_LNG, 'radius_m' => 100,
        ])->assertRedirect('/admin/lokasi')->assertSessionHas('success');

        $this->assertSame(100, WorkLocation::first()->radius_m);

        // Sekitar 120 m dari titik Puskesmas: ditolak karena radius sekarang 100 m.
        Sanctum::actingAs($siti);
        $this->postJson('/api/attendance/check-in', [
            'latitude' => -6.9010800, 'longitude' => self::PUSKESMAS_LNG, 'accuracy' => 10, 'is_mock' => false,
        ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'maksimal 100 m'));
    }

    public function test_validasi_lokasi(): void
    {
        $this->makeLocation();

        $this->put('/admin/lokasi', ['name' => '', 'latitude' => 'abc', 'longitude' => 200, 'radius_m' => 0])
            ->assertSessionHasErrors(['name', 'latitude', 'longitude', 'radius_m']);
    }

    public function test_crud_shift(): void
    {
        $this->post('/admin/shift', ['name' => 'Malam', 'start_time' => '21:00', 'end_time' => '07:00', 'late_tolerance_min' => 15])
            ->assertRedirect('/admin/shift');
        $shift = Shift::where('name', 'Malam')->first();
        $this->assertSame('21:00:00', $shift->start_time);

        $this->get('/admin/shift')->assertOk()->assertSee('Malam')->assertSee('21:00');

        $this->put("/admin/shift/{$shift->id}", ['name' => 'Malam', 'start_time' => '21:30', 'end_time' => '07:00', 'late_tolerance_min' => 5])
            ->assertRedirect('/admin/shift');
        $this->assertSame('21:30:00', $shift->fresh()->start_time);

        $this->post('/admin/shift', ['name' => '', 'start_time' => '25:00', 'end_time' => '', 'late_tolerance_min' => -1])
            ->assertSessionHasErrors(['name', 'start_time', 'end_time', 'late_tolerance_min']);

        $this->delete("/admin/shift/{$shift->id}")->assertRedirect('/admin/shift');
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_shift_baru_dipakai_di_jadwal_tampil_di_api_schedules(): void
    {
        $siti = $this->makePegawai();
        $this->post('/admin/shift', ['name' => 'Sore', 'start_time' => '16:00', 'end_time' => '22:00', 'late_tolerance_min' => 10]);
        $shift = Shift::where('name', 'Sore')->first();

        $this->post('/admin/jadwal', ['user_id' => $siti->id, 'date' => '2026-11-20', 'shift_id' => $shift->id])
            ->assertRedirect("/admin/jadwal?user_id={$siti->id}&month=2026-11")
            ->assertSessionHas('success');

        Sanctum::actingAs($siti);
        $this->getJson('/api/schedules?month=2026-11')
            ->assertOk()
            ->assertJsonPath('data.0.date', '2026-11-20')
            ->assertJsonPath('data.0.shift.name', 'Sore')
            ->assertJsonPath('data.0.shift.start_time', '16:00:00');
    }

    public function test_jadwal_ganda_tidak_bisa_disimpan(): void
    {
        $siti = $this->makePegawai('2026-11-17');
        $shift = $this->makeShift();

        $this->post('/admin/jadwal', ['user_id' => $siti->id, 'date' => '2026-11-17', 'shift_id' => $shift->id])
            ->assertSessionHasErrors(['date' => 'Pegawai ini sudah punya jadwal di tanggal tersebut. Ubah lewat tabel jadwal bulanan.']);

        $this->assertSame(1, Schedule::where('user_id', $siti->id)->whereDate('date', '2026-11-17')->count());

        $this->post('/admin/jadwal', ['user_id' => $siti->id, 'date' => '2026-11-18'])
            ->assertSessionHasErrors(['shift_id' => 'Pilih shift, atau centang Libur.']);

        $this->post('/admin/jadwal', ['user_id' => $siti->id, 'date' => '2026-11-18', 'is_day_off' => 1])->assertSessionHasNoErrors();
        $this->assertTrue(Schedule::where('user_id', $siti->id)->whereDate('date', '2026-11-18')->first()->is_day_off);
    }

    public function test_atur_jadwal_satu_bulan_sekaligus(): void
    {
        $siti = $this->makePegawai('2026-11-17');
        $shift = $this->makeShift();

        $this->get("/admin/jadwal?user_id={$siti->id}&month=2026-11")
            ->assertOk()
            ->assertSee('Jadwal '.$siti->name)
            ->assertSee('name="days[2026-11-30]"', false);

        $this->put('/admin/jadwal/bulan', [
            'user_id' => $siti->id,
            'month' => '2026-11',
            'days' => [
                '2026-11-17' => '',              // dihapus
                '2026-11-18' => (string) $shift->id,
                '2026-11-19' => 'libur',
            ],
        ])->assertRedirect("/admin/jadwal?user_id={$siti->id}&month=2026-11#bulanan");

        $schedules = Schedule::where('user_id', $siti->id)->orderBy('date')->get();
        $this->assertSame(['2026-11-18', '2026-11-19'], $schedules->map(fn ($s) => $s->date->toDateString())->all());
        $this->assertSame($shift->id, $schedules[0]->shift_id);
        $this->assertTrue($schedules[1]->is_day_off);

        $this->put('/admin/jadwal/bulan', ['user_id' => $siti->id, 'month' => '2026-11', 'days' => ['2026-11-18' => '999999']])
            ->assertSessionHasErrors('days.2026-11-18');
    }

    public function test_halaman_jadwal_tanpa_pegawai_terpilih(): void
    {
        $this->get('/admin/jadwal')->assertOk()->assertSee('Pilih pegawai dan bulan');
    }
}
