<?php

namespace App\Commands;

use App\Config\AppConfigStore;
use App\Contracts\FeedFetcher;
use App\Services\QueueRepository;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class QueueSyncCommand extends Command
{
    protected $signature = 'queue:sync';

    protected $description = 'Fetch the dedicated playlist feed and persist a normalized queue snapshot.';

    public function handle(AppConfigStore $store, QueueRepository $repository, FeedFetcher $feedFetcher): int
    {
        $config = $store->load();
        $existing = collect($repository->load($config))->keyBy('video_id');
        $fresh = collect($feedFetcher->fetch($config->playlistFeedUrl))
            ->keyBy('video_id')
            ->map(function (array $video) use ($existing): array {
                /** @var array<string, mixed> $persisted */
                $persisted = $existing->get($video['video_id'], []);

                return array_merge($persisted, $video);
            })
            ->values()
            ->all();

        $repository->save($config, $fresh);

        $this->info(sprintf('Synced %d videos.', count($fresh)));

        return self::SUCCESS;
    }

    public function schedule(Schedule $schedule): void
    {
        $schedule->command(static::class)->dailyAt(config('yt_suggestions.daily_sync_time'));
    }
}
