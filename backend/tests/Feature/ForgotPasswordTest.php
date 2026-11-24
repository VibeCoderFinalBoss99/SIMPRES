<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tautan_dikirim_ke_email_akun_yang_dicari_lewat_nip(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'budi.santoso@gmail.com', 'nip' => '199001012020011001']);
        $other = User::factory()->create();

        $this->postJson('/api/forgot-password', ['login' => '199001012020011001'])
            ->assertOk()
            ->assertJsonPath('email', 'bu**********@gmail.com');

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertNotSentTo($other, ResetPassword::class);
    }

    public function test_email_tidak_terdaftar_ditolak(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['login' => 'tidakada@simpres.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('login');

        Notification::assertNothingSent();
    }

    public function test_isi_email_berbahasa_indonesia_dan_tautan_mengubah_sandi(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'siti@simpres.test', 'password' => 'lama12345']);
        $user->createToken('android');

        $this->postJson('/api/forgot-password', ['login' => 'siti@simpres.test'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user, &$token) {
            $mail = $n->toMail($user);
            $token = $n->token;

            return $mail->subject === 'Atur Ulang Kata Sandi SIMPRES'
                && str_contains($mail->actionUrl, '/reset-password/'.$n->token);
        });

        $this->get('/reset-password/'.$token.'?email=siti@simpres.test')->assertOk()->assertSee('Kata sandi baru');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'siti@simpres.test',
            'password' => 'baru12345',
            'password_confirmation' => 'baru12345',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_token_salah_tidak_mengubah_sandi(): void
    {
        $user = User::factory()->create(['email' => 'siti@simpres.test', 'password' => 'lama12345']);

        $this->post('/reset-password', [
            'token' => 'token-palsu',
            'email' => 'siti@simpres.test',
            'password' => 'baru12345',
            'password_confirmation' => 'baru12345',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('lama12345', $user->fresh()->password));
    }
}
