<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../modelo/pedidos.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    responder_json(405, ['error' => 'Metodo no permitido.']);
}

if (!autenticado()) {
    responder_json(401, ['error' => 'Tu sesion vencio. Vuelve a iniciar sesion.']);
}

if (rol_actual() !== 'administrador') {
    responder_json(403, ['error' => 'No tenes permiso para consultar los pedidos.']);
}

$estado  = (string) ($_GET['estado'] ?? '');
$zonaId  = (string) ($_GET['zona_id'] ?? '');
$buscar  = trim((string) ($_GET['buscar'] ?? ''));
$errores = [];

if ($estado !== '' && !in_array($estado, estados_pedido(), true)) {
    $errores[] = 'El estado indicado no es valido.';
}

if ($zonaId !== '' && entero($zonaId) === null) {
    $errores[] = 'La zona indicada no es valida.';
}

if (mb_strlen($buscar) > 100) {
    $errores[] = 'La busqueda no puede superar 100 caracteres.';
}

if ($errores) {
    responder_json(422, ['error' => implode(' ', $errores)]);
}

try {
    $pedidos = listar_pedidos(['estado' => $estado, 'zona_id' => entero($zonaId), 'buscar' => $buscar]);
} catch (Throwable $falla) {
    error_log('api/pedidos: ' . $falla->getMessage());
    responder_json(500, ['error' => 'No se pudieron cargar los pedidos. Intenta nuevamente en unos minutos.']);
}

responder_json(200, [
    'total'   => count($pedidos),
    'pedidos' => array_map(static fn (array $pedido): array => [
        'id'       => (int) $pedido['id'],
        'cliente'  => $pedido['cliente'],
        'tipo'     => $pedido['tipo'],
        'zona'     => $pedido['zona_codigo'],
        'entrega'  => fecha_corta($pedido['fecha_entrega']),
        'viandas'  => (int) $pedido['viandas'],
        'estado'   => $pedido['estado'],
        'clase'    => clase_estado($pedido['estado']),
        'vehiculo' => $pedido['vehiculo'] ?? 'sin asignar',
        'total'    => moneda($pedido['total']),
    ], $pedidos),
]);
