<?php

namespace App\Observers;

use App\Models\Feed;
use App\Helpers\NotificationHelper;

class FeedObserver
{
    /**
     * Handle the Feed "updated" event.
     * Jika stok pakan berubah dan menjadi kurang dari 100 kg, kirim notifikasi ke admin.
     *
     * @param  \App\Models\Feed  $feed
     * @return void
     */
    public function updated(Feed $feed): void
    {
        // Cek apakah kolom current_stock berubah, stok sekarang di bawah threshold (100 kg), dan feed masih aktif
        if ($feed->wasChanged('current_stock') && $feed->current_stock < 100 && $feed->is_active) {
            NotificationHelper::sendToAdmins(
                'Stok Pakan Menipis',
                "Stok {$feed->name} tersisa {$feed->current_stock} kg. Segera lakukan restok.",
                'warning',
                ['feed_id' => $feed->id, 'current_stock' => $feed->current_stock]
            );
        }
    }

    /**
     * Handle the Feed "created" event.
     * Jika feed baru ditambahkan dengan stok awal kurang dari 100 kg, kirim notifikasi ke admin.
     *
     * @param  \App\Models\Feed  $feed
     * @return void
     */
    public function created(Feed $feed): void
    {
        // Jika feed aktif dan stok awal < 100 kg
        if ($feed->current_stock < 100 && $feed->is_active) {
            NotificationHelper::sendToAdmins(
                'Stok Pakan Menipis',
                "Stok {$feed->name} awal hanya {$feed->current_stock} kg. Segera lakukan restok.",
                'warning',
                ['feed_id' => $feed->id, 'current_stock' => $feed->current_stock]
            );
        }
    }
}