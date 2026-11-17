<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Rekap absensi per bulan per pegawai, tampil di halaman dan bisa diunduh CSV. */
class RecapController extends Controller
{
    public function index(Request $request): View
    {
        $month = $this->month($request);

        return view('admin.recap', ['month' => $month, 'rows' => $this->rows($month)]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $month = $this->month($request);
        $rows = $this->rows($month);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
            // Pemisah titik koma: format standar Excel dengan pengaturan wilayah Indonesia.
            fputcsv($out, ['No', 'NIP', 'Nama', 'Jabatan', 'Hadir', 'Terlambat', 'Izin', 'Alpa', 'Total menit terlambat'], ';');

            foreach ($rows->values() as $i => $row) {
                // NIP diberi tanda = "..." supaya Excel tidak mengubahnya menjadi angka 1,98501E+17.
                fputcsv($out, [$i + 1, '="'.$row->nip.'"', $row->name, $row->position, $row->hadir, $row->terlambat, $row->izin, $row->alpa, $row->late_minutes], ';');
            }

            fclose($out);
        }, 'rekap-absensi-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function month(Request $request): Carbon
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return Carbon::createFromFormat('Y-m-d', ($request->month ?? now()->format('Y-m')).'-01')->startOfDay();
    }

    /**
     * Hadir, terlambat, izin, dan menit terlambat dihitung dari tabel attendances
     * (sama dengan query rekap di docs/PANDUAN.md bagian 6).
     * Alpa = absensi berstatus alpa + hari kerja terjadwal (bukan libur) yang sudah lewat tanpa absensi sama sekali.
     */
    private function rows(Carbon $month): Collection
    {
        $start = $month->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $rows = DB::table('users as u')
            ->leftJoin('attendances as a', fn ($join) => $join->on('a.user_id', '=', 'u.id')->whereBetween('a.date', [$start, $end]))
            ->where('u.role', 'pegawai')
            ->groupBy('u.id', 'u.nip', 'u.name', 'u.position')
            ->orderBy('u.name')
            ->selectRaw("u.id, u.nip, u.name, u.position,
                COALESCE(SUM(a.status = 'hadir'), 0) AS hadir,
                COALESCE(SUM(a.status = 'terlambat'), 0) AS terlambat,
                COALESCE(SUM(a.status = 'izin'), 0) AS izin,
                COALESCE(SUM(a.status = 'alpa'), 0) AS alpa,
                COALESCE(SUM(a.late_minutes), 0) AS late_minutes")
            ->get();

        // Hari kerja terjadwal yang sudah lewat (sampai kemarin) tetapi tidak ada absensinya.
        $lastPastDay = min($end, now()->subDay()->toDateString());
        $missing = DB::table('schedules as s')
            ->leftJoin('attendances as a', fn ($join) => $join->on('a.user_id', '=', 's.user_id')->on('a.date', '=', 's.date'))
            ->where('s.is_day_off', false)
            ->whereBetween('s.date', [$start, $lastPastDay])
            ->whereNull('a.id')
            ->groupBy('s.user_id')
            ->pluck(DB::raw('COUNT(*)'), 's.user_id');

        return $rows->map(function ($row) use ($missing) {
            foreach (['hadir', 'terlambat', 'izin', 'alpa', 'late_minutes'] as $column) {
                $row->$column = (int) $row->$column;
            }
            $row->alpa += (int) ($missing[$row->id] ?? 0);

            return $row;
        });
    }
}
