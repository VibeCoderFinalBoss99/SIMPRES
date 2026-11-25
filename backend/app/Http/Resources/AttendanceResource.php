<?php

namespace App\Http\Resources;

use App\Services\AttendanceValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('Y-m-d'),
            'status' => $this->status,
            'late_minutes' => $this->late_minutes,
            'check_in_at' => $this->check_in_at?->format('Y-m-d H:i:s'),
            'check_in_lat' => $this->toFloat($this->check_in_lat),
            'check_in_lng' => $this->toFloat($this->check_in_lng),
            'check_in_accuracy_m' => $this->toFloat($this->check_in_accuracy_m),
            'check_in_distance_m' => $this->toFloat($this->check_in_distance_m),
            'check_out_at' => $this->check_out_at?->format('Y-m-d H:i:s'),
            'check_out_lat' => $this->toFloat($this->check_out_lat),
            'check_out_lng' => $this->toFloat($this->check_out_lng),
            'check_out_accuracy_m' => $this->toFloat($this->check_out_accuracy_m),
            'check_out_distance_m' => $this->toFloat($this->check_out_distance_m),
            'source' => $this->source,
            'client_uuid' => $this->client_uuid,
            // Lama kerja dan pulang lebih cepat, dihitung dari jam absen dan jadwal hari itu.
            'work_minutes' => $this->check_in_at && $this->check_out_at
                ? (int) floor($this->check_in_at->diffInMinutes($this->check_out_at)) : null,
            'early_leave_minutes' => $this->earlyLeave(),
        ];
    }

    private function earlyLeave(): int
    {
        if (! $this->check_out_at) {
            return 0;
        }
        $validator = app(AttendanceValidator::class);

        return $validator->earlyLeaveMinutes($validator->scheduleFor($this->user, $this->date->toDateString()),
            $this->date, $this->check_out_at);
    }

    private function toFloat($value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
