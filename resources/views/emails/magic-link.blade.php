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
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <h2>Hi {{ $userName }},</h2>

    <p>Click the button below to log in to Call Grader:</p>

    <a href="{{ $url }}" class="button">Log In to Call Grader</a>

    <p>Or copy and paste this link into your browser:</p>
    <p style="word-break: break-all; color: #666;">{{ $url }}</p>

    <div class="footer">
        <p>This link will expire at {{ $expiresAt->format('g:i A') }} ({{ $expiresAt->diffForHumans() }}).</p>
        <p>If you didn't request this login link, you can safely ignore this email.</p>
    </div>
</body>
</html>
