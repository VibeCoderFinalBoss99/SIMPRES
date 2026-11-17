<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $siti;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(8, 0));
        $this->siti = $this->makePegawai();
        Sanctum::actingAs($this->siti);
    }

    private function cuti(array $data = []): array
    {
        return array_merge([
            'type' => 'cuti',
            'start_date' => '2026-11-18',
            'end_date' => '2026-11-19',
            'reason' => 'Acara keluarga',
        ], $data);
    }

    private function leaveOf(User $user, array $data = []): LeaveRequest
    {
        return LeaveRequest::create(array_merge(['user_id' => $user->id, 'status' => 'menunggu'], $this->cuti(), $data));
    }

    public function test_ajukan_cuti_dua_hari_berstatus_menunggu(): void
    {
        $this->postJson('/api/leaves', $this->cuti())
            ->assertCreated()
            ->assertJsonPath('data.status', 'menunggu')
            ->assertJsonPath('data.type', 'cuti')
            ->assertJsonPath('data.end_date', '2026-11-19');

        $this->assertDatabaseHas('leave_requests', ['user_id' => $this->siti->id, 'type' => 'cuti', 'status' => 'menunggu']);
    }

    public function test_tanggal_selesai_sebelum_tanggal_mulai_ditolak(): void
    {
        $this->postJson('/api/leaves', $this->cuti(['end_date' => '2026-11-17']))
            ->assertStatus(422)
            ->assertJsonPath('errors.end_date.0', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
    }

    public function test_alasan_wajib_diisi(): void
    {
        $this->postJson('/api/leaves', $this->cuti(['reason' => '']))
            ->assertStatus(422)
            ->assertJsonPath('errors.reason.0', 'Alasan wajib diisi.');
    }

    public function test_izin_sakit_tanpa_lampiran_ditolak(): void
    {
        $this->postJson('/api/leaves', $this->cuti(['type' => 'sakit']))
            ->assertStatus(422)
            ->assertJsonPath('errors.lampiran.0', 'Izin sakit wajib melampirkan foto surat keterangan (jpg/png).');
    }

    public function test_izin_sakit_dengan_foto_tersimpan_di_storage_private(): void
    {
        $response = $this->post('/api/leaves', $this->cuti([
            'type' => 'sakit',
            'lampiran' => UploadedFile::fake()->image('surat.jpg'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $leave = LeaveRequest::find($response->json('data.id'));
        $this->assertStringStartsWith('lampiran-izin/', $leave->attachment_path);
        Storage::disk('local')->assertExists($leave->attachment_path);
        $response->assertJsonPath('data.has_attachment', true);
    }

    public function test_lampiran_bukan_gambar_atau_terlalu_besar_ditolak(): void
    {
        $this->post('/api/leaves', $this->cuti(['type' => 'sakit', 'lampiran' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf')]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('lampiran');

        $this->post('/api/leaves', $this->cuti(['type' => 'sakit', 'lampiran' => UploadedFile::fake()->image('besar.jpg')->size(3000)]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.lampiran.0', 'Lampiran maksimal 2048 KB.');
    }

    public function test_izin_keluar_wajib_jam(): void
    {
        $this->postJson('/api/leaves', $this->cuti(['type' => 'izin_keluar', 'start_date' => '2026-11-17', 'end_date' => '2026-11-17']))
            ->assertStatus(422)
            ->assertJsonPath('errors.start_time.0', 'Jam mulai wajib diisi jika jenis adalah izin keluar.')
            ->assertJsonValidationErrors('end_time');

        $this->postJson('/api/leaves', $this->cuti(['type' => 'izin_keluar', 'start_date' => '2026-11-17', 'end_date' => '2026-11-17', 'start_time' => '10:00', 'end_time' => '11:30']))
            ->assertCreated()
            ->assertJsonPath('data.start_time', '10:00:00');
    }

    public function test_daftar_hanya_milik_sendiri_dan_bisa_filter_status(): void
    {
        $this->leaveOf($this->siti);
        $this->leaveOf($this->siti, ['status' => 'diterima']);
        $this->leaveOf($this->makePegawai());

        $this->getJson('/api/leaves')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/leaves?status=diterima')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'diterima');
    }

    public function test_detail_ubah_dan_batalkan_izin_milik_sendiri(): void
    {
        $leave = $this->leaveOf($this->siti);

        $this->getJson("/api/leaves/{$leave->id}")->assertOk()->assertJsonPath('data.reason', 'Acara keluarga');

        $this->putJson("/api/leaves/{$leave->id}", ['reason' => 'Menghadiri pernikahan'])
            ->assertOk()
            ->assertJsonPath('data.reason', 'Menghadiri pernikahan')
            ->assertJsonPath('data.end_date', '2026-11-19');
        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'reason' => 'Menghadiri pernikahan']);

        $this->deleteJson("/api/leaves/{$leave->id}")->assertOk()->assertJsonPath('message', 'Pengajuan izin berhasil dibatalkan.');
        $this->assertDatabaseMissing('leave_requests', ['id' => $leave->id]);
    }

    public function test_pegawai_lain_tidak_bisa_membuka_atau_mengubah(): void
    {
        $leave = $this->leaveOf($this->siti);
        Sanctum::actingAs($this->makePegawai());

        $this->getJson("/api/leaves/{$leave->id}")->assertForbidden()->assertJsonPath('message', 'Anda tidak punya akses ke izin ini.');
        $this->putJson("/api/leaves/{$leave->id}", ['reason' => 'x'])->assertForbidden();
        $this->deleteJson("/api/leaves/{$leave->id}")->assertForbidden();
        $this->getJson("/api/leaves/{$leave->id}/attachment")->assertForbidden();
    }

    public function test_pegawai_tidak_bisa_memakai_endpoint_admin(): void
    {
        $leave = $this->leaveOf($this->siti);

        $this->patchJson("/api/admin/leaves/{$leave->id}", ['status' => 'diterima'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Hanya admin yang boleh mengakses fitur ini.');
    }

    public function test_admin_menerima_izin_mengisi_peninjau_dan_mencatat_hari_izin(): void
    {
        $leave = $this->leaveOf($this->siti);
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/leaves/{$leave->id}", ['status' => 'diterima', 'admin_note' => 'Disetujui'])
            ->assertOk()
            ->assertJsonPath('data.status', 'diterima')
            ->assertJsonPath('data.admin_note', 'Disetujui');

        $leave->refresh();
        $this->assertSame($admin->id, $leave->reviewed_by);
        $this->assertNotNull($leave->reviewed_at);
        $this->assertSame(2, Attendance::where('user_id', $this->siti->id)->where('status', 'izin')->count());

        // Keputusan tidak bisa diulang.
        $this->patchJson("/api/admin/leaves/{$leave->id}", ['status' => 'ditolak'])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Pengajuan ini sudah diputuskan sebelumnya.');
    }

    public function test_admin_status_tidak_valid_ditolak(): void
    {
        $leave = $this->leaveOf($this->siti);
        Sanctum::actingAs($this->makeAdmin());

        $this->patchJson("/api/admin/leaves/{$leave->id}", ['status' => 'menunggu'])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Status harus diterima atau ditolak.');
    }

    public function test_izin_yang_sudah_diputuskan_tidak_bisa_diubah_atau_dibatalkan(): void
    {
        $leave = $this->leaveOf($this->siti, ['status' => 'diterima']);

        $this->putJson("/api/leaves/{$leave->id}", ['reason' => 'Ganti'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Izin yang sudah diproses admin tidak bisa diubah atau dibatalkan.');
        $this->deleteJson("/api/leaves/{$leave->id}")->assertForbidden();
    }

    public function test_lampiran_hanya_bisa_dibuka_pemilik_lewat_endpoint(): void
    {
        $id = $this->post('/api/leaves', $this->cuti(['type' => 'sakit', 'lampiran' => UploadedFile::fake()->image('surat.png')]), ['Accept' => 'application/json'])
            ->json('data.id');

        $this->get("/api/leaves/{$id}/attachment")->assertOk()->assertHeader('Content-Type', 'image/png');

        $path = LeaveRequest::find($id)->attachment_path;
        $this->get('/storage/'.$path)->assertNotFound();
    }

    public function test_hapus_izin_ikut_menghapus_lampiran(): void
    {
        $id = $this->post('/api/leaves', $this->cuti(['type' => 'sakit', 'lampiran' => UploadedFile::fake()->image('surat.jpg')]), ['Accept' => 'application/json'])
            ->json('data.id');
        $path = LeaveRequest::find($id)->attachment_path;

        $this->deleteJson("/api/leaves/{$id}")->assertOk();

        Storage::disk('local')->assertMissing($path);
    }
}
