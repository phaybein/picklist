<?php

namespace App\Contracts;

use App\Config\AppConfig;

interface FeedFetcher
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetch(AppConfig $config): array;
}
