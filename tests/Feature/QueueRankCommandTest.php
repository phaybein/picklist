<?php

use App\Config\AppConfig;
use App\Config\AppConfigStore;
use App\Contracts\VideoMetadataFetcher;
use App\Services\QueueRepository;

it('enriches and ranks queued videos', function () {
    $home = testHome();
    $binary = fakeBinary($home.'/bin/yt-dlp');
    $config = new AppConfig(
        playlistId: 'PLabc123',
        vaultRoot: $home.'/vault',
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 3,
        sectionHeading: 'Watch This Week',
        ytDlpCookiesFromBrowser: '',
        dataDirectory: $home.'/data',
        ytDlpPath: $binary,
        scheduleEnabled: true,
    );

    app(AppConfigStore::class)->save($config);
    app(QueueRepository::class)->save($config, [[
        'video_id' => 'abc123',
        'url' => 'https://youtube.com/watch?v=abc123',
        'title' => 'Placeholder',
        'channel' => 'Unknown',
        'published_at' => '2026-03-15T00:00:00+00:00',
        'feed_seen_at' => '2026-03-17T00:00:00+00:00',
    ]]);

    app()->instance(VideoMetadataFetcher::class, new class implements VideoMetadataFetcher
    {
        public function fetch(string $url, string $binaryPath): array
        {
            return [
                'title' => 'AI and Human Creativity',
                'description' => 'A discussion about AI, writing, and human meaning.',
                'channel' => 'Thoughtful Channel',
                'duration_seconds' => 1500,
                'published_at' => '2026-03-16T00:00:00+00:00',
                'transcript_excerpt' => 'This conversation explores AI, writing, human meaning, and creativity in the future.',
                'subtitle_status' => 'available',
            ];
        }
    });

    $this->artisan('queue:rank')
        ->expectsOutputToContain('Ranking 1 videos...')
        ->expectsOutputToContain('1/1')
        ->assertExitCode(0);

    $videos = app(QueueRepository::class)->load($config);

    expect($videos[0]['labels'])->toContain('AI')
        ->and($videos[0]['content_tier'])->toBe('A Tier')
        ->and($videos[0]['weekly_priority_score'])->toBeGreaterThan(60)
        ->and($videos[0]['transcript_excerpt'])->toContain('human meaning');
});
