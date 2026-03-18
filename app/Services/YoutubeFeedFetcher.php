<?php

namespace App\Services;

use App\Contracts\FeedFetcher;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use SimpleXMLElement;

final class YoutubeFeedFetcher implements FeedFetcher
{
    public function __construct(private readonly ClientInterface $http) {}

    public function fetch(string $url): array
    {
        $response = $this->http->request('GET', $url, ['http_errors' => true]);
        $xml = new SimpleXMLElement((string) $response->getBody());
        $entries = [];

        foreach ($xml->entry as $entry) {
            $namespaces = $entry->getNamespaces(true);
            $yt = isset($namespaces['yt']) ? $entry->children($namespaces['yt']) : null;
            $author = $entry->author;
            $linkAttributes = $entry->link->attributes();
            $videoId = trim((string) ($yt?->videoId ?? ''));

            if ($videoId === '') {
                continue;
            }

            $entries[] = [
                'video_id' => $videoId,
                'url' => (string) ($linkAttributes['href'] ?? 'https://www.youtube.com/watch?v='.$videoId),
                'title' => trim((string) $entry->title),
                'channel' => trim((string) ($author->name ?? '')),
                'published_at' => trim((string) $entry->published),
                'feed_seen_at' => now()->toIso8601String(),
            ];
        }

        if ($entries === []) {
            throw new RuntimeException('No videos were found in the feed.');
        }

        return $entries;
    }
}
