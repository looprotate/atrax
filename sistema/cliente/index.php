<?php

require_once __DIR__ . '/../includes/auth.php';
requiere_rol('cliente');

require_once __DIR__ . '/../modelo/catalogo.php';
require_once __DIR__ . '/../modelo/stock.php';
require_once __DIR__ . '/../modelo/usuarios.php';

$planes    = listar_planes(true);
$productos = array_filter(stock_por_producto(), static fn ($p) => (int) $p['activo'] === 1);
$cliente   = cliente_de_usuario((int) usuario()['id']);

$titulo  = 'Menu disponible';
$seccion = 'menu';
require __DIR__ . '/../includes/cabecera.php';
?>
<div class="encabezado-pagina">
  <div>
    <h1>Menu disponible</h1>
    <p>Entrega en <?= e($cliente['codigo'] . ' · ' . $cliente['zona']) ?> — <?= e($cliente['direccion']) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= url('cliente/pedido.php') ?>">Armar pedido</a>
</div>

<section class="panel">
  <header><h2>Modalidades</h2></header>
  <div class="row g-3">
    <?php foreach ($planes as $plan): ?>
      <div class="col-md-4">
        <article class="tarjeta">
          <h3 class="h5">Plan <?= e($plan['nombre']) ?></h3>
          <p class="tarjeta-pie"><?= e($plan['descripcion']) ?></p>
          <p class="tarjeta-numero"><?= (int) $plan['viandas'] ?></p>
          <p class="tarjeta-pie mb-0">viandas por plan</p>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <header><h2>Platos</h2></header>
  <div class="row g-3">
    <?php foreach ($productos as $producto): ?>
      <?php $agotado = (int) $producto['disponible'] <= 0; ?>
      <div class="col-md-6 col-xl-4">
        <article class="tarjeta tarjeta-plato<?= $agotado ? ' agotado' : '' ?>">
          <img class="plato-foto" src="<?= e(imagen_plato($producto['imagen'])) ?>" alt="<?= e($producto['nombre']) ?>"
               width="800" height="600" loading="lazy">
          <div class="plato-cuerpo">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <h3 class="h6 mb-0"><?= e($producto['nombre']) ?></h3>
              <span class="etiqueta <?= $agotado ? 'peligro' : 'ok' ?>">
                <?= $agotado ? 'agotado hoy' : (int) $producto['disponible'] . ' disp.' ?>
              </span>
            </div>
            <p class="tarjeta-pie"><?= e($producto['descripcion']) ?></p>
            <p class="fw-semibold mb-0"><?= moneda($producto['precio']) ?> por vianda</p>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/pie.php'; ?>
