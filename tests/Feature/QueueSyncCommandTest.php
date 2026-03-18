<?php

use App\Config\AppConfig;
use App\Config\AppConfigStore;
use App\Contracts\FeedFetcher;
use App\Services\QueueRepository;

it('syncs and deduplicates feed items into local state', function () {
    $home = testHome();
    $binary = fakeBinary($home.'/bin/yt-dlp');
    $config = new AppConfig(
        playlistFeedUrl: 'https://example.com/feed.xml',
        vaultRoot: $home.'/vault',
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 3,
        sectionHeading: 'Watch This Week',
        dataDirectory: $home.'/data',
        ytDlpPath: $binary,
        scheduleEnabled: true,
    );

    app(AppConfigStore::class)->save($config);

    app()->instance(FeedFetcher::class, new class implements FeedFetcher
    {
        public function fetch(string $url): array
        {
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
        ->and($videos[0]['video_id'])->toBe('abc123');
});
