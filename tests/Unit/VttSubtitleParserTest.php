<?php

use App\Support\VttSubtitleParser;

it('strips timestamps and markup from vtt subtitles', function () {
    $contents = <<<'VTT'
WEBVTT

00:00:00.000 --> 00:00:02.000
<c>This is a test</c>

00:00:02.000 --> 00:00:04.000
For subtitles
VTT;

    $parsed = app(VttSubtitleParser::class)->parse($contents);

    expect($parsed)->toBe('This is a test For subtitles');
});
