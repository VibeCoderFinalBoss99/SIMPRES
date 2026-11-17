<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Services\AttendanceValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AttendanceValidatorTest extends TestCase
{
    use DatabaseTransactions;

    private AttendanceValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AttendanceValidator;
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(7, 25));
    }

    // Aturan 1: jarak Haversine dibandingkan dengan radius_m dari tabel work_locations.

    public function test_jarak_sekitar_22_meter_diterima(): void
    {
        $user = $this->makePegawai('2026-11-17');

        $result = $this->validator->checkIn($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
        $this->assertEqualsWithDelta(22.2, $result['distance_m'], 0.5);
        $this->assertSame('hadir', $result['status']);
    }

    public function test_jarak_sekitar_220_meter_ditolak(): void
    {
        $user = $this->makePegawai('2026-11-17');

        $result = $this->validator->checkIn($user, self::JAUH_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('di luar radius', $result['reason']);
        $this->assertEqualsWithDelta(222.4, $result['distance_m'], 0.5);
    }

    public function test_radius_diambil_dari_tabel_work_locations(): void
    {
        $user = $this->makePegawai('2026-11-17');
        $user->workLocation->update(['radius_m' => 10]);

        $result = $this->validator->checkIn($user->fresh(), self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('maksimal 10 m', $result['reason']);
    }

    public function test_haversine_menghitung_jarak_dengan_benar(): void
    {
        // Selisih 0,001 derajat lintang sekitar 111,2 m.
        $this->assertEqualsWithDelta(111.2, $this->validator->distanceMeters(-6.9, 109.6, -6.901, 109.6), 0.2);
        $this->assertSame(0.0, $this->validator->distanceMeters(-6.9, 109.6, -6.9, 109.6));
    }

    // Aturan 2: akurasi GPS lebih dari 100 m ditolak.

    public function test_akurasi_gps_lebih_dari_100_meter_ditolak(): void
    {
        $user = $this->makePegawai('2026-11-17');

        $result = $this->validator->checkIn($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 101, false);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('Akurasi GPS', $result['reason']);
    }

    public function test_akurasi_gps_tepat_100_meter_masih_diterima(): void
    {
        $user = $this->makePegawai('2026-11-17');

        $this->assertTrue($this->validator->checkIn($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 100, false)['allowed']);
    }

    // Aturan 3: lokasi palsu ditolak.

    public function test_lokasi_palsu_ditolak(): void
    {
        $user = $this->makePegawai('2026-11-17');

        $result = $this->validator->checkIn($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, true);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('lokasi palsu', $result['reason']);
    }

    // Aturan 4: satu check-in per hari.

    public function test_check_in_kedua_di_hari_yang_sama_ditolak(): void
    {
        $user = $this->makePegawai('2026-11-17');
        Attendance::create(['user_id' => $user->id, 'date' => '2026-11-17', 'check_in_at' => now(), 'status' => 'hadir']);

        $result = $this->validator->checkIn($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertFalse($result['allowed']);
        $this->assertSame('Anda sudah check-in hari ini.', $result['reason']);
    }

    public function test_check_in_besok_tetap_boleh(): void
    {
        $user = $this->makePegawai('2026-11-17');
        Attendance::create(['user_id' => $user->id, 'date' => '2026-11-16', 'check_in_at' => now()->subDay(), 'status' => 'hadir']);

        $this->assertTrue($this->validator->checkIn($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false)['allowed']);
    }

    // Aturan 5: check-out hanya setelah check-in dan belum check-out.

    public function test_check_out_tanpa_check_in_ditolak(): void
    {
        $user = $this->makePegawai('2026-11-17');

        $result = $this->validator->checkOut($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertFalse($result['allowed']);
        $this->assertSame('Anda belum check-in hari ini.', $result['reason']);
    }

    public function test_check_out_setelah_check_in_diterima(): void
    {
        $user = $this->makePegawai('2026-11-17');
        $attendance = Attendance::create(['user_id' => $user->id, 'date' => '2026-11-17', 'check_in_at' => now(), 'status' => 'hadir']);
        $this->travelTo(now()->setTime(14, 5));

        $result = $this->validator->checkOut($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['attendance']->is($attendance));
    }

    public function test_check_out_kedua_ditolak(): void
    {
        $user = $this->makePegawai('2026-11-17');
        Attendance::create(['user_id' => $user->id, 'date' => '2026-11-17', 'check_in_at' => now(), 'check_out_at' => now(), 'status' => 'hadir']);

        $result = $this->validator->checkOut($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertFalse($result['allowed']);
        $this->assertSame('Anda sudah check-out hari ini.', $result['reason']);
    }

    public function test_check_out_di_luar_radius_ditolak(): void
    {
        $user = $this->makePegawai('2026-11-17');
        Attendance::create(['user_id' => $user->id, 'date' => '2026-11-17', 'check_in_at' => now(), 'status' => 'hadir']);

        $result = $this->validator->checkOut($user, self::JAUH_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('di luar radius', $result['reason']);
    }

    // Aturan 6: terlambat setelah jam masuk shift + toleransi.

    public function test_check_in_setelah_batas_toleransi_berstatus_terlambat(): void
    {
        $user = $this->makePegawai('2026-11-17');
        $this->travelTo(now()->setTime(7, 55)); // shift 07:30, toleransi 10 menit

        $result = $this->validator->checkIn($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertTrue($result['allowed']);
        $this->assertSame('terlambat', $result['status']);
        $this->assertSame(25, $result['late_minutes']);
    }

    public function test_check_in_masih_dalam_toleransi_berstatus_hadir(): void
    {
        $user = $this->makePegawai('2026-11-17');
        $this->travelTo(now()->setTime(7, 40));

        $result = $this->validator->checkIn($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertSame('hadir', $result['status']);
        $this->assertSame(0, $result['late_minutes']);
    }

    // Aturan 7: izin diterima yang mencakup hari ini.

    public function test_pegawai_dengan_izin_diterima_tidak_perlu_check_in(): void
    {
        $user = $this->makePegawai('2026-11-17');
        LeaveRequest::create([
            'user_id' => $user->id, 'type' => 'cuti', 'start_date' => '2026-11-16', 'end_date' => '2026-11-18',
            'reason' => 'Cuti tahunan', 'status' => 'diterima',
        ]);

        $result = $this->validator->checkIn($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('tidak perlu check-in', $result['reason']);
    }

    public function test_izin_yang_masih_menunggu_tidak_menghalangi_check_in(): void
    {
        $user = $this->makePegawai('2026-11-17');
        LeaveRequest::create([
            'user_id' => $user->id, 'type' => 'cuti', 'start_date' => '2026-11-17', 'end_date' => '2026-11-17',
            'reason' => 'Cuti', 'status' => 'menunggu',
        ]);

        $this->assertTrue($this->validator->checkIn($user, self::DEKAT_LAT, self::PUSKESMAS_LNG, 10, false)['allowed']);
    }
}
