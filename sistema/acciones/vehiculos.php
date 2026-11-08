<?php

require_once __DIR__ . '/../includes/auth.php';
requiere_rol('administrador');

require_once __DIR__ . '/../modelo/zonas.php';

solo_post();
validar_token();

$id        = (int) campo('id', '0');
$nombre    = campo('nombre');
$matricula = strtoupper(campo('matricula'));
$capacidad = entero(campo('capacidad'));
$zonaId    = entero(campo('zona_id'));
$estado    = campo('estado');
$volver    = $id > 0 ? 'admin/vehiculos.php?editar=' . $id : 'admin/vehiculos.php';
$datos     = ['nombre' => $nombre, 'matricula' => $matricula, 'capacidad' => campo('capacidad'), 'zona_id' => campo('zona_id'), 'estado' => $estado];
$errores   = [];
$actual    = $id > 0 ? vehiculo_por_id($id) : null;

if (mb_strlen($nombre) < 3) {
    $errores[] = 'El nombre del vehiculo debe tener al menos 3 caracteres.';
} elseif (mb_strlen($nombre) > 40) {
    $errores[] = 'El nombre del vehiculo no puede superar 40 caracteres.';
}

if (!preg_match('/^[A-Z0-9 -]{5,15}$/', $matricula)) {
    $errores[] = 'La matricula debe tener entre 5 y 15 caracteres.';
}

if ($capacidad === null || $capacidad < 1 || $capacidad > 500) {
    $errores[] = 'La capacidad debe ser un numero entre 1 y 500.';
}

if ($zonaId === null || zona_por_id($zonaId) === null) {
    $errores[] = 'La zona seleccionada no existe.';
}

if (!in_array($estado, ['Disponible', 'En ruta', 'Mantenimiento'], true)) {
    $errores[] = 'El estado indicado no es valido.';
}

if ($id > 0 && $actual === null) {
    $errores[] = 'El vehiculo indicado no existe.';
}

if (!$errores && existe_matricula($matricula, $id)) {
    $errores[] = 'Ya existe otro vehiculo con esa matricula.';
}

if (!$errores && $actual !== null && $capacidad < (int) $actual['carga']) {
    $errores[] = 'La capacidad no puede ser menor que la carga asignada actualmente (' . (int) $actual['carga'] . ' viandas).';
}

if ($errores) {
    volver_con_errores($volver, $errores, $datos);
}

if ($id > 0) {
    actualizar_vehiculo($id, $nombre, $matricula, $capacidad, $zonaId, $estado);
    volver_con_exito('admin/vehiculos.php', 'Vehiculo actualizado correctamente.');
}

crear_vehiculo($nombre, $matricula, $capacidad, $zonaId, $estado);
volver_con_exito('admin/vehiculos.php', 'Vehiculo creado correctamente.');
