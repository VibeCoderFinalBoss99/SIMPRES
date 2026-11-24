<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\FieldDuty;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keputusan admin (terima/tolak) untuk izin, koreksi absensi, dan tugas lapangan.
 * Dipakai endpoint admin di API dan halaman persetujuan di Admin Web.
 */
class SubmissionReviewer
{
    public function __construct(private AttendanceValidator $validator, private PushNotifier $push) {}

    public function review(Model $submission, User $admin, string $status, ?string $note): void
    {
        if ($submission->status !== 'menunggu') {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan ini sudah diputuskan sebelumnya.',
            ]);
        }

        $notification = DB::transaction(function () use ($submission, $admin, $status, $note) {
            $submission->update([
                'status' => $status,
                'admin_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if ($status === 'diterima' && $submission instanceof LeaveRequest) {
                $this->recordLeaveDays($submission);
            }

            if ($status === 'diterima' && $submission instanceof AttendanceCorrection) {
                $this->applyCorrection($submission);
            }

            return $this->notifyEmployee($submission, $status, $note);
        });

        // Dikirim setelah data tersimpan; jika gagal, HP tetap mengambilnya lewat pengecekan berkala.
        $this->push->send($notification);
    }

    /** Notifikasi di aplikasi untuk pegawai pemilik pengajuan. */
    private function notifyEmployee(Model $submission, string $status, ?string $note): UserNotification
    {
        [$type, $label, $detail] = match (true) {
            $submission instanceof LeaveRequest => [
                'izin', 'Izin',
                'Pengajuan '.str_replace('_', ' ', $submission->type).' tanggal '.$submission->start_date->format('d/m/Y')
                    .($submission->end_date->isSameDay($submission->start_date) ? '' : ' s.d. '.$submission->end_date->format('d/m/Y')),
            ],
            $submission instanceof AttendanceCorrection => [
                'koreksi', 'Koreksi absensi',
                'Koreksi absensi tanggal '.$submission->date->format('d/m/Y'),
            ],
            $submission instanceof FieldDuty => [
                'tugas_lapangan', 'Tugas lapangan',
                'Laporan tugas lapangan di '.$submission->location_name.' tanggal '.$submission->date->format('d/m/Y'),
            ],
        };

        return UserNotification::create([
            'user_id' => $submission->user_id,
            'type' => $type,
            'title' => "{$label} {$status}",
            'body' => "{$detail} {$status} oleh admin.".($note ? " Catatan: {$note}" : ''),
            'data' => ['submission_type' => $type, 'submission_id' => $submission->id, 'status' => $status],
        ]);
    }

    /**
     * Koreksi yang diterima mengganti jam masuk dan/atau pulang di tabel attendances.
     * Jika hari itu belum ada absensi sama sekali, absensi baru dibuat.
     * Jika jam masuk dikoreksi, status hadir/terlambat dihitung ulang dari jadwal shift.
     */
    private function applyCorrection(AttendanceCorrection $correction): void
    {
        $date = $correction->date->toDateString();
        $schedule = $this->validator->scheduleFor($correction->user, $date);

        $attendance = $correction->attendance
            ?? Attendance::where('user_id', $correction->user_id)->whereDate('date', $date)->first()
            ?? new Attendance([
                'user_id' => $correction->user_id,
                'schedule_id' => $schedule?->id,
                'date' => $date,
                'status' => 'hadir',
                'source' => 'online',
            ]);

        if ($correction->corrected_check_in) {
            $checkIn = Carbon::parse("{$date} {$correction->corrected_check_in}");
            [$status, $lateMinutes] = $this->validator->lateStatus($schedule, $checkIn);
            $attendance->fill(['check_in_at' => $checkIn, 'status' => $status, 'late_minutes' => $lateMinutes]);
        }

        if ($correction->corrected_check_out) {
            $attendance->check_out_at = Carbon::parse("{$date} {$correction->corrected_check_out}");
        }

        $attendance->save();
        $correction->update(['attendance_id' => $attendance->id]);
    }

    /**
     * Izin yang diterima dicatat sebagai absensi berstatus izin per hari, supaya rekap dan dashboard
     * menghitungnya. Izin keluar tidak dicatat karena pegawai tetap masuk kerja di hari itu.
     * Hari yang sudah punya absensi atau jadwalnya libur dilewati.
     */
    private function recordLeaveDays(LeaveRequest $leave): void
    {
        if ($leave->type === 'izin_keluar') {
            return;
        }

        foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $day) {
            $date = $day->toDateString();

            $hasAttendance = Attendance::where('user_id', $leave->user_id)->whereDate('date', $date)->exists();
            $schedule = Schedule::where('user_id', $leave->user_id)->whereDate('date', $date)->first();

            if ($hasAttendance || $schedule?->is_day_off) {
                continue;
            }

            Attendance::create([
                'user_id' => $leave->user_id,
                'schedule_id' => $schedule?->id,
                'date' => $date,
                'status' => 'izin',
                'source' => 'online',
            ]);
        }
    }
}
