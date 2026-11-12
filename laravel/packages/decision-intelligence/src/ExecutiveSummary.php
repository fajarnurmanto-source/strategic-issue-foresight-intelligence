<?php

namespace DecisionIntelligence;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Advisory only. This class never saves, verifies, or authorizes domain decisions. */
final class ExecutiveSummary
{
    public function generate(array $evidence, string $decisionQuestion): array
    {
        $driver = (string) config('decision-intelligence.driver', 'off');
        $limits = (int) config('decision-intelligence.max_input_chars', 12000);
        if (! in_array($driver, ['ollama', 'gemini', 'deepseek'], true)) {
            return ['status' => 'AI_DISABLED', 'provider' => 'off', 'advisory' => null];
        }
        if (! $evidence) {
            return ['status' => 'INSUFFICIENT_EVIDENCE', 'provider' => $driver, 'advisory' => null];
        }
        $data = ['question' => $decisionQuestion, 'evidence' => $evidence];
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (mb_strlen($payload) > $limits) {
            return ['status' => 'INPUT_TOO_LARGE', 'provider' => $driver, 'advisory' => null];
        }
        $instructions = 'Anda adalah analis pendukung keputusan. Hanya gunakan evidence input, bukan pengetahuan yang tidak disitasi. '
            .'Balas dalam JSON object dengan keys executive_summary (string), options (array of strings), recommendation (string), '
            .'risks (array of strings), evidence_ids (array of strings), uncertainties (array of strings), human_decision_required (boolean). '
            .'Jika bukti tidak cukup, tulis ketidakpastian secara eksplisit. Jangan mengklaim verifikasi fakta atau mengambil keputusan otomatis.';
        $timeout = max(3, min(60, (int) config('decision-intelligence.timeout', 25)));
        try {
            $body = $this->dispatch($driver, $instructions, $payload, $timeout);
            $parsed = json_decode($body, true);
            if (! is_array($parsed) || ! isset($parsed['executive_summary'], $parsed['recommendation'], $parsed['human_decision_required'])) {
                return ['status' => 'INVALID_MODEL_OUTPUT', 'provider' => $driver, 'advisory' => null];
            }

            return ['status' => 'ADVISORY_UNVERIFIED', 'provider' => $driver, 'advisory' => [
                'executive_summary' => (string) $parsed['executive_summary'],
                'options' => array_values(array_filter((array) ($parsed['options'] ?? []), 'is_string')),
                'recommendation' => (string) $parsed['recommendation'],
                'risks' => array_values(array_filter((array) ($parsed['risks'] ?? []), 'is_string')),
                'evidence_ids' => array_values(array_filter((array) ($parsed['evidence_ids'] ?? []), 'is_string')),
                'uncertainties' => array_values(array_filter((array) ($parsed['uncertainties'] ?? []), 'is_string')),
                'human_decision_required' => true,
            ]];
        } catch (\Throwable $e) {
            // Never expose API keys/HTTP error bodies; do not silently switch to another provider.
            return ['status' => 'PROVIDER_UNAVAILABLE', 'provider' => $driver, 'advisory' => null];
        }
    }

    private function dispatch(string $driver, string $system, string $payload, int $timeout): string
    {
        $http = Http::timeout($timeout)->connectTimeout(5)->acceptJson();
        if ($driver === 'ollama') {
            $base = rtrim((string) config('decision-intelligence.ollama.url'), '/');
            // Localhost only. No user-selected remote destination.
            if (! in_array($base, ['http://127.0.0.1:11434', 'http://localhost:11434'], true)) {
                throw new RuntimeException('Ollama URL not local');
            }
            $model = (string) config('decision-intelligence.ollama.model');
            if ($model === '') {
                throw new RuntimeException('Ollama model unset');
            }
            $json = $http->post($base.'/api/chat', [
                'model' => $model, 'stream' => false, 'format' => 'json',
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $payload]],
            ])->throw()->json();

            return (string) ($json['message']['content'] ?? '');
        }
        if ($driver === 'gemini') {
            $key = (string) config('decision-intelligence.gemini.key');
            $model = (string) config('decision-intelligence.gemini.model');
            if ($key === '' || ! preg_match('/\A[a-zA-Z0-9_.-]+\z/D', $model)) {
                throw new RuntimeException('Gemini credentials/model unset');
            }
            $json = $http->withHeaders(['x-goog-api-key' => $key])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $payload]]]],
                    'generationConfig' => ['responseMimeType' => 'application/json'],
                ])->throw()->json();

            return (string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
        }
        $key = (string) config('decision-intelligence.deepseek.key');
        $model = (string) config('decision-intelligence.deepseek.model');
        if ($key === '' || $model === '') {
            throw new RuntimeException('DeepSeek credentials/model unset');
        }
        $json = $http->withToken($key)->post('https://api.deepseek.com/chat/completions', [
            'model' => $model, 'stream' => false,
            'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $payload]],
        ])->throw()->json();

        return (string) ($json['choices'][0]['message']['content'] ?? '');
    }
}
