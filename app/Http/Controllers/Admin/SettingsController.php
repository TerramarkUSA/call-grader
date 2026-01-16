<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    /**
     * Show settings page
     */
    public function index()
    {
        $settings = [
            'deepgram_api_key' => Setting::get('deepgram_api_key') ? '••••••••' : '',
            'sendgrid_api_key' => Setting::get('sendgrid_api_key') ? '••••••••' : '',
            'sendgrid_from_email' => Setting::get('sendgrid_from_email', ''),
            'sendgrid_from_name' => Setting::get('sendgrid_from_name', 'Call Grader'),
            'grading_quality_flag_threshold' => Setting::get('grading_quality_flag_threshold', '25'),
            'grading_quality_suspicious_threshold' => Setting::get('grading_quality_suspicious_threshold', '50'),
            'cost_alert_daily_threshold' => Setting::get('cost_alert_daily_threshold', '50'),
            'cost_alert_weekly_threshold' => Setting::get('cost_alert_weekly_threshold', '200'),
            // Deepgram transcription options
            'deepgram_model' => Setting::get('deepgram_model', 'nova-3'),
            'deepgram_diarize' => Setting::get('deepgram_diarize', 'true'),
            'deepgram_smart_format' => Setting::get('deepgram_smart_format', 'true'),
            'deepgram_punctuate' => Setting::get('deepgram_punctuate', 'true'),
            'deepgram_utterances' => Setting::get('deepgram_utterances', 'true'),
            'deepgram_paragraphs' => Setting::get('deepgram_paragraphs', 'true'),
            'deepgram_multichannel' => Setting::get('deepgram_multichannel', 'true'),
        ];

        return view('admin.settings.index', compact('settings'));
    }

    /**
     * Update API settings
     */
    public function updateApi(Request $request)
    {
        $request->validate([
            'deepgram_api_key' => 'nullable|string',
            'sendgrid_api_key' => 'nullable|string',
            'sendgrid_from_email' => 'nullable|email',
            'sendgrid_from_name' => 'nullable|string',
        ]);

        // Only update if value provided (not the masked placeholder)
        if ($request->filled('deepgram_api_key') && $request->deepgram_api_key !== '••••••••') {
            Setting::setEncrypted('deepgram_api_key', $request->deepgram_api_key);
        }

        if ($request->filled('sendgrid_api_key') && $request->sendgrid_api_key !== '••••••••') {
            Setting::setEncrypted('sendgrid_api_key', $request->sendgrid_api_key);
        }

        if ($request->filled('sendgrid_from_email')) {
            Setting::set('sendgrid_from_email', $request->sendgrid_from_email);
        }

        if ($request->filled('sendgrid_from_name')) {
            Setting::set('sendgrid_from_name', $request->sendgrid_from_name);
        }

        return back()->with('success', 'API settings updated.');
    }

    /**
     * Update grading settings
     */
    public function updateGrading(Request $request)
    {
        $request->validate([
            'grading_quality_flag_threshold' => 'required|integer|min:0|max:100',
            'grading_quality_suspicious_threshold' => 'required|integer|min:0|max:100',
        ]);

        Setting::set('grading_quality_flag_threshold', $request->grading_quality_flag_threshold);
        Setting::set('grading_quality_suspicious_threshold', $request->grading_quality_suspicious_threshold);

        return back()->with('success', 'Grading settings updated.');
    }

    /**
     * Update cost alert settings
     */
    public function updateAlerts(Request $request)
    {
        $request->validate([
            'cost_alert_daily_threshold' => 'required|numeric|min:0',
            'cost_alert_weekly_threshold' => 'required|numeric|min:0',
        ]);

        Setting::set('cost_alert_daily_threshold', $request->cost_alert_daily_threshold);
        Setting::set('cost_alert_weekly_threshold', $request->cost_alert_weekly_threshold);

        return back()->with('success', 'Alert settings updated.');
    }

    /**
     * Update Deepgram transcription settings
     */
    public function updateDeepgram(Request $request)
    {
        $request->validate([
            'deepgram_model' => 'required|in:nova-3,nova-2,whisper-large,whisper-medium',
            'deepgram_diarize' => 'required|in:true,false',
            'deepgram_smart_format' => 'required|in:true,false',
            'deepgram_punctuate' => 'required|in:true,false',
            'deepgram_utterances' => 'required|in:true,false',
            'deepgram_paragraphs' => 'required|in:true,false',
            'deepgram_multichannel' => 'required|in:true,false',
        ]);

        Setting::set('deepgram_model', $request->deepgram_model);
        Setting::set('deepgram_diarize', $request->deepgram_diarize);
        Setting::set('deepgram_smart_format', $request->deepgram_smart_format);
        Setting::set('deepgram_punctuate', $request->deepgram_punctuate);
        Setting::set('deepgram_utterances', $request->deepgram_utterances);
        Setting::set('deepgram_paragraphs', $request->deepgram_paragraphs);
        Setting::set('deepgram_multichannel', $request->deepgram_multichannel);

        return back()->with('success', 'Deepgram transcription settings updated.');
    }

    /**
     * Test Deepgram connection
     */
    public function testDeepgram()
    {
        $apiKey = Setting::getEncrypted('deepgram_api_key');

        if (!$apiKey) {
            return back()->with('error', 'Deepgram API key not configured.');
        }

        try {
            $response = \Http::withHeaders([
                'Authorization' => 'Token ' . $apiKey,
            ])->get('https://api.deepgram.com/v1/projects');

            if ($response->successful()) {
                return back()->with('success', 'Deepgram connection successful!');
            }

            return back()->with('error', 'Deepgram connection failed: ' . ($response->json('err_msg') ?? 'Invalid API key'));
        } catch (\Exception $e) {
            return back()->with('error', 'Deepgram connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Test SendGrid email configuration
     */
    public function testEmail()
    {
        $sendgridApiKey = Setting::getEncrypted('sendgrid_api_key');
        $fromEmail = Setting::get('sendgrid_from_email');

        if (!$sendgridApiKey) {
            return back()->with('error', 'SendGrid API key not configured.');
        }

        if (!$fromEmail) {
            return back()->with('error', 'SendGrid from email not configured.');
        }

        $testEmail = auth()->user()->email;

        Log::info('Testing SendGrid email configuration', [
            'to' => $testEmail,
            'from' => $fromEmail,
            'mailer' => config('mail.default'),
        ]);

        try {
            Mail::raw('This is a test email from Call Grader to verify your SendGrid configuration is working correctly.', function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Call Grader - Test Email');
            });

            Log::info('Test email sent successfully', ['to' => $testEmail]);

            return back()->with('success', 'Test email sent to ' . $testEmail . '. Check your inbox (and spam folder).');
        } catch (\Exception $e) {
            Log::error('Test email failed', [
                'to' => $testEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }

    /**
     * Show current mail configuration (for debugging)
     */
    public function mailConfig()
    {
        $config = [
            'default_mailer' => config('mail.default'),
            'sendgrid_host' => config('mail.mailers.sendgrid.host'),
            'sendgrid_port' => config('mail.mailers.sendgrid.port'),
            'sendgrid_username' => config('mail.mailers.sendgrid.username'),
            'sendgrid_password_set' => !empty(config('mail.mailers.sendgrid.password')),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'sendgrid_api_key_in_db' => !empty(Setting::getEncrypted('sendgrid_api_key')),
            'sendgrid_from_email_in_db' => Setting::get('sendgrid_from_email'),
        ];

        return response()->json($config);
    }
}
