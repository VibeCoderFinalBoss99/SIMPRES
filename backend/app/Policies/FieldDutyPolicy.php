<?php

namespace App\Policies;

use App\Models\FieldDuty;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FieldDutyPolicy
{
    /** Pemilik dan admin boleh melihat tugas lapangan beserta fotonya. */
    public function view(User $user, FieldDuty $duty): Response
    {
        return $user->id === $duty->user_id || $user->role === 'admin'
            ? Response::allow()
            : Response::deny('Anda tidak punya akses ke tugas lapangan ini.');
    }

    public function delete(User $user, FieldDuty $duty): Response
    {
        if ($user->id !== $duty->user_id) {
            return Response::deny('Anda tidak punya akses ke tugas lapangan ini.');
        }

        return $duty->status === 'menunggu'
            ? Response::allow()
            : Response::deny('Tugas lapangan yang sudah diproses admin tidak bisa dibatalkan.');
    }
}
