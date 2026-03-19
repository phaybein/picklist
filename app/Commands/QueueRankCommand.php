<?php

namespace App\Commands;

use App\Config\AppConfigStore;
use App\Contracts\VideoMetadataFetcher;
use App\Services\QueueRanker;
use App\Services\QueueRepository;
use LaravelZero\Framework\Commands\Command;

class QueueRankCommand extends Command
{
    protected $signature = 'queue:rank';

    protected $description = 'Enrich queued videos with metadata and rank them for the week.';

    public function handle(
        AppConfigStore $store,
        QueueRepository $repository,
        VideoMetadataFetcher $metadataFetcher,
        QueueRanker $ranker,
    ): int {
        $config = $store->load();
        $videos = $repository->load($config);
        $total = count($videos);
        $ranked = [];

        $this->line(sprintf('Ranking %d videos...', $total));

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        foreach ($videos as $video) {
            $metadata = $metadataFetcher->fetch(
                (string) $video['url'],
                $config->ytDlpPath,
                $config->ytDlpCookiesFromBrowser,
            );
            $ranked[] = $ranker->rank(array_merge($video, $metadata));
            $progressBar->advance();
        }

        usort($ranked, static fn (array $left, array $right): int => ($right['final_rank'] ?? 0) <=> ($left['final_rank'] ?? 0));

        $progressBar->finish();
        $this->newLine(2);

        $repository->save($config, $ranked);

        $this->info(sprintf('Ranked %d videos.', count($ranked)));

        return self::SUCCESS;
    }
}
