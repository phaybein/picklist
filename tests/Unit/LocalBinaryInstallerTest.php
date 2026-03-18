<?php

use App\Services\LocalBinaryInstaller;
use Illuminate\Filesystem\Filesystem;

it('refuses to replace an unrelated existing picklist executable', function () {
    $originalHome = $_SERVER['HOME'] ?? getenv('HOME') ?: null;
    $home = sys_get_temp_dir().'/picklist-installer-home';
    app(Filesystem::class)->deleteDirectory($home);
    app(Filesystem::class)->ensureDirectoryExists($home.'/.local/bin');
    putenv('HOME='.$home);
    $_SERVER['HOME'] = $home;

    app(Filesystem::class)->put($home.'/.local/bin/picklist', '#!/bin/sh'.PHP_EOL.'exit 0'.PHP_EOL);

    expect(fn () => app(LocalBinaryInstaller::class)->install())
        ->toThrow(RuntimeException::class, 'Refusing to replace an existing executable');

    if ($originalHome === null) {
        putenv('HOME');
        unset($_SERVER['HOME']);

        return;
    }

    putenv('HOME='.$originalHome);
    $_SERVER['HOME'] = $originalHome;
});
