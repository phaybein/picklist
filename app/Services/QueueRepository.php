<?php

namespace App\Services;

use App\Config\AppConfig;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final class QueueRepository
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function load(AppConfig $config): array
    {
        $path = $this->path($config);

        if (! $this->files->exists($path)) {
            return [];
        }

        /** @var array{videos?: list<array<string, mixed>>} $data */
        $data = json_decode((string) $this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        return $data['videos'] ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $videos
     */
    public function save(AppConfig $config, array $videos): void
    {
        $path = $this->path($config);
        $dataDirectory = $config->dataDirectory;
        $dataDirectoryExists = $this->files->isDirectory($dataDirectory);

        $this->files->ensureDirectoryExists($dataDirectory);
        $this->files->put(
            $path,
            json_encode(['videos' => array_values($videos)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        if (! $dataDirectoryExists) {
            $this->chmodOrFail($dataDirectory, 0700);
        }

        $this->chmodOrFail($path, 0600);
    }

    public function path(AppConfig $config): string
    {
        return rtrim($config->dataDirectory, '/').'/queue.json';
    }

    private function chmodOrFail(string $path, int $mode): void
    {
        if ($this->files->chmod($path, $mode) === false) {
            throw new RuntimeException(sprintf('Unable to set permissions on %s.', $path));
        }
    }
}
