<?php

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function base_url(): string
{
    static $base = null;

    if ($base === null) {
        $raiz = str_replace('\\', '/', realpath(APP_RAIZ));
        $documento = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $base = ($documento !== '' && strpos($raiz, $documento) === 0)
            ? substr($raiz, strlen($documento))
            : '';
        $base = rtrim($base, '/');
    }

    return $base;
}

function url(string $ruta = ''): string
{
    return base_url() . '/' . ltrim($ruta, '/');
}

function imagen_plato(?string $archivo): string
{
    $archivo = basename((string) $archivo);

    if ($archivo === '' || !is_file(APP_RAIZ . '/assets/img/platos/' . $archivo)) {
        $archivo = 'sin-imagen.svg';
    }

    return url('assets/img/platos/' . $archivo);
}

function redirigir(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

// Impide que otro sitio incruste las paginas, que el navegador adivine tipos de archivo y que se
// ejecute codigo que no venga de este sitio o de la libreria Bootstrap.
function enviar_cabeceras_seguridad(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; "
        . "style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; "
        . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

function campo(string $nombre, $porDefecto = ''): string
{
    return trim((string) ($_POST[$nombre] ?? $porDefecto));
}

function entero($valor): ?int
{
    $numero = filter_var($valor, FILTER_VALIDATE_INT);

    return $numero === false ? null : $numero;
}

function es_email(string $valor): bool
{
    return mb_strlen($valor) <= 150 && filter_var($valor, FILTER_VALIDATE_EMAIL) !== false;
}

function es_telefono(string $valor): bool
{
    return preg_match('/^[0-9 +()-]{8,20}$/', $valor) === 1 && preg_match_all('/[0-9]/', $valor) >= 8;
}

function es_fecha(string $valor): bool
{
    $fecha = DateTime::createFromFormat('Y-m-d', $valor);

    return $fecha !== false && $fecha->format('Y-m-d') === $valor;
}

function moneda($valor): string
{
    return '$ ' . number_format((float) $valor, 0, ',', '.');
}

function clase_estado(string $estado): string
{
    // Mismo color para cada estado del pedido en todas las pantallas.
    $clases = [
        'Entregado' => 'ok',
        'Cancelado' => 'peligro',
    ];

    return $clases[$estado] ?? 'acento';
}

function fecha_corta(?string $valor): string
{
    return $valor ? date('d/m/Y', strtotime($valor)) : '-';
}

function fecha_hora(?string $valor): string
{
    return $valor ? date('d/m/Y H:i', strtotime($valor)) : '-';
}

function flash_guardar(string $clave, $valor): void
{
    $_SESSION['flash'][$clave] = $valor;
}

function flash_leer(string $clave, $porDefecto = null)
{
    if (!isset($_SESSION['flash'][$clave])) {
        return $porDefecto;
    }

    $valor = $_SESSION['flash'][$clave];
    unset($_SESSION['flash'][$clave]);

    return $valor;
}

function errores(): array
{
    static $errores = null;

    if ($errores === null) {
        $errores = flash_leer('errores', []);
    }

    return $errores;
}

function viejo(string $campo, $porDefecto = '')
{
    static $datos = null;

    if ($datos === null) {
        $datos = flash_leer('datos', []);
    }

    return $datos[$campo] ?? $porDefecto;
}

function volver_con_errores(string $ruta, array $errores, array $datos = []): void
{
    unset($datos['contrasena'], $datos['contrasena2'], $datos['token']);
    flash_guardar('errores', $errores);
    flash_guardar('datos', $datos);
    redirigir($ruta);
}

function volver_con_exito(string $ruta, string $texto): void
{
    flash_guardar('exito', $texto);
    redirigir($ruta);
}

function token(): string
{
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['token'];
}

function campo_token(): string
{
    return '<input type="hidden" name="token" value="' . e(token()) . '">';
}

function validar_token(): void
{
    $enviado = $_POST['token'] ?? '';

    if (!is_string($enviado) || empty($_SESSION['token']) || !hash_equals($_SESSION['token'], $enviado)) {
        http_response_code(400);
        exit('Solicitud invalida.');
    }
}

function solo_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Metodo no permitido.');
    }
}
