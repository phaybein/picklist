<?php

namespace App\Services;

use App\Contracts\CronManager;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class SystemCronManager implements CronManager
{
    public function current(): string
    {
        $process = new Process(['crontab', '-l']);
        $process->run();

        if (! $process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }

    public function install(string $contents): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'picklist-cron-');

        if ($tempFile === false) {
            throw new \RuntimeException('Unable to create temporary cron file.');
        }

        file_put_contents($tempFile, $contents.PHP_EOL);

        try {
            $process = new Process(['crontab', $tempFile]);
            $process->mustRun();
        } finally {
            @unlink($tempFile);
        }
    }

    public function entry(): string
    {
        return sprintf(
            '* * * * * cd %s && %s picklist schedule:run >> /dev/null 2>&1',
            escapeshellarg(base_path()),
            escapeshellarg(PHP_BINARY),
        );
    }

    public function hasEntry(): bool
    {
        return Str::contains($this->current(), $this->entry());
    }
}
