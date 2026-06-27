<?php

namespace App\Providers;

use App\Models\Feed;
use App\Models\FeedingRecord;
use App\Observers\FeedObserver;
use App\Observers\FeedingRecordObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method is called by Laravel during the service registration phase.
     * Use it to bind classes into the service container, but avoid doing any
     * heavy work here because it runs on every request.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * This method is called after all services have been registered.
     * Use it to perform tasks like registering observers, event listeners,
     * middleware, or any other bootstrapping logic.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register observer for FeedingRecord model to automatically
        // update HPP (Harga Pokok Produksi) whenever a feeding record
        // is created, updated, or deleted.
        FeedingRecord::observe(FeedingRecordObserver::class);

        // Register observer for Feed model to send real-time notifications
        // to administrators when feed stock falls below the threshold (100 kg).
        Feed::observe(FeedObserver::class);
    }
}