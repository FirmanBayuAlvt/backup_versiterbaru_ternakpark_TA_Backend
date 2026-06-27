<?php

namespace App\Console;

use App\Helpers\NotificationHelper;
use App\Models\Feed;
use App\Models\Livestock;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\ExportTrainingData::class,
        \App\Console\Commands\PopulateHppReal::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Jalankan pengecekan stok dan kesehatan setiap jam
        $schedule->call(function () {
            // Cek stok pakan rendah (kurang dari 100 kg)
            $feeds = Feed::where('is_active', true)
                ->where('current_stock', '<', 100)
                ->get();

            foreach ($feeds as $feed) {
                NotificationHelper::sendToAdmins(
                    'Stok Pakan Menipis',
                    "Stok {$feed->name} tersisa {$feed->current_stock} kg",
                    'warning'
                );
            }

            // Cek kesehatan ternak (status fair atau poor)
            $sickLivestocks = Livestock::whereIn('health_status', ['fair', 'poor'])->get();

            foreach ($sickLivestocks as $livestock) {
                NotificationHelper::sendToAdmins(
                    'Peringatan Kesehatan',
                    "Ternak {$livestock->ear_tag} status kesehatan {$livestock->health_status}",
                    'danger'
                );
            }
        })->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
