<?php

namespace App\Support;

final class VttSubtitleParser
{
    public function parse(string $contents): string
    {
        $lines = preg_split('/\R/', $contents) ?: [];
        $parts = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || $trimmed === 'WEBVTT') {
                continue;
            }

            if (preg_match('/^\d+$/', $trimmed) === 1) {
                continue;
            }

            if (str_contains($trimmed, '-->')) {
                continue;
            }

            $parts[] = preg_replace('/<[^>]+>/', '', $trimmed) ?? $trimmed;
        }

        $joined = trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');

        return $joined;
    }
}
