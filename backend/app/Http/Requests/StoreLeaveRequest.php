<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:izin_keluar,sakit,cuti,lainnya'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_if:type,izin_keluar', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'required_if:type,izin_keluar', 'date_format:H:i,H:i:s', 'after:start_time'],
            'reason' => ['required', 'string', 'max:1000'],
            'lampiran' => ['nullable', 'required_if:type,sakit', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'lampiran.required_if' => 'Izin sakit wajib melampirkan foto surat keterangan (jpg/png).',
        ];
    }
}
