<?php

namespace App\Services;

use App\Models\RubricCategory;

class ScoringService
{
    /**
     * Calculate weighted score from category scores
     *
     * @param array $categoryScores ['category_id' => score (1-4)]
     * @return array ['raw_score' => float, 'max_score' => float, 'percentage' => float]
     */
    public function calculateWeightedScore(array $categoryScores): array
    {
        $categories = RubricCategory::where('is_active', true)->get();

        $totalWeightedScore = 0;
        $totalWeight = 0;

        foreach ($categories as $category) {
            if (isset($categoryScores[$category->id]) && $categoryScores[$category->id] !== null) {
                $score = (int) $categoryScores[$category->id];
                $totalWeightedScore += $score * $category->weight;
                $totalWeight += $category->weight;
            }
        }

        if ($totalWeight === 0) {
            return ['raw_score' => 0, 'max_score' => 0, 'percentage' => 0];
        }

        $maxPossible = $totalWeight * 4; // Max score is 4 per category
        $percentage = ($totalWeightedScore / $maxPossible) * 100;

        return [
            'raw_score' => round($totalWeightedScore, 2),
            'max_score' => $maxPossible,
            'percentage' => round($percentage, 1),
        ];
    }

    /**
     * Get score color class based on percentage
     */
    public function getScoreColor(float $percentage): string
    {
        return match(true) {
            $percentage >= 85 => 'text-green-600',
            $percentage >= 70 => 'text-blue-600',
            $percentage >= 50 => 'text-orange-500',
            default => 'text-red-600',
        };
    }

    /**
     * Get score background color class based on percentage
     */
    public function getScoreBgColor(float $percentage): string
    {
        return match(true) {
            $percentage >= 85 => 'bg-green-500',
            $percentage >= 70 => 'bg-blue-500',
            $percentage >= 50 => 'bg-orange-500',
            default => 'bg-red-500',
        };
    }

    /**
     * Get score label based on percentage
     */
    public function getScoreLabel(float $percentage): string
    {
        return match(true) {
            $percentage >= 85 => 'Excellent',
            $percentage >= 70 => 'Good',
            $percentage >= 50 => 'Needs Work',
            default => 'Poor',
        };
    }
}
