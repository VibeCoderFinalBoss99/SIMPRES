<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['email' => 'admin@simpres.test', 'password' => 'simpres123', 'name' => 'Admin Puskesmas']);
    }

    public function test_admin_tanpa_login_diarahkan_ke_halaman_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Masuk')->assertSee('admin.css');
    }

    public function test_login_admin_berhasil_dan_sidebar_tampil(): void
    {
        $this->post('/admin/login', ['email' => 'admin@simpres.test', 'password' => 'simpres123'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($this->admin);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Pegawai')
            ->assertSee('Pengajuan')
            ->assertSee('Jadwal')
            ->assertSee('Lokasi')
            ->assertSee('Rekap')
            ->assertSee('Admin Puskesmas');
    }

    public function test_login_pegawai_ditolak_dengan_pesan(): void
    {
        User::factory()->create(['email' => 'siti@simpres.test', 'password' => 'simpres123']);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'siti@simpres.test', 'password' => 'simpres123'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email' => 'Akun ini bukan admin. Admin Web hanya untuk admin Puskesmas.']);

        $this->assertGuest();
    }

    public function test_password_salah_menampilkan_pesan_dan_email_tetap_terisi(): void
    {
        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'admin@simpres.test', 'password' => 'salah'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email' => 'Email atau password salah.'])
            ->assertSessionHasInput('email', 'admin@simpres.test');

        $this->assertGuest();

        // Seperti di browser: ikuti redirect, pesan tampil dan email tidak hilang.
        $this->from('/admin/login')
            ->followingRedirects()
            ->post('/admin/login', ['email' => 'admin@simpres.test', 'password' => 'salah'])
            ->assertSee('Email atau password salah.')
            ->assertSee('value="admin@simpres.test"', false);
    }

    public function test_admin_nonaktif_tidak_bisa_login(): void
    {
        $this->admin->update(['is_active' => false]);

        $this->post('/admin/login', ['email' => 'admin@simpres.test', 'password' => 'simpres123'])
            ->assertSessionHasErrors(['email' => 'Akun admin ini nonaktif.']);
        $this->assertGuest();
    }

    public function test_logout_kembali_ke_login_dan_halaman_admin_tidak_disimpan_cache(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();

        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_pegawai_yang_sudah_punya_session_tetap_tidak_bisa_masuk(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email' => 'Hanya admin yang boleh masuk ke Admin Web.']);
    }

    public function test_admin_yang_sudah_login_membuka_halaman_login_diarahkan_ke_dashboard(): void
    {
        $this->actingAs($this->admin)->get('/admin/login')->assertRedirect('/admin');
    }
}
