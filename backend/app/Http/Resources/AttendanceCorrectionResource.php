<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceCorrectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attendance_id' => $this->attendance_id,
            'date' => $this->date->format('Y-m-d'),
            'corrected_check_in' => $this->corrected_check_in,
            'corrected_check_out' => $this->corrected_check_out,
            'reason' => $this->reason,
            'has_attachment' => (bool) $this->attachment_path,
            'status' => $this->status,
            'admin_note' => $this->admin_note,
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
