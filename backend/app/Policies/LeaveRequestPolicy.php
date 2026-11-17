<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LeaveRequestPolicy
{
    /** Pemilik dan admin boleh melihat izin beserta lampirannya. */
    public function view(User $user, LeaveRequest $leave): Response
    {
        return $user->id === $leave->user_id || $user->role === 'admin'
            ? Response::allow()
            : Response::deny('Anda tidak punya akses ke izin ini.');
    }

    public function update(User $user, LeaveRequest $leave): Response
    {
        return $this->ownWaiting($user, $leave);
    }

    public function delete(User $user, LeaveRequest $leave): Response
    {
        return $this->ownWaiting($user, $leave);
    }

    private function ownWaiting(User $user, LeaveRequest $leave): Response
    {
        if ($user->id !== $leave->user_id) {
            return Response::deny('Anda tidak punya akses ke izin ini.');
        }

        return $leave->status === 'menunggu'
            ? Response::allow()
            : Response::deny('Izin yang sudah diproses admin tidak bisa diubah atau dibatalkan.');
    }
}
