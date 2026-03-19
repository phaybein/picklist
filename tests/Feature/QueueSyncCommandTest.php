<?php

use App\Config\AppConfig;
use App\Config\AppConfigStore;
use App\Contracts\FeedFetcher;
use App\Services\QueueRepository;

it('syncs and deduplicates feed items into local state', function () {
    $home = testHome();
    $binary = fakeBinary($home.'/bin/yt-dlp');
    $config = new AppConfig(
        playlistId: 'PLabc123',
        vaultRoot: $home.'/vault',
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 3,
        sectionHeading: 'Watch This Week',
        ytDlpCookiesFromBrowser: 'safari',
        dataDirectory: $home.'/data',
        ytDlpPath: $binary,
        scheduleEnabled: true,
    );

    app(AppConfigStore::class)->save($config);

    $state = (object) ['seenUrl' => null, 'cookies' => null];

    app()->instance(FeedFetcher::class, new class($state) implements FeedFetcher
    {
        public function __construct(private object $state) {}

        public function fetch(AppConfig $config): array
        {
            $this->state->seenUrl = $config->playlistUrl();
            $this->state->cookies = $config->ytDlpCookiesFromBrowser;

            return [
                [
                    'video_id' => 'abc123',
                    'url' => 'https://youtube.com/watch?v=abc123',
                    'title' => 'First',
                    'channel' => 'Channel One',
                    'published_at' => '2026-03-15T00:00:00+00:00',
                    'feed_seen_at' => '2026-03-17T00:00:00+00:00',
                ],
                [
                    'video_id' => 'abc123',
                    'url' => 'https://youtube.com/watch?v=abc123',
                    'title' => 'First duplicate',
                    'channel' => 'Channel One',
                    'published_at' => '2026-03-15T00:00:00+00:00',
                    'feed_seen_at' => '2026-03-17T00:00:00+00:00',
                ],
            ];
        }
    });

    $this->artisan('queue:sync')->assertExitCode(0);

    $videos = app(QueueRepository::class)->load($config);

    expect($videos)->toHaveCount(1)
        ->and($videos[0]['video_id'])->toBe('abc123')
        ->and($state->seenUrl)->toBe('https://www.youtube.com/playlist?list=PLabc123')
        ->and($state->cookies)->toBe('safari');
});
