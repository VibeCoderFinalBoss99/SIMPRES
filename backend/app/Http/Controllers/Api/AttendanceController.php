<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Http\Requests\CheckOutRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Services\AttendanceValidator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceValidator $validator) {}

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();
        $attendance = $user->attendances()->whereDate('date', $today)->first();
        $schedule = $this->validator->scheduleFor($user, $today);

        return response()->json([
            'data' => [
                'date' => $today,
                'schedule' => $schedule ? [
                    'is_day_off' => $schedule->is_day_off,
                    'shift' => $schedule->shift?->name,
                    'start_time' => $schedule->shift?->start_time,
                    'end_time' => $schedule->shift?->end_time,
                ] : null,
                'has_checked_in' => (bool) $attendance?->check_in_at,
                'has_checked_out' => (bool) $attendance?->check_out_at,
                'attendance' => $attendance ? new AttendanceResource($attendance) : null,
            ],
        ]);
    }

    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $user = $request->user();

        // Kiriman ulang dari HP (misalnya sinyal putus): kembalikan data yang sudah tersimpan.
        if ($request->client_uuid) {
            $existing = Attendance::where('client_uuid', $request->client_uuid)->first();

            if ($existing && $existing->user_id === $user->id) {
                return response()->json([
                    'message' => 'Check-in ini sudah tercatat sebelumnya.',
                    'data' => new AttendanceResource($existing),
                ]);
            }

            if ($existing) {
                return response()->json(['message' => 'client_uuid sudah dipakai. Buat UUID baru.'], 422);
            }
        }

        $result = $this->validator->checkIn(
            $user, $request->float('latitude'), $request->float('longitude'),
            $request->float('accuracy'), $request->boolean('is_mock'), null, $request->boolean('is_emulator'),
        );

        if (! $result['allowed']) {
            return $this->rejected($result);
        }

        try {
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'schedule_id' => $result['schedule_id'],
                'date' => now()->toDateString(),
                'check_in_at' => now(),
                'check_in_lat' => $request->latitude,
                'check_in_lng' => $request->longitude,
                'check_in_accuracy_m' => $request->accuracy,
                'check_in_distance_m' => $result['distance_m'],
                'status' => $result['status'],
                'late_minutes' => $result['late_minutes'],
                'source' => $request->input('source', 'online'),
                'client_uuid' => $request->client_uuid,
                'device_info' => $request->device_info,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dua request check-in datang bersamaan: yang kedua ditolak oleh unique (user_id, date).
            return response()->json(['message' => 'Anda sudah check-in hari ini.'], 422);
        }

        $message = $attendance->status === 'terlambat'
            ? "Check-in berhasil. Anda terlambat {$attendance->late_minutes} menit."
            : 'Check-in berhasil.';

        return response()->json([
            'message' => $message,
            'data' => new AttendanceResource($attendance),
        ], 201);
    }

    public function checkOut(CheckOutRequest $request): JsonResponse
    {
        $result = $this->validator->checkOut(
            $request->user(), $request->float('latitude'), $request->float('longitude'),
            $request->float('accuracy'), $request->boolean('is_mock'), null, $request->boolean('is_emulator'),
        );

        if (! $result['allowed']) {
            return $this->rejected($result);
        }

        $attendance = $result['attendance'];
        $attendance->update([
            'check_out_at' => now(),
            'check_out_lat' => $request->latitude,
            'check_out_lng' => $request->longitude,
            'check_out_accuracy_m' => $request->accuracy,
            'check_out_distance_m' => $result['distance_m'],
        ]);

        $early = $this->validator->earlyLeaveMinutes(
            $this->validator->scheduleFor($request->user(), $attendance->date->toDateString()),
            $attendance->date, $attendance->check_out_at,
        );

        return response()->json([
            'message' => $early > 0 ? "Check-out berhasil. Anda pulang {$early} menit lebih cepat dari jadwal." : 'Check-out berhasil.',
            'data' => new AttendanceResource($attendance),
        ]);
    }

    public function index(Request $request)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $month = Carbon::createFromFormat('Y-m-d', ($request->month ?? now()->format('Y-m')).'-01');

        $attendances = $request->user()->attendances()
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('date')
            ->get();

        return AttendanceResource::collection($attendances)->additional([
            'month' => $month->format('Y-m'),
        ]);
    }

    public function show(Request $request, int $id): AttendanceResource
    {
        // Hanya absensi milik sendiri. Milik pegawai lain dijawab 404.
        return new AttendanceResource($request->user()->attendances()->findOrFail($id));
    }

    private function rejected(array $result): JsonResponse
    {
        return response()->json([
            'message' => $result['reason'],
            'distance_m' => $result['distance_m'],
        ], 422);
    }
}
