<?php

namespace App\Config;

final class AppConfig
{
    public function __construct(
        public readonly string $playlistId,
        public readonly string $vaultRoot,
        public readonly string $dailyNotePathPattern,
        public readonly string $timezone,
        public readonly int $weeklyPickCount,
        public readonly string $sectionHeading,
        public readonly string $ytDlpCookiesFromBrowser,
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
            playlistId: self::playlistIdFromArray($data),
            vaultRoot: rtrim((string) ($data['vault_root'] ?? ''), '/'),
            dailyNotePathPattern: ltrim((string) ($data['daily_note_path_pattern'] ?? ''), '/'),
            timezone: (string) ($data['timezone'] ?? 'UTC'),
            weeklyPickCount: (int) ($data['weekly_pick_count'] ?? 5),
            sectionHeading: (string) ($data['section_heading'] ?? 'Watch This Week'),
            ytDlpCookiesFromBrowser: trim((string) ($data['yt_dlp_cookies_from_browser'] ?? '')),
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
            'playlist_id' => $this->playlistId,
            'vault_root' => $this->vaultRoot,
            'daily_note_path_pattern' => $this->dailyNotePathPattern,
            'timezone' => $this->timezone,
            'weekly_pick_count' => $this->weeklyPickCount,
            'section_heading' => $this->sectionHeading,
            'yt_dlp_cookies_from_browser' => $this->ytDlpCookiesFromBrowser,
            'data_directory' => $this->dataDirectory,
            'yt_dlp_path' => $this->ytDlpPath,
            'schedule_enabled' => $this->scheduleEnabled,
        ];
    }

    public function playlistUrl(): string
    {
        return 'https://www.youtube.com/playlist?list='.rawurlencode($this->playlistId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function playlistIdFromArray(array $data): string
    {
        $playlistId = trim((string) ($data['playlist_id'] ?? ''));

        if ($playlistId !== '') {
            return $playlistId;
        }

        $query = parse_url(trim((string) ($data['playlist_feed_url'] ?? '')), PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);

        return trim((string) ($params['playlist_id'] ?? ''));
    }
}
