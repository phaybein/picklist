<?php

namespace App\Config;

final class AppConfig
{
    public function __construct(
        public readonly string $playlistFeedUrl,
        public readonly string $vaultRoot,
        public readonly string $dailyNotePathPattern,
        public readonly string $timezone,
        public readonly int $weeklyPickCount,
        public readonly string $sectionHeading,
        public readonly string $dataDirectory,
        public readonly string $ytDlpPath,
        public readonly bool $scheduleEnabled,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            playlistFeedUrl: (string) ($data['playlist_feed_url'] ?? ''),
            vaultRoot: rtrim((string) ($data['vault_root'] ?? ''), '/'),
            dailyNotePathPattern: ltrim((string) ($data['daily_note_path_pattern'] ?? ''), '/'),
            timezone: (string) ($data['timezone'] ?? 'UTC'),
            weeklyPickCount: (int) ($data['weekly_pick_count'] ?? 5),
            sectionHeading: (string) ($data['section_heading'] ?? 'Watch This Week'),
            dataDirectory: rtrim((string) ($data['data_directory'] ?? ''), '/'),
            ytDlpPath: (string) ($data['yt_dlp_path'] ?? 'yt-dlp'),
            scheduleEnabled: (bool) ($data['schedule_enabled'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'playlist_feed_url' => $this->playlistFeedUrl,
            'vault_root' => $this->vaultRoot,
            'daily_note_path_pattern' => $this->dailyNotePathPattern,
            'timezone' => $this->timezone,
            'weekly_pick_count' => $this->weeklyPickCount,
            'section_heading' => $this->sectionHeading,
            'data_directory' => $this->dataDirectory,
            'yt_dlp_path' => $this->ytDlpPath,
            'schedule_enabled' => $this->scheduleEnabled,
        ];
    }
}
