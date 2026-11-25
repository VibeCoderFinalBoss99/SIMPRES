<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduleResource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $month = Carbon::createFromFormat('Y-m-d', ($request->month ?? now()->format('Y-m')).'-01');

        // Jadwal roster ditambah jadwal tetap pegawai untuk tanggal yang tidak diatur di roster.
        $schedules = $request->user()->schedulesBetween($month, $month->copy()->endOfMonth())->values();

        return ScheduleResource::collection($schedules)->additional([
            'month' => $month->format('Y-m'),
        ]);
    }
}
