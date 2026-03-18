<?php

use App\Services\SystemCronManager;

it('builds a cron entry with quoted paths and the current php binary', function () {
    $entry = app(SystemCronManager::class)->entry();

    expect($entry)->toContain(escapeshellarg(PHP_BINARY))
        ->and($entry)->toContain(escapeshellarg(base_path()))
        ->and($entry)->toContain('picklist schedule:run');
});
