<?php

namespace Tests\Feature\Admin;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Halaman Audit Log dan Konfigurasi (desain Figma A7 dan A8). */
class AuditConfigTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Admin Uji']);
    }

    public function test_audit_log_menampilkan_keputusan_admin(): void
    {
        $admin = $this->admin();
        $pegawai = User::factory()->create(['name' => 'Rina Audit']);
        LeaveRequest::create([
            'user_id' => $pegawai->id, 'type' => 'cuti', 'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(), 'reason' => 'Acara keluarga', 'status' => 'diterima',
            'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'admin_note' => 'Silakan',
        ]);

        $this->actingAs($admin)->get('/admin/audit')
            ->assertOk()
            ->assertSee('Menyetujui izin')
            ->assertSee('Rina Audit')
            ->assertSee('Silakan');

        $this->actingAs($admin)->get('/admin/audit/csv')->assertOk();
    }

    public function test_tugas_lapangan_diterima_dihitung_hadir_di_rekap(): void
    {
        $admin = $this->admin();
        $day = now()->subDay()->toDateString();
        $pegawai = $this->makePegawai($day, ['name' => 'Dina Lapangan']);
        \App\Models\FieldDuty::create([
            'user_id' => $pegawai->id, 'date' => $day, 'location_name' => 'Posyandu', 'description' => 'Imunisasi',
            'photo_path' => 'tugas-lapangan/x.jpg', 'status' => 'diterima', 'reviewed_by' => $admin->id, 'reviewed_at' => now(),
        ]);

        $row = $this->actingAs($admin)->get('/admin/rekap?month='.substr($day, 0, 7))
            ->assertOk()->viewData('rows')->firstWhere('name', 'Dina Lapangan');

        $this->assertSame(1, $row->hadir);
        $this->assertSame(0, $row->alpa);
    }

    public function test_konfigurasi_bisa_dibuka_dan_pegawai_ditolak(): void
    {
        $this->actingAs($this->admin())->get('/admin/konfigurasi')
            ->assertOk()
            ->assertSee('Batas akurasi GPS (meter)')
            ->assertSee('value="100"', false);

        $this->actingAs(User::factory()->create(['role' => 'pegawai']))->get('/admin/konfigurasi')->assertRedirect('/admin/login');
    }
}
