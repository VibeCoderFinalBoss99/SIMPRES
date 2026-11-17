<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Data pegawai untuk aplikasi Android. Password tidak pernah ikut. */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nip' => $this->nip,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'unit' => $this->unit,
            'role' => $this->role,
            'work_location' => $this->whenLoaded('workLocation', fn () => $this->workLocation ? [
                'id' => $this->workLocation->id,
                'name' => $this->workLocation->name,
                'latitude' => (float) $this->workLocation->latitude,
                'longitude' => (float) $this->workLocation->longitude,
                'radius_m' => $this->workLocation->radius_m,
            ] : null),
        ];
    }
}
