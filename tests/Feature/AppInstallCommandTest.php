<?php

use App\Config\AppConfigStore;
use App\Contracts\BinaryInstaller;
use App\Contracts\CronManager;
use Illuminate\Filesystem\Filesystem;

it('writes local config and installs cron when requested', function () {
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $filesystem = app(Filesystem::class);
    $filesystem->ensureDirectoryExists($vaultRoot);
    $binary = fakeBinary($home.'/bin/yt-dlp');
    $state = (object) ['installedCron' => null, 'binaryInstalled' => false];

    app()->instance(CronManager::class, new class($state) implements CronManager
    {
        public function __construct(private object $state) {}

        public function current(): string
        {
            return '';
        }

        public function install(string $contents): void
        {
            $this->state->installedCron = $contents;
        }

        public function entry(): string
        {
            return '* * * * * cd /tmp/picklist && /opt/homebrew/bin/php picklist schedule:run >> /dev/null 2>&1';
        }

        public function hasEntry(): bool
        {
            return false;
        }
    });

    app()->instance(BinaryInstaller::class, new class($state) implements BinaryInstaller
    {
        public function __construct(private object $state) {}

        public function binDirectoryPath(): string
        {
            return '/tmp';
        }

        public function linkPath(): string
        {
            return '/tmp/picklist';
        }

        public function install(): void
        {
            $this->state->binaryInstalled = true;
        }

        public function isInstalled(): bool
        {
            return false;
        }

        public function isBinDirectoryOnPath(): bool
        {
            return true;
        }
    });

    $this->artisan('install')
        ->expectsQuestion('Playlist ID', 'PLabc123')
        ->expectsQuestion('Obsidian vault root', $vaultRoot)
        ->expectsQuestion('Daily note path pattern', 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md')
        ->expectsQuestion('Timezone (examples: America/Los_Angeles, America/New_York, UTC)', 'America/Los_Angeles')
        ->expectsQuestion('Weekly pick count', '4')
        ->expectsQuestion('Section heading', 'Watch This Week')
        ->expectsQuestion('Browser cookies source for private YouTube access (optional; examples: safari, chrome, firefox)', 'safari')
        ->expectsQuestion('Local data directory', $home.'/data')
        ->expectsQuestion('yt-dlp binary path', $binary)
        ->expectsConfirmation('Enable scheduled sync and weekly publishing?', 'yes')
        ->expectsConfirmation('Install picklist into ~/.local/bin so it can run from anywhere?', 'yes')
        ->expectsConfirmation('Install or update the cron entry now?', 'yes')
        ->assertExitCode(0);

    $config = app(AppConfigStore::class)->load();

    expect($config->playlistId)->toBe('PLabc123')
        ->and($config->ytDlpCookiesFromBrowser)->toBe('safari')
        ->and($config->weeklyPickCount)->toBe(4)
        ->and($config->vaultRoot)->toBe($vaultRoot)
        ->and($state->binaryInstalled)->toBeTrue()
        ->and($state->installedCron)->toContain('schedule:run');
});

it('skips the global binary install when the user declines it', function () {
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $filesystem = app(Filesystem::class);
    $filesystem->ensureDirectoryExists($vaultRoot);
    $binary = fakeBinary($home.'/bin/yt-dlp');
    $state = (object) ['binaryInstalled' => false];

    app()->instance(BinaryInstaller::class, new class($state) implements BinaryInstaller
    {
        public function __construct(private object $state) {}

        public function binDirectoryPath(): string
        {
            return '/tmp';
        }

        public function linkPath(): string
        {
            return '/tmp/picklist';
        }

        public function install(): void
        {
            $this->state->binaryInstalled = true;
        }

        public function isInstalled(): bool
        {
            return false;
        }

        public function isBinDirectoryOnPath(): bool
        {
            return true;
        }
    });

    app()->instance(CronManager::class, new class implements CronManager
    {
        public function current(): string
        {
            return '';
        }

        public function install(string $contents): void {}

        public function entry(): string
        {
            return '* * * * * cd /tmp/picklist && /opt/homebrew/bin/php picklist schedule:run >> /dev/null 2>&1';
        }

        public function hasEntry(): bool
        {
            return false;
        }
    });

    $this->artisan('install')
        ->expectsQuestion('Playlist ID', 'PLabc123')
        ->expectsQuestion('Obsidian vault root', $vaultRoot)
        ->expectsQuestion('Daily note path pattern', 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md')
        ->expectsQuestion('Timezone (examples: America/Los_Angeles, America/New_York, UTC)', 'America/Los_Angeles')
        ->expectsQuestion('Weekly pick count', '4')
        ->expectsQuestion('Section heading', 'Watch This Week')
        ->expectsQuestion('Browser cookies source for private YouTube access (optional; examples: safari, chrome, firefox)', '')
        ->expectsQuestion('Local data directory', $home.'/data')
        ->expectsQuestion('yt-dlp binary path', $binary)
        ->expectsConfirmation('Enable scheduled sync and weekly publishing?', 'no')
        ->expectsConfirmation('Install picklist into ~/.local/bin so it can run from anywhere?', 'no')
        ->assertExitCode(0);

    expect($state->binaryInstalled)->toBeFalse();
});

it('warns when the binary link directory is not on PATH', function () {
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $filesystem = app(Filesystem::class);
    $filesystem->ensureDirectoryExists($vaultRoot);
    $binary = fakeBinary($home.'/bin/yt-dlp');
    $state = (object) ['binaryInstalled' => false];

    app()->instance(BinaryInstaller::class, new class($state) implements BinaryInstaller
    {
        public function __construct(private object $state) {}

        public function binDirectoryPath(): string
        {
            return '/tmp/missing-path';
        }

        public function linkPath(): string
        {
            return '/tmp/missing-path/picklist';
        }

        public function install(): void
        {
            $this->state->binaryInstalled = true;
        }

        public function isInstalled(): bool
        {
            return false;
        }

        public function isBinDirectoryOnPath(): bool
        {
            return false;
        }
    });

    app()->instance(CronManager::class, new class implements CronManager
    {
        public function current(): string
        {
            return '';
        }

        public function install(string $contents): void {}

        public function entry(): string
        {
            return '* * * * * cd /tmp/picklist && /opt/homebrew/bin/php picklist schedule:run >> /dev/null 2>&1';
        }

        public function hasEntry(): bool
        {
            return false;
        }
    });

    $this->artisan('install')
        ->expectsQuestion('Playlist ID', 'PLabc123')
        ->expectsQuestion('Obsidian vault root', $vaultRoot)
        ->expectsQuestion('Daily note path pattern', 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md')
        ->expectsQuestion('Timezone (examples: America/Los_Angeles, America/New_York, UTC)', 'America/Los_Angeles')
        ->expectsQuestion('Weekly pick count', '4')
        ->expectsQuestion('Section heading', 'Watch This Week')
        ->expectsQuestion('Browser cookies source for private YouTube access (optional; examples: safari, chrome, firefox)', '')
        ->expectsQuestion('Local data directory', $home.'/data')
        ->expectsQuestion('yt-dlp binary path', $binary)
        ->expectsConfirmation('Enable scheduled sync and weekly publishing?', 'no')
        ->expectsConfirmation('Install picklist into ~/.local/bin so it can run from anywhere?', 'yes')
        ->expectsOutputToContain('is not on your PATH in this shell')
        ->assertExitCode(0);

    expect($state->binaryInstalled)->toBeTrue();
});

it('warns when the global binary install fails', function () {
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $filesystem = app(Filesystem::class);
    $filesystem->ensureDirectoryExists($vaultRoot);
    $binary = fakeBinary($home.'/bin/yt-dlp');

    app()->instance(BinaryInstaller::class, new class implements BinaryInstaller
    {
        public function binDirectoryPath(): string
        {
            return '/tmp';
        }

        public function linkPath(): string
        {
            return '/tmp/picklist';
        }

        public function install(): void
        {
            throw new RuntimeException('Unable to create the picklist executable link at /tmp/picklist.');
        }

        public function isInstalled(): bool
        {
            return false;
        }

        public function isBinDirectoryOnPath(): bool
        {
            return true;
        }
    });

    app()->instance(CronManager::class, new class implements CronManager
    {
        public function current(): string
        {
            return '';
        }

        public function install(string $contents): void {}

        public function entry(): string
        {
            return '* * * * * cd /tmp/picklist && /opt/homebrew/bin/php picklist schedule:run >> /dev/null 2>&1';
        }

        public function hasEntry(): bool
        {
            return false;
        }
    });

    $this->artisan('install')
        ->expectsQuestion('Playlist ID', 'PLabc123')
        ->expectsQuestion('Obsidian vault root', $vaultRoot)
        ->expectsQuestion('Daily note path pattern', 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md')
        ->expectsQuestion('Timezone (examples: America/Los_Angeles, America/New_York, UTC)', 'America/Los_Angeles')
        ->expectsQuestion('Weekly pick count', '4')
        ->expectsQuestion('Section heading', 'Watch This Week')
        ->expectsQuestion('Browser cookies source for private YouTube access (optional; examples: safari, chrome, firefox)', '')
        ->expectsQuestion('Local data directory', $home.'/data')
        ->expectsQuestion('yt-dlp binary path', $binary)
        ->expectsConfirmation('Enable scheduled sync and weekly publishing?', 'no')
        ->expectsConfirmation('Install picklist into ~/.local/bin so it can run from anywhere?', 'yes')
        ->expectsOutputToContain('Unable to create the picklist executable link')
        ->assertExitCode(0);
});
