<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Titik Puskesmas dan radius absensi (satu lokasi kerja). */
class LocationController extends Controller
{
    public function edit(): View
    {
        return view('admin.location', [
            'location' => WorkLocation::first() ?? new WorkLocation(['radius_m' => 150]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'min:10', 'max:5000'],
        ]);

        $location = WorkLocation::first();

        if ($location) {
            $location->update($data);
        } else {
            WorkLocation::create($data + ['is_active' => true]);
        }

        return redirect()->route('admin.lokasi.edit')->with('success', 'Lokasi Puskesmas berhasil disimpan.');
    }
}
