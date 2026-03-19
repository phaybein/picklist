<?php

namespace App\Contracts;

interface YtDlpInstaller
{
    public function canInstallWithHomebrew(): bool;

    public function installWithHomebrew(): string;
}
