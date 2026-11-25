<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkLocation;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Halaman Konfigurasi: aturan yang dipakai server saat validasi absensi, tanpa mengubah aplikasi. */
class ConfigController extends Controller
{
    public function edit(): View
    {
        return view('admin.config', ['values' => Settings::all(), 'location' => WorkLocation::first()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = ['radius_m' => ['nullable', 'integer', 'min:10', 'max:1000']];
        $names = ['radius_m' => 'radius absensi'];
        foreach (Settings::FIELDS as $fields) {
            foreach ($fields as $key => [$label, $default, $type, $min, $max]) {
                $rules[$key] = match ($type) {
                    'number' => ['required', 'integer', "min:{$min}", "max:{$max}"],
                    'list' => ['nullable', 'string', 'max:5000', $this->holidayRule()],
                    default => ['nullable'],
                };
                $names[$key] = mb_strtolower($label);
            }
        }
        $data = $request->validate($rules, [], $names);

        $values = [];
        foreach (Settings::FIELDS as $fields) {
            foreach ($fields as $key => [$label, $default, $type]) {
                $values[$key] = match ($type) {
                    'number' => (int) $data[$key],
                    'list' => trim(preg_replace('/\R+/', "\n", (string) ($data[$key] ?? ''))),
                    default => $request->boolean($key),
                };
            }
        }
        Settings::save($values, $request->user()->name);

        // Radius absensi disimpan langsung di lokasi kerja, jadi sama dengan halaman Lokasi Kerja.
        $location = WorkLocation::first();
        if ($location && isset($data['radius_m']) && (int) $data['radius_m'] !== (int) $location->radius_m) {
            $location->update(['radius_m' => (int) $data['radius_m']]);
        }

        return redirect()->route('admin.konfigurasi.edit')->with('success', 'Konfigurasi disimpan dan langsung dipakai server.');
    }

    /** Setiap baris daftar libur harus diawali tanggal YYYY-MM-DD yang benar. */
    private function holidayRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            foreach (preg_split('/\R/', (string) $value) as $i => $line) {
                if (trim($line) === '') {
                    continue;
                }
                if (! preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})\b/', $line, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                    $fail('Baris '.($i + 1).' hari libur harus diawali tanggal YYYY-MM-DD, contoh: 2026-12-25 Hari Raya Natal.');

                    return;
                }
            }
        };
    }
}
