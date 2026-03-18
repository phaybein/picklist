<?php

namespace App\Commands;

use App\Config\AppConfigStore;
use App\Services\DailyNotePublisher;
use App\Services\QueueRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class PublishWeeklyNoteCommand extends Command
{
    protected $signature = 'note:publish-weekly {--date= : Override the publish date (Y-m-d)}';

    protected $description = 'Publish the top weekly picks into the configured daily note.';

    public function handle(AppConfigStore $store, QueueRepository $repository, DailyNotePublisher $publisher): int
    {
        $config = $store->load();
        $date = $this->option('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->option('date'), $config->timezone)
            : CarbonImmutable::now($config->timezone);

        $queue = $repository->load($config);
        $videos = collect($queue)
            ->sortByDesc('final_rank')
            ->filter(fn (array $video): bool => empty($video['published_to_note_at']))
            ->take($config->weeklyPickCount)
            ->values()
            ->all();

        $path = $publisher->publish($config, $date, $videos);
        $publishedIds = array_column($videos, 'video_id');
        $publishedAt = $date->toIso8601String();

        $repository->save(
            $config,
            array_map(
                fn (array $video): array => in_array($video['video_id'] ?? null, $publishedIds, true)
                    ? $video + ['published_to_note_at' => $publishedAt]
                    : $video,
                $queue,
            ),
        );

        $this->info('Weekly note updated: '.$path);

        return self::SUCCESS;
    }

    public function schedule(Schedule $schedule): void
    {
        $schedule->command(static::class)->weeklyOn(1, config('yt_suggestions.weekly_publish_time'));
    }
}
