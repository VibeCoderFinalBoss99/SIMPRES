<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\FieldDuty;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->toDateString();

        $attendances = Attendance::with('user')
            ->whereDate('date', $today)
            ->whereHas('user', fn ($query) => $query->where('role', 'pegawai'))
            ->orderBy('check_in_at')
            ->get();

        // Belum absen: pegawai aktif yang belum punya data absensi hari ini dan tidak libur.
        $notYet = User::where('role', 'pegawai')
            ->where('is_active', true)
            ->whereDoesntHave('attendances', fn ($query) => $query->whereDate('date', $today))
            ->whereDoesntHave('schedules', fn ($query) => $query->whereDate('date', $today)->where('is_day_off', true))
            ->orderBy('name')
            ->get();

        return view('admin.dashboard', [
            'today' => now(),
            'attendances' => $attendances,
            'notYet' => $notYet,
            'counts' => [
                'hadir' => $attendances->where('status', 'hadir')->count(),
                'terlambat' => $attendances->where('status', 'terlambat')->count(),
                'izin' => $attendances->where('status', 'izin')->count(),
                'belum' => $notYet->count(),
            ],
            'waiting' => [
                'izin' => LeaveRequest::where('status', 'menunggu')->count(),
                'koreksi' => AttendanceCorrection::where('status', 'menunggu')->count(),
                'tugas' => FieldDuty::where('status', 'menunggu')->count(),
            ],
        ]);
    }
}
