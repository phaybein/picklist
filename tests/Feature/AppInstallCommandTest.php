<?php

use App\Config\AppConfigStore;
use App\Contracts\BinaryInstaller;
use App\Contracts\CronManager;
use App\Contracts\YtDlpInstaller;
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

it('offers to install yt-dlp with homebrew when the configured binary is missing', function () {
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $filesystem = app(Filesystem::class);
    $filesystem->ensureDirectoryExists($vaultRoot);
    $missingBinary = $home.'/bin/yt-dlp';
    $installedBinary = $home.'/homebrew/bin/yt-dlp';
    $state = (object) ['binaryInstalled' => false, 'ytDlpInstalled' => false];

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

    app()->instance(YtDlpInstaller::class, new class($state, $installedBinary) implements YtDlpInstaller
    {
        public function __construct(private object $state, private string $binaryPath) {}

        public function canInstallWithHomebrew(): bool
        {
            return true;
        }

        public function installWithHomebrew(): string
        {
            $this->state->ytDlpInstalled = true;

            return fakeBinary($this->binaryPath);
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
        ->expectsQuestion('yt-dlp binary path', $missingBinary)
        ->expectsConfirmation('Enable scheduled sync and weekly publishing?', 'no')
        ->expectsConfirmation('yt-dlp was not found. Install it with Homebrew now?', 'yes')
        ->expectsConfirmation('Install picklist into ~/.local/bin so it can run from anywhere?', 'no')
        ->assertExitCode(0);

    $config = app(AppConfigStore::class)->load();

    expect($state->ytDlpInstalled)->toBeTrue()
        ->and($config->ytDlpPath)->toBe($installedBinary);
});

it('fails install when yt-dlp is missing and the user declines the homebrew install', function () {
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $filesystem = app(Filesystem::class);
    $filesystem->ensureDirectoryExists($vaultRoot);
    $missingBinary = $home.'/bin/yt-dlp';
    $state = (object) ['ytDlpInstallAttempted' => false];

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

        public function install(): void {}

        public function isInstalled(): bool
        {
            return true;
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

    app()->instance(YtDlpInstaller::class, new class($state) implements YtDlpInstaller
    {
        public function __construct(private object $state) {}

        public function canInstallWithHomebrew(): bool
        {
            return true;
        }

        public function installWithHomebrew(): string
        {
            $this->state->ytDlpInstallAttempted = true;

            return '/tmp/should-not-exist';
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
        ->expectsQuestion('yt-dlp binary path', $missingBinary)
        ->expectsConfirmation('Enable scheduled sync and weekly publishing?', 'no')
        ->expectsConfirmation('yt-dlp was not found. Install it with Homebrew now?', 'no')
        ->expectsOutputToContain('yt-dlp binary was not found or is not executable.')
        ->assertExitCode(1);

    expect($state->ytDlpInstallAttempted)->toBeFalse()
        ->and($filesystem->isDirectory($vaultRoot.'/daily/03 March'))->toBeFalse();
});

it('fails install when the homebrew yt-dlp install fails', function () {
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $filesystem = app(Filesystem::class);
    $filesystem->ensureDirectoryExists($vaultRoot);
    $missingBinary = $home.'/bin/yt-dlp';

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

        public function install(): void {}

        public function isInstalled(): bool
        {
            return true;
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

    app()->instance(YtDlpInstaller::class, new class implements YtDlpInstaller
    {
        public function canInstallWithHomebrew(): bool
        {
            return true;
        }

        public function installWithHomebrew(): string
        {
            throw new RuntimeException('Homebrew could not install yt-dlp.');
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
        ->expectsQuestion('yt-dlp binary path', $missingBinary)
        ->expectsConfirmation('Enable scheduled sync and weekly publishing?', 'no')
        ->expectsConfirmation('yt-dlp was not found. Install it with Homebrew now?', 'yes')
        ->expectsOutputToContain('Homebrew could not install yt-dlp.')
        ->assertExitCode(1);
});

it('does not install yt-dlp before the rest of the config is valid', function () {
    $home = testHome();
    $missingVaultRoot = $home.'/missing-vault';
    $state = (object) ['ytDlpInstallAttempted' => false];

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

        public function install(): void {}

        public function isInstalled(): bool
        {
            return true;
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

    app()->instance(YtDlpInstaller::class, new class($state) implements YtDlpInstaller
    {
        public function __construct(private object $state) {}

        public function canInstallWithHomebrew(): bool
        {
            return true;
        }

        public function installWithHomebrew(): string
        {
            $this->state->ytDlpInstallAttempted = true;

            return fakeBinary('/tmp/unexpected-yt-dlp');
        }
    });

    $this->artisan('install')
        ->expectsQuestion('Playlist ID', 'PLabc123')
        ->expectsQuestion('Obsidian vault root', $missingVaultRoot)
        ->expectsQuestion('Daily note path pattern', 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md')
        ->expectsQuestion('Timezone (examples: America/Los_Angeles, America/New_York, UTC)', 'America/Los_Angeles')
        ->expectsQuestion('Weekly pick count', '4')
        ->expectsQuestion('Section heading', 'Watch This Week')
        ->expectsQuestion('Browser cookies source for private YouTube access (optional; examples: safari, chrome, firefox)', '')
        ->expectsQuestion('Local data directory', $home.'/data')
        ->expectsQuestion('yt-dlp binary path', $home.'/bin/yt-dlp')
        ->expectsConfirmation('Enable scheduled sync and weekly publishing?', 'no')
        ->expectsOutputToContain('Vault root directory does not exist.')
        ->assertExitCode(1);

    expect($state->ytDlpInstallAttempted)->toBeFalse();
});

it('skips the homebrew prompt when homebrew install is unavailable', function () {
    $home = testHome();
    $vaultRoot = $home.'/vault';
    $filesystem = app(Filesystem::class);
    $filesystem->ensureDirectoryExists($vaultRoot);
    $state = (object) ['ytDlpInstallAttempted' => false];

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

        public function install(): void {}

        public function isInstalled(): bool
        {
            return true;
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

    app()->instance(YtDlpInstaller::class, new class($state) implements YtDlpInstaller
    {
        public function __construct(private object $state) {}

        public function canInstallWithHomebrew(): bool
        {
            return false;
        }

        public function installWithHomebrew(): string
        {
            $this->state->ytDlpInstallAttempted = true;

            return '/tmp/should-not-exist';
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
        ->expectsQuestion('yt-dlp binary path', $home.'/bin/yt-dlp')
        ->expectsConfirmation('Enable scheduled sync and weekly publishing?', 'no')
        ->expectsOutputToContain('yt-dlp binary was not found or is not executable.')
        ->assertExitCode(1);

    expect($state->ytDlpInstallAttempted)->toBeFalse();
});

it('rejects daily note patterns that traverse outside the vault', function () {
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

        public function install(): void {}

        public function isInstalled(): bool
        {
            return true;
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
        ->expectsQuestion('Daily note path pattern', '../../outside.md')
        ->expectsQuestion('Timezone (examples: America/Los_Angeles, America/New_York, UTC)', 'America/Los_Angeles')
        ->expectsQuestion('Weekly pick count', '4')
        ->expectsQuestion('Section heading', 'Watch This Week')
        ->expectsQuestion('Browser cookies source for private YouTube access (optional; examples: safari, chrome, firefox)', '')
        ->expectsQuestion('Local data directory', $home.'/data')
        ->expectsQuestion('yt-dlp binary path', $binary)
        ->expectsConfirmation('Enable scheduled sync and weekly publishing?', 'no')
        ->expectsOutputToContain('Daily note path is invalid: Daily note path pattern cannot contain ".." path segments.')
        ->assertExitCode(1);
});
