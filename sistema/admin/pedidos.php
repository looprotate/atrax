<?php

require_once __DIR__ . '/../includes/auth.php';
requiere_rol('administrador');

require_once __DIR__ . '/../modelo/pedidos.php';
require_once __DIR__ . '/../modelo/zonas.php';

$estadoFiltro = $_GET['estado'] ?? '';
$zonaFiltro   = entero($_GET['zona_id'] ?? '');
$buscarFiltro = mb_substr(trim((string) ($_GET['buscar'] ?? '')), 0, 100);

if (!in_array($estadoFiltro, estados_pedido(), true)) {
    $estadoFiltro = '';
}

$pedidos = listar_pedidos(['estado' => $estadoFiltro, 'zona_id' => $zonaFiltro, 'buscar' => $buscarFiltro]);
$zonas   = listar_zonas();
$detalle = isset($_GET['ver']) ? pedido_por_id((int) $_GET['ver']) : null;

$titulo  = 'Pedidos';
$seccion = 'pedidos';
require __DIR__ . '/../includes/cabecera.php';
?>
<div class="encabezado-pagina">
  <div>
    <h1>Pedidos</h1>
    <p>Consulta de todos los pedidos registrados en el sistema.</p>
  </div>
</div>

<section class="panel">
  <header><h2>Filtros</h2></header>
  <form class="row g-3" action="<?= url('admin/pedidos.php') ?>" method="get"
        data-pedidos-en-vivo data-api="<?= url('api/pedidos.php') ?>" data-ver="<?= url('admin/pedidos.php?ver=') ?>">
    <div class="col-md-3">
      <label class="form-label" for="buscar">Buscar</label>
      <input class="form-control" type="search" id="buscar" name="buscar" maxlength="100"
             placeholder="Cliente o numero" value="<?= e($buscarFiltro) ?>" autocomplete="off">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="estado">Estado</label>
      <select class="form-select" id="estado" name="estado">
        <option value="">Todos</option>
        <?php foreach (estados_pedido() as $estado): ?>
          <option value="<?= $estado ?>"<?= $estadoFiltro === $estado ? ' selected' : '' ?>><?= $estado ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="zona_id">Zona</label>
      <select class="form-select" id="zona_id" name="zona_id">
        <option value="">Todas</option>
        <?php foreach ($zonas as $zona): ?>
          <option value="<?= (int) $zona['id'] ?>"<?= $zonaFiltro === (int) $zona['id'] ? ' selected' : '' ?>>
            <?= e($zona['codigo'] . ' · ' . $zona['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end gap-2">
      <button class="btn btn-primary" type="submit">Filtrar</button>
      <a class="btn btn-outline-secondary" href="<?= url('admin/pedidos.php') ?>" data-limpiar>Limpiar</a>
    </div>
  </form>
</section>

<section class="panel" data-pedidos-resultados>
  <header>
    <h2>Resultados (<span data-pedidos-total><?= count($pedidos) ?></span>)</h2>
    <p class="en-vivo-estado mb-0" role="status" aria-live="polite" data-pedidos-estado></p>
  </header>
  <div class="tabla-scroll">
    <table class="table align-middle">
      <thead>
        <tr><th>Pedido</th><th>Cliente</th><th>Tipo</th><th>Zona</th><th>Entrega</th><th>Viandas</th><th>Estado</th><th>Vehiculo</th><th class="text-end">Total</th><th></th></tr>
      </thead>
      <tbody data-pedidos-cuerpo>
        <?php foreach ($pedidos as $pedido): ?>
          <tr>
            <td class="fw-semibold">#<?= (int) $pedido['id'] ?></td>
            <td><?= e($pedido['cliente']) ?></td>
            <td><?= e($pedido['tipo']) ?></td>
            <td><?= e($pedido['zona_codigo']) ?></td>
            <td><?= fecha_corta($pedido['fecha_entrega']) ?></td>
            <td><?= (int) $pedido['viandas'] ?></td>
            <td><span class="etiqueta <?= clase_estado($pedido['estado']) ?>"><?= e($pedido['estado']) ?></span></td>
            <td><?= e($pedido['vehiculo'] ?? 'sin asignar') ?></td>
            <td class="text-end"><?= moneda($pedido['total']) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/pedidos.php?ver=' . (int) $pedido['id']) ?>">Ver</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$pedidos): ?>
          <tr><td colspan="10" class="text-secondary">No hay pedidos que coincidan con el filtro.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($detalle): ?>
  <section class="panel">
    <header>
      <h2>Pedido #<?= (int) $detalle['id'] ?> — <?= e($detalle['cliente']) ?></h2>
      <a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/pedidos.php') ?>">Cerrar</a>
    </header>

    <div class="row g-3">
      <div class="col-md-6">
        <dl class="row mb-0">
          <dt class="col-5">Plan</dt><dd class="col-7"><?= e($detalle['plan']) ?></dd>
          <dt class="col-5">Zona</dt><dd class="col-7"><?= e($detalle['zona_codigo'] . ' · ' . $detalle['zona']) ?></dd>
          <dt class="col-5">Direccion</dt><dd class="col-7"><?= e($detalle['direccion']) ?></dd>
          <dt class="col-5">Fecha de pedido</dt><dd class="col-7"><?= fecha_hora($detalle['fecha_pedido']) ?></dd>
          <dt class="col-5">Fecha de entrega</dt><dd class="col-7"><?= fecha_corta($detalle['fecha_entrega']) ?></dd>
          <dt class="col-5">Estado</dt><dd class="col-7"><?= e($detalle['estado']) ?></dd>
          <dt class="col-5">Total</dt><dd class="col-7"><?= moneda($detalle['total']) ?></dd>
        </dl>
      </div>
      <div class="col-md-6">
        <table class="table table-sm">
          <thead><tr><th>Plato</th><th>Cant.</th><th class="text-end">Subtotal</th></tr></thead>
          <tbody>
            <?php foreach (detalle_pedido((int) $detalle['id']) as $linea): ?>
              <tr>
                <td><?= e($linea['producto']) ?></td>
                <td><?= (int) $linea['cantidad'] ?></td>
                <td class="text-end"><?= moneda($linea['subtotal']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
<?php endif; ?>
<script src="<?= url('assets/js/pedidos_en_vivo.js') ?>"></script>
<?php require __DIR__ . '/../includes/pie.php'; ?>
