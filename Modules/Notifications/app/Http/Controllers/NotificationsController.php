<?php

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->integer('per_page', 15), 50);

        $notifications = $user->notifications()->paginate($perPage);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'preferences' => [
                'notifications_enabled' => $user->notifications_enabled,
                'email_notifications' => $user->email_notifications,
                'push_notifications' => $user->push_notifications,
            ],
            'data' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marquée comme lue.',
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Toutes les notifications ont été marquées comme lues.',
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notifications_enabled' => ['nullable', 'boolean'],
            'email_notifications' => ['nullable', 'boolean'],
            'push_notifications' => ['nullable', 'boolean'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Préférences de notification mises à jour.',
            'preferences' => [
                'notifications_enabled' => $request->user()->notifications_enabled,
                'email_notifications' => $request->user()->email_notifications,
                'push_notifications' => $request->user()->push_notifications,
            ],
        ]);
    }
}
