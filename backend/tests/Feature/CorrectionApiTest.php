<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CorrectionApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $siti;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(15, 0));
        $this->siti = $this->makePegawai('2026-11-17');
        Sanctum::actingAs($this->siti);
    }

    private function attendanceToday(): Attendance
    {
        return Attendance::create([
            'user_id' => $this->siti->id, 'date' => '2026-11-17',
            'check_in_at' => '2026-11-17 07:35:00', 'status' => 'hadir',
        ]);
    }

    public function test_ajukan_koreksi_jam_pulang_untuk_hari_yang_sudah_check_in(): void
    {
        $attendance = $this->attendanceToday();

        $this->postJson('/api/corrections', ['date' => '2026-11-17', 'corrected_check_out' => '14:00', 'reason' => 'Lupa absen pulang'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'menunggu')
            ->assertJsonPath('data.attendance_id', $attendance->id)
            ->assertJsonPath('data.corrected_check_out', '14:00:00');
    }

    public function test_koreksi_tanpa_jam_sama_sekali_ditolak(): void
    {
        $this->postJson('/api/corrections', ['date' => '2026-11-17', 'reason' => 'Lupa'])
            ->assertStatus(422)
            ->assertJsonPath('errors.corrected_check_in.0', 'Isi jam masuk atau jam pulang yang dikoreksi (minimal salah satu).');
    }

    public function test_koreksi_tanggal_besok_dan_jam_pulang_sebelum_masuk_ditolak(): void
    {
        $this->postJson('/api/corrections', ['date' => '2026-11-18', 'corrected_check_in' => '07:30', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('date');

        $this->postJson('/api/corrections', ['date' => '2026-11-17', 'corrected_check_in' => '14:00', 'corrected_check_out' => '07:30', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonPath('errors.corrected_check_out.0', 'Jam pulang harus setelah jam masuk.');
    }

    public function test_koreksi_dengan_lampiran_opsional(): void
    {
        $id = $this->post('/api/corrections', [
            'date' => '2026-11-17', 'corrected_check_in' => '07:30', 'reason' => 'GPS bermasalah',
            'lampiran' => UploadedFile::fake()->image('bukti.png'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.has_attachment', true)->json('data.id');

        Storage::disk('local')->assertExists(AttendanceCorrection::find($id)->attachment_path);
    }

    public function test_admin_menerima_koreksi_jam_di_attendances_ikut_berubah(): void
    {
        $attendance = $this->attendanceToday();
        $id = $this->postJson('/api/corrections', ['date' => '2026-11-17', 'corrected_check_out' => '14:00', 'reason' => 'Lupa absen pulang'])->json('data.id');

        Sanctum::actingAs($this->makeAdmin());
        $this->patchJson("/api/admin/corrections/{$id}", ['status' => 'diterima'])
            ->assertOk()
            ->assertJsonPath('data.status', 'diterima');

        $this->assertSame('2026-11-17 14:00:00', $attendance->fresh()->check_out_at->format('Y-m-d H:i:s'));
    }

    public function test_koreksi_jam_masuk_menghitung_ulang_status_terlambat(): void
    {
        $attendance = $this->attendanceToday();
        $attendance->update(['check_in_at' => '2026-11-17 08:30:00', 'status' => 'terlambat', 'late_minutes' => 60]);
        $id = $this->postJson('/api/corrections', ['date' => '2026-11-17', 'corrected_check_in' => '07:30', 'reason' => 'Sudah datang, HP mati'])->json('data.id');

        Sanctum::actingAs($this->makeAdmin());
        $this->patchJson("/api/admin/corrections/{$id}", ['status' => 'diterima'])->assertOk();

        $attendance->refresh();
        $this->assertSame('07:30', $attendance->check_in_at->format('H:i'));
        $this->assertSame('hadir', $attendance->status);
        $this->assertSame(0, $attendance->late_minutes);
    }

    public function test_koreksi_diterima_untuk_hari_tanpa_absensi_membuat_absensi_baru(): void
    {
        $id = $this->postJson('/api/corrections', [
            'date' => '2026-11-17', 'corrected_check_in' => '07:50', 'corrected_check_out' => '14:00', 'reason' => 'Lupa absen',
        ])->assertJsonPath('data.attendance_id', null)->json('data.id');

        Sanctum::actingAs($this->makeAdmin());
        $this->patchJson("/api/admin/corrections/{$id}", ['status' => 'diterima'])->assertOk();

        $attendance = Attendance::where('user_id', $this->siti->id)->first();
        $this->assertSame('terlambat', $attendance->status);
        $this->assertSame(20, $attendance->late_minutes);
        $this->assertSame($attendance->id, AttendanceCorrection::find($id)->attendance_id);
    }

    public function test_koreksi_ditolak_tidak_mengubah_absensi(): void
    {
        $attendance = $this->attendanceToday();
        $id = $this->postJson('/api/corrections', ['date' => '2026-11-17', 'corrected_check_out' => '14:00', 'reason' => 'x'])->json('data.id');

        Sanctum::actingAs($this->makeAdmin());
        $this->patchJson("/api/admin/corrections/{$id}", ['status' => 'ditolak', 'admin_note' => 'Tidak ada bukti'])
            ->assertOk()->assertJsonPath('data.admin_note', 'Tidak ada bukti');

        $this->assertNull($attendance->fresh()->check_out_at);
    }

    public function test_daftar_detail_dan_batal_hanya_milik_sendiri(): void
    {
        $id = $this->postJson('/api/corrections', ['date' => '2026-11-17', 'corrected_check_out' => '14:00', 'reason' => 'x'])->json('data.id');

        $this->getJson('/api/corrections')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/corrections/{$id}")->assertOk();

        Sanctum::actingAs($this->makePegawai());
        $this->getJson('/api/corrections')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/corrections/{$id}")->assertForbidden();
        $this->deleteJson("/api/corrections/{$id}")->assertForbidden();

        Sanctum::actingAs($this->siti);
        $this->deleteJson("/api/corrections/{$id}")->assertOk();
        $this->assertDatabaseMissing('attendance_corrections', ['id' => $id]);
    }

    public function test_koreksi_yang_sudah_diputuskan_tidak_bisa_dibatalkan_dan_pegawai_tidak_bisa_memutuskan(): void
    {
        $id = $this->postJson('/api/corrections', ['date' => '2026-11-17', 'corrected_check_out' => '14:00', 'reason' => 'x'])->json('data.id');

        $this->patchJson("/api/admin/corrections/{$id}", ['status' => 'diterima'])->assertForbidden();

        AttendanceCorrection::find($id)->update(['status' => 'ditolak']);
        $this->deleteJson("/api/corrections/{$id}")->assertForbidden();
    }
}
