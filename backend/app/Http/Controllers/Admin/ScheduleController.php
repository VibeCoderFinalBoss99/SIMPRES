<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Jadwal kerja pegawai per bulan: pilih pegawai, lalu atur shift atau libur tiap tanggal. */
class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'user_id' => ['nullable', 'integer']]);

        $month = Carbon::createFromFormat('Y-m-d', ($request->month ?? now()->format('Y-m')).'-01');
        $employees = User::where('role', 'pegawai')->where('is_active', true)->orderBy('name')->get();
        $selected = $request->user_id ? $employees->firstWhere('id', (int) $request->user_id) : null;

        $schedules = $selected
            ? Schedule::where('user_id', $selected->id)
                ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->get()
                ->keyBy(fn ($schedule) => $schedule->date->toDateString())
            : collect();

        return view('admin.schedules.index', [
            'month' => $month,
            'days' => CarbonPeriod::create($month, $month->copy()->endOfMonth()),
            'employees' => $employees,
            'selected' => $selected,
            'schedules' => $schedules,
            'shifts' => Shift::orderBy('start_time')->get(),
        ]);
    }

    /** Tambah satu jadwal. Pegawai dan tanggal yang sama tidak boleh dobel. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => [
                'required', 'date_format:Y-m-d',
                Rule::unique('schedules')->where('user_id', $request->user_id),
            ],
            'shift_id' => ['nullable', 'required_unless:is_day_off,1', 'exists:shifts,id'],
            'is_day_off' => ['nullable', 'boolean'],
        ], [
            'date.unique' => 'Pegawai ini sudah punya jadwal di tanggal tersebut. Ubah lewat tabel jadwal bulanan.',
            'shift_id.required_unless' => 'Pilih shift, atau centang Libur.',
        ]);

        $isDayOff = $request->boolean('is_day_off');

        Schedule::create([
            'user_id' => $data['user_id'],
            'date' => $data['date'],
            'shift_id' => $isDayOff ? null : $data['shift_id'],
            'is_day_off' => $isDayOff,
        ]);

        return redirect()
            ->route('admin.jadwal.index', ['user_id' => $data['user_id'], 'month' => substr($data['date'], 0, 7)])
            ->with('success', 'Jadwal berhasil ditambahkan.');
    }

    /**
     * Simpan jadwal satu pegawai untuk satu bulan sekaligus.
     * Isi tiap tanggal: kosong (hapus jadwal), "libur", atau id shift.
     */
    public function updateMonth(Request $request): RedirectResponse
    {
        $shiftIds = Shift::pluck('id')->map(fn ($id) => (string) $id)->all();

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'month' => ['required', 'date_format:Y-m'],
            'days' => ['array'],
            'days.*' => ['nullable', Rule::in(array_merge(['libur'], $shiftIds))],
        ]);

        $month = Carbon::createFromFormat('Y-m-d', $data['month'].'-01');
        $days = $data['days'] ?? [];

        foreach (CarbonPeriod::create($month, $month->copy()->endOfMonth()) as $day) {
            $date = $day->toDateString();
            $value = $days[$date] ?? null;
            $schedule = Schedule::where('user_id', $data['user_id'])->whereDate('date', $date)->first();

            if ($value === null || $value === '') {
                $schedule?->delete();

                continue;
            }

            $values = $value === 'libur'
                ? ['shift_id' => null, 'is_day_off' => true]
                : ['shift_id' => (int) $value, 'is_day_off' => false];

            $schedule
                ? $schedule->update($values)
                : Schedule::create($values + ['user_id' => $data['user_id'], 'date' => $date]);
        }

        return redirect()
            ->route('admin.jadwal.index', ['user_id' => $data['user_id'], 'month' => $data['month']])
            ->with('success', 'Jadwal bulan '.$month->translatedFormat('F Y').' berhasil disimpan.');
    }
}
