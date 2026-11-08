<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Prueba de concurrencia sobre crear_pedido() y descontar_fifo().
//
// descontar_fifo() lee el disponible de un lote con un SELECT comun y despues lo escribe con
// UPDATE lotes SET disponible = <valor calculado>. Si dos pedidos leen el mismo valor antes de
// que el otro confirme, el segundo pisa el descuento del primero. La prueba lanza varios
// procesos que piden una vianda del mismo lote al mismo tiempo y compara el stock final con
// el libro de movimientos.
//
//   ATRAX_BD_HOST=127.0.0.1 ATRAX_BD_PUERTO=3306 ATRAX_SQL="mysql -u root atrax -e" \
//     php docs/pruebas-caja-blanca-concurrencia.php [procesos] [pedidos por proceso]
//
// El resultado depende de los tiempos de cada corrida: si no se pierde ninguna actualizacion
// no demuestra que no pueda pasar, solo que no paso en esa corrida.

$raiz = dirname(__DIR__);

require_once $raiz . '/includes/conexion.php';
require_once $raiz . '/modelo/pedidos.php';

const OPERADOR_ID = 3;

function un_pedido(int $platoId): void
{
    crear_pedido(
        1,
        1,
        2,
        date('Y-m-d', strtotime('+3 days')),
        [['producto_id' => $platoId, 'cantidad' => 1, 'precio_unitario' => 100.0, 'subtotal' => 100.0]],
        OPERADOR_ID
    );
}

function trabajador(int $platoId, int $pedidos, float $inicio): void
{
    $creados  = 0;
    $fallidos = 0;
    $motivos  = [];

    while (microtime(true) < $inicio) {
        usleep(200);
    }

    for ($i = 0; $i < $pedidos; $i++) {
        try {
            un_pedido($platoId);
            $creados++;
        } catch (Throwable $error) {
            $fallidos++;
            $motivos[preg_replace('/\s+/', ' ', substr($error->getMessage(), 0, 90))] = true;
        }
    }

    echo json_encode(['creados' => $creados, 'fallidos' => $fallidos, 'motivos' => array_keys($motivos)]);
}

if (($argv[1] ?? '') === 'trabajador') {
    trabajador((int) $argv[2], (int) $argv[3], (float) $argv[4]);
    exit;
}

$procesos = (int) ($argv[1] ?? 8);
$pedidos  = (int) ($argv[2] ?? 25);
$total    = $procesos * $pedidos;
$sql      = getenv('ATRAX_SQL') ?: 'mysql -u root atrax -e';

shell_exec($sql . ' ' . escapeshellarg('source ' . $raiz . '/bd/atrax.sql'));
bd()->exec('USE atrax');

$platoId = insertar('INSERT INTO productos (nombre, descripcion, precio, stock_minimo) VALUES ("Plato concurrente", "Plato de prueba", 100, 0)');
$loteId  = crear_lote($platoId, OPERADOR_ID, 'CONC-001', date('Y-m-d', strtotime('+10 days')), $total + 100);
$inicio  = microtime(true) + 1.5;
$hijos   = [];

for ($i = 0; $i < $procesos; $i++) {
    $comando = [PHP_BINARY, __FILE__, 'trabajador', (string) $platoId, (string) $pedidos, (string) $inicio];
    $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, null, null);
    $hijos[] = [$proceso, $tuberias];
}

$creados  = 0;
$fallidos = 0;
$motivos  = [];

foreach ($hijos as [$proceso, $tuberias]) {
    $salida = stream_get_contents($tuberias[1]);
    proc_close($proceso);
    $datos = json_decode($salida, true);

    if (!is_array($datos)) {
        echo "Salida inesperada de un proceso: $salida\n";
        continue;
    }

    $creados  += $datos['creados'];
    $fallidos += $datos['fallidos'];

    foreach ($datos['motivos'] as $motivo) {
        $motivos[$motivo] = true;
    }
}

$lote            = lote_por_id($loteId);
$salidas         = (int) consultarValor('SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_stock WHERE lote_id = ? AND tipo = "Salida"', [$loteId], 0);
$vendidas        = (int) consultarValor('SELECT COALESCE(SUM(d.cantidad), 0) FROM pedido_detalle d WHERE d.producto_id = ?', [$platoId], 0);
$disponibleReal  = (int) $lote['disponible'];
$disponibleLibro = (int) $lote['cantidad'] - $salidas;
$perdidas        = $disponibleReal - $disponibleLibro;

echo "Salida completa de docs/pruebas-caja-blanca-concurrencia.php\n";
echo 'Ejecutado el ' . date('d/m/Y') . " con $procesos procesos y $pedidos pedidos por proceso ($total pedidos de 1 vianda sobre un mismo lote)\n";
echo 'PHP ' . PHP_VERSION . ' - ' . bd()->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n\n";

echo "| Medida | Valor |\n|---|---|\n";
echo "| Pedidos confirmados | $creados |\n";
echo "| Pedidos con error | $fallidos |\n";
echo "| Viandas vendidas segun pedido_detalle | $vendidas |\n";
echo "| Salidas registradas en movimientos_stock | $salidas |\n";
echo "| Disponible que deberia tener el lote (cantidad - salidas) | $disponibleLibro |\n";
echo "| Disponible real del lote | $disponibleReal |\n";
echo "| Unidades que el lote muestra de mas | $perdidas |\n";

if ($motivos) {
    echo "\nErrores distintos: " . implode(' / ', array_keys($motivos)) . "\n";
}

echo "\n" . ($perdidas === 0 && $vendidas === $salidas
    ? "RESULTADO: el stock del lote coincide con el libro de movimientos en esta corrida.\n"
    : "RESULTADO: el stock del lote NO coincide con el libro de movimientos (actualizacion perdida).\n");
