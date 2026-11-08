<?php

require_once __DIR__ . '/../includes/conexion.php';

function stock_por_producto(): array
{
    return consultar(
        'SELECT p.id, p.nombre, p.descripcion, p.precio, p.stock_minimo, p.imagen, p.activo,
                COALESCE(SUM(l.disponible), 0) AS disponible
         FROM productos p
         LEFT JOIN lotes l
                ON l.producto_id = p.id
               AND l.estado = "Disponible"
               AND l.fecha_vencimiento >= CURDATE()
         GROUP BY p.id, p.nombre, p.descripcion, p.precio, p.stock_minimo, p.imagen, p.activo
         ORDER BY p.nombre'
    );
}

function disponible_de(int $productoId): int
{
    return (int) consultarValor(
        'SELECT COALESCE(SUM(disponible), 0) FROM lotes
         WHERE producto_id = ? AND estado = "Disponible" AND fecha_vencimiento >= CURDATE()',
        [$productoId],
        0
    );
}

function alertas_stock(): array
{
    return consultar(
        'SELECT p.id, p.nombre, p.stock_minimo, COALESCE(SUM(l.disponible), 0) AS disponible
         FROM productos p
         LEFT JOIN lotes l
                ON l.producto_id = p.id
               AND l.estado = "Disponible"
               AND l.fecha_vencimiento >= CURDATE()
         WHERE p.activo = 1
         GROUP BY p.id, p.nombre, p.stock_minimo
         HAVING disponible < p.stock_minimo
         ORDER BY (p.stock_minimo - disponible) DESC'
    );
}

function listar_lotes(bool $soloDisponibles = false): array
{
    $sql = 'SELECT l.id, l.numero_lote, l.fecha_produccion, l.fecha_vencimiento, l.cantidad, l.disponible, l.estado,
                   p.nombre AS producto, p.stock_minimo, u.nombre AS operador,
                   DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_para_vencer
            FROM lotes l
            INNER JOIN productos p ON p.id = l.producto_id
            INNER JOIN usuarios u ON u.id = l.operador_id';

    if ($soloDisponibles) {
        $sql .= ' WHERE l.estado = "Disponible" AND l.disponible > 0';
    }

    return consultar($sql . ' ORDER BY l.fecha_vencimiento, l.fecha_produccion, l.id');
}

function lote_por_id(int $id): ?array
{
    return consultarFila(
        'SELECT l.id, l.numero_lote, l.producto_id, l.cantidad, l.disponible, l.estado, p.nombre AS producto
         FROM lotes l INNER JOIN productos p ON p.id = l.producto_id
         WHERE l.id = ?',
        [$id]
    );
}

function existe_lote(string $numero): bool
{
    return consultarValor('SELECT COUNT(*) FROM lotes WHERE numero_lote = ?', [$numero], 0) > 0;
}

function siguiente_numero_lote(): string
{
    $ultimo = (int) consultarValor(
        'SELECT MAX(CAST(SUBSTRING(numero_lote, 3) AS UNSIGNED)) FROM lotes WHERE numero_lote LIKE "L-%"',
        [],
        0
    );

    return 'L-' . str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
}

function registrar_movimiento(int $loteId, int $usuarioId, string $tipo, int $cantidad, string $motivo, ?int $pedidoId = null): int
{
    return insertar(
        'INSERT INTO movimientos_stock (lote_id, usuario_id, pedido_id, tipo, cantidad, motivo)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$loteId, $usuarioId, $pedidoId, $tipo, $cantidad, $motivo]
    );
}

function crear_lote(int $productoId, int $operadorId, string $numero, string $fechaVencimiento, int $cantidad): int
{
    $loteId = insertar(
        'INSERT INTO lotes (numero_lote, producto_id, operador_id, fecha_produccion, fecha_vencimiento, cantidad, disponible)
         VALUES (?, ?, ?, CURDATE(), ?, ?, ?)',
        [$numero, $productoId, $operadorId, $fechaVencimiento, $cantidad, $cantidad]
    );

    registrar_movimiento($loteId, $operadorId, 'Entrada', $cantidad, 'Produccion lote ' . $numero);

    return $loteId;
}

function lotes_fifo(int $productoId): array
{
    return consultar(
        'SELECT id, numero_lote, disponible FROM lotes
         WHERE producto_id = ? AND estado = "Disponible" AND disponible > 0 AND fecha_vencimiento >= CURDATE()
         ORDER BY fecha_vencimiento, fecha_produccion, id',
        [$productoId]
    );
}

function descontar_fifo(int $productoId, int $cantidad, int $usuarioId, int $pedidoId): void
{
    $restante = $cantidad;

    foreach (lotes_fifo($productoId) as $lote) {
        if ($restante <= 0) {
            break;
        }

        $tomar = min($restante, (int) $lote['disponible']);
        $queda = (int) $lote['disponible'] - $tomar;

        ejecutar(
            'UPDATE lotes SET disponible = ?, estado = ? WHERE id = ?',
            [$queda, $queda === 0 ? 'Agotado' : 'Disponible', $lote['id']]
        );

        registrar_movimiento($lote['id'], $usuarioId, 'Salida', $tomar, 'Pedido #' . $pedidoId, $pedidoId);
        $restante -= $tomar;
    }

    if ($restante > 0) {
        throw new RuntimeException('Stock insuficiente para completar el pedido.');
    }
}

function ajustar_lote(int $loteId, int $usuarioId, string $tipo, int $cantidad, int $disponibleActual, string $motivo): void
{
    $queda = $tipo === 'Salida' ? $disponibleActual - $cantidad : $disponibleActual + $cantidad;

    ejecutar(
        'UPDATE lotes SET disponible = ?, estado = ? WHERE id = ?',
        [$queda, $queda === 0 ? 'Agotado' : 'Disponible', $loteId]
    );

    registrar_movimiento($loteId, $usuarioId, $tipo, $cantidad, $motivo);
}

function descartar_lote(int $loteId, int $usuarioId, int $disponible): void
{
    ejecutar('UPDATE lotes SET disponible = 0, estado = "Descartado" WHERE id = ?', [$loteId]);

    if ($disponible > 0) {
        registrar_movimiento($loteId, $usuarioId, 'Salida', $disponible, 'Lote descartado por vencimiento');
    }
}

function ultimos_movimientos(int $limite = 12): array
{
    $limite = max(1, min($limite, 100));

    return consultar(
        'SELECT m.tipo, m.cantidad, m.motivo, m.fecha, l.numero_lote, p.nombre AS producto, u.nombre AS usuario
         FROM movimientos_stock m
         INNER JOIN lotes l ON l.id = m.lote_id
         INNER JOIN productos p ON p.id = l.producto_id
         INNER JOIN usuarios u ON u.id = m.usuario_id
         ORDER BY m.fecha DESC, m.id DESC
         LIMIT ' . $limite
    );
}
