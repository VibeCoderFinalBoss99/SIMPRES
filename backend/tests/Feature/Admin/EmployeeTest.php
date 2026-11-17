<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin();
        $this->actingAs($this->admin);
    }

    private function data(array $data = []): array
    {
        return array_merge([
            'nip' => '199505052020121001',
            'name' => 'Rina Wati',
            'email' => 'rina@simpres.test',
            'password' => 'rahasia123',
            'phone' => '081234567890',
            'position' => 'Apoteker',
            'unit' => 'Farmasi',
            'role' => 'pegawai',
            'work_location_id' => $this->makeLocation()->id,
        ], $data);
    }

    public function test_daftar_pegawai_dengan_pencarian_nama_atau_nip(): void
    {
        User::factory()->create(['name' => 'Siti Aminah', 'nip' => '198501012010012001']);
        User::factory()->create(['name' => 'Budi Santoso', 'nip' => '199002022015021002']);

        $this->get('/admin/pegawai')->assertOk()->assertSee('Siti Aminah')->assertSee('Budi Santoso');
        $this->get('/admin/pegawai?q=siti')->assertOk()->assertSee('Siti Aminah')->assertDontSee('Budi Santoso');
        $this->get('/admin/pegawai?q=19900202')->assertOk()->assertSee('Budi Santoso')->assertDontSee('Siti Aminah');
    }

    public function test_daftar_pegawai_memakai_pagination(): void
    {
        User::factory()->count(20)->create();

        $this->get('/admin/pegawai')->assertOk()->assertSee('class="pagination"', false)->assertViewHas('users', fn ($users) => $users->count() === 15);
    }

    public function test_tambah_pegawai_lalu_bisa_login_lewat_api_dan_password_berupa_hash(): void
    {
        $this->post('/admin/pegawai', $this->data())->assertRedirect('/admin/pegawai')->assertSessionHas('success');

        $user = User::where('email', 'rina@simpres.test')->first();
        $this->assertStringStartsWith('$2y$', $user->getRawOriginal('password'));
        $this->assertSame('Apoteker', $user->position);

        $this->postJson('/api/login', ['email' => 'rina@simpres.test', 'password' => 'rahasia123'])->assertOk()->assertJsonPath('user.name', 'Rina Wati');
    }

    public function test_nip_atau_email_yang_sudah_ada_ditolak(): void
    {
        User::factory()->create(['nip' => '199505052020121001', 'email' => 'lama@simpres.test']);

        $this->from('/admin/pegawai/create')->post('/admin/pegawai', $this->data())
            ->assertRedirect('/admin/pegawai/create')
            ->assertSessionHasErrors(['nip' => 'NIP sudah dipakai pegawai lain.']);

        $this->post('/admin/pegawai', $this->data(['nip' => '1', 'email' => 'lama@simpres.test']))
            ->assertSessionHasErrors(['email' => 'Email sudah dipakai pegawai lain.']);
    }

    public function test_kolom_wajib_dan_password_awal(): void
    {
        $this->post('/admin/pegawai', $this->data(['name' => '', 'position' => '', 'password' => '']))
            ->assertSessionHasErrors(['name' => 'Nama wajib diisi.', 'position' => 'Jabatan wajib diisi.', 'password' => 'Password awal wajib diisi.']);

        $this->post('/admin/pegawai', $this->data(['password' => 'pendek']))
            ->assertSessionHasErrors(['password' => 'Password minimal 8 karakter.']);
    }

    public function test_ubah_jabatan_tersimpan_tanpa_mengganti_password(): void
    {
        $user = User::factory()->create(['password' => 'simpres123', 'position' => 'Perawat']);

        $this->get("/admin/pegawai/{$user->id}/edit")->assertOk()->assertSee('value="Perawat"', false);

        $this->put("/admin/pegawai/{$user->id}", $this->data([
            'nip' => $user->nip, 'email' => $user->email, 'name' => $user->name, 'position' => 'Kepala Ruang', 'password' => '',
        ]))->assertRedirect('/admin/pegawai');

        $user->refresh();
        $this->assertSame('Kepala Ruang', $user->position);
        $this->assertTrue(password_verify('simpres123', $user->password));
    }

    public function test_nonaktifkan_pegawai_membuat_login_api_ditolak_dan_token_dihapus(): void
    {
        $user = User::factory()->create(['email' => 'siti@simpres.test', 'password' => 'simpres123']);
        $user->createToken('android');

        $this->patch("/admin/pegawai/{$user->id}/status")->assertRedirect()->assertSessionHas('success', "Akun {$user->name} dinonaktifkan.");

        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/login', ['email' => 'siti@simpres.test', 'password' => 'simpres123'])->assertForbidden();

        $this->patch("/admin/pegawai/{$user->id}/status");
        $this->assertTrue($user->fresh()->is_active);
        $this->postJson('/api/login', ['email' => 'siti@simpres.test', 'password' => 'simpres123'])->assertOk();
    }

    public function test_admin_tidak_bisa_menonaktifkan_atau_menurunkan_peran_dirinya_sendiri(): void
    {
        $this->patch("/admin/pegawai/{$this->admin->id}/status")->assertSessionHasErrors('is_active');
        $this->assertTrue($this->admin->fresh()->is_active);

        $this->put("/admin/pegawai/{$this->admin->id}", $this->data([
            'nip' => $this->admin->nip, 'email' => $this->admin->email, 'role' => 'pegawai', 'password' => '',
        ]))->assertSessionHasErrors('role');
        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    public function test_halaman_tambah_pegawai_tampil(): void
    {
        $this->get('/admin/pegawai/create')->assertOk()->assertSee('Password awal');
    }
}
