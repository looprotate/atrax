<?php

require_once __DIR__ . '/../includes/auth.php';
requiere_rol('operador');

require_once __DIR__ . '/../modelo/pedidos.php';
require_once __DIR__ . '/../modelo/zonas.php';

solo_post();
validar_token();

$accion   = campo('accion');
$pedidoId = entero(campo('pedido_id'));
$pedido   = $pedidoId === null ? null : pedido_por_id($pedidoId);
$volver   = 'operador/distribucion.php';

if ($pedido === null) {
    volver_con_errores($volver, ['El pedido indicado no existe.']);
}

if ($accion === 'quitar') {
    if ($pedido['estado'] !== 'Listo') {
        volver_con_errores($volver, ['Solo se puede quitar la asignacion de un pedido en estado "Listo".']);
    }

    asignar_vehiculo($pedidoId, null);
    volver_con_exito($volver, 'Se quito la asignacion del pedido #' . $pedidoId . '.');
}

if ($accion !== 'asignar') {
    volver_con_errores($volver, ['La accion solicitada no es valida.']);
}

$vehiculoId = entero(campo('vehiculo_id'));
$vehiculo   = $vehiculoId === null ? null : vehiculo_por_id($vehiculoId);
$errores    = [];

if ($pedido['estado'] !== 'Listo') {
    $errores[] = 'Solo se pueden asignar pedidos en estado "Listo".';
}

if ($vehiculo === null) {
    $errores[] = 'El vehiculo seleccionado no existe.';
} else {
    if ($vehiculo['estado'] === 'Mantenimiento') {
        $errores[] = 'El vehiculo ' . $vehiculo['nombre'] . ' esta en mantenimiento.';
    }

    if ((int) $vehiculo['zona_id'] !== (int) $pedido['zona_id']) {
        $errores[] = 'El vehiculo no cubre la zona ' . $pedido['zona_codigo'] . ' del pedido.';
    }

    // La carga del vehiculo ya incluye al pedido cuando este ya esta asignado a el.
    $viandasNuevas = (int) $pedido['vehiculo_id'] === (int) $vehiculo['id'] ? 0 : (int) $pedido['viandas'];

    if ((int) $vehiculo['carga'] + $viandasNuevas > (int) $vehiculo['capacidad']) {
        $errores[] = 'El vehiculo ' . $vehiculo['nombre'] . ' no tiene capacidad suficiente ('
            . (int) $vehiculo['carga'] . ' + ' . $viandasNuevas . ' supera ' . (int) $vehiculo['capacidad'] . ').';
    }
}

if ($errores) {
    volver_con_errores($volver, $errores);
}

asignar_vehiculo($pedidoId, $vehiculoId);
volver_con_exito($volver, 'Pedido #' . $pedidoId . ' asignado a ' . $vehiculo['nombre'] . '.');
