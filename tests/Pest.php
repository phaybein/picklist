<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function testHome(): string
{
    $home = sys_get_temp_dir().'/picklist-tests/'.md5((string) test()->name());
    app(Filesystem::class)->deleteDirectory($home);
    app(Filesystem::class)->ensureDirectoryExists($home);
    putenv('PICKLIST_HOME='.$home);
    $_ENV['PICKLIST_HOME'] = $home;
    $_SERVER['PICKLIST_HOME'] = $home;
    putenv('YT_SUGGESTIONS_HOME');
    unset($_ENV['YT_SUGGESTIONS_HOME'], $_SERVER['YT_SUGGESTIONS_HOME']);

    return $home;
}

function fakeBinary(string $path): string
{
    app(Filesystem::class)->ensureDirectoryExists(dirname($path));
    app(Filesystem::class)->put($path, "#!/bin/sh\nexit 0\n");
    chmod($path, 0755);

    return $path;
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-03-17 09:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});
