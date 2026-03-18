<?php

namespace App\Support;

use App\Config\AppConfig;
use Carbon\CarbonInterface;
use RuntimeException;

final class DailyNotePathResolver
{
    public function resolve(AppConfig $config, CarbonInterface $date): string
    {
        $pattern = $config->dailyNotePathPattern;

        if ($pattern === '') {
            throw new RuntimeException('Daily note path pattern is required.');
        }

        $replacements = [
            '{year}' => $date->format('Y'),
            '{month_number}' => $date->format('n'),
            '{month_number_padded}' => $date->format('m'),
            '{month_name}' => $date->format('F'),
            '{month_short}' => $date->format('M'),
            '{day_number}' => $date->format('j'),
            '{day_number_padded}' => $date->format('d'),
            '{day_name}' => $date->format('l'),
            '{day_short}' => $date->format('D'),
        ];

        $relativePath = str_replace(array_keys($replacements), array_values($replacements), $pattern);
        $relativePath = ltrim($relativePath, '/');

        return rtrim($config->vaultRoot, '/').'/'.$relativePath;
    }
}
