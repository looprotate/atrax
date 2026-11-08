<?php

require_once __DIR__ . '/../includes/auth.php';
requiere_rol('cliente');

require_once __DIR__ . '/../modelo/catalogo.php';
require_once __DIR__ . '/../modelo/stock.php';
require_once __DIR__ . '/../modelo/usuarios.php';

$planes    = listar_planes(true);
$productos = array_filter(stock_por_producto(), static fn ($p) => (int) $p['activo'] === 1);
$cliente   = cliente_de_usuario((int) usuario()['id']);
$minimo    = date('Y-m-d', strtotime('+1 day'));
$maximo    = date('Y-m-d', strtotime('+60 days'));

$titulo  = 'Armar pedido';
$seccion = 'pedido';
require __DIR__ . '/../includes/cabecera.php';
?>
<div class="encabezado-pagina">
  <div>
    <h1>Armar pedido</h1>
    <p>Elegi la modalidad y reparti las viandas entre los platos disponibles.</p>
  </div>
</div>

<form action="<?= url('acciones/pedido_cliente.php') ?>" method="post" data-validar data-resumen-pedido novalidate>
  <?= campo_token() ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <section class="panel">
        <header>
          <h2>Platos</h2>
          <span class="small text-secondary">Viandas elegidas: <strong data-total-viandas>0 / 0</strong></span>
        </header>

        <div class="tabla-scroll">
          <table class="table align-middle">
            <thead><tr><th>Plato</th><th>Precio</th><th>Disponible</th><th style="width:130px">Cantidad</th></tr></thead>
            <tbody>
              <?php foreach ($productos as $producto): ?>
                <?php $disponible = (int) $producto['disponible']; ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-3">
                      <img class="plato-miniatura" src="<?= e(imagen_plato($producto['imagen'])) ?>" alt=""
                           width="64" height="48" loading="lazy">
                      <div>
                        <span class="fw-semibold"><?= e($producto['nombre']) ?></span><br>
                        <span class="small text-secondary"><?= e($producto['descripcion']) ?></span>
                      </div>
                    </div>
                  </td>
                  <td><?= moneda($producto['precio']) ?></td>
                  <td>
                    <?php if ($disponible > 0): ?>
                      <span class="etiqueta ok"><?= $disponible ?></span>
                    <?php else: ?>
                      <span class="etiqueta peligro">agotado</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <label class="visually-hidden" for="cantidad<?= (int) $producto['id'] ?>">Cantidad de <?= e($producto['nombre']) ?></label>
                    <input class="form-control form-control-sm" type="number" data-vianda
                           data-nombre="<?= e($producto['nombre']) ?>" data-precio="<?= (float) $producto['precio'] ?>"
                           id="cantidad<?= (int) $producto['id'] ?>"
                           name="cantidad[<?= (int) $producto['id'] ?>]"
                           min="0" max="<?= $disponible ?>" value="<?= (int) viejo('cantidad_' . $producto['id'], 0) ?>"
                           <?= $disponible <= 0 ? 'disabled' : '' ?>>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div class="col-lg-4">
      <section class="panel">
        <header><h2>Resumen</h2></header>

        <div class="mb-3">
          <label class="form-label" for="plan_id">Modalidad</label>
          <select class="form-select" id="plan_id" name="plan_id" required>
            <?php foreach ($planes as $plan): ?>
              <option value="<?= (int) $plan['id'] ?>" data-viandas="<?= (int) $plan['viandas'] ?>" data-nombre="<?= e($plan['nombre']) ?>"
                      <?= (string) viejo('plan_id') === (string) $plan['id'] ? ' selected' : '' ?>>
                <?= e($plan['nombre'] . ' — ' . (int) $plan['viandas'] . ' viandas') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="fecha_entrega">Entregar a partir de</label>
          <input class="form-control" type="date" id="fecha_entrega" name="fecha_entrega" required
                 min="<?= $minimo ?>" max="<?= $maximo ?>"
                 value="<?= e(viejo('fecha_entrega', $minimo)) ?>">
          <div class="invalid-feedback">Elegi una fecha valida.</div>
        </div>

        <dl class="row small">
          <dt class="col-6">Zona de entrega</dt>
          <dd class="col-6 text-end"><?= e($cliente['codigo'] . ' · ' . $cliente['zona']) ?></dd>
          <dt class="col-6">Direccion</dt>
          <dd class="col-6 text-end"><?= e($cliente['direccion']) ?></dd>
          <dt class="col-6">Costo de entrega</dt>
          <dd class="col-6 text-end">incluido</dd>
        </dl>

        <button class="btn btn-primary w-100" type="submit">Confirmar pedido</button>
      </section>
    </div>
  </div>
</form>

<div class="modal fade" id="modalPedido" tabindex="-1" aria-labelledby="modalPedidoTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content vs-modal">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalPedidoTitulo">Confirma tu pedido</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <dl class="row small mb-3">
          <dt class="col-5">Modalidad</dt>
          <dd class="col-7 text-end mb-1" data-resumen="plan"></dd>
          <dt class="col-5">Entregar a partir de</dt>
          <dd class="col-7 text-end mb-1" data-resumen="fecha"></dd>
          <dt class="col-5">Direccion</dt>
          <dd class="col-7 text-end mb-0"><?= e($cliente['direccion']) ?></dd>
        </dl>
        <ul class="resumen-pedido" data-resumen="lineas"></ul>
        <div class="resumen-total"><span>Total</span><strong data-resumen="total"></strong></div>
        <p class="small text-secondary mt-3 mb-0">Al confirmar reservamos las viandas del stock. El costo de entrega esta incluido.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Seguir editando</button>
        <button type="button" class="btn btn-primary" data-pedido-confirmar>Confirmar pedido</button>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/pie.php'; ?>
