<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Pruebas de caja blanca: llaman directamente a las funciones de includes/ y modelo/
// con entradas elegidas para recorrer cada camino independiente de su grafo de flujo.
//
//   ATRAX_BD_HOST=127.0.0.1 ATRAX_BD_PUERTO=3306 ATRAX_SQL="mysql -u root atrax -e" \
//     php docs/pruebas-caja-blanca.php
//
// Reimporta bd/atrax.sql al empezar. Cada caso crea sus propios platos y lotes, asi que
// no depende de los datos de ejemplo. Un caso pasa cuando lo obtenido coincide con lo
// esperado; si lo esperado termina en "..." alcanza con que empiece igual.

$raiz = dirname(__DIR__);

require_once $raiz . '/includes/conexion.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/modelo/pedidos.php';
require_once $raiz . '/modelo/zonas.php';
require_once $raiz . '/modelo/accesos.php';

const OPERADOR  = 3;
const CLIENTE   = 1;
const PLAN      = 1;
const ZONA      = 2;
const MOTIVO_OK = 'Prueba de caja blanca';

$contador = 0;
$pasan    = 0;
$fallan   = 0;

function reiniciar_base(): void
{
    global $raiz;

    $sql = getenv('ATRAX_SQL') ?: 'mysql -u root atrax -e';
    shell_exec($sql . ' ' . escapeshellarg('source ' . $raiz . '/bd/atrax.sql'));
    bd()->exec('USE atrax');
}

function mostrar($valor): string
{
    return is_string($valor) ? $valor : json_encode($valor, JSON_UNESCAPED_UNICODE);
}

function resumen_excepcion(Throwable $error): string
{
    $mensaje = $error->getMessage();

    if (preg_match('/CONSTRAINT `(\w+)` failed/', $mensaje, $m)) {
        return 'excepcion: CHECK ' . $m[1];
    }

    if (preg_match("/Data too long for column '(\w+)'/", $mensaje, $m)) {
        return "excepcion: dato demasiado largo en '" . $m[1] . "'";
    }

    if (preg_match("/Data truncated for column '(\w+)'/", $mensaje, $m)) {
        return "excepcion: valor invalido en '" . $m[1] . "'";
    }

    if (strpos($mensaje, 'foreign key constraint fails') !== false) {
        return 'excepcion: clave foranea';
    }

    return 'excepcion: ' . $mensaje;
}

function capturar(callable $accion): string
{
    try {
        $accion();

        return 'sin excepcion';
    } catch (Throwable $error) {
        return resumen_excepcion($error);
    }
}

function probar(string $unidad, string $camino, string $entrada, string $esperado, callable $accion): void
{
    global $contador, $pasan, $fallan;

    $contador++;
    $id = sprintf('CB-%02d', $contador);

    try {
        $obtenido = mostrar($accion());
    } catch (Throwable $error) {
        $obtenido = resumen_excepcion($error);
    }

    $ok = substr($esperado, -3) === '...'
        ? strpos($obtenido, substr($esperado, 0, -3)) === 0
        : $obtenido === $esperado;

    $ok ? $pasan++ : $fallan++;

    echo '| ' . implode(' | ', [
        $id, "`$unidad`", $camino, str_replace('|', '\\|', $entrada),
        str_replace('|', '\\|', $esperado), str_replace('|', '\\|', $obtenido), $ok ? 'PASA' : 'FALLA',
    ]) . " |\n";
}

function encabezado(string $titulo): void
{
    echo "\n### $titulo\n\n| Caso | Unidad | Camino | Entrada | Resultado esperado | Resultado obtenido | Estado |\n|---|---|---|---|---|---|---|\n";
}

// ---- datos de prueba ----------------------------------------------------------------------

function nuevo_plato(): int
{
    static $n = 0;
    $n++;

    return insertar(
        'INSERT INTO productos (nombre, descripcion, precio, stock_minimo) VALUES (?, "Plato de prueba", 100, 0)',
        ['Plato de prueba ' . $n]
    );
}

function nuevo_lote(int $platoId, int $cantidad, int $diasParaVencer = 10): int
{
    static $n = 0;
    $n++;

    return crear_lote($platoId, OPERADOR, 'T-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT), date('Y-m-d', strtotime("+$diasParaVencer days")), $cantidad);
}

function lote_texto(int $loteId): string
{
    $lote = lote_por_id($loteId);

    return $lote['disponible'] . '/' . $lote['cantidad'] . ' ' . $lote['estado'];
}

function linea(int $platoId, int $cantidad): array
{
    return ['producto_id' => $platoId, 'cantidad' => $cantidad, 'precio_unitario' => 100.0, 'subtotal' => $cantidad * 100.0];
}

function nuevo_pedido(array $lineas): int
{
    return crear_pedido(CLIENTE, PLAN, ZONA, date('Y-m-d', strtotime('+3 days')), $lineas, OPERADOR);
}

function contar(string $sql, array $parametros = []): int
{
    return (int) consultarValor($sql, $parametros, 0);
}

function movimientos(int $loteId, string $tipo): int
{
    return contar('SELECT COUNT(*) FROM movimientos_stock WHERE lote_id = ? AND tipo = ?', [$loteId, $tipo]);
}

function salidas_por_descarte(int $loteId): int
{
    return contar('SELECT COUNT(*) FROM movimientos_stock WHERE lote_id = ? AND motivo = "Lote descartado por vencimiento"', [$loteId]);
}

reiniciar_base();

echo "Salida completa de docs/pruebas-caja-blanca.php\n";
echo 'Ejecutado el ' . date('d/m/Y') . ' sobre la base de datos recien importada desde bd/atrax.sql' . "\n";
echo 'PHP ' . PHP_VERSION . ' - ' . bd()->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";

// ---- includes/funciones.php ---------------------------------------------------------------

encabezado('includes/funciones.php');

probar('entero()', 'valor valido: devuelve el numero', "'42'", '42', fn () => entero('42'));
probar('entero()', 'filter_var devuelve false: devuelve null', "'4.2'", 'null', fn () => entero('4.2'));
probar('entero()', 'espacios alrededor: se aceptan', "' 7 '", '7', fn () => entero(' 7 '));
probar('entero()', 'desborda el entero: devuelve null', "'99999999999999999999'", 'null', fn () => entero('99999999999999999999'));

probar('es_fecha()', 'primer operando falso: no es una fecha', "'abc'", 'false', fn () => es_fecha('abc'));
probar('es_fecha()', 'primero verdadero y segundo falso: la fecha se desborda', "'2027-02-30'", 'false', fn () => es_fecha('2027-02-30'));
probar('es_fecha()', 'primero verdadero y segundo falso: sin ceros', "'2027-1-5'", 'false', fn () => es_fecha('2027-1-5'));
probar('es_fecha()', 'ambos operandos verdaderos', "'2027-12-31'", 'true', fn () => es_fecha('2027-12-31'));

probar('es_telefono()', 'formato valido', "'+598 99 123 456'", 'true', fn () => es_telefono('+598 99 123 456'));
probar('es_telefono()', 'menos de 8 caracteres', "'1234567'", 'false', fn () => es_telefono('1234567'));
probar('es_telefono()', 'letras', "'abcdefgh'", 'false', fn () => es_telefono('abcdefgh'));
probar('es_telefono()', 'solo signos permitidos: deberia ser falso', "'--------'", 'false', fn () => es_telefono('--------'));

// ---- modelo/pedidos.php: transiciones() ----------------------------------------------------

encabezado('modelo/pedidos.php, transiciones()');

probar('transiciones()', 'clave existente con destinos', 'Pendiente', '["Preparando","Cancelado"]', fn () => transiciones('Pendiente'));
probar('transiciones()', 'clave existente con destinos', 'Listo', '["En distribucion","Cancelado"]', fn () => transiciones('Listo'));
probar('transiciones()', 'clave existente sin destinos', 'Entregado', '[]', fn () => transiciones('Entregado'));
probar('transiciones()', 'clave existente sin destinos', 'Cancelado', '[]', fn () => transiciones('Cancelado'));
probar('transiciones()', 'clave inexistente: usa ?? y devuelve []', 'Volando', '[]', fn () => transiciones('Volando'));

// ---- modelo/stock.php: descontar_fifo() ----------------------------------------------------

encabezado('modelo/stock.php, descontar_fifo() y lotes_fifo()');

probar('descontar_fifo()', 'R1: sin lotes, el bucle no corre y falta stock', 'plato sin lotes, pedir 1', 'excepcion: Stock insuficiente para completar el pedido.', function () {
    $plato = nuevo_plato();
    descontar_fifo($plato, 1, OPERADOR, 1);
});

probar('descontar_fifo()', 'R2: un lote, pedido igual al disponible (queda 0: Agotado)', 'lote de 10, pedir 10', '0/10 Agotado | salidas: 1', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    nuevo_pedido([linea($plato, 10)]);

    return lote_texto($lote) . ' | salidas: ' . movimientos($lote, 'Salida');
});

probar('descontar_fifo()', 'R3: un lote, pedido menor al disponible (queda stock: Disponible)', 'lote de 10, pedir 4', '6/10 Disponible | salidas: 1', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    nuevo_pedido([linea($plato, 4)]);

    return lote_texto($lote) . ' | salidas: ' . movimientos($lote, 'Salida');
});

probar('descontar_fifo()', 'R4: dos lotes, el pedido los recorre en orden de vencimiento', 'lote A (vence +20d, 5) creado primero, lote B (vence +5d, 5), pedir 7', 'B 0/5 Agotado | A 3/5 Disponible', function () {
    $plato = nuevo_plato();
    $a     = nuevo_lote($plato, 5, 20);
    $b     = nuevo_lote($plato, 5, 5);
    nuevo_pedido([linea($plato, 7)]);

    return 'B ' . lote_texto($b) . ' | A ' . lote_texto($a);
});

probar('descontar_fifo()', 'R5: la primera vuelta cubre el pedido y la segunda sale por break', 'lote A (+5d, 10), lote B (+20d, 10), pedir 4', 'A 6/10 Disponible | B 10/10 Disponible', function () {
    $plato = nuevo_plato();
    $a     = nuevo_lote($plato, 10, 5);
    $b     = nuevo_lote($plato, 10, 20);
    nuevo_pedido([linea($plato, 4)]);

    return 'A ' . lote_texto($a) . ' | B ' . lote_texto($b);
});

probar('descontar_fifo()', 'R6: recorre todos los lotes y sigue faltando (llamada sin transaccion deja el consumo a medias)', 'lote A (3), lote B (2), pedir 6 llamando a la funcion sola', 'excepcion: Stock insuficiente para completar el pedido. | A 0/3 Agotado | B 0/2 Agotado', function () {
    $plato = nuevo_plato();
    $a     = nuevo_lote($plato, 3, 5);
    $b     = nuevo_lote($plato, 2, 6);
    $pedido = insertar('INSERT INTO pedidos (cliente_id, plan_id, zona_id, fecha_entrega, viandas, total) VALUES (1, 1, 2, CURDATE() + INTERVAL 3 DAY, 6, 600)');
    $resultado = capturar(fn () => descontar_fifo($plato, 6, OPERADOR, $pedido));

    return $resultado . ' | A ' . lote_texto($a) . ' | B ' . lote_texto($b);
});

probar('lotes_fifo()', 'el filtro de la consulta descarta los lotes vencidos', 'lote de 10 que vencio ayer, pedir 1', 'excepcion: Stock insuficiente para completar el pedido.', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10, -1);
    descontar_fifo($plato, 1, OPERADOR, 1);
});

probar('lotes_fifo()', 'el filtro de la consulta descarta los lotes descartados', 'lote descartado de 10, pedir 1', 'excepcion: Stock insuficiente para completar el pedido.', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    descartar_lote($lote, OPERADOR, 10);
    descontar_fifo($plato, 1, OPERADOR, 1);
});

// ---- modelo/pedidos.php: crear_pedido() ----------------------------------------------------

encabezado('modelo/pedidos.php, crear_pedido()');

probar('crear_pedido()', 'camino normal: ambos bucles corren y se confirma', 'dos platos (3 + 2 viandas)', 'estado=Pendiente viandas=5 total=500.00 detalle=2 salidas=2 historial=1', function () {
    $a = nuevo_plato();
    $b = nuevo_plato();
    nuevo_lote($a, 10);
    nuevo_lote($b, 10);
    $id     = nuevo_pedido([linea($a, 3), linea($b, 2)]);
    $pedido = pedido_por_id($id);

    return 'estado=' . $pedido['estado'] . ' viandas=' . $pedido['viandas'] . ' total=' . $pedido['total']
        . ' detalle=' . contar('SELECT COUNT(*) FROM pedido_detalle WHERE pedido_id = ?', [$id])
        . ' salidas=' . contar('SELECT COUNT(*) FROM movimientos_stock WHERE pedido_id = ? AND tipo = "Salida"', [$id])
        . ' historial=' . contar('SELECT COUNT(*) FROM pedido_estados WHERE pedido_id = ?', [$id]);
});

probar('crear_pedido()', 'sin lineas: los bucles no corren (la funcion no valida; lo hace la accion)', 'lineas vacias', 'estado=Pendiente viandas=0 total=0.00 detalle=0', function () {
    $id     = nuevo_pedido([]);
    $pedido = pedido_por_id($id);

    return 'estado=' . $pedido['estado'] . ' viandas=' . $pedido['viandas'] . ' total=' . $pedido['total']
        . ' detalle=' . contar('SELECT COUNT(*) FROM pedido_detalle WHERE pedido_id = ?', [$id]);
});

probar('crear_pedido()', 'catch: falta stock en la segunda linea y se revierte todo', 'plato A (stock 10, pedir 3) y plato B (stock 1, pedir 5)', 'excepcion: Stock insuficiente para completar el pedido. | pedidos nuevos=0 | A 10/10 Disponible', function () {
    $a = nuevo_plato();
    $b = nuevo_plato();
    $loteA = nuevo_lote($a, 10);
    nuevo_lote($b, 1);
    $antes = contar('SELECT COUNT(*) FROM pedidos');
    $resultado = capturar(fn () => nuevo_pedido([linea($a, 3), linea($b, 5)]));

    return $resultado . ' | pedidos nuevos=' . (contar('SELECT COUNT(*) FROM pedidos') - $antes) . ' | A ' . lote_texto($loteA);
});

probar('crear_pedido()', 'catch: una linea apunta a un plato inexistente y se revierte todo', 'plato inexistente 999999', 'excepcion: clave foranea | pedidos nuevos=0', function () {
    $antes = contar('SELECT COUNT(*) FROM pedidos');
    $resultado = capturar(fn () => nuevo_pedido([linea(999999, 1)]));

    return $resultado . ' | pedidos nuevos=' . (contar('SELECT COUNT(*) FROM pedidos') - $antes);
});

// ---- modelo/pedidos.php: devolver_stock() --------------------------------------------------

encabezado('modelo/pedidos.php, devolver_stock()');

probar('devolver_stock()', 'sin salidas: el bucle no corre', 'pedido sin movimientos', 'entradas de devolucion=0', function () {
    $id = nuevo_pedido([]);
    devolver_stock($id, OPERADOR);

    return 'entradas de devolucion=' . contar('SELECT COUNT(*) FROM movimientos_stock WHERE pedido_id = ? AND tipo = "Entrada"', [$id]);
});

probar('devolver_stock()', 'camino normal: cada salida vuelve a su lote', 'pedido de 4 sobre un lote de 10', '10/10 Disponible | entradas de devolucion=1', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    $id    = nuevo_pedido([linea($plato, 4)]);
    devolver_stock($id, OPERADOR);

    return lote_texto($lote) . ' | entradas de devolucion=' . contar('SELECT COUNT(*) FROM movimientos_stock WHERE pedido_id = ? AND tipo = "Entrada"', [$id]);
});

probar('devolver_stock()', 'lote descartado: se saltea con continue', 'pedido de 4; luego el lote se descarta', '0/10 Descartado | entradas de devolucion=0', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    $id    = nuevo_pedido([linea($plato, 4)]);
    descartar_lote($lote, OPERADOR, 6);
    devolver_stock($id, OPERADOR);

    return lote_texto($lote) . ' | entradas de devolucion=' . contar('SELECT COUNT(*) FROM movimientos_stock WHERE pedido_id = ? AND tipo = "Entrada"', [$id]);
});

probar('devolver_stock()', 'el lote ya fue repuesto con un ajuste: la devolucion deberia completarse', 'pedido de 4; ajuste +4 deja el lote en 10/10; devolver', 'sin excepcion | 10/10 Disponible | entradas de devolucion=0', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    $id    = nuevo_pedido([linea($plato, 4)]);
    ajustar_lote($lote, OPERADOR, 'Ajuste', 4, 6, MOTIVO_OK);
    $resultado = capturar(fn () => devolver_stock($id, OPERADOR));

    return $resultado . ' | ' . lote_texto($lote) . ' | entradas de devolucion=' . contar('SELECT COUNT(*) FROM movimientos_stock WHERE lote_id = ? AND tipo = "Entrada" AND pedido_id = ?', [$lote, $id]);
});

// ---- modelo/pedidos.php: cambiar_estado() --------------------------------------------------

encabezado('modelo/pedidos.php, cambiar_estado()');

probar('cambiar_estado()', 'sin cancelar: confirma el cambio y el historial', 'Pendiente a Preparando', 'estado=Preparando historial=2', function () {
    $id = nuevo_pedido([]);
    cambiar_estado($id, 'Pendiente', 'Preparando', OPERADOR);

    return 'estado=' . pedido_por_id($id)['estado'] . ' historial=' . contar('SELECT COUNT(*) FROM pedido_estados WHERE pedido_id = ?', [$id]);
});

probar('cambiar_estado()', 'cancelar: devuelve el stock y libera el vehiculo', 'Listo con vehiculo 1 a Cancelado', 'estado=Cancelado vehiculo=null 10/10 Disponible', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    $id    = nuevo_pedido([linea($plato, 4)]);
    ejecutar('UPDATE pedidos SET estado = "Listo", vehiculo_id = 1 WHERE id = ?', [$id]);
    cambiar_estado($id, 'Listo', 'Cancelado', OPERADOR);
    $pedido = pedido_por_id($id);

    return 'estado=' . $pedido['estado'] . ' vehiculo=' . mostrar($pedido['vehiculo_id']) . ' ' . lote_texto($lote);
});

probar('cambiar_estado()', 'catch: la base rechaza el estado y se revierte', "estado nuevo 'Volando'", "excepcion: valor invalido en 'estado' | estado=Pendiente historial=1", function () {
    $id = nuevo_pedido([]);
    $resultado = capturar(fn () => cambiar_estado($id, 'Pendiente', 'Volando', OPERADOR));

    return $resultado . ' | estado=' . pedido_por_id($id)['estado'] . ' historial=' . contar('SELECT COUNT(*) FROM pedido_estados WHERE pedido_id = ?', [$id]);
});

probar('cambiar_estado()', 'cancelar con el lote ya repuesto: la cancelacion se completa sin pasar el tope del lote', 'pedido de 4; ajuste +4; cancelar', 'sin excepcion | estado=Cancelado historial=2 10/10 Disponible', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    $id    = nuevo_pedido([linea($plato, 4)]);
    ajustar_lote($lote, OPERADOR, 'Ajuste', 4, 6, MOTIVO_OK);
    $resultado = capturar(fn () => cambiar_estado($id, 'Pendiente', 'Cancelado', OPERADOR));

    return $resultado . ' | estado=' . pedido_por_id($id)['estado'] . ' historial=' . contar('SELECT COUNT(*) FROM pedido_estados WHERE pedido_id = ?', [$id]) . ' ' . lote_texto($lote);
});

// ---- modelo/stock.php: ajustar_lote() y descartar_lote() ------------------------------------

encabezado('modelo/stock.php, ajustar_lote() y descartar_lote()');

probar('ajustar_lote()', 'Salida que deja stock: ambos ternarios por la rama de Salida / Disponible', 'lote de 10, Salida 3', '7/10 Disponible | salidas: 1', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    ajustar_lote($lote, OPERADOR, 'Salida', 3, 10, MOTIVO_OK);

    return lote_texto($lote) . ' | salidas: ' . movimientos($lote, 'Salida');
});

probar('ajustar_lote()', 'Salida que vacia el lote: Agotado', 'lote de 10, Salida 10', '0/10 Agotado', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    ajustar_lote($lote, OPERADOR, 'Salida', 10, 10, MOTIVO_OK);

    return lote_texto($lote);
});

probar('ajustar_lote()', 'Ajuste: suma al disponible', 'lote de 10 con salida 4, Ajuste +2', '8/10 Disponible', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    ajustar_lote($lote, OPERADOR, 'Salida', 4, 10, MOTIVO_OK);
    ajustar_lote($lote, OPERADOR, 'Ajuste', 2, 6, MOTIVO_OK);

    return lote_texto($lote);
});

probar('ajustar_lote()', 'Ajuste sobre un lote agotado: vuelve a Disponible', 'lote agotado, Ajuste +3', '3/10 Disponible', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    ajustar_lote($lote, OPERADOR, 'Salida', 10, 10, MOTIVO_OK);
    ajustar_lote($lote, OPERADOR, 'Ajuste', 3, 0, MOTIVO_OK);

    return lote_texto($lote);
});

probar('ajustar_lote()', 'si registrar el movimiento falla, el lote no deberia quedar modificado', 'lote de 10, Salida 1, motivo de 151 caracteres', "excepcion: dato demasiado largo en 'motivo' | 10/10 Disponible", function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    $resultado = capturar(fn () => ajustar_lote($lote, OPERADOR, 'Salida', 1, 10, str_repeat('m', 151)));

    return $resultado . ' | ' . lote_texto($lote);
});

probar('descartar_lote()', 'con stock: registra la salida del descarte', 'lote de 10 disponible 10', '0/10 Descartado | salidas por descarte: 1', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    descartar_lote($lote, OPERADOR, 10);

    return lote_texto($lote) . ' | salidas por descarte: ' . salidas_por_descarte($lote);
});

probar('descartar_lote()', 'sin stock: no registra ninguna salida', 'lote agotado', '0/10 Descartado | salidas por descarte: 0', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    ajustar_lote($lote, OPERADOR, 'Salida', 10, 10, MOTIVO_OK);
    descartar_lote($lote, OPERADOR, 0);

    return lote_texto($lote) . ' | salidas por descarte: ' . salidas_por_descarte($lote);
});

// ---- modelo/zonas.php: vehiculo_por_id() ---------------------------------------------------

encabezado('modelo/zonas.php, vehiculo_por_id()');

probar('vehiculo_por_id()', 'la carga suma las viandas de los pedidos Listo o En distribucion asignados', 'Moto 1 con un pedido Listo de 5 viandas', 'carga=5', function () {
    $plato = nuevo_plato();
    nuevo_lote($plato, 10);
    $id = nuevo_pedido([linea($plato, 5)]);
    ejecutar('UPDATE pedidos SET estado = "Listo", vehiculo_id = 3 WHERE id = ?', [$id]);

    return 'carga=' . vehiculo_por_id(3)['carga'];
});

probar('vehiculo_por_id()', 'los pedidos Pendiente o Entregado no cuentan', 'Moto 1 con un pedido Entregado de 5 viandas y otro Cancelado', 'carga=0', function () {
    $plato = nuevo_plato();
    nuevo_lote($plato, 10);
    $a = nuevo_pedido([linea($plato, 3)]);
    $b = nuevo_pedido([linea($plato, 2)]);
    ejecutar('UPDATE pedidos SET estado = "Entregado", vehiculo_id = 4 WHERE id = ?', [$a]);
    ejecutar('UPDATE pedidos SET estado = "Cancelado", vehiculo_id = 4 WHERE id = ?', [$b]);

    return 'carga=' . vehiculo_por_id(4)['carga'];
});

// ---- casos agregados al corregir los defectos --------------------------------------------
// Van al final para no cambiar la numeracion de los casos anteriores.

encabezado('modelo/pedidos.php, devolver_stock(): devolucion parcial');

probar('devolver_stock()', 'el lote repuesto solo admite parte de lo devuelto: se devuelve lo que cabe y se anota', 'pedido de 4; ajuste +2 deja el lote en 8/10; devolver', '10/10 Disponible | devuelto=2 | motivo con "parcial"=1', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    $id    = nuevo_pedido([linea($plato, 4)]);
    ajustar_lote($lote, OPERADOR, 'Ajuste', 2, 6, MOTIVO_OK);
    devolver_stock($id, OPERADOR);

    $devuelto = contar('SELECT cantidad FROM movimientos_stock WHERE lote_id = ? AND tipo = "Entrada" AND pedido_id = ?', [$lote, $id]);
    $parcial  = contar('SELECT COUNT(*) FROM movimientos_stock WHERE lote_id = ? AND pedido_id = ? AND motivo LIKE "%parcial%"', [$lote, $id]);

    return lote_texto($lote) . ' | devuelto=' . $devuelto . ' | motivo con "parcial"=' . $parcial;
});

encabezado('modelo/stock.php, descartar_lote() y descontar_fifo()');

probar('descartar_lote()', 'si registrar la salida falla, el lote no queda descartado', 'lote de 10, usuario inexistente', 'excepcion: clave foranea | 10/10 Disponible', function () {
    $plato = nuevo_plato();
    $lote  = nuevo_lote($plato, 10);
    $resultado = capturar(fn () => descartar_lote($lote, 999999, 10));

    return $resultado . ' | ' . lote_texto($lote);
});

probar('descontar_fifo()', 'sin stock se lanza la excepcion de negocio, no una generica', 'plato sin lotes, pedir 1', 'StockInsuficiente (es una RuntimeException)', function () {
    $plato = nuevo_plato();

    try {
        descontar_fifo($plato, 1, OPERADOR, 1);
    } catch (Throwable $error) {
        return get_class($error) . ($error instanceof RuntimeException ? ' (es una RuntimeException)' : '');
    }

    return 'sin excepcion';
});

encabezado('includes/conexion.php, en_transaccion()');

probar('en_transaccion()', 'el trabajo termina bien: devuelve su valor y confirma', 'inserta un plato y devuelve 42', 'valor=42 | plato guardado=1', function () {
    $valor = en_transaccion(function () {
        ejecutar('INSERT INTO productos (nombre, descripcion, precio, stock_minimo) VALUES ("Plato en transaccion", "Plato de prueba", 100, 0)');

        return 42;
    });

    return 'valor=' . $valor . ' | plato guardado=' . contar('SELECT COUNT(*) FROM productos WHERE nombre = "Plato en transaccion"');
});

probar('en_transaccion()', 'el trabajo lanza una excepcion: revierte y la relanza', 'inserta un plato y lanza una excepcion', 'excepcion: fallo a proposito | plato guardado=0', function () {
    $resultado = capturar(fn () => en_transaccion(function () {
        ejecutar('INSERT INTO productos (nombre, descripcion, precio, stock_minimo) VALUES ("Plato revertido", "Plato de prueba", 100, 0)');

        throw new RuntimeException('fallo a proposito');
    }));

    return $resultado . ' | plato guardado=' . contar('SELECT COUNT(*) FROM productos WHERE nombre = "Plato revertido"');
});

encabezado('includes/funciones.php, es_telefono() y es_email()');

probar('es_telefono()', 'ocho digitos con signos: justo en el limite', "'+(598) 12-345'", 'true', fn () => es_telefono('+(598) 12-345'));
probar('es_telefono()', 'siete digitos con signos: el formato pasa pero faltan digitos', "'+(598) 12-34'", 'false', fn () => es_telefono('+(598) 12-34'));

$correoLargo = fn (int $dominio) => str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', $dominio);

probar('es_email()', 'ambos operandos verdaderos: 150 caracteres, el maximo de la columna', 'correo de 150 caracteres', 'true', fn () => es_email($correoLargo(21)));
probar('es_email()', 'primer operando falso: 151 caracteres', 'correo de 151 caracteres', 'false', fn () => es_email($correoLargo(22)));
probar('es_email()', 'primero verdadero y segundo falso: sin arroba', "'abc'", 'false', fn () => es_email('abc'));

encabezado('modelo/accesos.php (limite de intentos fallidos)');

function limpiar_tabla_intentos(): void
{
    ejecutar('DELETE FROM intentos_acceso');
}

function fallar(string $identificador, string $direccion, int $veces): void
{
    for ($i = 0; $i < $veces; $i++) {
        registrar_intento_fallido($identificador, $direccion);
    }
}

probar('acceso_bloqueado()', 'sin intentos: no esta bloqueado', 'tabla vacia', 'false', function () {
    limpiar_tabla_intentos();

    return acceso_bloqueado('gerente', '10.0.0.1');
});

probar('acceso_bloqueado()', 'un fallo menos que el maximo: ambos operandos falsos', '4 fallos del mismo usuario', 'false', function () {
    limpiar_tabla_intentos();
    fallar('gerente', '10.0.0.1', ACCESO_MAX_FALLOS - 1);

    return acceso_bloqueado('gerente', '10.0.0.1');
});

probar('acceso_bloqueado()', 'primer operando verdadero: se alcanza el maximo del usuario', '5 fallos del mismo usuario', 'true', function () {
    limpiar_tabla_intentos();
    fallar('gerente', '10.0.0.1', ACCESO_MAX_FALLOS);

    return acceso_bloqueado('gerente', '10.0.0.1');
});

probar('acceso_bloqueado()', 'primero falso y segundo verdadero: muchos usuarios distintos desde una direccion', '20 fallos de 20 usuarios desde la misma direccion, ingresa otro', 'true', function () {
    limpiar_tabla_intentos();

    for ($i = 1; $i <= ACCESO_MAX_FALLOS_DIRECCION; $i++) {
        fallar('usuario' . $i, '10.0.0.2', 1);
    }

    return acceso_bloqueado('cocina', '10.0.0.2');
});

probar('acceso_bloqueado()', 'los fallos fuera de la ventana no cuentan', '5 fallos de hace 11 minutos', 'false', function () {
    limpiar_tabla_intentos();
    fallar('gerente', '10.0.0.1', ACCESO_MAX_FALLOS);
    ejecutar('UPDATE intentos_acceso SET fecha = fecha - INTERVAL ' . (ACCESO_VENTANA_MINUTOS + 1) . ' MINUTE');

    return acceso_bloqueado('gerente', '10.0.0.1');
});

probar('clave_de_acceso()', 'se normaliza: sin espacios, en minusculas y de a lo sumo 150 caracteres', "' GERENTE ' y un texto de 200", 'gerente | largo=150', fn () => clave_de_acceso(' GERENTE ') . ' | largo=' . mb_strlen(clave_de_acceso(str_repeat('X', 200))));

probar('registrar_intento_fallido()', 'borra los intentos de mas de un dia', 'un intento de hace 2 dias y uno nuevo', 'intentos guardados=1', function () {
    limpiar_tabla_intentos();
    fallar('viejo', '10.0.0.3', 1);
    ejecutar('UPDATE intentos_acceso SET fecha = fecha - INTERVAL 2 DAY');
    fallar('nuevo', '10.0.0.3', 1);

    return 'intentos guardados=' . contar('SELECT COUNT(*) FROM intentos_acceso');
});

probar('limpiar_intentos()', 'borra solo los del usuario indicado', '2 fallos de a, 1 de b; limpiar A', 'quedan=1', function () {
    limpiar_tabla_intentos();
    fallar('a', '10.0.0.4', 2);
    fallar('b', '10.0.0.4', 1);
    limpiar_intentos('A');

    return 'quedan=' . contar('SELECT COUNT(*) FROM intentos_acceso');
});

encabezado('modelo/pedidos.php, listar_pedidos(): filtro de busqueda');

probar('listar_pedidos()', 'buscar con # y numero: coincide por id', "'#' + id de un pedido recien creado", 'unico=si', function () {
    $plato = nuevo_plato();
    nuevo_lote($plato, 10);
    $id    = nuevo_pedido([linea($plato, 1)]);
    $filas = listar_pedidos(['buscar' => '#' . $id]);

    return 'unico=' . (count($filas) === 1 && (int) $filas[0]['id'] === $id ? 'si' : 'no');
});

probar('listar_pedidos()', 'buscar un nombre: todas las filas son de ese cliente', "'Julio Rod'", 'todas coinciden=si', function () {
    $filas = listar_pedidos(['buscar' => 'Julio Rod']);
    $todas = $filas !== [] && count(array_filter($filas, fn (array $f) => stripos($f['cliente'], 'Julio Rod') !== false)) === count($filas);

    return 'todas coinciden=' . ($todas ? 'si' : 'no');
});

probar('listar_pedidos()', 'texto sin coincidencias', "'zzzz'", 'filas=0', fn () => 'filas=' . count(listar_pedidos(['buscar' => 'zzzz'])));
probar('listar_pedidos()', 'el comodin % se busca como texto, no como "cualquier cosa"', "'%'", 'filas=0', fn () => 'filas=' . count(listar_pedidos(['buscar' => '%'])));

probar('listar_pedidos()', 'busqueda vacia: la condicion no se agrega', "''", 'igual que sin filtro=si', fn () => 'igual que sin filtro=' . (count(listar_pedidos(['buscar' => ''])) === count(listar_pedidos()) ? 'si' : 'no'));

limpiar_tabla_intentos();

echo "\nTOTAL: $contador casos, $pasan pasan, $fallan fallan.\n";
