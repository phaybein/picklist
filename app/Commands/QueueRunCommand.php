<?php

namespace App\Commands;

use App\Config\AppConfigStore;
use Carbon\CarbonImmutable;
use LaravelZero\Framework\Commands\Command;

class QueueRunCommand extends Command
{
    protected $signature = 'queue:run {--date= : Override the orchestration date (Y-m-d)}';

    protected $description = 'Run the normal sync and ranking flow, and publish on Mondays.';

    public function handle(AppConfigStore $store): int
    {
        $config = $store->load();
        $date = $this->option('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->option('date'), $config->timezone)
            : CarbonImmutable::now($config->timezone);

        if ($status = $this->runRequiredCommand('queue:sync')) {
            return $status;
        }

        if ($status = $this->runRequiredCommand('queue:rank')) {
            return $status;
        }

        if ($date->isMonday()) {
            return $this->runRequiredCommand('note:publish-weekly', ['--date' => $date->format('Y-m-d')]);
        }

        $this->line('Skipping weekly note publish because today is not Monday.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, scalar|null>  $arguments
     */
    protected function runRequiredCommand(string $command, array $arguments = []): int
    {
        $status = $this->call($command, $arguments);

        if ($status !== self::SUCCESS) {
            $this->error(sprintf('Command "%s" failed with exit code %d.', $command, $status));
        }

        return $status;
    }
}
