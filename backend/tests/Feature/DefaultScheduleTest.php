<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Jadwal tetap pegawai (non-shift / petugas lapangan) dan isi jadwal sekaligus untuk pegawai shift. */
class DefaultScheduleTest extends TestCase
{
    use DatabaseTransactions;

    private User $budi;

    protected function setUp(): void
    {
        parent::setUp();
        // Selasa, 17 November 2026.
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(7, 25));
        $pagi = $this->makeShift();
        $this->budi = $this->makePegawai(null, ['name' => 'Budi Santoso', 'work_type' => 'non_shift', 'default_shift_id' => $pagi->id, 'created_at' => '2026-10-01 08:00:00']);
    }

    private function gps(): array
    {
        return ['latitude' => self::DEKAT_LAT, 'longitude' => self::PUSKESMAS_LNG, 'accuracy' => 10, 'is_mock' => false];
    }

    public function test_tanpa_roster_pegawai_non_shift_memakai_shift_tetap(): void
    {
        Sanctum::actingAs($this->budi);

        $this->getJson('/api/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.schedule.shift', 'Pagi')
            ->assertJsonPath('data.schedule.is_day_off', false);

        // Minggu otomatis libur.
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(8, 0));
        $this->getJson('/api/attendance/today')->assertJsonPath('data.schedule.is_day_off', true);
    }

    public function test_jadwal_di_roster_diutamakan_dari_shift_tetap(): void
    {
        $siang = $this->makeShift(['name' => 'Siang', 'start_time' => '14:00:00', 'end_time' => '21:00:00']);
        Schedule::create(['user_id' => $this->budi->id, 'shift_id' => $siang->id, 'date' => '2026-11-17']);
        Sanctum::actingAs($this->budi);

        $this->getJson('/api/attendance/today')->assertJsonPath('data.schedule.shift', 'Siang');
    }

    public function test_terlambat_dihitung_dari_shift_tetap(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        Sanctum::actingAs($this->budi);

        $this->postJson('/api/attendance/check-in', $this->gps())
            ->assertCreated()
            ->assertJsonPath('data.status', 'terlambat')
            ->assertJsonPath('data.late_minutes', 30);
    }

    public function test_jadwal_bulanan_di_aplikasi_berisi_shift_tetap(): void
    {
        Sanctum::actingAs($this->budi);

        $data = collect($this->getJson('/api/schedules?month=2026-11')->assertOk()->json('data'));

        $this->assertCount(30, $data);
        $this->assertTrue($data->every(fn ($s) => $s['is_default'] === true));
        $sundays = $data->filter(fn ($s) => $s['is_day_off'])->pluck('date')->all();
        $this->assertSame(['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29'], array_values($sundays));
        $this->assertSame('Pagi', $data->firstWhere('date', '2026-11-17')['shift']['name']);
    }

    public function test_pegawai_shift_tanpa_roster_tidak_punya_jadwal(): void
    {
        $siti = $this->makePegawai(null, ['work_type' => 'shift']);
        Sanctum::actingAs($siti);

        $this->getJson('/api/attendance/today')->assertJsonPath('data.schedule', null);
    }

    public function test_rekap_menghitung_alpa_dari_shift_tetap(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));
        Attendance::create(['user_id' => $this->budi->id, 'date' => '2026-11-16', 'status' => 'hadir']);
        $this->actingAs($this->makeAdmin());

        $row = $this->get('/admin/rekap?month=2026-11')->assertOk()->viewData('rows')->firstWhere('name', 'Budi Santoso');

        // 1-19 November = 19 hari, dikurangi 3 hari Minggu (1, 8, 15) dan 1 hari hadir.
        $this->assertSame(1, $row->hadir);
        $this->assertSame(15, $row->alpa);
    }

    public function test_hari_minggu_tidak_dihitung_belum_absen_di_dashboard(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(9, 0));
        $this->actingAs($this->makeAdmin());

        $this->get('/admin')->assertOk()->assertViewHas('notYet', fn ($users) => ! $users->contains('id', $this->budi->id));
    }

    public function test_isi_jadwal_sekaligus_satu_tahun(): void
    {
        $siti = $this->makePegawai(null, ['work_type' => 'shift']);
        $malam = $this->makeShift(['name' => 'Malam', 'start_time' => '21:00:00', 'end_time' => '07:00:00']);
        Schedule::create(['user_id' => $siti->id, 'shift_id' => $malam->id, 'date' => '2026-12-01']);
        $pagi = $this->budi->default_shift_id;
        $this->actingAs($this->makeAdmin());

        $form = [
            'user_ids' => [$siti->id], 'from' => '2026-12', 'to' => '2027-11', 'shift_id' => $pagi,
            'weekdays' => [1, 2, 3, 4, 5, 6], 'other_days_off' => 1,
        ];
        $this->post('/admin/jadwal/isi', $form)->assertRedirect('/admin/jadwal')->assertSessionHas('success');

        // Desember 2026 s.d. November 2027 = 365 hari; 1 Desember sudah ada dan tidak ditimpa.
        $rows = Schedule::where('user_id', $siti->id)->get()->keyBy(fn ($s) => $s->date->toDateString());
        $this->assertCount(365, $rows);
        $this->assertSame($malam->id, $rows['2026-12-01']->shift_id);
        $this->assertSame($pagi, $rows['2026-12-02']->shift_id);
        $this->assertTrue($rows['2026-12-06']->is_day_off); // Minggu
        $this->assertSame($pagi, $rows['2027-11-30']->shift_id);

        // Dengan "timpa", jadwal 1 Desember ikut diganti.
        $this->post('/admin/jadwal/isi', $form + ['overwrite' => 1]);
        $this->assertSame($pagi, Schedule::where('user_id', $siti->id)->whereDate('date', '2026-12-01')->value('shift_id'));
    }

    public function test_isi_jadwal_sekaligus_dibatasi_12_bulan(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->post('/admin/jadwal/isi', [
            'user_ids' => [$this->budi->id], 'from' => '2026-12', 'to' => '2027-12',
            'shift_id' => $this->budi->default_shift_id, 'weekdays' => [1],
        ])->assertSessionHasErrors(['to' => 'Rentang paling lama 12 bulan.']);

        $this->post('/admin/jadwal/isi', ['user_ids' => [], 'from' => '2026-12', 'to' => '2026-11', 'weekdays' => []])
            ->assertSessionHasErrors(['user_ids', 'to', 'weekdays', 'shift_id']);
        $this->assertSame(0, Schedule::where('user_id', $this->budi->id)->count());
    }

    public function test_roster_menampilkan_jadwal_otomatis(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->get('/admin/jadwal?minggu=2026-11-16')
            ->assertOk()
            ->assertSee('badge green auto', false)
            ->assertSee('Non-shift');

        $this->get("/admin/jadwal?user_id={$this->budi->id}&month=2026-11")
            ->assertSee('- ikut shift tetap (Pagi, Minggu libur) -');
    }
}
