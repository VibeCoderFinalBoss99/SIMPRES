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

        // Grafik: jumlah pegawai yang hadir (hadir + terlambat) per hari, 7 hari terakhir.
        $week = collect(range(6, 0))->map(function ($daysAgo) {
            $day = now()->subDays($daysAgo);

            return [
                'label' => $day->translatedFormat('D'),
                'count' => Attendance::whereDate('date', $day->toDateString())->whereIn('status', ['hadir', 'terlambat'])->count(),
            ];
        });

        // Daftar "Perlu ditinjau": 4 pengajuan menunggu terbaru dari ketiga jenis.
        $pending = collect([
            ['izin', LeaveRequest::class], ['koreksi', AttendanceCorrection::class], ['tugas', FieldDuty::class],
        ])->flatMap(fn ($t) => $t[1]::with('user')->where('status', 'menunggu')->latest()->take(4)->get()
            ->map(fn ($item) => ['type' => $t[0], 'item' => $item]))
            ->sortByDesc(fn ($row) => $row['item']->created_at)
            ->take(4)
            ->values();

        return view('admin.dashboard', [
            'week' => $week,
            'pending' => $pending,
            'totalEmployees' => User::where('role', 'pegawai')->where('is_active', true)->count(),
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
