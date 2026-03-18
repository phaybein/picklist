<?php

use App\Services\QueueRanker;

it('assigns hybrid labels and scores from transcript-rich metadata', function () {
    $ranked = app(QueueRanker::class)->rank([
        'title' => 'AI, Meaning, and the Future of Creativity',
        'description' => 'An essay about human flourishing and writing with AI.',
        'transcript_excerpt' => 'We explore human meaning, AI, creativity, writing, and education.',
        'duration_seconds' => 1800,
        'published_at' => now()->subDays(3)->toIso8601String(),
    ]);

    expect($ranked['labels'])->toContain('AI')
        ->and($ranked['labels'])->toContain('Meaning')
        ->and($ranked['quality_score'])->toBeGreaterThan(70)
        ->and($ranked['weekly_priority_score'])->toBeGreaterThan(70)
        ->and(in_array($ranked['content_tier'], ['A Tier', 'S Tier'], true))->toBeTrue();
});
