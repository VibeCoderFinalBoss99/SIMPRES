<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequest;
use App\Http\Requests\UpdateLeaveRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController extends Controller
{
    private const FOLDER = 'lampiran-izin';

    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:menunggu,diterima,ditolak']]);

        $leaves = $request->user()->leaveRequests()
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return LeaveRequestResource::collection($leaves);
    }

    public function store(StoreLeaveRequest $request): JsonResponse
    {
        $data = $this->cleanTimes($request->safe()->except('lampiran'));
        $data['status'] = 'menunggu';

        if ($request->hasFile('lampiran')) {
            $data['attachment_path'] = $request->file('lampiran')->store(self::FOLDER);
        }

        $leave = $request->user()->leaveRequests()->create($data)->refresh();

        return response()->json([
            'message' => 'Pengajuan izin berhasil dikirim dan menunggu konfirmasi admin.',
            'data' => new LeaveRequestResource($leave),
        ], 201);
    }

    public function show(LeaveRequest $leave): LeaveRequestResource
    {
        Gate::authorize('view', $leave);

        return new LeaveRequestResource($leave);
    }

    public function update(UpdateLeaveRequest $request, LeaveRequest $leave): JsonResponse
    {
        Gate::authorize('update', $leave);

        $data = $this->cleanTimes($request->safe()->except('lampiran'));

        if ($request->hasFile('lampiran')) {
            $old = $leave->attachment_path;
            $data['attachment_path'] = $request->file('lampiran')->store(self::FOLDER);
        }

        $leave->update($data);

        if (isset($old)) {
            Storage::delete($old);
        }

        return response()->json([
            'message' => 'Pengajuan izin berhasil diubah.',
            'data' => new LeaveRequestResource($leave->refresh()),
        ]);
    }

    public function destroy(LeaveRequest $leave): JsonResponse
    {
        Gate::authorize('delete', $leave);

        if ($leave->attachment_path) {
            Storage::delete($leave->attachment_path);
        }

        $leave->delete();

        return response()->json(['message' => 'Pengajuan izin berhasil dibatalkan.']);
    }

    public function attachment(LeaveRequest $leave): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $leave);

        if (! $leave->attachment_path || ! Storage::exists($leave->attachment_path)) {
            return response()->json(['message' => 'Izin ini tidak punya lampiran.'], 404);
        }

        return Storage::response($leave->attachment_path);
    }

    /** Jam mulai/selesai hanya dipakai untuk izin keluar. */
    private function cleanTimes(array $data): array
    {
        if ($data['type'] !== 'izin_keluar') {
            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        return $data;
    }
}
