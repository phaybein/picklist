<?php

namespace App\Providers;

use App\Contracts\BinaryInstaller;
use App\Contracts\CronManager;
use App\Contracts\FeedFetcher;
use App\Contracts\VideoMetadataFetcher;
use App\Services\LocalBinaryInstaller;
use App\Services\SystemCronManager;
use App\Services\YoutubeFeedFetcher;
use App\Services\YtDlpVideoMetadataFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
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
        $this->app->singleton(ClientInterface::class, fn (): ClientInterface => new Client([
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'picklist/1.0',
            ],
        ]));

        $this->app->singleton(FeedFetcher::class, YoutubeFeedFetcher::class);
        $this->app->singleton(VideoMetadataFetcher::class, YtDlpVideoMetadataFetcher::class);
        $this->app->singleton(CronManager::class, SystemCronManager::class);
        $this->app->singleton(BinaryInstaller::class, LocalBinaryInstaller::class);
    }
}
