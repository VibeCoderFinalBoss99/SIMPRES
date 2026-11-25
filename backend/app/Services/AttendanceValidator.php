<?php

namespace App\Services;

use App\Models\Attendance;
use App\Support\Settings;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\User;
use App\Models\WorkLocation;
use Carbon\CarbonInterface;

/**
 * Aturan sah atau tidaknya absensi. Dipakai API check-in/check-out dan koreksi absensi.
 *
 * Hasil checkIn() dan checkOut() berupa array:
 * - allowed       : true jika boleh absen
 * - reason        : alasan ditolak (bahasa Indonesia), null jika boleh
 * - distance_m    : jarak ke titik Puskesmas dalam meter (null jika lokasi kerja belum diatur)
 * - status        : hadir / terlambat (null jika ditolak)
 * - late_minutes  : menit terlambat dihitung dari jam masuk shift
 * - schedule_id   : jadwal hari itu (jika ada)
 * - attendance    : data absensi hari itu (hanya untuk check-out)
 */
class AttendanceValidator
{
    /** Akurasi GPS terburuk yang masih diterima, dalam meter. */
    public const MAX_ACCURACY_M = 100;

    private const EARTH_RADIUS_M = 6371000;

    public function checkIn(User $user, float $lat, float $lng, float $accuracy, bool $isMock, ?CarbonInterface $at = null, bool $isEmulator = false): array
    {
        $at ??= now();
        $date = $at->toDateString();
        $location = $this->activeLocation($user);
        $distance = $location ? $this->distanceToLocation($location, $lat, $lng) : null;

        if ($this->hasApprovedLeave($user, $date)) {
            return $this->reject('Anda memiliki izin yang sudah diterima untuk hari ini, jadi tidak perlu check-in.', $distance);
        }

        $attendance = Attendance::where('user_id', $user->id)->whereDate('date', $date)->first();
        if ($attendance) {
            $reason = $attendance->check_in_at
                ? 'Anda sudah check-in hari ini.'
                : 'Data absensi hari ini sudah ada (status '.$attendance->status.').';

            return $this->reject($reason, $distance);
        }

        $schedule = $this->scheduleFor($user, $date);
        if ($error = $this->tooEarly($schedule, $at)) {
            return $this->reject($error, $distance);
        }

        if ($error = $this->locationError($location, $distance, $accuracy, $isMock, $isEmulator)) {
            return $this->reject($error, $distance);
        }

        [$status, $lateMinutes] = $this->lateStatus($schedule, $at);

        return [
            'allowed' => true,
            'reason' => null,
            'distance_m' => $distance,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'schedule_id' => $schedule?->id,
            'attendance' => null,
        ];
    }

    public function checkOut(User $user, float $lat, float $lng, float $accuracy, bool $isMock, ?CarbonInterface $at = null, bool $isEmulator = false): array
    {
        $at ??= now();
        $location = $this->activeLocation($user);
        $distance = $location ? $this->distanceToLocation($location, $lat, $lng) : null;

        $attendance = Attendance::where('user_id', $user->id)->whereDate('date', $at->toDateString())->first();

        if (! $attendance || ! $attendance->check_in_at) {
            return $this->reject('Anda belum check-in hari ini.', $distance);
        }

        if ($attendance->check_out_at) {
            return $this->reject('Anda sudah check-out hari ini.', $distance);
        }

        if ($error = $this->locationError($location, $distance, $accuracy, $isMock, $isEmulator)) {
            return $this->reject($error, $distance);
        }

        return [
            'allowed' => true,
            'reason' => null,
            'distance_m' => $distance,
            'status' => $attendance->status,
            'late_minutes' => $attendance->late_minutes,
            'schedule_id' => $attendance->schedule_id,
            'attendance' => $attendance,
        ];
    }

    /**
     * Status hadir atau terlambat berdasarkan jam masuk shift + toleransi.
     * Menit terlambat dihitung dari jam masuk shift (bukan dari batas toleransi).
     *
     * @return array{0: string, 1: int}
     */
    public function lateStatus(?Schedule $schedule, CarbonInterface $checkInAt): array
    {
        $shift = $schedule?->shift;

        if (! $shift || $schedule->is_day_off) {
            return ['hadir', 0];
        }

        $start = $checkInAt->copy()->setTimeFromTimeString($shift->start_time);
        $limit = $start->copy()->addMinutes($shift->late_tolerance_min);

        if ($checkInAt->lessThanOrEqualTo($limit)) {
            return ['hadir', 0];
        }

        return ['terlambat', (int) floor($start->diffInMinutes($checkInAt))];
    }

    /**
     * Check-in baru dibuka beberapa menit sebelum jam masuk shift (Konfigurasi: checkin_open_before_min, bawaan 60).
     * Tanpa jadwal atau hari libur, tidak ada batas jam.
     */
    private function tooEarly(?Schedule $schedule, CarbonInterface $at): ?string
    {
        $shift = $schedule?->shift;
        if (! $shift || $schedule->is_day_off) {
            return null;
        }

        $start = $at->copy()->setTimeFromTimeString($shift->start_time);
        $open = $start->copy()->subMinutes((int) (Settings::get('checkin_open_before_min') ?? 60));
        if ($at->greaterThanOrEqualTo($open)) {
            return null;
        }

        return 'Absen gagal: belum masuk jam kerja. Check-in shift '.$shift->name.' dibuka pukul '
            .$open->format('H.i').' WITA (jam masuk '.$start->format('H.i').').';
    }

    /**
     * Menit pulang lebih cepat dari jam pulang shift. Shift malam yang pulang esok hari (jam pulang lebih kecil
     * dari jam masuk) dihitung ke hari berikutnya. Tanpa jadwal atau hari libur: 0.
     */
    public function earlyLeaveMinutes(?Schedule $schedule, CarbonInterface $date, CarbonInterface $checkOutAt): int
    {
        $shift = $schedule?->shift;
        if (! $shift || $schedule->is_day_off) {
            return 0;
        }

        $end = $date->copy()->setTimeFromTimeString($shift->end_time);
        if ($shift->end_time <= $shift->start_time) {
            $end->addDay();
        }

        return $checkOutAt->lessThan($end) ? (int) ceil($checkOutAt->diffInMinutes($end)) : 0;
    }

    /** Jadwal dari roster, atau jadwal tetap pegawai jika tanggal itu tidak diatur di roster. */
    public function scheduleFor(User $user, string $date): ?Schedule
    {
        return $user->scheduleOn($date);
    }

    /** Jarak dua titik GPS dalam meter dengan rumus Haversine. */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function distanceToLocation(WorkLocation $location, float $lat, float $lng): float
    {
        $distance = $this->distanceMeters((float) $location->latitude, (float) $location->longitude, $lat, $lng);

        return round($distance, 1);
    }

    private function locationError(?WorkLocation $location, ?float $distance, float $accuracy, bool $isMock, bool $isEmulator = false): ?string
    {
        // Batas akurasi, penolakan lokasi palsu, dan penolakan emulator bisa diatur admin di halaman Konfigurasi.
        if ($isMock && Settings::get('reject_mock')) {
            return 'Terdeteksi lokasi palsu (fake GPS). Matikan aplikasi lokasi palsu lalu coba lagi.';
        }

        if ($isEmulator && Settings::get('reject_emulator')) {
            return 'Absen ditolak: aplikasi berjalan di emulator, bukan di HP asli.';
        }

        // GPS asli selalu punya nilai akurasi lebih dari 0. Nilai 0 hanya muncul dari lokasi buatan.
        if ($accuracy <= 0) {
            return 'Terdeteksi lokasi palsu (akurasi GPS tidak wajar). Matikan aplikasi lokasi palsu lalu coba lagi.';
        }

        $maxAccuracy = (int) (Settings::get('max_accuracy_m') ?? self::MAX_ACCURACY_M);
        if ($accuracy > $maxAccuracy) {
            return 'Akurasi GPS terlalu rendah ('.round($accuracy).' m, maksimal '.$maxAccuracy.' m). Pindah ke tempat terbuka lalu coba lagi.';
        }

        if (! $location) {
            return 'Lokasi kerja Anda belum diatur. Hubungi admin Puskesmas.';
        }

        if ($distance > $location->radius_m) {
            return 'Anda berada di luar radius Puskesmas (jarak '.round($distance).' m, maksimal '.$location->radius_m.' m).';
        }

        return null;
    }

    private function activeLocation(User $user): ?WorkLocation
    {
        $location = $user->workLocation;

        return $location && $location->is_active ? $location : null;
    }

    /** Izin keluar tidak dihitung, karena pegawai tetap masuk kerja di hari itu. */
    private function hasApprovedLeave(User $user, string $date): bool
    {
        return LeaveRequest::where('user_id', $user->id)
            ->where('status', 'diterima')
            ->where('type', '!=', 'izin_keluar')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    private function reject(string $reason, ?float $distance): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'distance_m' => $distance,
            'status' => null,
            'late_minutes' => 0,
            'schedule_id' => null,
            'attendance' => null,
        ];
    }
}
