<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../modelo/usuarios.php';
require_once __DIR__ . '/../modelo/zonas.php';

solo_post();
validar_token();

$datos = [
    'nombre'    => campo('nombre'),
    'usuario'   => strtolower(campo('usuario')),
    'email'     => strtolower(campo('email')),
    'telefono'  => campo('telefono'),
    'direccion' => campo('direccion'),
    'tipo'      => campo('tipo'),
    'zona_id'   => campo('zona_id'),
];

$contrasena  = (string) ($_POST['contrasena'] ?? '');
$contrasena2 = (string) ($_POST['contrasena2'] ?? '');
$errores     = [];

if (mb_strlen($datos['nombre']) < 3) {
    $errores[] = 'El nombre debe tener al menos 3 caracteres.';
} elseif (mb_strlen($datos['nombre']) > 100) {
    $errores[] = 'El nombre no puede superar 100 caracteres.';
}

if (!preg_match('/^[A-Za-z0-9_.]{4,40}$/', $datos['usuario'])) {
    $errores[] = 'El usuario debe tener entre 4 y 40 caracteres (letras, numeros, punto o guion bajo).';
} elseif (existe_usuario($datos['usuario'])) {
    $errores[] = 'El nombre de usuario ya esta en uso.';
}

if (!es_email($datos['email'])) {
    $errores[] = 'El correo electronico no tiene un formato valido.';
} elseif (existe_email($datos['email'])) {
    $errores[] = 'El correo electronico ya esta registrado.';
}

if (!es_telefono($datos['telefono'])) {
    $errores[] = 'El telefono no tiene un formato valido.';
}

if (mb_strlen($datos['direccion']) < 5) {
    $errores[] = 'La direccion debe tener al menos 5 caracteres.';
} elseif (mb_strlen($datos['direccion']) > 200) {
    $errores[] = 'La direccion no puede superar 200 caracteres.';
}

if (!in_array($datos['tipo'], ['Particular', 'Empresa', 'Institucion'], true)) {
    $errores[] = 'El tipo de cliente no es valido.';
}

$zonaId = entero($datos['zona_id']);

$zona = $zonaId === null ? null : zona_por_id($zonaId);

if ($zona === null || (int) $zona['activa'] !== 1) {
    $errores[] = 'Selecciona una zona de entrega valida.';
}

if (mb_strlen($contrasena) < 8) {
    $errores[] = 'La contrasena debe tener al menos 8 caracteres.';
} elseif (trim($contrasena) === '') {
    $errores[] = 'La contrasena no puede estar formada solo por espacios.';
}

if ($contrasena !== $contrasena2) {
    $errores[] = 'Las contrasenas no coinciden.';
}

if ($errores) {
    volver_con_errores('registro.php', $errores, $datos);
}

$rolCliente = rol_id('cliente');
$conexion   = bd();
$conexion->beginTransaction();

try {
    $usuarioId = crear_usuario($rolCliente, $datos['nombre'], $datos['usuario'], $datos['email'], $datos['telefono'], $contrasena);
    crear_cliente($usuarioId, $zonaId, $datos['tipo'], $datos['direccion']);
    $conexion->commit();
} catch (Throwable $error) {
    $conexion->rollBack();
    volver_con_errores('registro.php', ['No se pudo crear la cuenta. Intenta nuevamente.'], $datos);
}

volver_con_exito('login.php', 'Cuenta creada correctamente. Ya podes iniciar sesion.');
