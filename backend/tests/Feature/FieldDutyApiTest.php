<?php

namespace Tests\Feature;

use App\Models\FieldDuty;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FieldDutyApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $siti;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(10, 0));
        $this->siti = $this->makePegawai();
        Sanctum::actingAs($this->siti);
    }

    private function send(array $data = [])
    {
        return $this->post('/api/field-duties', array_merge([
            'date' => '2026-11-17',
            'location_name' => 'Posyandu Melati',
            'description' => 'Imunisasi balita',
            'latitude' => -6.91,
            'longitude' => 109.61,
            'foto' => UploadedFile::fake()->image('kegiatan.jpg'),
        ], $data), ['Accept' => 'application/json']);
    }

    public function test_ajukan_tugas_lapangan_dengan_foto_tersimpan_di_storage_private(): void
    {
        $response = $this->send()
            ->assertCreated()
            ->assertJsonPath('data.status', 'menunggu')
            ->assertJsonPath('data.location_name', 'Posyandu Melati')
            ->assertJsonPath('data.latitude', -6.91);

        $duty = FieldDuty::find($response->json('data.id'));
        $this->assertStringStartsWith('tugas-lapangan/', $duty->photo_path);
        Storage::disk('local')->assertExists($duty->photo_path);
    }

    public function test_tanpa_foto_ditolak(): void
    {
        $this->send(['foto' => null])
            ->assertStatus(422)
            ->assertJsonPath('errors.foto.0', 'Foto kegiatan wajib dilampirkan (jpg, maksimal 2 MB).');
    }

    public function test_foto_lebih_dari_2_mb_atau_bukan_jpg_ditolak(): void
    {
        $this->send(['foto' => UploadedFile::fake()->image('besar.jpg')->size(2049)])
            ->assertStatus(422)->assertJsonPath('errors.foto.0', 'Foto maksimal 2048 KB.');

        $this->send(['foto' => UploadedFile::fake()->image('kegiatan.png')])
            ->assertStatus(422)->assertJsonPath('errors.foto.0', 'Foto harus berformat jpg.');
    }

    public function test_kolom_wajib_ditolak_jika_kosong(): void
    {
        $this->send(['location_name' => '', 'description' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.location_name.0', 'Nama lokasi wajib diisi.')
            ->assertJsonPath('errors.description.0', 'Keterangan wajib diisi.');
    }

    public function test_foto_bisa_dibuka_pemilik_dan_admin_tapi_tidak_pegawai_lain(): void
    {
        $id = $this->send()->json('data.id');

        $this->get("/api/field-duties/{$id}/photo")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        Sanctum::actingAs($this->makeAdmin());
        $this->get("/api/field-duties/{$id}/photo")->assertOk();

        Sanctum::actingAs($this->makePegawai());
        $this->getJson("/api/field-duties/{$id}/photo")->assertForbidden();
        $this->getJson("/api/field-duties/{$id}")->assertForbidden();
    }

    public function test_admin_menolak_dengan_catatan(): void
    {
        $id = $this->send()->json('data.id');
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/field-duties/{$id}", ['status' => 'ditolak', 'admin_note' => 'Foto tidak jelas'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ditolak')
            ->assertJsonPath('data.admin_note', 'Foto tidak jelas');

        $this->assertDatabaseHas('field_duties', ['id' => $id, 'status' => 'ditolak', 'admin_note' => 'Foto tidak jelas', 'reviewed_by' => $admin->id]);
    }

    public function test_pegawai_tidak_bisa_memutuskan_tugas_lapangan(): void
    {
        $id = $this->send()->json('data.id');

        $this->patchJson("/api/admin/field-duties/{$id}", ['status' => 'diterima'])->assertForbidden();
    }

    public function test_daftar_dan_batal_hanya_jika_menunggu(): void
    {
        $id = $this->send()->json('data.id');
        $path = FieldDuty::find($id)->photo_path;

        $this->getJson('/api/field-duties')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/field-duties/{$id}")->assertOk();
        Storage::disk('local')->assertMissing($path);

        $id = $this->send()->json('data.id');
        FieldDuty::find($id)->update(['status' => 'diterima']);
        $this->deleteJson("/api/field-duties/{$id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Tugas lapangan yang sudah diproses admin tidak bisa dibatalkan.');
    }
}
