<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tambah dan ubah pegawai di Admin Web. */
class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user'); // null saat tambah

        return [
            'nip' => ['required', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($user)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'position' => ['required', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'role' => ['required', 'in:pegawai,admin'],
            'work_location_id' => ['nullable', 'exists:work_locations,id'],
            'work_type' => ['required', 'in:non_shift,shift,petugas_lapangan'],
            // Pegawai non-shift dan petugas lapangan wajib punya shift tetap; pegawai shift diatur di roster.
            'default_shift_id' => [
                Rule::requiredIf(fn () => $this->input('role') === 'pegawai' && $this->input('work_type') !== 'shift'),
                'nullable', 'exists:shifts,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nip.unique' => 'NIP sudah dipakai pegawai lain.',
            'email.unique' => 'Email sudah dipakai pegawai lain.',
            'password.required' => 'Password awal wajib diisi.',
            'default_shift_id.required' => 'Pilih shift tetap untuk pegawai non-shift atau petugas lapangan.',
        ];
    }
}
