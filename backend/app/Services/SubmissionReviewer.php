<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keputusan admin (terima/tolak) untuk izin, koreksi absensi, dan tugas lapangan.
 * Dipakai endpoint admin di API dan halaman persetujuan di Admin Web.
 */
class SubmissionReviewer
{
    public function review(Model $submission, User $admin, string $status, ?string $note): void
    {
        if ($submission->status !== 'menunggu') {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan ini sudah diputuskan sebelumnya.',
            ]);
        }

        DB::transaction(function () use ($submission, $admin, $status, $note) {
            $submission->update([
                'status' => $status,
                'admin_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if ($status === 'diterima' && $submission instanceof LeaveRequest) {
                $this->recordLeaveDays($submission);
            }
        });
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
