<?php

use App\Config\AppConfig;
use App\Config\AppConfigStore;
use App\Services\QueueRepository;
use Illuminate\Filesystem\Filesystem;

it('publishes the top ranked videos into the configured daily note without touching other content', function () {
    $filesystem = app(Filesystem::class);
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $dataDirectory = $home.'/data';
    $binary = fakeBinary($home.'/bin/yt-dlp');
    $notePath = $vaultRoot.'/daily/03 March/17 March.md';

    $filesystem->ensureDirectoryExists(dirname($notePath));
    $filesystem->put($notePath, "# Existing note\n\n## Notes\n\nKeep this.\n");

    app(AppConfigStore::class)->save(new AppConfig(
        playlistFeedUrl: 'https://example.com/feed.xml',
        vaultRoot: $vaultRoot,
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 2,
        sectionHeading: 'Watch This Week',
        dataDirectory: $dataDirectory,
        ytDlpPath: $binary,
        scheduleEnabled: true,
    ));

    app(QueueRepository::class)->save(app(AppConfigStore::class)->load(), [
        [
            'video_id' => 'one',
            'title' => 'Video One',
            'url' => 'https://youtube.com/watch?v=one',
            'channel' => 'Channel One',
            'duration_seconds' => 900,
            'content_tier' => 'A Tier',
            'watch_reason' => 'Timely AI video worth prioritizing this week.',
            'final_rank' => 88,
        ],
        [
            'video_id' => 'two',
            'title' => 'Video Two',
            'url' => 'https://youtube.com/watch?v=two',
            'channel' => 'Channel Two',
            'duration_seconds' => 1200,
            'content_tier' => 'B Tier',
            'watch_reason' => 'Strong productivity fit with durable long-term value.',
            'final_rank' => 80,
        ],
    ]);

    $this->artisan('note:publish-weekly', ['--date' => '2026-03-17'])->assertExitCode(0);

    $updated = $filesystem->get($notePath);

    expect($updated)->toContain('## Watch This Week')
        ->and($updated)->toContain('[Video One](<https://youtube.com/watch?v=one>)')
        ->and($updated)->toContain('Keep this.');
});

it('escapes markdown-sensitive video fields before writing the managed note section', function () {
    $filesystem = app(Filesystem::class);
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $dataDirectory = $home.'/data';
    $binary = fakeBinary($home.'/bin/yt-dlp');
    $notePath = $vaultRoot.'/daily/03 March/17 March.md';

    $filesystem->ensureDirectoryExists(dirname($notePath));

    app(AppConfigStore::class)->save(new AppConfig(
        playlistFeedUrl: 'https://example.com/feed.xml',
        vaultRoot: $vaultRoot,
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 1,
        sectionHeading: 'Watch This Week',
        dataDirectory: $dataDirectory,
        ytDlpPath: $binary,
        scheduleEnabled: true,
    ));

    app(QueueRepository::class)->save(app(AppConfigStore::class)->load(), [
        [
            'video_id' => 'odd',
            'title' => 'Weird [Title] | Test',
            'url' => 'https://youtube.com/watch?v=odd(value)',
            'channel' => 'Channel | Name',
            'duration_seconds' => 900,
            'content_tier' => 'A Tier',
            'watch_reason' => 'Reason with [brackets] | pipes',
            'final_rank' => 99,
        ],
    ]);

    $this->artisan('note:publish-weekly', ['--date' => '2026-03-17'])->assertExitCode(0);

    $updated = $filesystem->get($notePath);

    expect($updated)->toContain('[Weird \[Title\] \| Test](<https://youtube.com/watch?v=odd(value)>)')
        ->and($updated)->toContain('Channel \| Name')
        ->and($updated)->toContain('Reason with \[brackets\] \| pipes');
});
