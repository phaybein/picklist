<?php

namespace App\Contracts;

interface FeedFetcher
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetch(string $url): array;
}
