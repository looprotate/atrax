<?php
$logueado = !APP_MANTENIMIENTO && function_exists('autenticado') && autenticado();
$rolError = $logueado ? rol_actual() : '';
$ruta     = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($pantalla['titulo']) ?> — <?= APP_NOMBRE ?></title>
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/logo.svg') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= url('assets/css/atrax.css') ?>">
</head>
<body class="pagina-error">
<header class="error-escena">
  <div class="error-escena-contenido">
    <div class="error-mensaje">
      <p class="error-codigo"><?= (int) $codigo ?></p>
      <p class="error-globo"><?= e($pantalla['globo']) ?></p>
    </div>

    <svg class="error-ilustracion" viewBox="0 0 440 250" aria-hidden="true" focusable="false">
      <ellipse cx="303" cy="236" rx="128" ry="9" fill="#0b172c" opacity=".3"/>

      <?php if ($pantalla['ilustracion'] === 'perdido'): ?>
        <path d="M118 120C150 52 236 30 300 58" fill="none" stroke="#fff" stroke-opacity=".6" stroke-width="4" stroke-dasharray="1 12" stroke-linecap="round"/>
        <g transform="translate(330 14)">
          <path d="M0 74C0 74-32 38-32 18A32 32 0 0 1 32 18C32 38 0 74 0 74Z" fill="#d0b178"/>
          <text x="0" y="31" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="34" font-weight="800" fill="#162b4e">?</text>
        </g>
        <g>
          <rect x="94" y="122" width="7" height="116" rx="2" fill="#6c5531"/>
          <path d="M101 130h58l13 13-13 13h-58z" fill="#fff"/>
          <text x="132" y="149" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="14" font-weight="700" fill="#162b4e">Z?</text>
          <path d="M94 164H40l-13 13 13 13h54z" fill="#e3e9f3"/>
          <text x="62" y="183" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="14" font-weight="700" fill="#162b4e">??</text>
        </g>
      <?php else: ?>
        <g fill="#ccd7e8">
          <circle cx="414" cy="96" r="14" opacity=".9"/>
          <circle cx="398" cy="72" r="18" opacity=".75"/>
          <circle cx="420" cy="48" r="22" opacity=".55"/>
          <circle cx="396" cy="22" r="16" opacity=".35"/>
        </g>
        <g transform="translate(66 168)">
          <path d="M30 0L62 58H-2z" fill="#f2b84b" stroke="#fff" stroke-width="4" stroke-linejoin="round"/>
          <rect x="27" y="18" width="6" height="22" rx="3" fill="#162b4e"/>
          <circle cx="30" cy="48" r="4" fill="#162b4e"/>
        </g>
        <g transform="translate(16 196)">
          <path d="M14 0h12l10 40H4z" fill="#f2b84b"/>
          <path d="M10 16h20l3 10H7z" fill="#fff"/>
          <rect x="0" y="38" width="40" height="6" rx="2" fill="#6c5531"/>
        </g>
      <?php endif; ?>

      <g transform="translate(180 112)">
        <path d="M0 16A14 14 0 0 1 14 2H164V106H0Z" fill="#fff"/>
        <path d="M164 26H204C212 26 218 30 222 36L242 70C245 75 246 79 246 84V106H164Z" fill="#fff"/>
        <path d="M172 34H202C207 34 210 36 213 40L229 68H172Z" fill="#a9bad6"/>
        <rect x="0" y="82" width="246" height="12" fill="#162b4e"/>
        <rect x="14" y="20" width="44" height="44" rx="10" fill="#162b4e"/>
        <path d="M24 54V38L36 29L48 38V54" fill="none" stroke="#d0b178" stroke-width="4" stroke-linejoin="round"/>
        <circle cx="36" cy="45" r="4" fill="#3e8036"/>
        <text x="68" y="47" font-family="Segoe UI, Arial, sans-serif" font-size="14" font-weight="700" fill="#162b4e">ViandaSegura</text>
        <rect x="236" y="88" width="12" height="8" rx="2" fill="<?= $pantalla['ilustracion'] === 'perdido' ? '#f5d27a' : '#8c96a6' ?>"/>
        <circle cx="48" cy="110" r="20" fill="#1c2533"/>
        <circle cx="48" cy="110" r="8" fill="#ccd7e8"/>
        <circle cx="200" cy="110" r="20" fill="#1c2533"/>
        <circle cx="200" cy="110" r="8" fill="#ccd7e8"/>
      </g>
    </svg>
  </div>
</header>

<main class="error-cuerpo">
  <h1 class="h4"><?= e($pantalla['titulo']) ?></h1>
  <p class="text-secondary mb-0"><?= e($pantalla['texto']) ?></p>

  <?php if ($codigo === 404 && $ruta !== ''): ?>
    <p class="small text-secondary mt-2 mb-0">No encontramos <code><?= e($ruta) ?></code>.</p>
  <?php endif; ?>

  <div class="error-acciones">
    <?php if (isset($pantalla['reintentar'])): ?>
      <button class="btn btn-primary" type="button" data-reintentar>Reintentar ahora</button>
    <?php elseif ($logueado): ?>
      <a class="btn btn-primary" href="<?= url(panel_de($rolError)) ?>">Ir a mi panel</a>
    <?php else: ?>
      <a class="btn btn-primary" href="<?= url('login.php') ?>">Iniciar sesion</a>
    <?php endif; ?>

    <?php if ($codigo === 404): ?>
      <button class="btn btn-outline-secondary" type="button" data-volver>Volver atras</button>
    <?php elseif ($codigo === 500): ?>
      <a class="btn btn-outline-secondary" href="<?= url('index.php') ?>">Ir al inicio</a>
    <?php endif; ?>
  </div>

  <?php if (isset($pantalla['reintentar'])): ?>
    <p class="small text-secondary" aria-live="polite">
      Volvemos a intentar automaticamente en <strong data-cuenta><?= (int) $pantalla['reintentar'] ?></strong> segundos.
    </p>
  <?php endif; ?>

  <?php if ($codigo === 404 && $logueado): ?>
    <p class="small text-secondary mb-2">O anda directo a una seccion:</p>
    <nav class="atrax-pills justify-content-center" aria-label="Secciones">
      <?php foreach (menu_de($rolError) as [$clave, $texto, $destino]): ?>
        <a class="pill" href="<?= url($destino) ?>"><?= e($texto) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if ($detalle !== ''): ?>
    <details class="error-detalle">
      <summary>Detalle tecnico</summary>
      <code><?= e($detalle) ?></code>
    </details>
  <?php endif; ?>

  <footer class="error-pie">
    <img src="<?= url('assets/img/logo.svg') ?>" alt="" width="32" height="32">
    <nav aria-label="Enlaces de ayuda">
      <a href="<?= url('index.php') ?>">Inicio</a>
      <span aria-hidden="true">—</span>
      <a href="<?= url('login.php') ?>">Iniciar sesion</a>
      <span aria-hidden="true">—</span>
      <a href="<?= url('registro.php') ?>">Crear cuenta</a>
    </nav>
    <p class="mb-0"><?= APP_NOMBRE ?> &copy; 2026 — Error <?= (int) $codigo ?></p>
  </footer>
</main>

<script src="<?= url('assets/js/pantalla_error.js') ?>"></script>
</body>
</html>
