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
        ];
    }

    public function messages(): array
    {
        return [
            'nip.unique' => 'NIP sudah dipakai pegawai lain.',
            'email.unique' => 'Email sudah dipakai pegawai lain.',
            'password.required' => 'Password awal wajib diisi.',
        ];
    }
}
