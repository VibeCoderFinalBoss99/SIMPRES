<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Halaman Konfigurasi: aturan yang dipakai server, tanpa mengubah aplikasi. */
class ConfigController extends Controller
{
    public function edit(): View
    {
        return view('admin.config', ['values' => Settings::all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (Settings::FIELDS as $fields) {
            foreach ($fields as $key => [$label, $default, $type]) {
                $rules[$key] = match ($type) {
                    'number' => ['required', 'integer', 'min:0', 'max:100000'],
                    'text' => ['required', 'string', 'max:50'],
                    default => ['nullable'],
                };
            }
        }
        $data = $request->validate($rules, [], collect(Settings::FIELDS)->flatMap(fn ($f) => collect($f)->map(fn ($v) => $v[0]))->all());

        $values = [];
        foreach (Settings::FIELDS as $fields) {
            foreach ($fields as $key => [$label, $default, $type]) {
                $values[$key] = match ($type) {
                    'number' => (int) $data[$key],
                    'text' => $data[$key],
                    default => $request->boolean($key),
                };
            }
        }
        Settings::save($values, $request->user()->name);

        return redirect()->route('admin.konfigurasi.edit')->with('success', 'Konfigurasi disimpan.');
    }
}
