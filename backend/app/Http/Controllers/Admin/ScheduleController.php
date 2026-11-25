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
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'user_id' => ['nullable', 'integer'],
            'minggu' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $month = Carbon::createFromFormat('Y-m-d', ($request->month ?? now()->format('Y-m')).'-01');
        $employees = User::with('defaultShift')->where('role', 'pegawai')->where('is_active', true)->orderBy('name')->get();
        $selected = $request->user_id ? $employees->firstWhere('id', (int) $request->user_id) : null;

        // Roster mingguan (Senin s.d. Minggu): jadwal roster ditambah jadwal tetap pegawai.
        $weekStart = Carbon::parse($request->minggu ?? now()->toDateString())->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(6);
        $week = $employees->mapWithKeys(fn ($employee) => [$employee->id => $employee->schedulesBetween($weekStart, $weekEnd)]);

        $schedules = $selected
            ? Schedule::where('user_id', $selected->id)
                ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->get()
                ->keyBy(fn ($schedule) => $schedule->date->toDateString())
            : collect();

        return view('admin.schedules.index', [
            'month' => $month,
            'weekStart' => $weekStart,
            'weekDays' => CarbonPeriod::create($weekStart, $weekEnd),
            'week' => $week,
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
            ->to(route('admin.jadwal.index', ['user_id' => $data['user_id'], 'month' => $data['month']]).'#bulanan')
            ->with('success', 'Jadwal bulan '.$month->translatedFormat('F Y').' berhasil disimpan.');
    }

    /**
     * Isi jadwal sekaligus untuk beberapa pegawai dan beberapa bulan (maksimal 12 bulan).
     * Hari yang dicentang diisi shift terpilih; hari lain diisi Libur jika diminta.
     * Jadwal yang sudah ada hanya ditimpa jika "timpa" dicentang.
     */
    public function fill(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'from' => ['required', 'date_format:Y-m'],
            'to' => ['required', 'date_format:Y-m', 'after_or_equal:from'],
            'shift_id' => ['required', 'exists:shifts,id'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'other_days_off' => ['nullable', 'boolean'],
            'overwrite' => ['nullable', 'boolean'],
        ], [
            'user_ids.required' => 'Pilih minimal satu pegawai.',
            'weekdays.required' => 'Centang minimal satu hari kerja.',
            'to.after_or_equal' => 'Bulan akhir tidak boleh sebelum bulan awal.',
        ]);

        $start = Carbon::createFromFormat('Y-m-d', $data['from'].'-01')->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['to'].'-01')->endOfMonth()->startOfDay();
        if ($start->copy()->addMonths(12)->lte($end)) {
            return back()->withInput()->withErrors(['to' => 'Rentang paling lama 12 bulan.']);
        }

        $workdays = array_map('intval', $data['weekdays']);
        $daysOff = $request->boolean('other_days_off');
        $overwrite = $request->boolean('overwrite');
        $saved = 0;

        foreach ($data['user_ids'] as $userId) {
            $existing = Schedule::where('user_id', $userId)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get()
                ->keyBy(fn ($schedule) => $schedule->date->toDateString());

            foreach (CarbonPeriod::create($start, $end) as $day) {
                $isWorkday = in_array($day->dayOfWeekIso, $workdays, true);
                if (! $isWorkday && ! $daysOff) {
                    continue;
                }

                $values = $isWorkday
                    ? ['shift_id' => (int) $data['shift_id'], 'is_day_off' => false]
                    : ['shift_id' => null, 'is_day_off' => true];
                $schedule = $existing[$day->toDateString()] ?? null;

                if ($schedule && ! $overwrite) {
                    continue;
                }
                $schedule
                    ? $schedule->update($values)
                    : Schedule::create($values + ['user_id' => $userId, 'date' => $day->toDateString()]);
                $saved++;
            }
        }

        return redirect()
            ->route('admin.jadwal.index')
            ->with('success', "{$saved} jadwal tersimpan untuk ".count($data['user_ids']).' pegawai ('
                .$start->translatedFormat('F Y').' s.d. '.$end->translatedFormat('F Y').').');
    }
}
