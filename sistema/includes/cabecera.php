<?php

require_once __DIR__ . '/auth.php';

$titulo  = $titulo ?? APP_NOMBRE;
$seccion = $seccion ?? '';

$rol   = rol_actual();
$menu  = menu_de($rol);
$yo    = usuario();
$exito = flash_leer('exito');
$exitoTitulo = flash_leer('exito_titulo', 'Listo');
$celebrar = (bool) flash_leer('celebrar');
$listaErrores = errores();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> — <?= APP_NOMBRE ?></title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/logo.svg') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= url('assets/css/atrax.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/efectos.css') ?>">
</head>
<body class="rol-<?= e($rol ?: 'publico') ?>">
<?php if (autenticado()): ?>
<header class="atrax-topbar">
  <div class="container-fluid d-flex align-items-center gap-3">
    <?php if ($rol === 'administrador'): ?>
      <button class="btn btn-sm btn-outline-light d-lg-none" type="button"
              data-bs-toggle="offcanvas" data-bs-target="#menuLateral" aria-controls="menuLateral">
        <span class="visually-hidden">Abrir menu</span>&#9776;
      </button>
    <?php endif; ?>

    <a class="atrax-marca" href="<?= url(panel_de($rol)) ?>">
      <img class="atrax-logo" src="<?= url('assets/img/logo.svg') ?>" alt="" width="40" height="40">
      <span><?= APP_NOMBRE ?></span>
    </a>

    <?php if ($rol !== 'administrador'): ?>
      <nav class="atrax-pills d-none d-md-flex" aria-label="Secciones">
        <?php foreach ($menu as [$clave, $texto, $ruta]): ?>
          <a class="pill<?= $seccion === $clave ? ' active' : '' ?>" href="<?= url($ruta) ?>"><?= e($texto) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="d-none d-sm-inline atrax-usuario small"><?= e($yo['nombre']) ?> · <?= e($rol) ?></span>
      <span class="atrax-avatar" aria-hidden="true"><?= e($yo['inicial']) ?></span>
      <a class="btn btn-sm btn-outline-light" href="<?= url('logout.php') ?>"
         data-confirmar="Vas a salir de <?= APP_NOMBRE ?>. Para volver a entrar vas a necesitar tu usuario y contrasena."
         data-confirmar-titulo="Cerrar sesion?" data-confirmar-boton="Salir">Salir</a>
    </div>
  </div>
</header>

<div class="container-fluid atrax-cuerpo">
  <div class="row g-0">
    <?php if ($rol === 'administrador'): ?>
      <div class="col-lg-2">
        <aside class="atrax-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="menuLateral"
               aria-label="Menu del administrador">
          <div class="offcanvas-header d-lg-none">
            <h2 class="offcanvas-title h6">Menu</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#menuLateral"></button>
          </div>
          <nav class="offcanvas-body">
            <ul class="nav flex-column">
              <?php foreach ($menu as [$clave, $texto, $ruta]): ?>
                <li class="nav-item">
                  <a class="nav-link<?= $seccion === $clave ? ' active' : '' ?>" href="<?= url($ruta) ?>"><?= e($texto) ?></a>
                </li>
              <?php endforeach; ?>
            </ul>
          </nav>
        </aside>
      </div>
      <div class="col-lg-10">
    <?php else: ?>
      <div class="col-12">
    <?php endif; ?>

      <main class="atrax-main">
        <?php if ($rol !== 'administrador'): ?>
          <nav class="atrax-pills d-md-none mb-3" aria-label="Secciones">
            <?php foreach ($menu as [$clave, $texto, $ruta]): ?>
              <a class="pill<?= $seccion === $clave ? ' active' : '' ?>" href="<?= url($ruta) ?>"><?= e($texto) ?></a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
<?php else: ?>
<main class="atrax-publico">
<?php endif; ?>

<div class="atrax-toasts" aria-live="polite" data-avisos>
  <?php if ($exito): ?>
    <div class="toast show vs-toast exito" role="status" aria-atomic="true" data-bs-delay="5000"<?= $celebrar ? ' data-celebrar' : '' ?>>
      <span class="vs-toast-icono" aria-hidden="true">&#10003;</span>
      <div class="vs-toast-cuerpo">
        <p class="vs-toast-titulo"><?= e($exitoTitulo) ?></p>
        <p class="mb-0" data-mensaje="exito"><?= e($exito) ?></p>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Cerrar aviso"></button>
      <span class="vs-toast-tiempo" aria-hidden="true"></span>
    </div>
  <?php endif; ?>

  <?php if ($listaErrores): ?>
    <div class="toast show vs-toast error" role="alert" aria-atomic="true" data-bs-autohide="false">
      <span class="vs-toast-icono" aria-hidden="true">!</span>
      <div class="vs-toast-cuerpo">
        <p class="vs-toast-titulo"><?= count($listaErrores) > 1 ? 'Revisa estos datos' : 'No se pudo completar' ?></p>
        <ul<?= count($listaErrores) === 1 ? ' class="list-unstyled"' : '' ?>>
          <?php foreach ($listaErrores as $mensaje): ?>
            <li><?= e($mensaje) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Cerrar aviso"></button>
    </div>
  <?php endif; ?>
</div>
