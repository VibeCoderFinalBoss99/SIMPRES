<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFieldDutyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'location_name' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'foto' => ['required', 'file', 'mimes:jpg,jpeg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Tanggal tugas lapangan tidak boleh setelah hari ini.',
            'foto.required' => 'Foto kegiatan wajib dilampirkan (jpg, maksimal 2 MB).',
            'foto.mimes' => 'Foto harus berformat jpg.',
        ];
    }
}
