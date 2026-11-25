<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
            'is_mock' => ['required', 'boolean'],
            'is_emulator' => ['nullable', 'boolean'],
            'client_uuid' => ['nullable', 'uuid'],
            'device_info' => ['nullable', 'string', 'max:255'],
            // offline = dikirim dari antrean HP saat sinyal kembali.
            'source' => ['nullable', 'in:online,offline'],
        ];
    }
}
