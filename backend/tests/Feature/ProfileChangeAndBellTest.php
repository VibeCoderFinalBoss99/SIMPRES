<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Ajukan perubahan informasi (aplikasi), titik merah lonceng Admin Web, data jenis kerja di login. */
class ProfileChangeAndBellTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pegawai_mengajukan_perubahan_data_dan_admin_melihatnya(): void
    {
        Mail::fake();
        User::where('role', 'admin')->update(['is_active' => false]);
        $admin = $this->makeAdmin();
        $budi = $this->makePegawai(null, ['name' => 'Budi Santoso']);
        Sanctum::actingAs($budi);

        $this->postJson('/api/profile-change-request', ['message' => 'abc'])->assertStatus(422);
        $this->postJson('/api/profile-change-request', ['message' => 'Nomor HP baru 081234567890'])
            ->assertCreated()->assertJsonPath('message', 'Permintaan perubahan data sudah dikirim ke admin.');

        $request = UserNotification::where('user_id', $admin->id)->where('type', 'perubahan_data')->firstOrFail();
        $this->assertSame('Permintaan perubahan data: Budi Santoso', $request->title);

        $this->actingAs($admin);
        $this->get('/admin')->assertOk()->assertSee('Permintaan perubahan data (1)')->assertSee('Nomor HP baru 081234567890')
            ->assertSee('class="dot"', false);

        $this->post("/admin/permintaan/{$request->id}/selesai")->assertRedirect('/admin');
        $this->assertNotNull($request->fresh()->read_at);
        $this->get('/admin')->assertDontSee('Permintaan perubahan data (');
    }

    public function test_titik_lonceng_hilang_setelah_pengajuan_dilihat(): void
    {
        $admin = $this->makeAdmin();
        $budi = $this->makePegawai();
        LeaveRequest::create(['user_id' => $budi->id, 'type' => 'cuti', 'start_date' => '2026-12-01',
            'end_date' => '2026-12-02', 'reason' => 'Cuti', 'status' => 'menunggu']);
        $this->actingAs($admin);

        $this->get('/admin')->assertSee('class="dot"', false);
        $this->get('/admin/pengajuan')->assertOk();
        $this->travel(1)->seconds();
        $this->get('/admin')->assertDontSee('class="dot"', false);
    }

    public function test_login_mengirim_jenis_kerja_dan_shift_tetap(): void
    {
        $pagi = $this->makeShift();
        $user = $this->makePegawai(null, ['email' => 'rina.test@simpres.test', 'password' => 'rahasia123',
            'work_type' => 'petugas_lapangan', 'default_shift_id' => $pagi->id]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertOk()
            ->assertJsonPath('user.work_type', 'petugas_lapangan')
            ->assertJsonPath('user.default_shift', 'Pagi');
    }
}
