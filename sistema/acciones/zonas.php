<?php

require_once __DIR__ . '/../includes/auth.php';
requiere_rol('administrador');

require_once __DIR__ . '/../modelo/zonas.php';

solo_post();
validar_token();

$id      = (int) campo('id', '0');
$codigo  = strtoupper(campo('codigo'));
$nombre  = campo('nombre');
$barrios = campo('barrios');
$activa  = campo('activa') === '0' ? 0 : 1;
$volver  = $id > 0 ? 'admin/zonas.php?editar=' . $id : 'admin/zonas.php';
$datos   = ['codigo' => $codigo, 'nombre' => $nombre, 'barrios' => $barrios, 'activa' => $activa];
$errores = [];

if (!preg_match('/^[A-Z0-9]{2,5}$/', $codigo)) {
    $errores[] = 'El codigo debe tener entre 2 y 5 caracteres (letras o numeros).';
}

if (mb_strlen($nombre) < 3) {
    $errores[] = 'El nombre de la zona debe tener al menos 3 caracteres.';
} elseif (mb_strlen($nombre) > 60) {
    $errores[] = 'El nombre de la zona no puede superar 60 caracteres.';
}

if (mb_strlen($barrios) < 3) {
    $errores[] = 'Indica al menos un barrio.';
} elseif (mb_strlen($barrios) > 150) {
    $errores[] = 'Los barrios no pueden superar 150 caracteres.';
}

if ($id > 0 && zona_por_id($id) === null) {
    $errores[] = 'La zona indicada no existe.';
}

if (existe_zona('codigo', $codigo, $id)) {
    $errores[] = 'Ya existe otra zona con ese codigo.';
}

if (existe_zona('nombre', $nombre, $id)) {
    $errores[] = 'Ya existe otra zona con ese nombre.';
}

if ($errores) {
    volver_con_errores($volver, $errores, $datos);
}

if ($id > 0) {
    actualizar_zona($id, $codigo, $nombre, $barrios, $activa);
    volver_con_exito('admin/zonas.php', 'Zona actualizada correctamente.');
}

crear_zona($codigo, $nombre, $barrios);
volver_con_exito('admin/zonas.php', 'Zona creada correctamente.');
