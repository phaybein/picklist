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
        $path = $this->configPath();

        if (! $this->files->exists($path)) {
            throw new RuntimeException('App is not installed. Run "php picklist install" first.');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        return AppConfig::fromArray($data);
    }

    public function save(AppConfig $config): void
    {
        $homePath = $this->homePath();
        $configPath = $this->configPath();
        $dataDirectory = $config->dataDirectory;
        $homePathExists = $this->files->isDirectory($homePath);
        $dataDirectoryExists = $this->files->isDirectory($dataDirectory);

        $this->files->ensureDirectoryExists($homePath);
        $this->files->ensureDirectoryExists($dataDirectory);
        $this->files->put(
            $configPath,
            json_encode($config->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        if (! $homePathExists) {
            $this->chmodOrFail($homePath, 0700);
        }

        if (! $dataDirectoryExists) {
            $this->chmodOrFail($dataDirectory, 0700);
        }

        $this->chmodOrFail($configPath, 0600);
    }

    public function homePath(): string
    {
        foreach (['PICKLIST_HOME', 'YT_SUGGESTIONS_HOME'] as $variable) {
            $home = env($variable);

            if (is_string($home) && $home !== '') {
                return rtrim($home, '/');
            }
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

    private function chmodOrFail(string $path, int $mode): void
    {
        if ($this->files->chmod($path, $mode) === false) {
            throw new RuntimeException(sprintf('Unable to set permissions on %s.', $path));
        }
    }
}
