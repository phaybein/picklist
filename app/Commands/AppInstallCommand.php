<?php

namespace App\Commands;

use App\Config\AppConfig;
use App\Config\AppConfigStore;
use App\Contracts\BinaryInstaller;
use App\Contracts\CronManager;
use App\Support\AppConfigValidator;
use LaravelZero\Framework\Commands\Command;

class AppInstallCommand extends Command
{
    protected $signature = 'install {--force : Overwrite the existing local config}';

    protected $description = 'Run the guided onboarding flow for a new forked install.';

    public function handle(
        AppConfigStore $store,
        AppConfigValidator $validator,
        CronManager $cronManager,
        BinaryInstaller $binaryInstaller,
    ): int {
        if ($store->exists() && ! $this->option('force')) {
            $this->error('A config already exists. Re-run with --force to overwrite it.');

            return self::FAILURE;
        }

        $defaultDataDirectory = $store->homePath().'/data';
        $defaultYtDlpPath = trim((string) shell_exec('command -v yt-dlp 2>/dev/null')) ?: 'yt-dlp';

        $config = new AppConfig(
            playlistId: trim((string) $this->ask('Playlist ID')),
            vaultRoot: rtrim((string) $this->ask('Obsidian vault root'), '/'),
            dailyNotePathPattern: ltrim((string) $this->ask('Daily note path pattern', 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md'), '/'),
            timezone: trim((string) $this->ask('Timezone (examples: America/Los_Angeles, America/New_York, UTC)', date_default_timezone_get())),
            weeklyPickCount: (int) $this->ask('Weekly pick count', '5'),
            sectionHeading: (string) $this->ask('Section heading', 'Watch This Week'),
            ytDlpCookiesFromBrowser: trim((string) $this->ask('Browser cookies source for private YouTube access (optional; examples: safari, chrome, firefox)', '')),
            dataDirectory: rtrim((string) $this->ask('Local data directory', $defaultDataDirectory), '/'),
            ytDlpPath: (string) $this->ask('yt-dlp binary path', $defaultYtDlpPath),
            scheduleEnabled: (bool) $this->confirm('Enable scheduled sync and weekly publishing?', true),
        );

        $errors = $validator->validate($config);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $store->save($config);
        $this->info('Local config saved to '.$store->configPath());

        $this->maybeInstallBinary($binaryInstaller);

        if ($config->scheduleEnabled) {
            $entry = $cronManager->entry();
            $this->line('Cron entry: '.$entry);

            if ($this->confirm('Install or update the cron entry now?', false)) {
                $contents = trim($cronManager->current());
                $lines = $contents === '' ? [] : (preg_split('/\R/', $contents) ?: []);
                $lines = array_values(array_filter($lines, fn (string $line): bool => ! str_contains($line, 'picklist schedule:run')));
                $lines[] = $entry;
                $cronManager->install(implode(PHP_EOL, $lines));
                $this->info('Cron entry installed.');
            }
        }

        return self::SUCCESS;
    }

    private function maybeInstallBinary(BinaryInstaller $binaryInstaller): void
    {
        if ($binaryInstaller->isInstalled()) {
            return;
        }

        $this->line('Global command link: '.$binaryInstaller->linkPath());

        if (! $this->confirm('Install picklist into ~/.local/bin so it can run from anywhere?', true)) {
            return;
        }

        try {
            $binaryInstaller->install();
        } catch (\RuntimeException $exception) {
            $this->warn($exception->getMessage());

            return;
        }

        $this->info('picklist is now available at '.$binaryInstaller->linkPath());

        if (! $binaryInstaller->isBinDirectoryOnPath()) {
            $this->warn(sprintf(
                '%s is not on your PATH in this shell. Add it to your shell profile before relying on bare `picklist` commands.',
                $binaryInstaller->binDirectoryPath(),
            ));
        }
    }
}
