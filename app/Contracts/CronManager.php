<?php

namespace App\Contracts;

interface CronManager
{
    public function current(): string;

    public function install(string $contents): void;

    public function entry(): string;

    public function hasEntry(): bool;
}
