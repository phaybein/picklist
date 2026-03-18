<?php

namespace App\Config;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final class AppConfigStore
{
    public function __construct(private readonly Filesystem $files) {}

    public function exists(): bool
    {
        return $this->files->exists($this->configPath());
    }

    public function load(): AppConfig
    {
        if (! $this->exists()) {
            throw new RuntimeException('App is not installed. Run "./picklist install" first.');
        }

        $path = $this->configPath();
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        return AppConfig::fromArray($data);
    }

    public function save(AppConfig $config): void
    {
        $this->files->ensureDirectoryExists($this->homePath());
        $this->files->ensureDirectoryExists($config->dataDirectory);
        $this->files->put(
            $this->configPath(),
            json_encode($config->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    public function homePath(): string
    {
        $home = env('YT_SUGGESTIONS_HOME');

        if (is_string($home) && $home !== '') {
            return rtrim($home, '/');
        }

        $userHome = $_SERVER['HOME'] ?? getenv('HOME');

        if (! is_string($userHome) || $userHome === '') {
            throw new RuntimeException('Unable to determine the user home directory.');
        }

        return rtrim($userHome, '/').'/.picklist';
    }

    public function configPath(): string
    {
        return $this->homePath().'/config.json';
    }
}
