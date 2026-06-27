<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Str;

class NotificationHelper
{
    /**
     * Kirim notifikasi ke satu user.
     *
     * @param int $userId
     * @param string $title
     * @param string $message
     * @param string $type
     * @param array $additionalData
     * @return \App\Models\Notification
     */
    public static function send($userId, $title, $message, $type = 'info', $additionalData = [])
    {
        $notification = Notification::create([
            'id'               => Str::uuid()->toString(),
            'type'             => $type,
            'notifiable_type'  => 'App\Models\User',
            'notifiable_id'    => $userId,
            'data'             => array_merge([
                'title'   => $title,
                'message' => $message,
            ], $additionalData),
            'read_at'          => null,
        ]);

        return $notification;
    }

    /**
     * Kirim notifikasi ke semua user.
     *
     * @param string $title
     * @param string $message
     * @param string $type
     * @param array $additionalData
     * @return void
     */
    public static function sendToAll($title, $message, $type = 'info', $additionalData = [])
    {
        $users = User::all();
        foreach ($users as $user) {
            self::send($user->id, $title, $message, $type, $additionalData);
        }
    }

    /**
     * Kirim notifikasi ke semua administrator.
     *
     * @param string $title
     * @param string $message
     * @param string $type
     * @param array $additionalData
     * @return void
     */
    public static function sendToAdmins($title, $message, $type = 'info', $additionalData = [])
    {
        $admins = User::where('role', 'administrator')->get();
        foreach ($admins as $admin) {
            self::send($admin->id, $title, $message, $type, $additionalData);
        }
    }
}
