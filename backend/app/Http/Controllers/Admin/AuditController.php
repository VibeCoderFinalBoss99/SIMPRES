<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\FieldDuty;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkLocation;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit Log: jejak aktor, waktu, aksi, dan catatan. Disusun dari data yang sudah tercatat
 * (keputusan pengajuan, akun dibuat, lokasi diubah, perubahan konfigurasi), jadi tidak perlu tabel baru.
 */
class AuditController extends Controller
{
    public const ACTIONS = [
        'izin' => 'Keputusan izin',
        'koreksi' => 'Keputusan koreksi',
        'lapangan' => 'Keputusan tugas lapangan',
        'pegawai' => 'Menambah pegawai',
        'lokasi' => 'Mengubah lokasi',
        'konfigurasi' => 'Mengubah konfigurasi',
    ];

    public function index(Request $request): View
    {
        [$from, $to, $action] = $this->filters($request);

        return view('admin.audit', [
            'entries' => $this->entries($from, $to, $action),
            'from' => $from,
            'to' => $to,
            'action' => $action,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        [$from, $to, $action] = $this->filters($request);
        $entries = $this->entries($from, $to, $action);

        return response()->streamDownload(function () use ($entries) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Waktu', 'Aktor', 'Aksi', 'Target', 'Catatan'], ';');
            foreach ($entries as $e) {
                fputcsv($out, [$e['at']->format('d/m/Y H:i'), $e['actor'], $e['label'], $e['target'], $e['note']], ';');
            }
            fclose($out);
        }, 'audit-log.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $request->validate(['dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date'], 'aksi' => ['nullable', 'string']]);
        $from = Carbon::parse($request->input('dari', now()->subDays(30)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('sampai', now()->toDateString()))->endOfDay();
        $action = array_key_exists($request->input('aksi'), self::ACTIONS) ? $request->input('aksi') : null;

        return [$from, $to, $action];
    }

    private function entries(Carbon $from, Carbon $to, ?string $action): Collection
    {
        $entries = collect();
        $reviewed = [
            'izin' => [LeaveRequest::class, fn ($i) => (['izin_keluar' => 'Izin keluar', 'sakit' => 'Izin sakit', 'cuti' => 'Cuti', 'lainnya' => 'Izin lain'][$i->type] ?? 'Izin').' · '.$i->user->name],
            'koreksi' => [AttendanceCorrection::class, fn ($i) => 'Koreksi '.$i->date->format('d/m/Y').' · '.$i->user->name],
            'lapangan' => [FieldDuty::class, fn ($i) => 'Tugas lapangan '.$i->location_name.' · '.$i->user->name],
        ];
        foreach ($reviewed as $key => [$model, $target]) {
            if ($action && $action !== $key) {
                continue;
            }
            $model::with('user', 'reviewer')->whereNotNull('reviewed_at')->whereBetween('reviewed_at', [$from, $to])->get()
                ->each(fn ($i) => $entries->push([
                    'at' => $i->reviewed_at,
                    'actor' => $i->reviewer?->name ?? '-',
                    'key' => $key,
                    'label' => ($i->status === 'diterima' ? 'Menyetujui ' : 'Menolak ').($key === 'lapangan' ? 'lapangan' : $key),
                    'tone' => $i->status === 'diterima' ? 'green' : 'red',
                    'target' => $target($i),
                    'note' => $i->admin_note ?? '-',
                ]));
        }

        if (! $action || $action === 'pegawai') {
            User::whereBetween('created_at', [$from, $to])->get()->each(fn ($u) => $entries->push([
                'at' => $u->created_at, 'actor' => 'Admin', 'key' => 'pegawai', 'label' => 'Menambah pegawai', 'tone' => 'blue',
                'target' => $u->name, 'note' => trim(($u->position ?? '').' '.($u->unit ? '· '.$u->unit : '')) ?: '-',
            ]));
        }

        if (! $action || $action === 'lokasi') {
            WorkLocation::whereBetween('updated_at', [$from, $to])->get()->each(fn ($l) => $entries->push([
                'at' => $l->updated_at, 'actor' => 'Admin', 'key' => 'lokasi', 'label' => 'Mengubah lokasi', 'tone' => 'blue',
                'target' => $l->name, 'note' => 'Radius '.$l->radius_m.' m',
            ]));
        }

        if (! $action || $action === 'konfigurasi') {
            foreach (Settings::history() as $h) {
                $at = Carbon::parse($h['at']);
                if ($at->between($from, $to)) {
                    $entries->push([
                        'at' => $at, 'actor' => $h['actor'], 'key' => 'konfigurasi', 'label' => 'Mengubah konfigurasi', 'tone' => 'blue',
                        'target' => 'Konfigurasi', 'note' => implode('; ', $h['changes']),
                    ]);
                }
            }
        }

        return $entries->sortByDesc('at')->values();
    }
}
