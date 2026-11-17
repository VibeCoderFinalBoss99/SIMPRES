<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewSubmissionRequest;
use App\Models\AttendanceCorrection;
use App\Models\FieldDuty;
use App\Models\LeaveRequest;
use App\Services\SubmissionReviewer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Daftar dan persetujuan izin, koreksi absensi, dan tugas lapangan. */
class SubmissionController extends Controller
{
    /** jenis di URL => [model, judul, kolom file] */
    public const TYPES = [
        'izin' => [LeaveRequest::class, 'Izin dan cuti', 'attachment_path'],
        'koreksi' => [AttendanceCorrection::class, 'Koreksi absensi', 'attachment_path'],
        'tugas' => [FieldDuty::class, 'Tugas lapangan', 'photo_path'],
    ];

    public function index(Request $request): View
    {
        $type = array_key_exists($request->query('jenis'), self::TYPES) ? $request->query('jenis') : 'izin';
        $status = in_array($request->query('status'), ['menunggu', 'diterima', 'ditolak', 'semua'], true)
            ? $request->query('status')
            : 'menunggu';

        [$model] = self::TYPES[$type];

        $items = $model::with('user')
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $waitingCounts = collect(self::TYPES)->map(fn ($config) => $config[0]::where('status', 'menunggu')->count());

        return view('admin.submissions.index', compact('type', 'status', 'items', 'waitingCounts'));
    }

    public function show(Request $request, string $type, int $id): View
    {
        $item = $this->find($type, $id);
        $item->load('user', 'reviewer');

        // Daftar di sebelah kiri: jenis yang sama, status sesuai filter (default semua supaya item terpilih ikut tampil).
        $status = in_array($request->query('status'), ['menunggu', 'diterima', 'ditolak', 'semua'], true)
            ? $request->query('status')
            : 'semua';
        [$model] = self::TYPES[$type];
        $items = $model::with('user')
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $waitingCounts = collect(self::TYPES)->map(fn ($config) => $config[0]::where('status', 'menunggu')->count());

        return view('admin.submissions.show', [
            'type' => $type,
            'status' => $status,
            'items' => $items,
            'waitingCounts' => $waitingCounts,
            'selected' => $item,
        ]);
    }

    /** Lampiran atau foto dari storage private, hanya untuk admin yang login. */
    public function file(string $type, int $id): StreamedResponse
    {
        $path = $this->find($type, $id)->{self::TYPES[$type][2]};

        abort_unless($path && Storage::exists($path), 404, 'File tidak ditemukan.');

        return Storage::response($path);
    }

    public function review(ReviewSubmissionRequest $request, SubmissionReviewer $reviewer, string $type, int $id): RedirectResponse
    {
        $item = $this->find($type, $id);

        $reviewer->review($item, $request->user(), $request->status, $request->admin_note);

        return redirect()
            ->route('admin.pengajuan.show', [$type, $id])
            ->with('success', self::TYPES[$type][1].' '.($request->status === 'diterima' ? 'diterima.' : 'ditolak.'));
    }

    private function find(string $type, int $id): Model
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        return self::TYPES[$type][0]::findOrFail($id);
    }
}
