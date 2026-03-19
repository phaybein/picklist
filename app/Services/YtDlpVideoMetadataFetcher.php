<?php

namespace App\Services;

use App\Contracts\VideoMetadataFetcher;
use App\Support\VttSubtitleParser;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class YtDlpVideoMetadataFetcher implements VideoMetadataFetcher
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly VttSubtitleParser $subtitleParser,
        private readonly int $metadataTimeoutSeconds = 60,
        private readonly int $subtitleTimeoutSeconds = 180,
    ) {}

    public function fetch(string $url, string $binaryPath, string $cookiesFromBrowser = ''): array
    {
        $metadataProcess = new Process($this->buildCommand([
            $binaryPath,
            '--dump-single-json',
            '--skip-download',
            '--no-warnings',
        ], $url, $cookiesFromBrowser));
        $metadataProcess->setTimeout($this->metadataTimeoutSeconds);

        try {
            $metadataProcess->mustRun();
        } catch (ProcessTimedOutException) {
            return $this->timedOutMetadataFallback();
        }

        /** @var array<string, mixed> $metadata */
        $metadata = json_decode($metadataProcess->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $temporaryDirectory = sys_get_temp_dir().'/picklist-'.md5($url.microtime(true));
        $this->files->ensureDirectoryExists($temporaryDirectory);

        $subtitleExcerpt = '';
        $subtitleStatus = 'missing';

        try {
            $subtitleProcess = new Process($this->buildCommand([
                $binaryPath,
                '--skip-download',
                '--write-auto-subs',
                '--write-subs',
                '--sub-langs',
                'en.*,en',
                '--convert-subs',
                'vtt',
                '--output',
                '%(id)s.%(ext)s',
                '--paths',
                $temporaryDirectory,
            ], $url, $cookiesFromBrowser));
            $subtitleProcess->setTimeout($this->subtitleTimeoutSeconds);

            try {
                $subtitleProcess->run();
            } catch (ProcessTimedOutException) {
                $subtitleStatus = 'timeout';
            }

            if ($subtitleStatus !== 'timeout') {
                $subtitleFiles = glob($temporaryDirectory.'/*.vtt') ?: [];

                if ($subtitleFiles !== []) {
                    $subtitleExcerpt = $this->subtitleParser->parse((string) $this->files->get($subtitleFiles[0]));
                    $subtitleStatus = 'available';
                }
            }
        } finally {
            $this->files->deleteDirectory($temporaryDirectory);
        }

        return [
            'title' => (string) ($metadata['title'] ?? ''),
            'description' => (string) ($metadata['description'] ?? ''),
            'channel' => (string) ($metadata['channel'] ?? ''),
            'duration_seconds' => (int) ($metadata['duration'] ?? 0),
            'published_at' => isset($metadata['upload_date'])
                ? CarbonImmutable::createFromFormat('Ymd', (string) $metadata['upload_date'], 'UTC')->startOfDay()->toIso8601String()
                : '',
            'transcript_excerpt' => mb_substr($subtitleExcerpt, 0, 1500),
            'subtitle_status' => $subtitleStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timedOutMetadataFallback(): array
    {
        return [
            'description' => '',
            'duration_seconds' => 0,
            'transcript_excerpt' => '',
            'subtitle_status' => 'timeout',
        ];
    }

    /**
     * @param  list<string>  $command
     * @return list<string>
     */
    private function buildCommand(array $command, string $url, string $cookiesFromBrowser): array
    {
        if ($cookiesFromBrowser !== '') {
            $command[] = '--cookies-from-browser';
            $command[] = $cookiesFromBrowser;
        }

        $command[] = $url;

        return $command;
    }
}
