<?php

namespace App\Services;

use App\Contracts\YtDlpInstaller;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class HomebrewYtDlpInstaller implements YtDlpInstaller
{
    private const INSTALL_TIMEOUT_SECONDS = 1800;

    public function canInstallWithHomebrew(): bool
    {
        return PHP_OS_FAMILY === 'Darwin' && $this->commandPath('brew') !== '';
    }

    public function installWithHomebrew(): string
    {
        if (! $this->canInstallWithHomebrew()) {
            throw new RuntimeException('Homebrew is not available on this system.');
        }

        $process = new Process(['brew', 'install', 'yt-dlp']);
        $process->setTimeout(self::INSTALL_TIMEOUT_SECONDS);

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('Homebrew timed out while installing yt-dlp.', previous: $exception);
        } catch (ProcessFailedException $exception) {
            $details = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            $message = 'Homebrew could not install yt-dlp.'.($details !== '' ? ' '.$details : '');

            throw new RuntimeException($message, previous: $exception);
        }

        $brewPrefix = trim((string) shell_exec('brew --prefix 2>/dev/null'));
        $candidate = $brewPrefix === '' ? '' : $brewPrefix.'/bin/yt-dlp';

        if ($candidate !== '' && is_executable($candidate)) {
            return $candidate;
        }

        $resolved = $this->commandPath('yt-dlp');

        if ($resolved !== '') {
            return $resolved;
        }

        throw new RuntimeException('yt-dlp was installed, but its binary path could not be determined.');
    }

    private function commandPath(string $command): string
    {
        return trim((string) shell_exec('command -v '.escapeshellarg($command).' 2>/dev/null'));
    }
}
