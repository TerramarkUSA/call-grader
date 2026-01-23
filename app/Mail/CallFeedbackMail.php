<?php

namespace App\Mail;

use App\Models\Call;
use App\Models\Grade;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CallFeedbackMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Grade $grade,
        public Call $call,
        public User $manager,
        public string $repName,
        public string $repEmail
    ) {}

    public function envelope(): Envelope
    {
        $date = $this->call->called_at->format('M j, Y');
        $project = $this->call->project?->name ?? 'Unknown Project';

        return new Envelope(
            from: new Address($this->manager->email, $this->manager->name),
            replyTo: [new Address($this->manager->email, $this->manager->name)],
            subject: "Call Feedback - {$date} - {$project}",
        );
    }

    public function content(): Content
    {
        // Load relationships
        $this->grade->load(['categoryScores.rubricCategory', 'checkpointResponses.rubricCheckpoint', 'coachingNotes.rubricCategory']);

        // Calculate weighted percentage score
        $categoryScores = [];
        foreach ($this->grade->categoryScores as $cs) {
            $categoryScores[$cs->rubric_category_id] = $cs->score;
        }
        $scoringService = app(ScoringService::class);
        $scoreData = $scoringService->calculateWeightedScore($categoryScores);
        $overallPercentage = $scoreData['percentage'];

        // Format category scores with rating labels
        $categories = $this->grade->categoryScores->map(function ($cs) {
            return [
                'name' => $cs->rubricCategory->name,
                'score' => $cs->score,
                'rating' => $this->getScoreRating($cs->score),
            ];
        });

        // Format coaching notes (only those with transcript text)
        $coachingNotes = $this->grade->coachingNotes
            ->filter(fn($note) => $note->line_index_start !== null)
            ->map(function ($note) {
                return [
                    'text' => $note->note_text,
                    'transcript_snippet' => $note->transcript_text,
                    'category' => $note->rubricCategory?->name,
                    'timestamp' => $note->timestamp_start ? gmdate('i:s', (int)$note->timestamp_start) : null,
                ];
            });

        // Format checkpoints
        $positiveCheckpoints = [];
        $negativeCheckpoints = [];

        foreach ($this->grade->checkpointResponses as $response) {
            $checkpoint = $response->rubricCheckpoint;
            $item = [
                'name' => $checkpoint->name,
                'observed' => $response->observed,
            ];

            if ($checkpoint->type === 'positive') {
                $positiveCheckpoints[] = $item;
            } else {
                $negativeCheckpoints[] = $item;
            }
        }

        return new Content(
            view: 'emails.call-feedback',
            with: [
                'repName' => $this->repName,
                'managerName' => $this->manager->name,
                'callDate' => $this->call->called_at->format('l, F j, Y'),
                'callTime' => $this->call->called_at->format('g:i A'),
                'projectName' => $this->call->project?->name ?? 'Unknown Project',
                'overallScore' => round($overallPercentage),
                'scoreLabel' => $scoringService->getScoreLabel($overallPercentage),
                'categories' => $categories,
                'coachingNotes' => $coachingNotes,
                'positiveCheckpoints' => $positiveCheckpoints,
                'negativeCheckpoints' => $negativeCheckpoints,
                'hasCoachingNotes' => $coachingNotes->count() > 0,
                'hasCheckpoints' => count($positiveCheckpoints) > 0 || count($negativeCheckpoints) > 0,
            ],
        );
    }

    /**
     * Get rating label for a 1-4 score
     */
    private function getScoreRating(int $score): string
    {
        return match($score) {
            4 => 'Excellent',
            3 => 'Strong',
            2 => 'Needs Work',
            1 => 'Poor',
            default => 'N/A',
        };
    }
}
