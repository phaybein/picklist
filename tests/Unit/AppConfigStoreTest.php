<?php

use App\Config\AppConfig;
use App\Config\AppConfigStore;

it('saves and loads config from the local app home', function () {
    $store = app(AppConfigStore::class);
    $config = new AppConfig(
        playlistId: 'PLabc123',
        vaultRoot: testHome().'/vault',
        dailyNotePathPattern: 'daily/{month_number_padded} {month_name}/{day_number_padded} {month_name}.md',
        timezone: 'America/Los_Angeles',
        weeklyPickCount: 5,
        sectionHeading: 'Watch This Week',
        ytDlpCookiesFromBrowser: 'safari',
        dataDirectory: testHome().'/data',
        ytDlpPath: fakeBinary(testHome().'/bin/yt-dlp'),
        scheduleEnabled: true,
    );

    $store->save($config);
    $loaded = $store->load();

    expect($loaded->playlistId)->toBe('PLabc123')
        ->and($loaded->ytDlpCookiesFromBrowser)->toBe('safari')
        ->and($loaded->weeklyPickCount)->toBe(5);
});
