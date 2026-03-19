<?php

use App\Config\AppConfig;
use App\Services\QueueRepository;
use Illuminate\Filesystem\Filesystem;

it('writes queue data with owner-only permissions', function () {
    $home = testHome();
    $config = new AppConfig(
        playlistId: 'PLabc123',
        vaultRoot: $home.'/vault',
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 5,
        sectionHeading: 'Watch This Week',
        ytDlpCookiesFromBrowser: 'safari',
        dataDirectory: $home.'/data',
        ytDlpPath: fakeBinary($home.'/bin/yt-dlp'),
        scheduleEnabled: true,
    );

    $repository = app(QueueRepository::class);
    $repository->save($config, [[
        'video_id' => 'abc123',
        'url' => 'https://youtube.com/watch?v=abc123',
    ]]);

    expect(fileperms($repository->path($config)) & 0777)->toBe(0600)
        ->and(fileperms($config->dataDirectory) & 0777)->toBe(0700);
});

it('does not tighten permissions on an existing data directory', function () {
    $home = testHome();
    $dataDirectory = $home.'/shared-data';
    app(Filesystem::class)->ensureDirectoryExists($dataDirectory);
    chmod($dataDirectory, 0755);

    $config = new AppConfig(
        playlistId: 'PLabc123',
        vaultRoot: $home.'/vault',
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 5,
        sectionHeading: 'Watch This Week',
        ytDlpCookiesFromBrowser: 'safari',
        dataDirectory: $dataDirectory,
        ytDlpPath: fakeBinary($home.'/bin/yt-dlp'),
        scheduleEnabled: true,
    );

    $repository = app(QueueRepository::class);
    $repository->save($config, [[
        'video_id' => 'abc123',
        'url' => 'https://youtube.com/watch?v=abc123',
    ]]);

    expect(fileperms($dataDirectory) & 0777)->toBe(0755)
        ->and(fileperms($repository->path($config)) & 0777)->toBe(0600);
});

it('fails when queue file permissions cannot be hardened', function () {
    $home = testHome();
    $filesystem = new class extends Filesystem
    {
        public string $failingPath = '';

        public function chmod($path, $mode = null): mixed
        {
            if ($mode !== null && $path === $this->failingPath) {
                return false;
            }

            return parent::chmod($path, $mode);
        }
    };

    $config = new AppConfig(
        playlistId: 'PLabc123',
        vaultRoot: $home.'/vault',
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 5,
        sectionHeading: 'Watch This Week',
        ytDlpCookiesFromBrowser: 'safari',
        dataDirectory: $home.'/data',
        ytDlpPath: fakeBinary($home.'/bin/yt-dlp'),
        scheduleEnabled: true,
    );

    $repository = new QueueRepository($filesystem);
    $filesystem->failingPath = $repository->path($config);

    expect(fn () => $repository->save($config, [[
        'video_id' => 'abc123',
        'url' => 'https://youtube.com/watch?v=abc123',
    ]]))->toThrow(RuntimeException::class, 'Unable to set permissions on');
});
