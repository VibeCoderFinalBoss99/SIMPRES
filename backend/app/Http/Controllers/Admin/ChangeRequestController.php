<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Permintaan perubahan data pegawai (dari aplikasi) ditandai selesai setelah admin mengubah datanya. */
class ChangeRequestController extends Controller
{
    public function done(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id && $notification->type === 'perubahan_data', 404);

        $notification->update(['read_at' => now()]);

        return redirect()->route('admin.dashboard')->with('success', 'Permintaan perubahan data ditandai selesai.');
    }
}
