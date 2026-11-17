<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ubah izin. Kolom yang tidak dikirim memakai nilai lama, jadi cukup kirim yang berubah.
 * Untuk mengganti lampiran dari Android: kirim POST multipart dengan _method=PUT.
 */
class UpdateLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $leave = $this->route('leave');

        $this->mergeIfMissing([
            'type' => $leave->type,
            'start_date' => $leave->start_date->format('Y-m-d'),
            'end_date' => $leave->end_date->format('Y-m-d'),
            'start_time' => $leave->start_time,
            'end_time' => $leave->end_time,
            'reason' => $leave->reason,
        ]);
    }

    public function rules(): array
    {
        $needsAttachment = $this->input('type') === 'sakit' && ! $this->route('leave')->attachment_path;

        return [
            'type' => ['required', 'in:izin_keluar,sakit,cuti,lainnya'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_if:type,izin_keluar', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'required_if:type,izin_keluar', 'date_format:H:i,H:i:s', 'after:start_time'],
            'reason' => ['required', 'string', 'max:1000'],
            'lampiran' => ['nullable', Rule::requiredIf($needsAttachment), 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'lampiran.required' => 'Izin sakit wajib melampirkan foto surat keterangan (jpg/png).',
        ];
    }
}
