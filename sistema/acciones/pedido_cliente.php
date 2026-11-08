<?php

require_once __DIR__ . '/../includes/auth.php';
requiere_rol('cliente');

require_once __DIR__ . '/../modelo/catalogo.php';
require_once __DIR__ . '/../modelo/stock.php';
require_once __DIR__ . '/../modelo/pedidos.php';
require_once __DIR__ . '/../modelo/usuarios.php';

solo_post();
validar_token();

$volver    = 'cliente/pedido.php';
$cliente   = cliente_de_usuario((int) usuario()['id']);
$planId    = entero(campo('plan_id'));
$fecha     = campo('fecha_entrega');
$cantidades = $_POST['cantidad'] ?? [];
$plan      = $planId === null ? null : plan_por_id($planId);
$errores   = [];
$lineas    = [];
$totalViandas = 0;
$datos     = ['plan_id' => campo('plan_id'), 'fecha_entrega' => $fecha];

if ($cliente === null) {
    volver_con_errores($volver, ['Tu cuenta no tiene una ficha de cliente asociada.']);
}

if ($plan === null || (int) $plan['activo'] !== 1) {
    $errores[] = 'La modalidad seleccionada no esta disponible.';
}

if (!es_fecha($fecha)) {
    $errores[] = 'La fecha de entrega no es valida.';
} elseif ($fecha < date('Y-m-d', strtotime('+1 day'))) {
    $errores[] = 'La entrega debe programarse como minimo para manana.';
} elseif ($fecha > date('Y-m-d', strtotime('+60 days'))) {
    $errores[] = 'La entrega no puede programarse a mas de 60 dias.';
}

if (!is_array($cantidades)) {
    $cantidades = [];
}

foreach ($cantidades as $productoId => $valor) {
    $productoId = entero($productoId);
    $cantidad   = entero(trim((string) $valor));

    if ($cantidad === null || $cantidad < 0) {
        $errores[] = 'Las cantidades deben ser numeros enteros mayores o iguales a 0.';
        continue;
    }

    if ($cantidad === 0) {
        continue;
    }

    $producto = $productoId === null ? null : producto_por_id($productoId);
    $datos['cantidad_' . $productoId] = $cantidad;

    if ($producto === null || (int) $producto['activo'] !== 1) {
        $errores[] = 'Uno de los platos seleccionados ya no esta disponible.';
        continue;
    }

    $disponible = disponible_de($productoId);

    if ($cantidad > $disponible) {
        $errores[] = 'No hay stock suficiente de ' . $producto['nombre'] . ': pediste ' . $cantidad . ' y hay ' . $disponible . '.';
        continue;
    }

    $totalViandas += $cantidad;
    $lineas[] = [
        'producto_id'     => $productoId,
        'cantidad'        => $cantidad,
        'precio_unitario' => (float) $producto['precio'],
        'subtotal'        => $cantidad * (float) $producto['precio'],
    ];
}

if (!$lineas) {
    $errores[] = 'Tenes que elegir al menos un plato.';
}

if ($plan !== null && $totalViandas !== (int) $plan['viandas']) {
    $errores[] = 'El plan ' . $plan['nombre'] . ' requiere exactamente ' . (int) $plan['viandas'] . ' viandas y elegiste ' . $totalViandas . '.';
}

if ($errores) {
    volver_con_errores($volver, $errores, $datos);
}

try {
    $pedidoId = crear_pedido(
        (int) $cliente['id'],
        $planId,
        (int) $cliente['zona_id'],
        $fecha,
        $lineas,
        (int) usuario()['id']
    );
} catch (Throwable $error) {
    volver_con_errores($volver, ['No se pudo registrar el pedido: ' . $error->getMessage()], $datos);
}

flash_guardar('exito_titulo', 'Pedido confirmado');
flash_guardar('celebrar', true);
volver_con_exito('cliente/seguimiento.php?id=' . $pedidoId, 'Pedido #' . $pedidoId . ' confirmado. Te avisamos cuando este en camino.');
