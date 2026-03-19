<?php

use App\Config\AppConfig;
use App\Config\AppConfigStore;
use App\Contracts\CronManager;
use Illuminate\Filesystem\Filesystem;

it('fails when the app is not installed', function () {
    testHome();

    $this->artisan('doctor')
        ->expectsOutputToContain('[FAIL] Config is missing')
        ->assertExitCode(1);
});

it('reports passing checks for a healthy installation', function () {
    $filesystem = app(Filesystem::class);
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $dailyDirectory = $vaultRoot.'/daily';
    $dataDirectory = $home.'/data';
    $binary = fakeBinary($home.'/bin/yt-dlp');

    $filesystem->ensureDirectoryExists($vaultRoot);

    app(AppConfigStore::class)->save(new AppConfig(
        playlistId: 'PLabc123',
        vaultRoot: $vaultRoot,
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 5,
        sectionHeading: 'Watch This Week',
        ytDlpCookiesFromBrowser: '',
        dataDirectory: $dataDirectory,
        ytDlpPath: $binary,
        scheduleEnabled: true,
    ));

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

    expect($filesystem->isDirectory($dailyDirectory))->toBeFalse();
});
