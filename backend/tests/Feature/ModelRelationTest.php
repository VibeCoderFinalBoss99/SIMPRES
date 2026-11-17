<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\DeviceToken;
use App\Models\FieldDuty;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModelRelationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_terhubung_ke_lokasi_dan_jadwal(): void
    {
        $location = WorkLocation::create([
            'name' => 'UPTD Puskesmas Simbang',
            'latitude' => -6.9,
            'longitude' => 109.6,
            'radius_m' => 150,
        ]);
        $shift = Shift::create(['name' => 'Pagi', 'start_time' => '07:30:00', 'end_time' => '14:00:00', 'late_tolerance_min' => 10]);
        $user = User::factory()->create(['work_location_id' => $location->id]);
        $schedule = Schedule::create(['user_id' => $user->id, 'shift_id' => $shift->id, 'date' => '2026-11-17']);

        $this->assertTrue($user->workLocation->is($location));
        $this->assertTrue($location->users->first()->is($user));
        $this->assertSame(150, $location->fresh()->radius_m);
        $this->assertSame('-6.9000000', $location->fresh()->latitude);
        $this->assertTrue($user->schedules->first()->shift->is($shift));
        $this->assertSame('2026-11-17', $schedule->fresh()->date->format('Y-m-d'));
        $this->assertFalse($schedule->fresh()->is_day_off);
        $this->assertSame(10, $shift->fresh()->late_tolerance_min);
    }

    public function test_absensi_dan_koreksi_terhubung(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::create(['user_id' => $user->id, 'date' => '2026-11-17']);
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'date' => '2026-11-17',
            'check_in_at' => '2026-11-17 07:35:00',
            'check_in_distance_m' => 42.5,
            'status' => 'hadir',
        ]);
        $correction = AttendanceCorrection::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'date' => '2026-11-17',
            'corrected_check_out' => '14:00:00',
            'reason' => 'Lupa absen pulang',
        ]);

        $attendance = $attendance->fresh();
        $this->assertTrue($attendance->user->is($user));
        $this->assertTrue($attendance->schedule->is($schedule));
        $this->assertTrue($schedule->attendance->is($attendance));
        $this->assertTrue($attendance->corrections->first()->is($correction));
        $this->assertSame('07:35', $attendance->check_in_at->format('H:i'));
        $this->assertSame('42.5', $attendance->check_in_distance_m);
        $this->assertSame(0, $attendance->late_minutes);
        $this->assertSame('online', $attendance->source);
        $this->assertSame('menunggu', $correction->fresh()->status);
        $this->assertTrue($user->attendances->first()->is($attendance));
        $this->assertTrue($user->attendanceCorrections->first()->is($correction));
    }

    public function test_pengajuan_punya_pemohon_dan_admin_peninjau(): void
    {
        $pegawai = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $leave = LeaveRequest::create([
            'user_id' => $pegawai->id,
            'type' => 'sakit',
            'start_date' => '2026-11-18',
            'end_date' => '2026-11-19',
            'reason' => 'Demam',
            'status' => 'diterima',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
        $duty = FieldDuty::create([
            'user_id' => $pegawai->id,
            'date' => '2026-11-17',
            'location_name' => 'Posyandu Melati',
            'description' => 'Imunisasi',
            'photo_path' => 'field-duties/foto.jpg',
            'status' => 'ditolak',
            'reviewed_by' => $admin->id,
        ]);

        $this->assertTrue($leave->user->is($pegawai));
        $this->assertTrue($leave->reviewer->is($admin));
        $this->assertSame('2026-11-19', $leave->fresh()->end_date->format('Y-m-d'));
        $this->assertTrue($duty->reviewer->is($admin));
        $this->assertTrue($pegawai->leaveRequests->first()->is($leave));
        $this->assertTrue($pegawai->fieldDuties->first()->is($duty));
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_notifikasi_dan_token_hp_milik_user(): void
    {
        $user = User::factory()->create();
        $notification = UserNotification::create([
            'user_id' => $user->id,
            'type' => 'izin',
            'title' => 'Izin diterima',
            'body' => 'Izin sakit Anda diterima.',
            'data' => ['leave_request_id' => 5],
        ]);
        $token = DeviceToken::create(['user_id' => $user->id, 'fcm_token' => 'token-contoh']);

        $this->assertSame(['leave_request_id' => 5], $notification->fresh()->data);
        $this->assertNull($notification->fresh()->read_at);
        $this->assertTrue($user->userNotifications->first()->is($notification));
        $this->assertTrue($user->deviceTokens->first()->is($token));
        $this->assertSame('android', $token->fresh()->platform);
    }

    public function test_password_tersembunyi_di_hash_dan_token_sanctum_bisa_dibuat(): void
    {
        $user = User::factory()->create(['password' => 'simpres123']);

        $this->assertTrue(Hash::check('simpres123', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertTrue($user->fresh()->is_active);
        $this->assertNotEmpty($user->createToken('android')->plainTextToken);
        $this->assertSame(1, $user->tokens()->count());
    }
}
