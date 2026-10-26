<?php

namespace App\Services\Latex;

use RuntimeException;

/** Rede, tempo limite ou erro do serviço externo ao consultar uma referência: vira "não verificada". */
class FalhaConsultaReferencia extends RuntimeException {}
