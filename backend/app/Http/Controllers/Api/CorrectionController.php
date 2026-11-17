<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCorrectionRequest;
use App\Http\Resources\AttendanceCorrectionResource;
use App\Models\AttendanceCorrection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CorrectionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['status' => ['nullable', 'in:menunggu,diterima,ditolak']]);

        $corrections = $request->user()->attendanceCorrections()
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return AttendanceCorrectionResource::collection($corrections);
    }

    public function store(StoreCorrectionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->except('lampiran');
        $data['status'] = 'menunggu';
        $data['attendance_id'] = $user->attendances()->whereDate('date', $data['date'])->value('id');

        if ($request->hasFile('lampiran')) {
            $data['attachment_path'] = $request->file('lampiran')->store('lampiran-koreksi');
        }

        $correction = $user->attendanceCorrections()->create($data)->refresh();

        return response()->json([
            'message' => 'Pengajuan koreksi absensi berhasil dikirim dan menunggu konfirmasi admin.',
            'data' => new AttendanceCorrectionResource($correction),
        ], 201);
    }

    public function show(AttendanceCorrection $correction): AttendanceCorrectionResource
    {
        Gate::authorize('view', $correction);

        return new AttendanceCorrectionResource($correction);
    }

    public function destroy(AttendanceCorrection $correction): JsonResponse
    {
        Gate::authorize('delete', $correction);

        if ($correction->attachment_path) {
            Storage::delete($correction->attachment_path);
        }

        $correction->delete();

        return response()->json(['message' => 'Pengajuan koreksi berhasil dibatalkan.']);
    }
}
