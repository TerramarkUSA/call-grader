<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }
        .header h1 {
            color: #1f2937;
            font-size: 24px;
            margin: 0 0 5px 0;
        }
        .header p {
            color: #6b7280;
            margin: 0;
            font-size: 14px;
        }
        .score-section {
            text-align: center;
            margin: 30px 0;
            padding: 25px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 12px;
            color: white;
        }
        .score-number {
            font-size: 48px;
            font-weight: bold;
            margin: 0;
        }
        .score-label {
            font-size: 18px;
            opacity: 0.9;
            margin-top: 5px;
        }
        .section {
            margin: 25px 0;
        }
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .category-table {
            width: 100%;
            border-collapse: collapse;
        }
        .category-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
        }
        .category-table tr:last-child td {
            border-bottom: none;
        }
        .category-name {
            color: #374151;
        }
        .category-score {
            text-align: center;
            font-weight: 600;
            width: 60px;
        }
        .category-rating {
            text-align: right;
            font-size: 13px;
            width: 100px;
        }
        .rating-excellent { color: #059669; }
        .rating-strong { color: #2563eb; }
        .rating-needs-work { color: #d97706; }
        .rating-poor { color: #dc2626; }
        .note-card {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            border-left: 4px solid #3b82f6;
        }
        .note-transcript {
            font-style: italic;
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #ffffff;
            border-radius: 4px;
        }
        .note-text {
            color: #374151;
            margin: 0;
        }
        .note-meta {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 8px;
        }
        .checkpoint-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .checkpoint-item:last-child {
            border-bottom: none;
        }
        .checkpoint-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 14px;
        }
        .checkpoint-positive-yes {
            background-color: #d1fae5;
            color: #059669;
        }
        .checkpoint-positive-no {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .checkpoint-negative-yes {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .checkpoint-negative-no {
            background-color: #d1fae5;
            color: #059669;
        }
        .checkpoint-section-label {
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            margin: 15px 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Call Feedback</h1>
            <p>{{ $projectName }} &bull; {{ $callDate }}</p>
        </div>

        <p>Hi {{ $repName }},</p>
        <p>{{ $managerName }} has reviewed your call from {{ $callDate }} at {{ $callTime }} and provided the following feedback:</p>

        <!-- Overall Score -->
        <div class="score-section">
            <p class="score-number">{{ $overallScore }}/100</p>
            <p class="score-label">{{ $scoreLabel }}</p>
        </div>

        <!-- Category Breakdown -->
        @if($categories->count() > 0)
        <div class="section">
            <h3 class="section-title">Category Breakdown</h3>
            <table class="category-table">
                @foreach($categories as $category)
                <tr>
                    <td class="category-name">{{ $category['name'] }}</td>
                    <td class="category-score">{{ $category['score'] }}/4</td>
                    <td class="category-rating rating-{{ strtolower(str_replace(' ', '-', $category['rating'])) }}">
                        {{ $category['rating'] }}
                    </td>
                </tr>
                @endforeach
            </table>
        </div>
        @endif

        <!-- Coaching Notes -->
        @if($hasCoachingNotes)
        <div class="section">
            <h3 class="section-title">Coaching Notes</h3>
            @foreach($coachingNotes as $note)
            <div class="note-card">
                @if($note['transcript_snippet'])
                <div class="note-transcript">
                    "{{ $note['transcript_snippet'] }}"
                </div>
                @endif
                <p class="note-text">{{ $note['text'] }}</p>
                @if($note['category'] || $note['timestamp'])
                <p class="note-meta">
                    @if($note['timestamp']){{ $note['timestamp'] }}@endif
                    @if($note['timestamp'] && $note['category']) &bull; @endif
                    @if($note['category']){{ $note['category'] }}@endif
                </p>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <!-- Checkpoints -->
        @if($hasCheckpoints)
        <div class="section">
            <h3 class="section-title">Call Checkpoints</h3>

            @if(count($positiveCheckpoints) > 0)
            <p class="checkpoint-section-label">Best Practices</p>
            @foreach($positiveCheckpoints as $checkpoint)
            <div class="checkpoint-item">
                <span class="checkpoint-icon {{ $checkpoint['observed'] ? 'checkpoint-positive-yes' : 'checkpoint-positive-no' }}">
                    {{ $checkpoint['observed'] ? '✓' : '✗' }}
                </span>
                <span>{{ $checkpoint['name'] }}</span>
            </div>
            @endforeach
            @endif

            @if(count($negativeCheckpoints) > 0)
            <p class="checkpoint-section-label">Things to Avoid</p>
            @foreach($negativeCheckpoints as $checkpoint)
            <div class="checkpoint-item">
                <span class="checkpoint-icon {{ $checkpoint['observed'] ? 'checkpoint-negative-yes' : 'checkpoint-negative-no' }}">
                    {{ $checkpoint['observed'] ? '✗' : '✓' }}
                </span>
                <span>{{ $checkpoint['name'] }}{{ $checkpoint['observed'] ? ' (observed)' : '' }}</span>
            </div>
            @endforeach
            @endif
        </div>
        @endif

        <div class="footer">
            <p>This feedback was sent from Call Grader by {{ $managerName }}.</p>
            <p>Reply to this email to reach {{ $managerName }} directly.</p>
        </div>
    </div>
</body>
</html>
