<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lokasi kerja: titik Puskesmas dan tempat dinas petugas lapangan, masing-masing dengan radius absensi.
 * Pegawai absen di lokasi yang dipilih untuknya di Data Pegawai.
 */
class LocationController extends Controller
{
    public function edit(Request $request): View
    {
        $location = $request->boolean('baru')
            ? new WorkLocation(['radius_m' => 150, 'is_active' => true])
            : ($request->filled('id') ? WorkLocation::findOrFail($request->integer('id')) : WorkLocation::orderBy('id')->first());

        return view('admin.location', ['location' => $location ?? new WorkLocation(['radius_m' => 150, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $location = WorkLocation::create($this->validated($request) + ['is_active' => true]);

        return redirect()->route('admin.lokasi.edit', ['id' => $location->id])
            ->with('success', "Lokasi {$location->name} ditambahkan. Pilih lokasi ini untuk pegawai di menu Data Pegawai.");
    }

    /** Tanpa id: lokasi pertama (Puskesmas), sama seperti sebelum ada banyak lokasi. */
    public function update(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $location = $request->filled('id') ? WorkLocation::findOrFail($request->integer('id')) : WorkLocation::orderBy('id')->first();

        if ($location) {
            $location->update($data);
        } else {
            $location = WorkLocation::create($data + ['is_active' => true]);
        }

        return redirect()->route('admin.lokasi.edit', $request->filled('id') ? ['id' => $location->id] : [])
            ->with('success', "Lokasi {$location->name} berhasil disimpan.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'min:10', 'max:1000'],
        ]);
    }
}
