<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../modelo/usuarios.php';
require_once __DIR__ . '/../modelo/accesos.php';

solo_post();
validar_token();

$identificador = campo('usuario');
$contrasena    = (string) ($_POST['contrasena'] ?? '');
$errores       = [];

if ($identificador === '') {
    $errores[] = 'Ingresa tu usuario o correo.';
}

if ($contrasena === '') {
    $errores[] = 'Ingresa tu contrasena.';
}

if ($errores) {
    volver_con_errores('login.php', $errores, ['usuario' => $identificador]);
}

$direccion = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

if (acceso_bloqueado($identificador, $direccion)) {
    volver_con_errores(
        'login.php',
        ['Demasiados intentos fallidos. Espera ' . ACCESO_VENTANA_MINUTOS . ' minutos antes de volver a intentar.'],
        ['usuario' => $identificador]
    );
}

$usuario = usuario_por_acceso($identificador);

if ($usuario === null || !password_verify($contrasena, $usuario['contrasena'])) {
    registrar_intento_fallido($identificador, $direccion);
    volver_con_errores('login.php', ['Usuario o contrasena incorrectos.'], ['usuario' => $identificador]);
}

limpiar_intentos($identificador);

if ((int) $usuario['activo'] !== 1) {
    volver_con_errores('login.php', ['La cuenta esta inactiva. Consulta con el administrador.'], ['usuario' => $identificador]);
}

abrir_sesion($usuario);

$nombre = explode(' ', trim($usuario['nombre']))[0];
flash_guardar('exito_titulo', 'Hola, ' . $nombre);
flash_guardar('exito', 'Que bueno verte de nuevo en ' . APP_NOMBRE . '.');
redirigir(panel_de($usuario['rol']));
