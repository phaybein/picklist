<?php

namespace App\Support;

use App\Config\AppConfig;
use App\Contracts\FeedFetcher;
use DateTimeZone;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final class AppConfigValidator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly FeedFetcher $feedFetcher,
        private readonly DailyNotePathResolver $pathResolver,
    ) {}

    /**
     * @return list<string>
     */
    public function validate(AppConfig $config): array
    {
        $errors = [];

        if (! filter_var($config->playlistFeedUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'Playlist feed URL must be a valid URL.';
        } else {
            try {
                $items = $this->feedFetcher->fetch($config->playlistFeedUrl);

                if ($items === []) {
                    $errors[] = 'Playlist feed returned no videos.';
                }
            } catch (\Throwable $exception) {
                $errors[] = 'Playlist feed could not be fetched: '.$exception->getMessage();
            }
        }

        if (! $this->files->isDirectory($config->vaultRoot)) {
            $errors[] = 'Vault root directory does not exist.';
        }

        try {
            new DateTimeZone($config->timezone);
        } catch (\Throwable) {
            $errors[] = 'Timezone is invalid.';
        }

        if ($config->weeklyPickCount < 1 || $config->weeklyPickCount > 10) {
            $errors[] = 'Weekly pick count must be between 1 and 10.';
        }

        if ($config->sectionHeading === '') {
            $errors[] = 'Section heading is required.';
        }

        if ($config->dataDirectory === '') {
            $errors[] = 'Data directory is required.';
        }

        if (! $this->binaryExists($config->ytDlpPath)) {
            $errors[] = 'yt-dlp binary was not found or is not executable.';
        }

        try {
            $resolved = $this->pathResolver->resolve($config, now($config->timezone));
            $directory = dirname($resolved);

            if (! str_starts_with($resolved, $config->vaultRoot.'/') && $resolved !== $config->vaultRoot) {
                throw new RuntimeException('Daily note path pattern resolves outside the vault root.');
            }

            if (! $this->files->isDirectory($directory) && ! $this->files->makeDirectory($directory, 0755, true, true) && ! $this->files->isDirectory($directory)) {
                throw new RuntimeException('Daily note directory could not be created.');
            }
        } catch (\Throwable $exception) {
            $errors[] = 'Daily note path is invalid: '.$exception->getMessage();
        }

        return $errors;
    }

    private function binaryExists(string $binaryPath): bool
    {
        if ($binaryPath === '') {
            return false;
        }

        if (str_contains($binaryPath, '/')) {
            return $this->files->exists($binaryPath) && is_executable($binaryPath);
        }

        $resolved = trim((string) shell_exec('command -v '.escapeshellarg($binaryPath).' 2>/dev/null'));

        return $resolved !== '';
    }
}
