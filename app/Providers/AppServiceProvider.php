<?php

namespace App\Providers;

use App\Contracts\BinaryInstaller;
use App\Contracts\CronManager;
use App\Contracts\FeedFetcher;
use App\Contracts\VideoMetadataFetcher;
use App\Contracts\YtDlpInstaller;
use App\Services\HomebrewYtDlpInstaller;
use App\Services\LocalBinaryInstaller;
use App\Services\SystemCronManager;
use App\Services\YoutubeFeedFetcher;
use App\Services\YtDlpVideoMetadataFetcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FeedFetcher::class, YoutubeFeedFetcher::class);
        $this->app->singleton(VideoMetadataFetcher::class, YtDlpVideoMetadataFetcher::class);
        $this->app->singleton(CronManager::class, SystemCronManager::class);
        $this->app->singleton(BinaryInstaller::class, LocalBinaryInstaller::class);
        $this->app->singleton(YtDlpInstaller::class, HomebrewYtDlpInstaller::class);
    }
}
