<?php

use App\Commands\QueueRunCommand;
use App\Config\AppConfig;
use App\Config\AppConfigStore;
use App\Contracts\FeedFetcher;
use App\Contracts\VideoMetadataFetcher;
use App\Services\QueueRepository;
use Illuminate\Filesystem\Filesystem;

it('runs the full flow and publishes on monday', function () {
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $notePath = $vaultRoot.'/daily/03 March/16 March.md';
    app(Filesystem::class)->ensureDirectoryExists(dirname($notePath));
    $binary = fakeBinary($home.'/bin/yt-dlp');

    app(AppConfigStore::class)->save(new AppConfig(
        playlistId: 'PLabc123',
        vaultRoot: $vaultRoot,
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 1,
        sectionHeading: 'Watch This Week',
        ytDlpCookiesFromBrowser: 'safari',
        dataDirectory: $home.'/data',
        ytDlpPath: $binary,
        scheduleEnabled: true,
    ));

    app()->instance(FeedFetcher::class, new class implements FeedFetcher
    {
        public function fetch(AppConfig $config): array
        {
            return [[
                'video_id' => 'abc123',
                'url' => 'https://youtube.com/watch?v=abc123',
                'title' => 'AI and Human Creativity',
                'channel' => 'Thoughtful Channel',
                'published_at' => '2026-03-16T00:00:00+00:00',
                'feed_seen_at' => '2026-03-17T00:00:00+00:00',
            ]];
        }
    });

    app()->instance(VideoMetadataFetcher::class, new class implements VideoMetadataFetcher
    {
        public function fetch(string $url, string $binaryPath, string $cookiesFromBrowser = ''): array
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

    $this->artisan('queue:run', ['--date' => '2026-03-16'])->assertExitCode(0);

    $videos = app(QueueRepository::class)->load(app(AppConfigStore::class)->load());

    expect($videos[0]['published_to_note_at'])->not->toBeNull()
        ->and(app(Filesystem::class)->get($notePath))->toContain('## Watch This Week');
});

it('stops and returns a failure exit code when a required sub-command fails', function () {
    $home = testHome();
    $binary = fakeBinary($home.'/bin/yt-dlp');
    $store = app(AppConfigStore::class);

    $store->save(new AppConfig(
        playlistId: 'PLabc123',
        vaultRoot: $home.'/vault',
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 1,
        sectionHeading: 'Watch This Week',
        ytDlpCookiesFromBrowser: '',
        dataDirectory: $home.'/data',
        ytDlpPath: $binary,
        scheduleEnabled: true,
    ));

    $command = new class extends QueueRunCommand
    {
        /** @var list<string> */
        public array $invoked = [];

        public function option($key = null): mixed
        {
            return $key === 'date' ? '2026-03-17' : null;
        }

        protected function runRequiredCommand(string $command, array $arguments = []): int
        {
            $this->invoked[] = $command;

            return $command === 'queue:sync' ? self::FAILURE : self::SUCCESS;
        }
    };

    $status = $command->handle($store);

    expect($status)->toBe(1)
        ->and($command->invoked)->toBe(['queue:sync']);
});
