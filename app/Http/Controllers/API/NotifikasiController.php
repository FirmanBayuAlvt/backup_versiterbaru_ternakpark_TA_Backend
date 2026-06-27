<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotifikasiController extends Controller
{
    /**
     * Daftar notifikasi untuk user yang sedang login.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $perPage = $request->input('per_page', 15);
        $notifications = Notification::where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = $notifications->map(function ($notification) {
            return [
                'id'         => $notification->id,
                'type'       => $notification->type,
                'title'      => $notification->data['title'] ?? 'Notifikasi',
                'message'    => $notification->data['message'] ?? '',
                'read_at'    => $notification->read_at,
                'created_at' => $notification->created_at->toISOString(),
                'data'       => $notification->data,
            ];
        });

        return response()->json([
            'success'    => true,
            'data'       => $data,
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
                'last_page'    => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * Jumlah notifikasi yang belum dibaca.
     *
     * @return JsonResponse
     */
    public function unreadCount(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $count = Notification::where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'count'   => $count
        ]);
    }

    /**
     * Menandai notifikasi sebagai sudah dibaca.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function markAsRead(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $notification = Notification::where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan'
            ], 404);
        }

        if (is_null($notification->read_at)) {
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Menandai semua notifikasi sebagai sudah dibaca.
     *
     * @return JsonResponse
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        Notification::where('notifiable_type', 'App\\Models\\User')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
