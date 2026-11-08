<?php

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';

enviar_cabeceras_seguridad();

if (APP_MANTENIMIENTO) {
    mostrar_error(503);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_name('ATRAXSESION');
    session_start();
}

function autenticado(): bool
{
    return isset($_SESSION['usuario_id']);
}

function usuario(): ?array
{
    if (!autenticado()) {
        return null;
    }

    return [
        'id'      => $_SESSION['usuario_id'],
        'nombre'  => $_SESSION['usuario_nombre'],
        'rol'     => $_SESSION['usuario_rol'],
        'inicial' => mb_strtoupper(mb_substr($_SESSION['usuario_nombre'], 0, 1)),
    ];
}

function rol_actual(): string
{
    return $_SESSION['usuario_rol'] ?? '';
}

function cliente_id(): ?int
{
    return $_SESSION['cliente_id'] ?? null;
}

function panel_de(string $rol): string
{
    $paneles = [
        'administrador' => 'admin/index.php',
        'operador'      => 'operador/index.php',
        'cliente'       => 'cliente/index.php',
    ];

    return $paneles[$rol] ?? 'login.php';
}

function menu_de(string $rol): array
{
    $menus = [
        'administrador' => [
            ['dashboard',     'Dashboard',       'admin/index.php'],
            ['pedidos',       'Pedidos',         'admin/pedidos.php'],
            ['stock',         'Stock minimo',    'admin/stock.php'],
            ['menus',         'Menus y precios', 'admin/menus.php'],
            ['zonas',         'Zonas',           'admin/zonas.php'],
            ['vehiculos',     'Vehiculos',       'admin/vehiculos.php'],
            ['estadisticas',  'Estadisticas',    'admin/estadisticas.php'],
            ['usuarios',      'Usuarios',        'admin/usuarios.php'],
        ],
        'operador' => [
            ['panel',        'Panel del dia', 'operador/index.php'],
            ['produccion',   'Produccion',    'operador/produccion.php'],
            ['stock',        'Stock',         'operador/stock.php'],
            ['pedidos',      'Pedidos',       'operador/pedidos.php'],
            ['distribucion', 'Distribucion',  'operador/distribucion.php'],
        ],
        'cliente' => [
            ['menu',     'Menu',        'cliente/index.php'],
            ['pedido',   'Armar pedido','cliente/pedido.php'],
            ['pedidos',  'Mis pedidos', 'cliente/pedidos.php'],
        ],
    ];

    return $menus[$rol] ?? [];
}

function abrir_sesion(array $usuario): void
{
    session_regenerate_id(true);

    $_SESSION['usuario_id']     = (int) $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_rol']    = $usuario['rol'];

    if ($usuario['rol'] === 'cliente') {
        $_SESSION['cliente_id'] = (int) consultarValor(
            'SELECT id FROM clientes WHERE usuario_id = ?',
            [$usuario['id']],
            0
        );
    }

    ejecutar('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?', [$usuario['id']]);
}

function cerrar_sesion(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
    }

    session_destroy();
}

function requiere_login(): void
{
    if (!autenticado()) {
        flash_guardar('errores', ['Necesitas iniciar sesion para acceder.']);
        redirigir('login.php');
    }
}

function requiere_rol(string ...$roles): void
{
    requiere_login();

    if (!in_array(rol_actual(), $roles, true)) {
        http_response_code(403);
        require APP_RAIZ . '/includes/denegado.php';
        exit;
    }
}
