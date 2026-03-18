<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class QueueRanker
{
    /**
     * @param  array<string, mixed>  $video
     * @return array<string, mixed>
     */
    public function rank(array $video): array
    {
        $haystack = Str::lower(implode(' ', [
            (string) ($video['title'] ?? ''),
            (string) ($video['description'] ?? ''),
            (string) ($video['transcript_excerpt'] ?? ''),
            (string) ($video['channel'] ?? ''),
        ]));

        $labelMap = config('scoring.label_keywords', []);
        $labels = [];

        foreach ($labelMap as $label => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, Str::lower((string) $keyword))) {
                    $labels[] = (string) $label;
                    break;
                }
            }
        }

        if ($labels === []) {
            $labels[] = 'Miscellaneous';
        }

        $themeMatches = $this->countMatches($haystack, config('scoring.theme_keywords', []));
        $ideaMatches = $this->countMatches($haystack, config('scoring.idea_keywords', []));
        $politicalMatches = $this->countMatches($haystack, config('scoring.political_penalty_keywords', []));

        $qualityScore = 20 + ($themeMatches * 8) + ($ideaMatches * 4) + (count($labels) * 2);
        $qualityScore += mb_strlen((string) ($video['transcript_excerpt'] ?? '')) > 120 ? 8 : 0;
        $qualityScore -= $politicalMatches * 10;
        $qualityScore = max(1, min(100, $qualityScore));

        $weeklyPriority = 35;
        $weeklyPriority += $this->freshnessScore((string) ($video['published_at'] ?? ''));
        $weeklyPriority += $this->durationScore((int) ($video['duration_seconds'] ?? 0));
        $weeklyPriority += mb_strlen((string) ($video['transcript_excerpt'] ?? '')) > 0 ? 10 : 0;
        $weeklyPriority += min(20, $themeMatches * 4);
        $weeklyPriority = max(1, min(100, $weeklyPriority));

        $finalRank = (int) round(($qualityScore * 0.55) + ($weeklyPriority * 0.45));

        return array_merge($video, [
            'labels' => array_values(array_unique($labels)),
            'quality_score' => $qualityScore,
            'content_tier' => $this->tierFromScore($qualityScore),
            'weekly_priority_score' => $weeklyPriority,
            'final_rank' => $finalRank,
            'watch_reason' => $this->watchReason($labels, $weeklyPriority, $qualityScore),
        ]);
    }

    /**
     * @param  list<string>  $keywords
     */
    private function countMatches(string $haystack, array $keywords): int
    {
        $matches = 0;

        foreach ($keywords as $keyword) {
            if (str_contains($haystack, Str::lower($keyword))) {
                $matches++;
            }
        }

        return $matches;
    }

    private function freshnessScore(string $publishedAt): int
    {
        if ($publishedAt === '') {
            return 0;
        }

        $days = now()->diffInDays($publishedAt, absolute: true);

        return match (true) {
            $days <= 7 => 20,
            $days <= 30 => 14,
            $days <= 90 => 8,
            default => 2,
        };
    }

    private function durationScore(int $seconds): int
    {
        if ($seconds <= 0) {
            return 0;
        }

        $minutes = intdiv($seconds, 60);

        return match (true) {
            $minutes >= 8 && $minutes <= 35 => 18,
            $minutes >= 36 && $minutes <= 60 => 12,
            $minutes >= 4 && $minutes <= 7 => 9,
            $minutes >= 61 && $minutes <= 90 => 6,
            default => 2,
        };
    }

    /**
     * @param  list<string>  $labels
     */
    private function watchReason(array $labels, int $weeklyPriority, int $qualityScore): string
    {
        $topLabel = Arr::first($labels) ?? 'Miscellaneous';

        if ($weeklyPriority >= 75 && $qualityScore >= 75) {
            return sprintf('High-upside %s pick with strong weekly relevance.', Str::lower($topLabel));
        }

        if ($weeklyPriority >= 70) {
            return sprintf('Timely %s video worth prioritizing this week.', Str::lower($topLabel));
        }

        if ($qualityScore >= 70) {
            return sprintf('Strong %s fit with durable long-term value.', Str::lower($topLabel));
        }

        return sprintf('Solid %s candidate when you have time this week.', Str::lower($topLabel));
    }

    private function tierFromScore(int $score): string
    {
        return match (true) {
            $score >= 85 => 'S Tier',
            $score >= 70 => 'A Tier',
            $score >= 55 => 'B Tier',
            $score >= 40 => 'C Tier',
            default => 'D Tier',
        };
    }
}
