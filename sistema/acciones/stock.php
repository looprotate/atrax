<?php

require_once __DIR__ . '/../includes/auth.php';
requiere_rol('operador');

require_once __DIR__ . '/../modelo/stock.php';

solo_post();
validar_token();

$loteId   = entero(campo('lote_id'));
$tipo     = campo('tipo');
$cantidad = entero(campo('cantidad'));
$motivo   = campo('motivo');
$errores  = [];
$lote     = $loteId === null ? null : lote_por_id($loteId);

if ($lote === null) {
    $errores[] = 'El lote seleccionado no existe.';
} elseif ($lote['estado'] === 'Descartado') {
    $errores[] = 'El lote ya fue descartado.';
}

if (!in_array($tipo, ['Salida', 'Ajuste', 'Descarte'], true)) {
    $errores[] = 'El tipo de movimiento no es valido.';
}

if ($tipo !== 'Descarte' && ($cantidad === null || $cantidad < 1 || $cantidad > 500)) {
    $errores[] = 'La cantidad debe ser un numero entre 1 y 500.';
}

if (mb_strlen($motivo) < 3) {
    $errores[] = 'El motivo debe tener al menos 3 caracteres.';
} elseif (mb_strlen($motivo) > 150) {
    $errores[] = 'El motivo no puede superar 150 caracteres.';
}

if (!$errores && $tipo === 'Salida' && $cantidad > (int) $lote['disponible']) {
    $errores[] = 'La salida (' . $cantidad . ') supera el stock disponible del lote (' . (int) $lote['disponible'] . ').';
}

if (!$errores && $tipo === 'Ajuste' && $cantidad + (int) $lote['disponible'] > (int) $lote['cantidad']) {
    $errores[] = 'El ajuste no puede superar la cantidad original producida en el lote (' . (int) $lote['cantidad'] . ').';
}

if ($errores) {
    volver_con_errores('operador/stock.php', $errores);
}

if ($tipo === 'Descarte') {
    descartar_lote((int) $lote['id'], (int) usuario()['id'], (int) $lote['disponible']);
    volver_con_exito('operador/stock.php', 'Lote ' . $lote['numero_lote'] . ' descartado.');
}

ajustar_lote((int) $lote['id'], (int) usuario()['id'], $tipo, $cantidad, (int) $lote['disponible'], $motivo);
volver_con_exito('operador/stock.php', 'Movimiento registrado sobre el lote ' . $lote['numero_lote'] . '.');
