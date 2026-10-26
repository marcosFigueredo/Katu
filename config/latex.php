<?php

return [
    'binario' => env('LATEXMK_BIN', 'latexmk'),
    'timeout' => (int) env('LATEX_TIMEOUT', 180),
];
