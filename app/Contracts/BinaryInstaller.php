<?php

namespace App\Contracts;

interface BinaryInstaller
{
    public function binDirectoryPath(): string;

    public function linkPath(): string;

    public function install(): void;

    public function isInstalled(): bool;

    public function isBinDirectoryOnPath(): bool;
}
