<?php

require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/stock.php';

function estados_pedido(): array
{
    return ['Pendiente', 'Preparando', 'Listo', 'En distribucion', 'Entregado', 'Cancelado'];
}

function transiciones(string $estado): array
{
    $mapa = [
        'Pendiente'       => ['Preparando', 'Cancelado'],
        'Preparando'      => ['Listo', 'Cancelado'],
        'Listo'           => ['En distribucion', 'Cancelado'],
        'En distribucion' => ['Entregado'],
        'Entregado'       => [],
        'Cancelado'       => [],
    ];

    return $mapa[$estado] ?? [];
}

function listar_pedidos(array $filtros = []): array
{
    $sql = 'SELECT p.id, p.fecha_pedido, p.fecha_entrega, p.estado, p.viandas, p.total, p.vehiculo_id, p.zona_id,
                   u.nombre AS cliente, c.tipo, z.codigo AS zona_codigo, z.nombre AS zona, v.nombre AS vehiculo
            FROM pedidos p
            INNER JOIN clientes c ON c.id = p.cliente_id
            INNER JOIN usuarios u ON u.id = c.usuario_id
            INNER JOIN zonas z ON z.id = p.zona_id
            LEFT JOIN vehiculos v ON v.id = p.vehiculo_id
            WHERE 1 = 1';
    $parametros = [];

    if (!empty($filtros['estado'])) {
        $sql .= ' AND p.estado = ?';
        $parametros[] = $filtros['estado'];
    }

    if (!empty($filtros['zona_id'])) {
        $sql .= ' AND p.zona_id = ?';
        $parametros[] = $filtros['zona_id'];
    }

    if (!empty($filtros['cliente_id'])) {
        $sql .= ' AND p.cliente_id = ?';
        $parametros[] = $filtros['cliente_id'];
    }

    if (!empty($filtros['buscar'])) {
        $sql .= ' AND (u.nombre LIKE ? OR p.id = ?)';
        $parametros[] = '%' . addcslashes($filtros['buscar'], '%_\\') . '%';
        $parametros[] = (int) ltrim($filtros['buscar'], '#');
    }

    if (!empty($filtros['hoy'])) {
        $sql .= ' AND p.fecha_entrega = CURDATE()';
    }

    if (!empty($filtros['sin_vehiculo'])) {
        $sql .= ' AND p.vehiculo_id IS NULL';
    }

    return consultar($sql . ' ORDER BY p.fecha_entrega, p.id DESC', $parametros);
}

function pedido_por_id(int $id): ?array
{
    return consultarFila(
        'SELECT p.id, p.cliente_id, p.plan_id, p.zona_id, p.vehiculo_id, p.fecha_pedido, p.fecha_entrega,
                p.estado, p.viandas, p.total,
                u.nombre AS cliente, c.direccion, c.tipo, z.codigo AS zona_codigo, z.nombre AS zona,
                pl.nombre AS plan, v.nombre AS vehiculo
         FROM pedidos p
         INNER JOIN clientes c ON c.id = p.cliente_id
         INNER JOIN usuarios u ON u.id = c.usuario_id
         INNER JOIN zonas z ON z.id = p.zona_id
         INNER JOIN planes pl ON pl.id = p.plan_id
         LEFT JOIN vehiculos v ON v.id = p.vehiculo_id
         WHERE p.id = ?',
        [$id]
    );
}

function detalle_pedido(int $pedidoId): array
{
    return consultar(
        'SELECT d.cantidad, d.precio_unitario, d.subtotal, p.nombre AS producto
         FROM pedido_detalle d
         INNER JOIN productos p ON p.id = d.producto_id
         WHERE d.pedido_id = ?
         ORDER BY p.nombre',
        [$pedidoId]
    );
}

function historial_pedido(int $pedidoId): array
{
    return consultar(
        'SELECT h.estado_anterior, h.estado_nuevo, h.fecha, u.nombre AS usuario
         FROM pedido_estados h
         INNER JOIN usuarios u ON u.id = h.usuario_id
         WHERE h.pedido_id = ?
         ORDER BY h.fecha, h.id',
        [$pedidoId]
    );
}

function crear_pedido(int $clienteId, int $planId, int $zonaId, string $fechaEntrega, array $lineas, int $usuarioId): int
{
    // Los productos se toman siempre en el mismo orden para que dos pedidos con los mismos platos
    // no se esperen entre si (bloqueo mutuo).
    usort($lineas, fn (array $a, array $b) => $a['producto_id'] <=> $b['producto_id']);

    return en_transaccion(function () use ($clienteId, $planId, $zonaId, $fechaEntrega, $lineas, $usuarioId) {
        $total   = 0.0;
        $viandas = 0;

        foreach ($lineas as $linea) {
            $total   += $linea['subtotal'];
            $viandas += $linea['cantidad'];
        }

        $pedidoId = insertar(
            'INSERT INTO pedidos (cliente_id, plan_id, zona_id, fecha_entrega, viandas, total)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$clienteId, $planId, $zonaId, $fechaEntrega, $viandas, $total]
        );

        foreach ($lineas as $linea) {
            ejecutar(
                'INSERT INTO pedido_detalle (pedido_id, producto_id, cantidad, precio_unitario, subtotal)
                 VALUES (?, ?, ?, ?, ?)',
                [$pedidoId, $linea['producto_id'], $linea['cantidad'], $linea['precio_unitario'], $linea['subtotal']]
            );

            descontar_fifo((int) $linea['producto_id'], (int) $linea['cantidad'], $usuarioId, $pedidoId);
        }

        ejecutar(
            'INSERT INTO pedido_estados (pedido_id, estado_anterior, estado_nuevo, usuario_id)
             VALUES (?, NULL, "Pendiente", ?)',
            [$pedidoId, $usuarioId]
        );

        return $pedidoId;
    });
}

// Devuelve a cada lote lo que ese pedido le saco, sin pasar de la cantidad que produjo el lote:
// si alguien ya repuso el lote con un ajuste, esas viandas no se cuentan dos veces.
function devolver_stock(int $pedidoId, int $usuarioId): void
{
    $salidas = consultar(
        'SELECT lote_id, cantidad FROM movimientos_stock WHERE pedido_id = ? AND tipo = "Salida"',
        [$pedidoId]
    );

    foreach ($salidas as $salida) {
        $lote = lote_por_id((int) $salida['lote_id'], true);

        if ($lote === null || $lote['estado'] === 'Descartado') {
            continue;
        }

        $devolver = min((int) $salida['cantidad'], (int) $lote['cantidad'] - (int) $lote['disponible']);

        if ($devolver <= 0) {
            continue;
        }

        $motivo = 'Devolucion por cancelacion del pedido #' . $pedidoId;

        if ($devolver < (int) $salida['cantidad']) {
            $motivo .= ' (parcial: el lote ya habia sido repuesto)';
        }

        ejecutar(
            'UPDATE lotes SET disponible = ?, estado = "Disponible" WHERE id = ?',
            [(int) $lote['disponible'] + $devolver, $lote['id']]
        );
        registrar_movimiento((int) $lote['id'], $usuarioId, 'Entrada', $devolver, $motivo, $pedidoId);
    }
}

function cambiar_estado(int $pedidoId, string $estadoActual, string $estadoNuevo, int $usuarioId): void
{
    en_transaccion(function () use ($pedidoId, $estadoActual, $estadoNuevo, $usuarioId) {
        ejecutar('UPDATE pedidos SET estado = ? WHERE id = ?', [$estadoNuevo, $pedidoId]);

        ejecutar(
            'INSERT INTO pedido_estados (pedido_id, estado_anterior, estado_nuevo, usuario_id) VALUES (?, ?, ?, ?)',
            [$pedidoId, $estadoActual, $estadoNuevo, $usuarioId]
        );

        if ($estadoNuevo === 'Cancelado') {
            devolver_stock($pedidoId, $usuarioId);
            ejecutar('UPDATE pedidos SET vehiculo_id = NULL WHERE id = ?', [$pedidoId]);
        }
    });
}

function asignar_vehiculo(int $pedidoId, ?int $vehiculoId): int
{
    return ejecutar('UPDATE pedidos SET vehiculo_id = ? WHERE id = ?', [$vehiculoId, $pedidoId]);
}

function produccion_del_dia(): array
{
    return consultar(
        'SELECT p.id, p.nombre,
                COALESCE(SUM(d.cantidad), 0) AS pedidas,
                COALESCE((SELECT SUM(l.cantidad) FROM lotes l
                          WHERE l.producto_id = p.id AND l.fecha_produccion = CURDATE()), 0) AS producidas
         FROM productos p
         LEFT JOIN pedido_detalle d ON d.producto_id = p.id
         LEFT JOIN pedidos pe ON pe.id = d.pedido_id
              AND pe.fecha_entrega = CURDATE()
              AND pe.estado IN ("Pendiente", "Preparando", "Listo")
         WHERE p.activo = 1
         GROUP BY p.id, p.nombre
         ORDER BY pedidas DESC, p.nombre'
    );
}

function despacho_por_zona(): array
{
    return consultar(
        'SELECT z.codigo, z.nombre, COUNT(p.id) AS pedidos, COALESCE(SUM(p.viandas), 0) AS viandas
         FROM zonas z
         INNER JOIN pedidos p ON p.zona_id = z.id
              AND p.fecha_entrega = CURDATE()
              AND p.estado IN ("Listo", "En distribucion")
         GROUP BY z.id, z.codigo, z.nombre
         ORDER BY z.codigo'
    );
}

function resumen_pedidos(): array
{
    return [
        'hoy'          => (int) consultarValor('SELECT COUNT(*) FROM pedidos WHERE fecha_entrega = CURDATE()', [], 0),
        'viandas_hoy'  => (int) consultarValor('SELECT COALESCE(SUM(viandas), 0) FROM pedidos WHERE fecha_entrega = CURDATE() AND estado IN ("Pendiente", "Preparando")', [], 0),
        'distribucion' => (int) consultarValor('SELECT COALESCE(SUM(viandas), 0) FROM pedidos WHERE estado = "En distribucion"', [], 0),
        'pendientes'   => (int) consultarValor('SELECT COUNT(*) FROM pedidos WHERE estado = "Pendiente"', [], 0),
        'facturado'    => (float) consultarValor('SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE estado <> "Cancelado"', [], 0),
    ];
}

function pedidos_por_zona(): array
{
    return consultar(
        'SELECT z.codigo, z.nombre, COUNT(p.id) AS pedidos, COALESCE(SUM(p.total), 0) AS facturado
         FROM zonas z
         LEFT JOIN pedidos p ON p.zona_id = z.id AND p.estado <> "Cancelado"
         GROUP BY z.id, z.codigo, z.nombre
         ORDER BY pedidos DESC, z.codigo'
    );
}

function pedidos_por_estado(): array
{
    return consultar('SELECT estado, COUNT(*) AS cantidad FROM pedidos GROUP BY estado ORDER BY cantidad DESC');
}

function platos_mas_pedidos(): array
{
    return consultar(
        'SELECT pr.nombre, COALESCE(SUM(d.cantidad), 0) AS viandas, COALESCE(SUM(d.subtotal), 0) AS facturado
         FROM productos pr
         LEFT JOIN pedido_detalle d ON d.producto_id = pr.id
         LEFT JOIN pedidos p ON p.id = d.pedido_id AND p.estado <> "Cancelado"
         GROUP BY pr.id, pr.nombre
         ORDER BY viandas DESC, pr.nombre'
    );
}

function pedidos_por_mes(): array
{
    return consultar(
        'SELECT DATE_FORMAT(fecha_pedido, "%Y-%m") AS mes, COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS facturado
         FROM pedidos
         WHERE estado <> "Cancelado"
         GROUP BY mes
         ORDER BY mes DESC
         LIMIT 6'
    );
}
