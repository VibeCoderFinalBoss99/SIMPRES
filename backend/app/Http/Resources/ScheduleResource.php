<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('Y-m-d'),
            'is_day_off' => $this->is_day_off,
            // true = dari shift tetap pegawai (bukan diatur khusus di roster); id bernilai null.
            'is_default' => ! $this->exists,
            'shift' => $this->shift ? [
                'id' => $this->shift->id,
                'name' => $this->shift->name,
                'start_time' => $this->shift->start_time,
                'end_time' => $this->shift->end_time,
                'late_tolerance_min' => $this->shift->late_tolerance_min,
            ] : null,
        ];
    }
}
