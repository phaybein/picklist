<?php

use App\Config\AppConfig;
use App\Services\YoutubeFeedFetcher;
use Illuminate\Filesystem\Filesystem;

it('normalizes yt-dlp playlist JSON into queue items', function () {
    $home = testHome();
    $filesystem = app(Filesystem::class);
    $binary = $home.'/bin/yt-dlp-playlist';
    $argsLog = $home.'/yt-dlp-args.log';

    $filesystem->ensureDirectoryExists(dirname($binary));
    $filesystem->put($binary, <<<SH
#!/bin/sh
printf '%s\n' "\$@" > "$argsLog"
cat <<'JSON'
{"entries":[{"id":"abc123","title":"First video","channel":"Channel One","timestamp":1773532800},{"id":"def456","title":"Second video","uploader":"Channel Two","upload_date":"20260316"}]}
JSON
SH);
    chmod($binary, 0755);

    $videos = app(YoutubeFeedFetcher::class)->fetch(new AppConfig(
        playlistId: 'PLabc123',
        vaultRoot: $home.'/vault',
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 5,
        sectionHeading: 'Watch This Week',
        ytDlpCookiesFromBrowser: 'safari',
        dataDirectory: $home.'/data',
        ytDlpPath: $binary,
        scheduleEnabled: true,
    ));

    $args = preg_split('/\R/', trim((string) $filesystem->get($argsLog))) ?: [];

    expect($videos)->toHaveCount(2)
        ->and($videos[0]['video_id'])->toBe('abc123')
        ->and($videos[0]['url'])->toBe('https://www.youtube.com/watch?v=abc123')
        ->and($videos[0]['channel'])->toBe('Channel One')
        ->and($videos[0]['published_at'])->toBe('2026-03-15T00:00:00+00:00')
        ->and($videos[1]['channel'])->toBe('Channel Two')
        ->and($videos[1]['published_at'])->toBe('2026-03-16T00:00:00+00:00')
        ->and($args)->toContain('--flat-playlist')
        ->and($args)->toContain('--cookies-from-browser')
        ->and($args)->toContain('safari')
        ->and($args)->toContain('https://www.youtube.com/playlist?list=PLabc123');
});
