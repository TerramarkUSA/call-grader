<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallInteraction;
use App\Models\TranscriptionLog;
use App\Services\CTMService;
use App\Services\DeepgramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranscriptionController extends Controller
{
    /**
     * Process a call - fetch recording, transcribe, show grading UI
     */
    public function process(Call $call)
    {
        $this->authorize('view', $call);

        // Check if already transcribed
        if ($call->transcript) {
            return redirect()->route('manager.calls.grade', $call);
        }

        // Ensure relationships are loaded for the view
        $call->loadMissing(['rep', 'project']);

        // Log that the manager opened this call
        CallInteraction::create([
            'call_id' => $call->id,
            'user_id' => Auth::id(),
            'action' => 'opened',
            'page_seconds' => 0,
            'created_at' => now(),
        ]);

        // Show processing page
        return view('manager.calls.transcribe', compact('call'));
    }

    /**
     * Start transcription (called via AJAX or form submit)
     */
    public function transcribe(Request $request, Call $call)
    {
        $this->authorize('transcribe', $call);

        // Already transcribed?
        if ($call->transcript) {
            return response()->json([
                'success' => true,
                'message' => 'Already transcribed',
                'redirect' => route('manager.calls.grade', $call),
            ]);
        }

        // Get recording URL from CTM
        $ctmService = new CTMService($call->account);
        $recordingUrl = $ctmService->getRecordingUrl($call->ctm_activity_id);

        if (!$recordingUrl) {
            return response()->json([
                'success' => false,
                'message' => 'No recording found for this call',
            ], 404);
        }

        // Download and store recording locally
        $recordingPath = $this->downloadRecording($call, $recordingUrl);

        if (!$recordingPath) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download recording',
            ], 500);
        }

        // Transcribe with Deepgram
        $deepgram = new DeepgramService();
        $result = $deepgram->transcribeUrl($recordingUrl);

        // Log transcription attempt
        $cost = $deepgram->calculateCost($call->talk_time);
        TranscriptionLog::create([
            'call_id' => $call->id,
            'user_id' => Auth::id(),
            'audio_duration_seconds' => $call->talk_time,
            'cost' => $cost,
            'model' => 'nova-3',
            'success' => $result['success'],
            'error_message' => $result['message'] ?? null,
        ]);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 500);
        }

        // Store transcript and record who transcribed
        $call->update([
            'transcript' => $result['transcript'],
            'recording_path' => $recordingPath,
            'transcribed_by' => Auth::id(),
        ]);

        // Log transcription interaction
        CallInteraction::create([
            'call_id' => $call->id,
            'user_id' => Auth::id(),
            'action' => 'transcribed',
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transcription complete',
            'redirect' => route('manager.calls.grade', $call),
        ]);
    }

    /**
     * Download recording from CTM and store locally
     */
    protected function downloadRecording(Call $call, string $url): ?string
    {
        try {
            $response = Http::timeout(60)->get($url);

            if (!$response->successful()) {
                return null;
            }

            $filename = "recordings/{$call->id}_{$call->ctm_activity_id}.mp3";
            Storage::put($filename, $response->body());

            return $filename;
        } catch (\Exception $e) {
            Log::error('Failed to download recording', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get recording URL for audio preview (before transcription)
     */
    public function getRecordingUrl(Call $call)
    {
        $this->authorize('view', $call);

        // If we already have the recording downloaded, use local file
        if ($call->recording_path && Storage::exists($call->recording_path)) {
            return response()->json([
                'success' => true,
                'recording_url' => route('manager.calls.audio', $call),
            ]);
        }

        // Otherwise fetch from CTM
        $ctmService = new CTMService($call->account);
        $url = $ctmService->getRecordingUrl($call->ctm_activity_id);

        if (!$url) {
            return response()->json([
                'success' => false,
                'message' => 'Recording not available yet',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'recording_url' => $url,
        ]);
    }

    /**
     * Receive page-time beacon (best-effort, fire-and-forget)
     */
    public function logPageTime(Request $request, Call $call)
    {
        $pageSeconds = min((int) $request->input('page_seconds', 0), CallInteraction::PAGE_SECONDS_CAP);
        $playbackSeconds = max(0, (int) $request->input('playback_seconds', 0));

        $update = ['page_seconds' => $pageSeconds];
        if ($playbackSeconds > 0) {
            $update['metadata'] = json_encode(['playback_seconds' => $playbackSeconds]);
        }

        // Update the most recent 'opened' interaction for this user/call
        CallInteraction::where('call_id', $call->id)
            ->where('user_id', Auth::id())
            ->where('action', 'opened')
            ->latest('created_at')
            ->take(1)
            ->update($update);

        return response()->noContent();
    }

    /**
     * Stream recording audio
     */
    public function audio(Call $call)
    {
        $this->authorize('view', $call);

        if (!$call->recording_path || !Storage::exists($call->recording_path)) {
            abort(404, 'Recording not found');
        }

        $path = Storage::path($call->recording_path);

        $mimeType = 'audio/mpeg';
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension === 'wav') {
            $mimeType = 'audio/wav';
        }

        // Use response()->file() for proper range request support (needed for audio seeking)
        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
