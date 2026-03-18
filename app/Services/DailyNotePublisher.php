<?php

namespace App\Services;

use App\Config\AppConfig;
use App\Support\DailyNotePathResolver;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\Filesystem;

final class DailyNotePublisher
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly DailyNotePathResolver $pathResolver,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $videos
     */
    public function publish(AppConfig $config, CarbonInterface $date, array $videos): string
    {
        $path = $this->pathResolver->resolve($config, $date);
        $this->files->ensureDirectoryExists(dirname($path));

        $existing = $this->files->exists($path) ? (string) $this->files->get($path) : '';
        $section = $this->renderSection($config->sectionHeading, $videos)."\n";
        $updated = $this->replaceSection($existing, $config->sectionHeading, $section);

        $this->files->put($path, $updated);

        return $path;
    }

    /**
     * @param  list<array<string, mixed>>  $videos
     */
    private function renderSection(string $heading, array $videos): string
    {
        $lines = ['## '.$heading, ''];

        if ($videos === []) {
            $lines[] = '- No videos are ranked yet.';

            return implode("\n", $lines);
        }

        foreach ($videos as $video) {
            $title = $this->escapeMarkdownText((string) $video['title']);
            $url = $this->escapeMarkdownLinkDestination((string) $video['url']);
            $channel = $this->escapeMarkdownText((string) ($video['channel'] ?? 'Unknown channel'));
            $duration = $this->formatDuration((int) ($video['duration_seconds'] ?? 0));
            $tier = $this->escapeMarkdownText((string) ($video['content_tier'] ?? 'Unranked'));
            $reason = $this->escapeMarkdownText((string) ($video['watch_reason'] ?? 'Strong fit for this week.'));

            $lines[] = sprintf('- [%s](<%s>) | %s | %s | %s | %s', $title, $url, $channel, $duration, $tier, $reason);
        }

        return implode("\n", $lines);
    }

    private function replaceSection(string $existing, string $heading, string $section): string
    {
        $pattern = '/^## '.preg_quote($heading, '/').'\n(?:.*\n)*?(?=^## |\z)/ms';

        if (preg_match($pattern, $existing) === 1) {
            return preg_replace($pattern, $section, $existing, 1) ?? $existing;
        }

        $trimmed = rtrim($existing);

        return ($trimmed === '' ? '' : $trimmed."\n\n").$section;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'Unknown length';
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            return sprintf('%dm', $minutes);
        }

        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }

    private function escapeMarkdownText(string $value): string
    {
        return str_replace(
            ['\\', '[', ']', '|'],
            ['\\\\', '\[', '\]', '\|'],
            $value,
        );
    }

    private function escapeMarkdownLinkDestination(string $value): string
    {
        return str_replace(['<', '>'], ['%3C', '%3E'], $value);
    }
}
