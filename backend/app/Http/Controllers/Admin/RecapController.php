<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PdfTable;
use App\Support\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Rekap absensi per bulan per pegawai, tampil di halaman dan bisa diunduh CSV, Excel (XLSX), atau PDF. */
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
            fputcsv($out, self::HEADERS, ';');

            foreach ($this->exportRows($rows) as $line) {
                // NIP diberi tanda = "..." supaya Excel tidak mengubahnya menjadi angka 1,98501E+17.
                $line[1] = '="'.$line[1].'"';
                fputcsv($out, $line, ';');
            }

            fclose($out);
        }, 'rekap-absensi-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Rekap sebagai file Excel (.xlsx) dengan judul, kepala tabel berwarna, dan baris total. */
    public function xlsx(Request $request): BinaryFileResponse
    {
        $month = $this->month($request);
        $rows = $this->rows($month)->values();

        $data = $this->exportRows($rows);
        $total = [$this->exportTotal($rows)];

        $path = XlsxWriter::make(
            'Rekap '.$month->format('Y-m'),
            $this->titles($month),
            self::HEADERS,
            $data,
            $total,
            [5, 22, 26, 20, 13, 17, 16, 17, 11, 22],
        );

        return response()->download($path, 'rekap-absensi-'.$month->format('Y-m').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Rekap sebagai PDF A4 mendatar, siap dicetak atau dikirim. */
    public function pdf(Request $request): Response
    {
        $month = $this->month($request);
        $rows = $this->rows($month)->values();

        $columns = [['No', 26, 'R'], ['NIP', 126, 'L'], ['Nama', 150, 'L'], ['Jabatan', 106, 'L'],
            ['Masuk', 50, 'R'], ['Tepat waktu', 62, 'R'], ['Terlambat', 60, 'R'], ['Izin/Sakit', 58, 'R'],
            ['Alpa', 44, 'R'], ['Telat (menit)', 88, 'R']];
        $data = $this->exportRows($rows);
        $total = $this->exportTotal($rows);

        $pdf = PdfTable::make($this->titles($month), $columns, $data, $total, self::NOTE);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename=rekap-absensi-'.$month->format('Y-m').'.pdf',
        ]);
    }

    /** Semua angka hari dihitung dalam bulan yang dipilih. Masuk = tepat waktu + terlambat. */
    private const HEADERS = ['No', 'NIP', 'Nama', 'Jabatan', 'Masuk (hari)', 'Tepat waktu (hari)', 'Terlambat (hari)',
        'Izin/Sakit (hari)', 'Alpa (hari)', 'Total terlambat (menit)'];

    private function exportRows(Collection $rows): array
    {
        return $rows->values()->map(fn ($row, $i) => [$i + 1, (string) $row->nip, $row->name, (string) $row->position,
            $row->masuk, $row->hadir, $row->terlambat, $row->izin, $row->alpa, $row->late_minutes])->all();
    }

    private function exportTotal(Collection $rows): array
    {
        return ['', '', 'Total', '', $rows->sum('masuk'), $rows->sum('hadir'), $rows->sum('terlambat'),
            $rows->sum('izin'), $rows->sum('alpa'), $rows->sum('late_minutes')];
    }

    private const NOTE = 'Semua angka dalam satuan hari, kecuali Telat (menit). Masuk = tepat waktu + terlambat. Alpa = absensi berstatus alpa ditambah hari kerja terjadwal (bukan libur) yang sudah lewat tanpa absensi. '
        .'Tugas lapangan yang diterima dihitung tepat waktu.';

    private function titles(Carbon $month): array
    {
        return [
            'Rekap Absensi Pegawai - '.$month->translatedFormat('F Y'),
            'UPTD Puskesmas Simbang',
            'Dibuat '.now()->translatedFormat('d F Y H:i').' dari Admin Web SIMPRES',
        ];
    }

    private function month(Request $request): Carbon
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return Carbon::createFromFormat('Y-m-d', ($request->month ?? now()->format('Y-m')).'-01')->startOfDay();
    }

    /**
     * Hadir, terlambat, izin, dan menit terlambat dihitung dari tabel attendances
     * (sama dengan query rekap di docs/PANDUAN.md bagian 6).
     * Alpa = absensi berstatus alpa + hari kerja (roster atau jadwal tetap, bukan libur) yang sudah lewat tanpa absensi sama sekali.
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

        $missing = $this->missingWorkdays($rows->pluck('id'), $start, min($end, now()->subDay()->toDateString()));

        // Hari tugas lapangan diterima yang tidak punya data absensi (supaya tidak dihitung dua kali).
        $fieldDays = DB::table('field_duties as f')
            ->leftJoin('attendances as a', fn ($join) => $join->on('a.user_id', '=', 'f.user_id')->on('a.date', '=', 'f.date'))
            ->where('f.status', 'diterima')
            ->whereBetween('f.date', [$start, $end])
            ->whereNull('a.id')
            ->groupBy('f.user_id')
            ->selectRaw('f.user_id, COUNT(DISTINCT f.date) AS days')
            ->pluck('days', 'user_id');

        return $rows->map(function ($row) use ($missing, $fieldDays) {
            foreach (['hadir', 'terlambat', 'izin', 'alpa', 'late_minutes'] as $column) {
                $row->$column = (int) $row->$column;
            }
            $row->lapangan = (int) ($fieldDays[$row->id] ?? 0);
            $row->hadir += $row->lapangan;
            $row->alpa += (int) ($missing[$row->id] ?? 0);
            $row->masuk = $row->hadir + $row->terlambat;

            return $row;
        });
    }

    /**
     * Hari kerja (dari roster atau jadwal tetap, bukan libur) yang sudah lewat sampai kemarin
     * tetapi tidak ada absensinya. Tugas lapangan yang diterima admin tidak dihitung alpa,
     * karena pegawai bertugas resmi di luar gedung.
     *
     * @return array<int, int> jumlah hari per user_id
     */
    private function missingWorkdays(Collection $userIds, string $start, string $lastPastDay): array
    {
        if ($start > $lastPastDay) {
            return [];
        }

        $datesPerUser = fn ($query) => $query->whereIn('user_id', $userIds)->whereBetween('date', [$start, $lastPastDay])
            ->get(['user_id', 'date'])
            ->groupBy('user_id')
            ->map(fn ($items) => $items->mapWithKeys(fn ($item) => [substr($item->date, 0, 10) => true]));
        $attended = $datesPerUser(DB::table('attendances'));
        $duties = $datesPerUser(DB::table('field_duties')->where('status', 'diterima'));

        $missing = [];
        foreach (User::with('defaultShift')->whereIn('id', $userIds)->get() as $user) {
            // Pegawai baru: hari sebelum akunnya dibuat tidak dihitung alpa (termasuk roster yang terisi lebih dulu).
            $joined = $user->created_at?->toDateString();
            $missing[$user->id] = $user->schedulesBetween(Carbon::parse($start), Carbon::parse($lastPastDay))
                ->reject(fn ($schedule, $date) => $schedule->is_day_off || ($joined && $date < $joined)
                    || isset($attended[$user->id][$date]) || isset($duties[$user->id][$date]))
                ->count();
        }

        return $missing;
    }
}
