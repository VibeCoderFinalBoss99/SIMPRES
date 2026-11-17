<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    private function pegawai(array $data = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'siti@simpres.test',
            'password' => 'simpres123',
        ], $data));
    }

    public function test_login_berhasil_mengembalikan_token_dan_data_pegawai(): void
    {
        $user = $this->pegawai();

        $response = $this->postJson('/api/login', [
            'email' => 'siti@simpres.test',
            'password' => 'simpres123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.nip', $user->nip)
            ->assertJsonStructure(['token', 'user' => ['id', 'nip', 'name', 'email', 'position', 'role']])
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $this->assertSame(1, $user->tokens()->count());

        // Token yang didapat bisa langsung dipakai.
        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'siti@simpres.test');
    }

    public function test_password_salah_ditolak(): void
    {
        $user = $this->pegawai();

        $this->postJson('/api/login', [
            'email' => 'siti@simpres.test',
            'password' => 'salah123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Email atau password salah.')
            ->assertJsonPath('errors.email.0', 'Email atau password salah.')
            ->assertJsonMissingPath('token');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_email_tidak_terdaftar_mendapat_pesan_yang_sama(): void
    {
        $this->postJson('/api/login', [
            'email' => 'tidakada@simpres.test',
            'password' => 'simpres123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Email atau password salah.');
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $user = $this->pegawai(['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => 'siti@simpres.test',
            'password' => 'simpres123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Akun Anda nonaktif. Hubungi admin Puskesmas.')
            ->assertJsonMissingPath('token');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_input_kosong_mendapat_pesan_bahasa_indonesia(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Email wajib diisi. (dan 1 kesalahan lainnya)')
            ->assertJsonPath('errors.email.0', 'Email wajib diisi.')
            ->assertJsonPath('errors.password.0', 'Password wajib diisi.');
    }

    public function test_akses_me_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Anda belum login atau token tidak valid. Silakan login kembali.');
    }

    public function test_akses_me_tanpa_header_accept_json_tetap_dapat_json(): void
    {
        $this->get('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Anda belum login atau token tidak valid. Silakan login kembali.');
    }

    public function test_logout_menghapus_token(): void
    {
        $user = $this->pegawai();
        $token = $user->createToken('android')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout berhasil.');

        $this->assertSame(0, $user->tokens()->count());
    }
}
