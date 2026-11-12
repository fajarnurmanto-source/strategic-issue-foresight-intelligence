<?php

return [
    'driver' => env('DECISION_AI_DRIVER', 'off'), // off | ollama | gemini | deepseek
    'timeout' => (int) env('DECISION_AI_TIMEOUT', 25),
    'max_input_chars' => (int) env('DECISION_AI_MAX_INPUT_CHARS', 12000),
    'ollama' => [
        'url' => env('DECISION_OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('DECISION_OLLAMA_MODEL', ''),
    ],
    'gemini' => [
        'key' => env('DECISION_GEMINI_API_KEY', ''),
        'model' => env('DECISION_GEMINI_MODEL', ''),
    ],
    'deepseek' => [
        'key' => env('DECISION_DEEPSEEK_API_KEY', ''),
        'model' => env('DECISION_DEEPSEEK_MODEL', ''),
    ],
];
