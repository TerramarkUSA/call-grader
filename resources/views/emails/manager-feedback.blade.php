<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .message-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
            white-space: pre-wrap;
        }
        .meta {
            font-size: 14px;
            color: #6b7280;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #999;
        }
    </style>
</head>
<body>
    <h2>Feedback from {{ $userName }}</h2>

    <p class="meta">
        <strong>From:</strong> {{ $userName }} ({{ $userEmail }})<br>
        <strong>Role:</strong> {{ ucfirst(str_replace('_', ' ', $userRole)) }}<br>
        <strong>Sent:</strong> {{ $sentAt }}
    </p>

    <div class="message-box">{{ $feedbackMessage }}</div>

    <p class="meta">You can reply directly to this email to respond to {{ $userName }}.</p>

    <div class="footer">
        <p>This feedback was submitted via Call Grader.</p>
    </div>
</body>
</html>
