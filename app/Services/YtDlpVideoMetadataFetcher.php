<?php

namespace App\Services;

use App\Contracts\VideoMetadataFetcher;
use App\Support\VttSubtitleParser;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class YtDlpVideoMetadataFetcher implements VideoMetadataFetcher
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly VttSubtitleParser $subtitleParser,
    ) {}

    public function fetch(string $url, string $binaryPath): array
    {
        $metadataProcess = new Process([
            $binaryPath,
            '--dump-single-json',
            '--skip-download',
            '--no-warnings',
            $url,
        ]);

        $metadataProcess->mustRun();

        /** @var array<string, mixed> $metadata */
        $metadata = json_decode($metadataProcess->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $temporaryDirectory = sys_get_temp_dir().'/picklist-'.md5($url.microtime(true));
        $this->files->ensureDirectoryExists($temporaryDirectory);

        $subtitleExcerpt = '';

        try {
            $subtitleProcess = new Process([
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
                $url,
            ]);

            $subtitleProcess->run();

            $subtitleFiles = glob($temporaryDirectory.'/*.vtt') ?: [];

            if ($subtitleFiles !== []) {
                $subtitleExcerpt = $this->subtitleParser->parse((string) $this->files->get($subtitleFiles[0]));
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
                ? now()->createFromFormat('Ymd', (string) $metadata['upload_date'])->startOfDay()->toIso8601String()
                : '',
            'transcript_excerpt' => mb_substr($subtitleExcerpt, 0, 1500),
            'subtitle_status' => $subtitleExcerpt === '' ? 'missing' : 'available',
        ];
    }
}
