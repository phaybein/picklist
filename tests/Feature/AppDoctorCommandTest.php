<?php

use App\Config\AppConfig;
use App\Config\AppConfigStore;
use App\Contracts\CronManager;
use App\Contracts\FeedFetcher;
use Illuminate\Filesystem\Filesystem;

it('fails when the app is not installed', function () {
    $this->artisan('doctor')
        ->expectsOutputToContain('[FAIL] Config is missing')
        ->assertExitCode(1);
});

it('reports passing checks for a healthy installation', function () {
    $filesystem = app(Filesystem::class);
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $dataDirectory = $home.'/data';
    $binary = fakeBinary($home.'/bin/yt-dlp');

    $filesystem->ensureDirectoryExists($vaultRoot);

    app(AppConfigStore::class)->save(new AppConfig(
        playlistFeedUrl: 'https://example.com/feed.xml',
        vaultRoot: $vaultRoot,
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 5,
        sectionHeading: 'Watch This Week',
        dataDirectory: $dataDirectory,
        ytDlpPath: $binary,
        scheduleEnabled: true,
    ));

    app()->instance(FeedFetcher::class, new class implements FeedFetcher
    {
        public function fetch(string $url): array
        {
            return [[
                'video_id' => 'abc123',
                'url' => 'https://youtube.com/watch?v=abc123',
                'title' => 'Test video',
                'channel' => 'Channel',
                'published_at' => '2026-03-17T00:00:00+00:00',
                'feed_seen_at' => '2026-03-17T00:00:00+00:00',
            ]];
        }
    });

    app()->instance(CronManager::class, new class implements CronManager
    {
        public function current(): string
        {
            return $this->entry();
        }

        public function install(string $contents): void {}

        public function entry(): string
        {
            return '* * * * * cd /tmp && /opt/homebrew/bin/php picklist schedule:run >> /dev/null 2>&1';
        }

        public function hasEntry(): bool
        {
            return true;
        }
    });

    $this->artisan('doctor')
        ->expectsOutputToContain('[PASS] PHP version')
        ->expectsOutputToContain('[PASS] Config file')
        ->expectsOutputToContain('[PASS] Schedule status')
        ->assertExitCode(0);
});
