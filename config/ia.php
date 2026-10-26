<?php

return [
    // Verificação de conformidade com as normas do programa via RAG (Ollama).
    'conformidade_rag' => (bool) env('IA_CONFORMIDADE_RAG', false),

    // Análise do resumo por IA (Ollama) após o envio do PDF.
    'analise_resumo' => (bool) env('IA_ANALISE_RESUMO', false),
];
