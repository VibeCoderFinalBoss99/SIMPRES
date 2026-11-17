<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttendanceCorrectionPolicy
{
    public function view(User $user, AttendanceCorrection $correction): Response
    {
        return $user->id === $correction->user_id || $user->role === 'admin'
            ? Response::allow()
            : Response::deny('Anda tidak punya akses ke koreksi ini.');
    }

    public function delete(User $user, AttendanceCorrection $correction): Response
    {
        if ($user->id !== $correction->user_id) {
            return Response::deny('Anda tidak punya akses ke koreksi ini.');
        }

        return $correction->status === 'menunggu'
            ? Response::allow()
            : Response::deny('Koreksi yang sudah diproses admin tidak bisa dibatalkan.');
    }
}
