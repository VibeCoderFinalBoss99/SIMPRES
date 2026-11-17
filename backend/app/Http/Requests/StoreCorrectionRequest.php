<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $checkOutRules = ['nullable', 'required_without:corrected_check_in', 'date_format:H:i,H:i:s'];

        if ($this->filled('corrected_check_in')) {
            $checkOutRules[] = 'after:corrected_check_in';
        }

        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'corrected_check_in' => ['nullable', 'required_without:corrected_check_out', 'date_format:H:i,H:i:s'],
            'corrected_check_out' => $checkOutRules,
            'reason' => ['required', 'string', 'max:1000'],
            'lampiran' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Tanggal koreksi tidak boleh setelah hari ini.',
            'corrected_check_in.required_without' => 'Isi jam masuk atau jam pulang yang dikoreksi (minimal salah satu).',
            'corrected_check_out.required_without' => 'Isi jam masuk atau jam pulang yang dikoreksi (minimal salah satu).',
            'corrected_check_out.after' => 'Jam pulang harus setelah jam masuk.',
        ];
    }
}
