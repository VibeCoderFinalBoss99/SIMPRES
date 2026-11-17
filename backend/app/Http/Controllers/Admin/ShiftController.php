<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(): View
    {
        return view('admin.shifts.index', [
            'shifts' => Shift::withCount('schedules')->orderBy('start_time')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.shifts.form', ['shift' => new Shift(['late_tolerance_min' => 10])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shift = Shift::create($this->validated($request));

        return redirect()->route('admin.shift.index')->with('success', "Shift {$shift->name} berhasil ditambahkan.");
    }

    public function edit(Shift $shift): View
    {
        return view('admin.shifts.form', ['shift' => $shift]);
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $shift->update($this->validated($request));

        return redirect()->route('admin.shift.index')->with('success', "Shift {$shift->name} berhasil disimpan.");
    }

    /** Jadwal yang memakai shift ini tetap ada, kolom shift-nya menjadi kosong (ON DELETE SET NULL). */
    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete();

        return redirect()->route('admin.shift.index')->with('success', "Shift {$shift->name} dihapus.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['required', 'date_format:H:i,H:i:s'],
            'late_tolerance_min' => ['required', 'integer', 'min:0', 'max:240'],
        ], [], [
            'start_time' => 'jam masuk',
            'end_time' => 'jam pulang',
        ]);
    }
}
