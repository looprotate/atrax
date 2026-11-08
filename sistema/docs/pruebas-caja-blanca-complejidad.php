<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Complejidad ciclomatica V(G) = decisiones + 1 de cada funcion de includes/ y modelo/
// y de cada script de acciones/ y api/. Cuenta if, elseif, for, foreach, while, case, catch,
// &&, ||, ?? y el operador ternario sobre los tokens de PHP.
//
//   php docs/pruebas-caja-blanca-complejidad.php

const TOKENS_DE_DECISION = [
    T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_CASE, T_CATCH,
    T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_COALESCE,
];

function es_ignorable($token): bool
{
    return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

function medir_codigo(string $codigo): array
{
    $tokens     = token_get_all($codigo);
    $unidades   = [];
    $abiertas   = [];
    $pendiente  = null;
    $anterior   = null;
    $profundidad = 0;
    $script     = ['decisiones' => 0, 'linea' => 1, 'fin' => substr_count($codigo, "\n") + 1];

    $lineaActual = 1;

    foreach ($tokens as $indice => $token) {
        $linea        = $lineaActual;
        $lineaActual += substr_count(is_array($token) ? $token[1] : $token, "\n");

        if (es_ignorable($token)) {
            continue;
        }

        $id = is_array($token) ? $token[0] : $token;

        if ($id === T_FUNCTION) {
            $nombre = null;

            for ($i = $indice + 1; $i < count($tokens); $i++) {
                if (es_ignorable($tokens[$i])) {
                    continue;
                }
                if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $nombre = $tokens[$i][1];
                }
                break;
            }

            if ($nombre !== null && $abiertas === []) {
                $pendiente = ['nombre' => $nombre, 'linea' => $linea];
            }
        }

        if ($token === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
            $profundidad++;

            if ($pendiente !== null && $token === '{') {
                $abiertas[] = $pendiente + ['profundidad' => $profundidad, 'decisiones' => 0];
                $pendiente  = null;
            }
        } elseif ($token === '}') {
            $cierre = $abiertas === [] ? null : $abiertas[count($abiertas) - 1];

            if ($cierre !== null && $cierre['profundidad'] === $profundidad) {
                array_pop($abiertas);
                $unidades[] = [
                    'nombre'     => $cierre['nombre'],
                    'linea'      => $cierre['linea'],
                    'fin'        => $linea,
                    'decisiones' => $cierre['decisiones'],
                ];
            }
            $profundidad--;
        }

        $esDecision = in_array($id, TOKENS_DE_DECISION, true)
            || ($token === '?' && !in_array($anterior, ['(', ',', ':'], true));

        if ($esDecision) {
            if ($abiertas === []) {
                $script['decisiones']++;
            } else {
                $abiertas[count($abiertas) - 1]['decisiones']++;
            }
        }

        $anterior = $token;
    }

    return ['funciones' => $unidades, 'script' => $script];
}

function riesgo(int $complejidad): string
{
    if ($complejidad <= 10) {
        return 'baja';
    }

    return $complejidad <= 20 ? 'moderada' : 'alta';
}

$raiz  = dirname(__DIR__);
$filas = [];

foreach (['includes', 'modelo', 'acciones', 'api'] as $carpeta) {
    $archivos = glob($raiz . '/' . $carpeta . '/*.php');
    sort($archivos);

    foreach ($archivos as $archivo) {
        $relativo = $carpeta . '/' . basename($archivo);
        $medido   = medir_codigo(file_get_contents($archivo));

        foreach ($medido['funciones'] as $funcion) {
            $filas[] = [$relativo, $funcion['nombre'] . '()', $funcion['fin'] - $funcion['linea'] + 1, $funcion['decisiones'] + 1];
        }

        if ($carpeta === 'acciones' || $carpeta === 'api') {
            $filas[] = [$relativo, '(script)', $medido['script']['fin'], $medido['script']['decisiones'] + 1];
        }
    }
}

usort($filas, fn ($a, $b) => [$b[3], $a[0], $a[1]] <=> [$a[3], $b[0], $b[1]]);

echo "| Archivo | Unidad | Lineas | V(G) | Riesgo |\n|---|---|---|---|---|\n";

foreach ($filas as [$archivo, $unidad, $lineas, $complejidad]) {
    echo "| `$archivo` | `$unidad` | $lineas | $complejidad | " . riesgo($complejidad) . " |\n";
}

$total    = count($filas);
$suma     = array_sum(array_column($filas, 3));
$altas    = count(array_filter($filas, fn ($f) => $f[3] > 10));

echo "\nUnidades medidas: $total. V(G) total: $suma. Promedio: " . number_format($suma / $total, 2, ',', '') . '. ';
echo "Maximo: " . $filas[0][3] . " (" . $filas[0][0] . ' ' . $filas[0][1] . "). Unidades con V(G) mayor a 10: $altas.\n";
