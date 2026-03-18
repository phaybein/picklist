<?php

namespace App\Services;

use App\Contracts\BinaryInstaller;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final class LocalBinaryInstaller implements BinaryInstaller
{
    private const BINARY_NAME = 'picklist';

    public function __construct(private readonly Filesystem $files) {}

    public function binDirectoryPath(): string
    {
        return $this->homeDirectory().'/.local/bin';
    }

    public function linkPath(): string
    {
        return $this->binDirectoryPath().'/'.self::BINARY_NAME;
    }

    public function install(): void
    {
        $linkPath = $this->linkPath();
        $this->files->ensureDirectoryExists(dirname($linkPath));

        if ($this->files->exists($linkPath) || is_link($linkPath)) {
            if ($this->isManagedLink($linkPath)) {
                return;
            }

            throw new RuntimeException(sprintf(
                'Refusing to replace an existing executable at %s. Remove or rename it first.',
                $linkPath,
            ));
        }

        $linked = $this->files->link(base_path(self::BINARY_NAME), $linkPath);

        if ($linked === false || (! is_link($linkPath) && ! $this->files->exists($linkPath))) {
            throw new RuntimeException(sprintf(
                'Unable to create the picklist executable link at %s.',
                $linkPath,
            ));
        }
    }

    public function isInstalled(): bool
    {
        $resolved = trim((string) shell_exec('command -v '.self::BINARY_NAME.' 2>/dev/null'));

        return $resolved === $this->linkPath();
    }

    public function isBinDirectoryOnPath(): bool
    {
        $path = $_SERVER['PATH'] ?? getenv('PATH');

        if (! is_string($path) || $path === '') {
            return false;
        }

        return in_array($this->binDirectoryPath(), explode(PATH_SEPARATOR, $path), true);
    }

    private function homeDirectory(): string
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME');

        if (! is_string($home) || $home === '') {
            throw new RuntimeException('Unable to determine the user home directory.');
        }

        return rtrim($home, '/');
    }

    private function isManagedLink(string $linkPath): bool
    {
        $linkTarget = realpath($linkPath);
        $binaryTarget = realpath(base_path(self::BINARY_NAME));

        return $linkTarget !== false && $binaryTarget !== false && $linkTarget === $binaryTarget;
    }
}
