<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\FieldDuty;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $siti;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(15, 0));
        LeaveRequest::query()->delete();
        AttendanceCorrection::query()->delete();
        FieldDuty::query()->delete();
        $this->admin = $this->makeAdmin(['name' => 'Admin Puskesmas']);
        $this->siti = $this->makePegawai('2026-11-17', ['name' => 'Siti Aminah']);
    }

    private function asAdmin(): void
    {
        $this->app['auth']->shouldUse('web');
        $this->actingAs($this->admin);
    }

    /** Siti mengajukan izin sakit lewat API (seperti dari Postman/HP). */
    private function sitiSubmitsSickLeave(): int
    {
        Sanctum::actingAs($this->siti);

        return $this->post('/api/leaves', [
            'type' => 'sakit', 'start_date' => '2026-11-18', 'end_date' => '2026-11-18', 'reason' => 'Demam tinggi',
            'lampiran' => UploadedFile::fake()->image('surat.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    }

    public function test_pengajuan_dari_api_muncul_di_daftar_dengan_status_menunggu(): void
    {
        $this->sitiSubmitsSickLeave();
        $this->asAdmin();

        $this->get('/admin/pengajuan')
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertSee('Sakit')
            ->assertSee('Menunggu Konfirmasi');

        $this->get('/admin/pengajuan?jenis=izin&status=diterima')->assertOk()->assertDontSee('Siti Aminah');
    }

    public function test_tab_koreksi_dan_tugas_lapangan(): void
    {
        AttendanceCorrection::create(['user_id' => $this->siti->id, 'date' => '2026-11-17', 'corrected_check_out' => '14:00:00', 'reason' => 'Lupa absen pulang', 'status' => 'menunggu']);
        FieldDuty::create(['user_id' => $this->siti->id, 'date' => '2026-11-17', 'location_name' => 'Posyandu Melati', 'description' => 'Imunisasi', 'photo_path' => 'tugas-lapangan/a.jpg', 'status' => 'ditolak']);
        $this->asAdmin();

        $this->get('/admin/pengajuan?jenis=koreksi')->assertOk()->assertSee('Lupa absen pulang')->assertSee('pulang 14:00');
        $this->get('/admin/pengajuan?jenis=tugas')->assertOk()->assertDontSee('Posyandu Melati');
        $this->get('/admin/pengajuan?jenis=tugas&status=semua')->assertOk()->assertSee('Posyandu Melati');
    }

    public function test_lampiran_bisa_dibuka_dari_halaman_detail(): void
    {
        $id = $this->sitiSubmitsSickLeave();
        $this->asAdmin();

        $this->get("/admin/pengajuan/izin/{$id}")
            ->assertOk()
            ->assertSee('Demam tinggi')
            ->assertSee("/admin/pengajuan/izin/{$id}/file", false)
            ->assertSee('Terima')
            ->assertSee('Tolak');

        $this->get("/admin/pengajuan/izin/{$id}/file")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_file_tidak_bisa_dibuka_tanpa_login_admin(): void
    {
        $id = $this->sitiSubmitsSickLeave();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $this->get("/admin/pengajuan/izin/{$id}/file")->assertRedirect('/admin/login');
    }

    public function test_terima_izin_mengubah_status_dan_notifikasi_masuk_ke_pegawai(): void
    {
        $id = $this->sitiSubmitsSickLeave();
        $this->asAdmin();

        $this->post("/admin/pengajuan/izin/{$id}/keputusan", ['status' => 'diterima', 'admin_note' => 'Semoga lekas sembuh'])
            ->assertRedirect("/admin/pengajuan/izin/{$id}")
            ->assertSessionHas('success', 'Izin dan cuti diterima.');

        $leave = LeaveRequest::find($id);
        $this->assertSame('diterima', $leave->status);
        $this->assertSame($this->admin->id, $leave->reviewed_by);
        $this->assertNotNull($leave->reviewed_at);

        Sanctum::actingAs($this->siti);
        $this->getJson('/api/notifications')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.title', 'Izin diterima');
    }

    public function test_tolak_dengan_catatan_tampil_di_api_pegawai(): void
    {
        $id = $this->sitiSubmitsSickLeave();
        $this->asAdmin();

        $this->post("/admin/pengajuan/izin/{$id}/keputusan", ['status' => 'ditolak', 'admin_note' => 'Surat dokter tidak terbaca']);

        Sanctum::actingAs($this->siti);
        $this->getJson("/api/leaves/{$id}")
            ->assertJsonPath('data.status', 'ditolak')
            ->assertJsonPath('data.admin_note', 'Surat dokter tidak terbaca');
    }

    public function test_pengajuan_yang_sudah_diputuskan_tidak_menampilkan_tombol_lagi(): void
    {
        $id = $this->sitiSubmitsSickLeave();
        $this->asAdmin();
        $this->post("/admin/pengajuan/izin/{$id}/keputusan", ['status' => 'diterima']);

        $this->get("/admin/pengajuan/izin/{$id}")
            ->assertOk()
            ->assertSee('Admin Puskesmas')
            ->assertDontSee('name="status" value="diterima"', false)
            ->assertDontSee('name="status" value="ditolak"', false);

        // Kirim ulang keputusan (misalnya dari tab lama) tetap ditolak.
        $this->post("/admin/pengajuan/izin/{$id}/keputusan", ['status' => 'ditolak'])
            ->assertSessionHasErrors(['status' => 'Pengajuan ini sudah diputuskan sebelumnya.']);
        $this->assertSame('diterima', LeaveRequest::find($id)->status);
    }

    public function test_terima_koreksi_dari_admin_web_mengubah_jam_absensi(): void
    {
        $attendance = Attendance::create(['user_id' => $this->siti->id, 'date' => '2026-11-17', 'check_in_at' => '2026-11-17 07:25:00', 'status' => 'hadir']);
        $correction = AttendanceCorrection::create([
            'user_id' => $this->siti->id, 'attendance_id' => $attendance->id, 'date' => '2026-11-17',
            'corrected_check_out' => '14:00:00', 'reason' => 'Lupa', 'status' => 'menunggu',
        ]);
        $this->asAdmin();

        $this->get("/admin/pengajuan/koreksi/{$correction->id}")->assertOk()->assertSee('Masuk 07:25');
        $this->post("/admin/pengajuan/koreksi/{$correction->id}/keputusan", ['status' => 'diterima'])->assertSessionHasNoErrors();

        $this->assertSame('14:00', $attendance->fresh()->check_out_at->format('H:i'));
    }

    public function test_foto_tugas_lapangan_tampil_di_detail(): void
    {
        Storage::disk('local')->put('tugas-lapangan/foto.jpg', UploadedFile::fake()->image('foto.jpg')->get());
        $duty = FieldDuty::create(['user_id' => $this->siti->id, 'date' => '2026-11-17', 'location_name' => 'Posyandu Melati', 'description' => 'x', 'photo_path' => 'tugas-lapangan/foto.jpg', 'status' => 'menunggu']);
        $this->asAdmin();

        $this->get("/admin/pengajuan/tugas/{$duty->id}")->assertOk()->assertSee('Foto kegiatan');
        $this->get("/admin/pengajuan/tugas/{$duty->id}/file")->assertOk();
    }

    public function test_jenis_atau_id_tidak_dikenal_404(): void
    {
        $this->asAdmin();

        $this->get('/admin/pengajuan/lain/1')->assertNotFound();
        $this->get('/admin/pengajuan/izin/999999')->assertNotFound();
    }
}
