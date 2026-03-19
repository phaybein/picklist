<?php

use App\Services\YtDlpVideoMetadataFetcher;
use App\Support\VttSubtitleParser;
use Illuminate\Filesystem\Filesystem;

it('returns metadata when subtitle fetching times out', function () {
    $home = testHome();
    $filesystem = app(Filesystem::class);
    $binary = $home.'/bin/yt-dlp';

    $filesystem->ensureDirectoryExists(dirname($binary));
    $filesystem->put($binary, <<<'SH'
#!/bin/sh
case " $* " in
  *" --write-auto-subs "*)
    sleep 2
    exit 0
    ;;
  *)
    cat <<'JSON'
{"title":"Slow video","description":"A slow subtitle fetch.","channel":"Channel","duration":1200,"upload_date":"20260318"}
JSON
    ;;
esac
SH);
    chmod($binary, 0755);

    $fetcher = new YtDlpVideoMetadataFetcher(
        $filesystem,
        app(VttSubtitleParser::class),
        metadataTimeoutSeconds: 5,
        subtitleTimeoutSeconds: 1,
    );

    $metadata = $fetcher->fetch('https://www.youtube.com/watch?v=abc123', $binary);

    expect($metadata['title'])->toBe('Slow video')
        ->and($metadata['subtitle_status'])->toBe('timeout')
        ->and($metadata['transcript_excerpt'])->toBe('')
        ->and($metadata['published_at'])->toBe('2026-03-18T00:00:00+00:00');
});

it('returns a non-destructive fallback when metadata fetching times out', function () {
    $home = testHome();
    $filesystem = app(Filesystem::class);
    $binary = $home.'/bin/yt-dlp';

    $filesystem->ensureDirectoryExists(dirname($binary));
    $filesystem->put($binary, <<<'SH'
#!/bin/sh
sleep 2
SH);
    chmod($binary, 0755);

    $fetcher = new YtDlpVideoMetadataFetcher(
        $filesystem,
        app(VttSubtitleParser::class),
        metadataTimeoutSeconds: 1,
        subtitleTimeoutSeconds: 1,
    );

    $metadata = $fetcher->fetch('https://www.youtube.com/watch?v=abc123', $binary);

    expect($metadata)->toBe([
        'description' => '',
        'duration_seconds' => 0,
        'transcript_excerpt' => '',
        'subtitle_status' => 'timeout',
    ]);
});
