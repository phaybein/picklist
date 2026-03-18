<?php

namespace App\Services;

use App\Config\AppConfig;
use Illuminate\Filesystem\Filesystem;

final class QueueRepository
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function load(AppConfig $config): array
    {
        if (! $this->files->exists($this->path($config))) {
            return [];
        }

        /** @var array{videos?: list<array<string, mixed>>} $data */
        $data = json_decode((string) $this->files->get($this->path($config)), true, flags: JSON_THROW_ON_ERROR);

        return $data['videos'] ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $videos
     */
    public function save(AppConfig $config, array $videos): void
    {
        $this->files->ensureDirectoryExists($config->dataDirectory);
        $this->files->put(
            $this->path($config),
            json_encode(['videos' => array_values($videos)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    public function path(AppConfig $config): string
    {
        return rtrim($config->dataDirectory, '/').'/queue.json';
    }
}
