<?php

namespace App\Commands;

use App\Config\AppConfigStore;
use App\Contracts\CronManager;
use App\Support\AppConfigValidator;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class AppDoctorCommand extends Command
{
    protected $signature = 'doctor';

    protected $description = 'Check local dependencies, config, and note publishing readiness.';

    public function handle(AppConfigStore $store, AppConfigValidator $validator, CronManager $cronManager): int
    {
        if (! $store->exists()) {
            $this->error('[FAIL] Config is missing. Run "./picklist install" first.');

            return self::FAILURE;
        }

        try {
            $config = $store->load();
        } catch (Throwable $exception) {
            $this->error('[FAIL] Unable to load config: '.$exception->getMessage());

            return self::FAILURE;
        }

        $checks = [
            'PHP version' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'Config file' => true,
            'Schedule status' => ! $config->scheduleEnabled || $cronManager->hasEntry(),
        ];

        $validationErrors = $validator->validate($config, allowDirectoryCreation: false);

        foreach ($validationErrors as $error) {
            $this->error('[FAIL] '.$error);
        }

        foreach ($checks as $label => $passed) {
            $this->line(sprintf('[%s] %s', $passed ? 'PASS' : 'FAIL', $label));
        }

        return ($validationErrors !== [] || in_array(false, $checks, true)) ? self::FAILURE : self::SUCCESS;
    }
}
