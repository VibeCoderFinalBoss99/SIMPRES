<?php

namespace Tests\Feature\Admin;

use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Banyak lokasi kerja (tempat dinas petugas lapangan) dan pegawai baru yang tidak langsung alpa. */
class WorkLocationsAndNewStaffTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_menambah_tempat_dinas_dan_petugas_absen_di_sana(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(7, 20));
        $this->actingAs($this->makeAdmin());

        $this->get('/admin/lokasi?baru=1')->assertOk()->assertSee('Tambah lokasi baru');
        $this->post('/admin/lokasi', [
            'name' => 'Pustu Bontomarannu', 'address' => 'Desa Bontomarannu',
            'latitude' => -5.0100000, 'longitude' => 119.6800000, 'radius_m' => 120,
        ])->assertRedirect()->assertSessionHas('success');
        $pustu = WorkLocation::where('name', 'Pustu Bontomarannu')->firstOrFail();

        $rina = $this->makePegawai('2026-11-17', ['name' => 'Rina Lapangan', 'work_type' => 'petugas_lapangan', 'work_location_id' => $pustu->id]);
        $this->get("/admin/lokasi?id={$pustu->id}")->assertOk()->assertSee('Rina Lapangan')->assertSee('Petugas lapangan');

        // Mengubah lokasi kedua tidak mengubah lokasi Puskesmas.
        $puskesmas = $this->makeLocation();
        $this->put('/admin/lokasi', ['id' => $pustu->id, 'name' => 'Pustu Bontomarannu', 'latitude' => -5.0100000,
            'longitude' => 119.6800000, 'radius_m' => 200])->assertRedirect("/admin/lokasi?id={$pustu->id}");
        $this->assertSame(200, $pustu->fresh()->radius_m);
        $this->assertNotSame(200, $puskesmas->fresh()->radius_m);

        // Petugas check-in di tempat dinas (bukan di Puskesmas) dan diterima.
        Sanctum::actingAs($rina);
        $this->getJson('/api/me')->assertJsonPath('user.work_location.name', 'Pustu Bontomarannu');
        $this->postJson('/api/attendance/check-in', ['latitude' => -5.0100100, 'longitude' => 119.6800100, 'accuracy' => 10, 'is_mock' => false])
            ->assertCreated();
    }

    public function test_pegawai_baru_tidak_alpa_sebelum_akunnya_dibuat(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));
        $this->actingAs($this->makeAdmin());
        $pagi = $this->makeShift();
        $lama = $this->makePegawai(null, ['name' => 'Pegawai Lama', 'default_shift_id' => $pagi->id, 'created_at' => '2026-10-01 08:00:00']);
        $baru = $this->makePegawai(null, ['name' => 'Pegawai Baru', 'default_shift_id' => $pagi->id, 'created_at' => '2026-11-18 09:00:00']);

        $rows = $this->get('/admin/rekap?month=2026-11')->assertOk()->viewData('rows')->keyBy('name');
        // 1-19 November tanpa absen: pegawai lama alpa di semua hari kerja (Senin-Sabtu), pegawai baru hanya 18-19 November.
        $this->assertSame(16, $rows['Pegawai Lama']->alpa);
        $this->assertSame(2, $rows['Pegawai Baru']->alpa);

        // Jadwal di aplikasi pegawai baru juga dimulai dari tanggal akun dibuat, jadi kalender tidak menandai alpa sebelumnya.
        Sanctum::actingAs($baru);
        $dates = collect($this->getJson('/api/schedules?month=2026-11')->assertOk()->json('data'))->pluck('date');
        $this->assertSame('2026-11-18', $dates->first());
        $this->assertNotNull($lama);
    }
}
