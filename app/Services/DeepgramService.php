<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class DeepgramService
{
    protected ?string $apiKey;
    protected string $baseUrl = 'https://api.deepgram.com/v1';

    public function __construct()
    {
        // First try database setting, then fall back to .env config
        $this->apiKey = Setting::getEncrypted('deepgram_api_key') ?? config('services.deepgram.api_key');
    }

    /**
     * Check if API key is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get transcription options from database settings
     */
    protected function getDefaultOptions(): array
    {
        $multichannel = Setting::get('deepgram_multichannel', 'true') === 'true';

        $options = [
            'model' => Setting::get('deepgram_model', 'nova-3'),
            'smart_format' => Setting::get('deepgram_smart_format', 'true'),
            'punctuate' => Setting::get('deepgram_punctuate', 'true'),
            'utterances' => Setting::get('deepgram_utterances', 'true'),
            'paragraphs' => Setting::get('deepgram_paragraphs', 'true'),
        ];

        if ($multichannel) {
            // Multichannel mode - separate audio channels for Rep/Prospect
            $options['multichannel'] = 'true';
            // Diarization is mutually exclusive with multichannel
        } else {
            // Single channel mode - use diarization to identify speakers
            $options['diarize'] = Setting::get('deepgram_diarize', 'true');
        }

        return $options;
    }

    /**
     * Check if multichannel mode is enabled
     */
    public function isMultichannelEnabled(): bool
    {
        return Setting::get('deepgram_multichannel', 'true') === 'true';
    }

    /**
     * Transcribe audio from URL
     */
    public function transcribeUrl(string $audioUrl, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Deepgram API key not configured. Add DEEPGRAM_API_KEY to .env file.',
            ];
        }

        $defaultOptions = $this->getDefaultOptions();

        $params = array_merge($defaultOptions, $options);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(120)
            ->post($this->baseUrl . '/listen?' . http_build_query($params), [
                'url' => $audioUrl,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'transcript' => $this->formatTranscript($data),
                    'raw' => $data,
                    'duration' => $data['metadata']['duration'] ?? 0,
                ];
            }

            Log::error('Deepgram transcription failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Transcription failed: ' . ($response->json('err_msg') ?? 'Unknown error'),
            ];

        } catch (Exception $e) {
            Log::error('Deepgram exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Transcription error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Transcribe audio from file
     */
    public function transcribeFile(string $filePath, string $mimeType = 'audio/mpeg', array $options = []): array
    {
        $defaultOptions = $this->getDefaultOptions();

        $params = array_merge($defaultOptions, $options);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiKey,
                'Content-Type' => $mimeType,
            ])
            ->timeout(120)
            ->withBody(file_get_contents($filePath), $mimeType)
            ->post($this->baseUrl . '/listen?' . http_build_query($params));

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'transcript' => $this->formatTranscript($data),
                    'raw' => $data,
                    'duration' => $data['metadata']['duration'] ?? 0,
                ];
            }

            return [
                'success' => false,
                'message' => 'Transcription failed: ' . ($response->json('err_msg') ?? 'Unknown error'),
            ];

        } catch (Exception $e) {
            Log::error('Deepgram exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Transcription error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Format transcript with speaker labels and timestamps
     */
    protected function formatTranscript(array $data): array
    {
        $formatted = [
            'text' => '',
            'utterances' => [],
            'words' => [],
            'multichannel' => false,
        ];

        $results = $data['results'] ?? [];
        $channels = $results['channels'] ?? [];

        // Check if this is a multichannel response (multiple channels with data)
        $isMultichannel = count($channels) > 1;
        $formatted['multichannel'] = $isMultichannel;

        if ($isMultichannel) {
            // Multichannel mode: merge channels into utterances
            // Channel 0 = Rep (speaker 0), Channel 1 = Prospect (speaker 1)
            $formatted['utterances'] = $this->mergeMultichannelUtterances($channels);

            // Build full text from merged utterances
            $textParts = [];
            foreach ($formatted['utterances'] as $utterance) {
                $textParts[] = $utterance['text'];
            }
            $formatted['text'] = implode(' ', $textParts);
        } else {
            // Single channel / diarization mode
            if (!empty($channels)) {
                $alternatives = $channels[0]['alternatives'] ?? [];
                if (!empty($alternatives)) {
                    $formatted['text'] = $alternatives[0]['transcript'] ?? '';
                    $formatted['words'] = $alternatives[0]['words'] ?? [];
                }
            }

            // Get utterances (speaker-separated segments)
            $utterances = $results['utterances'] ?? [];

            foreach ($utterances as $utterance) {
                $formatted['utterances'][] = [
                    'speaker' => $utterance['speaker'] ?? 0,
                    'text' => $utterance['transcript'] ?? '',
                    'start' => $utterance['start'] ?? 0,
                    'end' => $utterance['end'] ?? 0,
                    'confidence' => $utterance['confidence'] ?? 0,
                ];
            }

            // If no utterances but we have words with speakers, build utterances
            if (empty($formatted['utterances']) && !empty($formatted['words'])) {
                $formatted['utterances'] = $this->buildUtterancesFromWords($formatted['words']);
            }
        }

        return $formatted;
    }

    /**
     * Merge multichannel results into chronological utterances
     * Channel 0 = Rep (speaker 0), Channel 1 = Prospect (speaker 1)
     *
     * Creates one utterance per speaker turn - only breaks when the channel changes.
     */
    protected function mergeMultichannelUtterances(array $channels): array
    {
        // Collect all words from all channels with their channel index
        $allWords = [];

        foreach ($channels as $channelIndex => $channel) {
            $alternatives = $channel['alternatives'] ?? [];
            if (empty($alternatives)) {
                continue;
            }

            $words = $alternatives[0]['words'] ?? [];
            foreach ($words as $word) {
                $allWords[] = [
                    'channel' => $channelIndex,
                    'word' => $word['punctuated_word'] ?? $word['word'] ?? '',
                    'start' => $word['start'] ?? 0,
                    'end' => $word['end'] ?? 0,
                    'confidence' => $word['confidence'] ?? 0,
                ];
            }
        }

        // Sort all words by start time
        usort($allWords, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        // Build utterances - only break when channel changes
        $utterances = [];
        $currentUtterance = null;

        foreach ($allWords as $word) {
            if ($currentUtterance === null) {
                // Start first utterance
                $currentUtterance = [
                    'speaker' => $word['channel'],
                    'text' => $word['word'],
                    'start' => $word['start'],
                    'end' => $word['end'],
                    'confidence' => $word['confidence'],
                ];
            } elseif ($currentUtterance['speaker'] !== $word['channel']) {
                // Channel changed - save current and start new utterance
                $utterances[] = $currentUtterance;
                $currentUtterance = [
                    'speaker' => $word['channel'],
                    'text' => $word['word'],
                    'start' => $word['start'],
                    'end' => $word['end'],
                    'confidence' => $word['confidence'],
                ];
            } else {
                // Same channel - append word to current utterance
                $currentUtterance['text'] .= ' ' . $word['word'];
                $currentUtterance['end'] = $word['end'];
            }
        }

        // Don't forget the last utterance
        if ($currentUtterance !== null) {
            $utterances[] = $currentUtterance;
        }

        return $utterances;
    }

    /**
     * Build utterances from word-level data if utterances aren't provided
     */
    protected function buildUtterancesFromWords(array $words): array
    {
        $utterances = [];
        $currentUtterance = null;

        foreach ($words as $word) {
            $speaker = $word['speaker'] ?? 0;

            if ($currentUtterance === null || $currentUtterance['speaker'] !== $speaker) {
                if ($currentUtterance !== null) {
                    $utterances[] = $currentUtterance;
                }
                $currentUtterance = [
                    'speaker' => $speaker,
                    'text' => $word['word'] ?? $word['punctuated_word'] ?? '',
                    'start' => $word['start'] ?? 0,
                    'end' => $word['end'] ?? 0,
                    'confidence' => $word['confidence'] ?? 0,
                ];
            } else {
                $currentUtterance['text'] .= ' ' . ($word['punctuated_word'] ?? $word['word'] ?? '');
                $currentUtterance['end'] = $word['end'] ?? $currentUtterance['end'];
            }
        }

        if ($currentUtterance !== null) {
            $utterances[] = $currentUtterance;
        }

        return $utterances;
    }

    /**
     * Calculate cost for transcription
     */
    public function calculateCost(float $durationSeconds): float
    {
        // Nova-3 pricing: approximately $0.0043 per minute
        $minutes = $durationSeconds / 60;
        return round($minutes * 0.0043, 4);
    }
}
