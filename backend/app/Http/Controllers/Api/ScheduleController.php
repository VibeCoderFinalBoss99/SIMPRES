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

        $schedules = $request->user()->schedules()
            ->with('shift')
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')
            ->get();

        return ScheduleResource::collection($schedules)->additional([
            'month' => $month->format('Y-m'),
        ]);
    }
}
