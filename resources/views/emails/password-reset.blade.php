<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6; margin: 0; padding: 40px 20px;">
    <div style="max-width: 500px; margin: 0 auto; background-color: white; border-radius: 8px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h1 style="color: #111827; font-size: 24px; font-weight: bold; margin: 0 0 20px 0; text-align: center;">
            Reset Your Password
        </h1>

        <p style="color: #4b5563; font-size: 16px; line-height: 24px; margin: 0 0 20px 0;">
            Hi {{ $user->name }},
        </p>

        <p style="color: #4b5563; font-size: 16px; line-height: 24px; margin: 0 0 20px 0;">
            You requested to reset your password for your Call Grader account. Click the button below to set a new password:
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}"
               style="display: inline-block; background-color: #2563eb; color: white; font-size: 16px; font-weight: 600; text-decoration: none; padding: 12px 32px; border-radius: 6px;">
                Reset Password
            </a>
        </div>

        <p style="color: #6b7280; font-size: 14px; line-height: 20px; margin: 0 0 10px 0;">
            This link will expire in 60 minutes.
        </p>

        <p style="color: #6b7280; font-size: 14px; line-height: 20px; margin: 0 0 20px 0;">
            If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.
        </p>

        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">

        <p style="color: #9ca3af; font-size: 12px; line-height: 18px; margin: 0; text-align: center;">
            If the button doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ $resetUrl }}" style="color: #2563eb; word-break: break-all;">{{ $resetUrl }}</a>
        </p>
    </div>
</body>
</html>
