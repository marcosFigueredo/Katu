<?php

return [
    'url'     => env('OLLAMA_URL', 'http://localhost:11434'),
    'model'   => env('OLLAMA_MODEL', 'qwen2.5:7b'),
    'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
];
