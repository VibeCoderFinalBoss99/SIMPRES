<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->userNotifications()->latest()->latest('id')->paginate(20);

        return UserNotificationResource::collection($notifications)->additional([
            'unread_count' => $user->userNotifications()->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, int $id): JsonResponse
    {
        // Hanya notifikasi milik sendiri. Milik pegawai lain dijawab 404.
        $notification = $request->user()->userNotifications()->findOrFail($id);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'data' => new UserNotificationResource($notification),
            'unread_count' => $request->user()->userNotifications()->whereNull('read_at')->count(),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = $request->user()->userNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'message' => "{$count} notifikasi ditandai sudah dibaca.",
            'unread_count' => 0,
        ]);
    }
}
