<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Models\WorkLocation;
use App\Support\Settings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Koreksi dosen: jam buka check-in, pulang cepat, emulator/fake GPS, libur nasional, Konfigurasi, email reset. */
class AttendanceRulesTest extends TestCase
{
    use DatabaseTransactions;

    private User $budi;

    protected function setUp(): void
    {
        parent::setUp();
        // Selasa, 17 November 2026, shift tetap Pagi 07.30-14.00.
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(7, 20));
        $pagi = $this->makeShift();
        $this->budi = $this->makePegawai(null, ['name' => 'Budi Santoso', 'work_type' => 'non_shift', 'default_shift_id' => $pagi->id]);
        Sanctum::actingAs($this->budi);
    }

    private function gps(array $extra = []): array
    {
        return array_merge(['latitude' => self::DEKAT_LAT, 'longitude' => self::PUSKESMAS_LNG, 'accuracy' => 10, 'is_mock' => false], $extra);
    }

    public function test_zona_waktu_aplikasi_wita(): void
    {
        $this->assertSame('Asia/Makassar', config('app.timezone'));
    }

    public function test_check_in_sebelum_jam_buka_ditolak_belum_masuk_jam_kerja(): void
    {
        // Jam buka = 07.30 - 60 menit = 06.30.
        $this->travelTo(now()->setTime(6, 15));
        $this->postJson('/api/attendance/check-in', $this->gps())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Absen gagal: belum masuk jam kerja. Check-in shift Pagi dibuka pukul 06.30 WITA (jam masuk 07.30).');
        $this->assertSame(0, Attendance::where('user_id', $this->budi->id)->count());

        $this->travelTo(now()->setTime(6, 30));
        $this->postJson('/api/attendance/check-in', $this->gps())->assertCreated()->assertJsonPath('data.status', 'hadir');
    }

    public function test_check_out_sebelum_jam_pulang_dicatat_pulang_cepat(): void
    {
        $this->postJson('/api/attendance/check-in', $this->gps())->assertCreated();

        $this->travelTo(now()->setTime(13, 15));
        $this->postJson('/api/attendance/check-out', $this->gps())
            ->assertOk()
            ->assertJsonPath('message', 'Check-out berhasil. Anda pulang 45 menit lebih cepat dari jadwal.')
            ->assertJsonPath('data.early_leave_minutes', 45)
            ->assertJsonPath('data.work_minutes', 355);

        $this->getJson('/api/attendance?month=2026-11')->assertJsonPath('data.0.early_leave_minutes', 45);
    }

    public function test_check_out_tepat_waktu_tidak_dihitung_pulang_cepat(): void
    {
        $this->postJson('/api/attendance/check-in', $this->gps())->assertCreated();
        $this->travelTo(now()->setTime(14, 5));
        $this->postJson('/api/attendance/check-out', $this->gps())
            ->assertJsonPath('message', 'Check-out berhasil.')
            ->assertJsonPath('data.early_leave_minutes', 0);
    }

    public function test_fake_gps_emulator_dan_akurasi_nol_ditolak(): void
    {
        $this->postJson('/api/attendance/check-in', $this->gps(['is_mock' => true]))
            ->assertStatus(422)->assertJsonPath('message', 'Terdeteksi lokasi palsu (fake GPS). Matikan aplikasi lokasi palsu lalu coba lagi.');
        $this->postJson('/api/attendance/check-in', $this->gps(['is_emulator' => true]))
            ->assertStatus(422)->assertJsonPath('message', 'Absen ditolak: aplikasi berjalan di emulator, bukan di HP asli.');
        $this->postJson('/api/attendance/check-in', $this->gps(['accuracy' => 0]))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'akurasi GPS tidak wajar'));

        $this->assertSame(0, Attendance::where('user_id', $this->budi->id)->count());
    }

    public function test_hari_libur_nasional_otomatis_libur(): void
    {
        // Nilai awal Konfigurasi berisi 2026-12-25 Hari Raya Natal (Jumat).
        $this->assertSame('Hari Raya Natal', Settings::holidays()['2026-12-25']);

        $day = collect($this->getJson('/api/schedules?month=2026-12')->json('data'))->firstWhere('date', '2026-12-25');
        $this->assertTrue($day['is_day_off']);
        $this->assertSame('Hari Raya Natal', $day['holiday']);

        $this->travelTo(now()->setDate(2026, 12, 25)->setTime(8, 0));
        $this->getJson('/api/attendance/today')->assertJsonPath('data.schedule.is_day_off', true);
    }

    public function test_konfigurasi_radius_langsung_mengubah_lokasi_kerja(): void
    {
        Storage::fake('local'); // jangan menimpa settings.json asli di komputer
        $location = $this->budi->workLocation;
        $this->actingAs($this->makeAdmin());
        WorkLocation::where('id', '!=', $location->id)->delete();

        $this->get('/admin/konfigurasi')->assertOk()->assertSee('Radius absensi (meter)')->assertSee('Hari libur nasional');

        $form = ['radius_m' => 80, 'max_accuracy_m' => 50, 'checkin_open_before_min' => 30, 'holidays' => "2026-12-25 Natal", 'reject_mock' => 1, 'reject_emulator' => 1];
        $this->put('/admin/konfigurasi', $form)->assertRedirect('/admin/konfigurasi')->assertSessionHasNoErrors();
        $this->assertSame(80, $location->fresh()->radius_m);

        // Nilai tidak wajar ditolak: akurasi 0 membuat semua absen gagal, tanggal libur harus benar.
        $this->put('/admin/konfigurasi', ['max_accuracy_m' => 0, 'radius_m' => 5000, 'holidays' => 'Natal 25 Desember'] + $form)
            ->assertSessionHasErrors(['max_accuracy_m', 'radius_m', 'holidays']);
    }

    public function test_email_lupa_sandi_lengkap_html_dan_teks(): void
    {
        $user = $this->budi;
        $mail = (new ResetPassword(Password::createToken($user)))->toMail($user);

        $this->assertSame('Atur ulang kata sandi akun SIMPRES Anda', $mail->subject);
        $html = (string) $mail->render();
        foreach (['Halo Budi Santoso', 'Buat kata sandi baru', 'Waktu permintaan', 'WITA', 'Berlaku sampai', 'abaikan email ini', '/reset-password/'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertSame(['html' => 'emails.reset-password', 'text' => 'emails.reset-password-text'], $mail->view);
    }
}
