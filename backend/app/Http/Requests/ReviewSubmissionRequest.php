<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Keputusan admin untuk izin, koreksi absensi, atau tugas lapangan (API dan Admin Web). */
class ReviewSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:diterima,ditolak'],
            'admin_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status harus diterima atau ditolak.',
        ];
    }
}
