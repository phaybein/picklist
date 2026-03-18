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

        $ranked = collect($videos)
            ->map(function (array $video) use ($config, $metadataFetcher, $ranker): array {
                $metadata = $metadataFetcher->fetch((string) $video['url'], $config->ytDlpPath);

                return $ranker->rank(array_merge($video, $metadata));
            })
            ->sortByDesc('final_rank')
            ->values()
            ->all();

        $repository->save($config, $ranked);

        $this->info(sprintf('Ranked %d videos.', count($ranked)));

        return self::SUCCESS;
    }
}
