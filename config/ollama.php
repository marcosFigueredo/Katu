<?php

return [
    'url'              => env('OLLAMA_URL', 'http://localhost:11434'),
    'model'            => env('OLLAMA_MODEL', 'qwen2.5:7b'),
    'embedding_model'  => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    'timeout'          => (int) env('OLLAMA_TIMEOUT', 120),
];
