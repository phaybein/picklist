<?php

namespace App\Services;

use App\Config\AppConfig;
use App\Contracts\FeedFetcher;
use Carbon\CarbonImmutable;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class YoutubeFeedFetcher implements FeedFetcher
{
    public function fetch(AppConfig $config): array
    {
        $command = [
            $config->ytDlpPath,
            '--dump-single-json',
            '--flat-playlist',
            '--skip-download',
            '--no-warnings',
        ];

        if ($config->ytDlpCookiesFromBrowser !== '') {
            $command[] = '--cookies-from-browser';
            $command[] = $config->ytDlpCookiesFromBrowser;
        }

        $command[] = $config->playlistUrl();

        $process = new Process($command);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $details = trim($process->getErrorOutput());
            $message = 'Playlist could not be fetched with yt-dlp.';

            if ($config->ytDlpCookiesFromBrowser === '') {
                $message .= ' If this is a private playlist, set a browser cookies source in the app config.';
            }

            if ($details !== '') {
                $message .= ' '.$details;
            }

            throw new RuntimeException($message, previous: $exception);
        }

        /** @var array{entries?: mixed} $payload */
        $payload = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $entries = [];

        foreach (($payload['entries'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $videoId = trim((string) ($entry['id'] ?? ''));

            if ($videoId === '') {
                continue;
            }

            $entries[] = [
                'video_id' => $videoId,
                'url' => str_starts_with((string) ($entry['url'] ?? ''), 'http')
                    ? (string) $entry['url']
                    : 'https://www.youtube.com/watch?v='.$videoId,
                'title' => trim((string) ($entry['title'] ?? '')),
                'channel' => trim((string) ($entry['channel'] ?? $entry['uploader'] ?? '')),
                'published_at' => $this->publishedAt($entry),
                'feed_seen_at' => now()->toIso8601String(),
            ];
        }

        if ($entries === []) {
            throw new RuntimeException('No videos were found in the playlist.');
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function publishedAt(array $entry): string
    {
        $timestamp = $entry['timestamp'] ?? $entry['release_timestamp'] ?? null;

        if (is_int($timestamp) || is_float($timestamp) || (is_string($timestamp) && is_numeric($timestamp))) {
            return CarbonImmutable::createFromTimestampUTC((int) $timestamp)->toIso8601String();
        }

        $uploadDate = trim((string) ($entry['upload_date'] ?? ''));

        if ($uploadDate === '') {
            return '';
        }

        return CarbonImmutable::createFromFormat('Ymd', $uploadDate, 'UTC')->startOfDay()->toIso8601String();
    }
}
