<?php

namespace App\Contracts;

interface VideoMetadataFetcher
{
    /**
     * @return array<string, mixed>
     */
    public function fetch(string $url, string $binaryPath): array;
}
