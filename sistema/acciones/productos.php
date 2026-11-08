<?php

require_once __DIR__ . '/../includes/auth.php';
requiere_rol('administrador');

require_once __DIR__ . '/../modelo/catalogo.php';

solo_post();
validar_token();

$accion = campo('accion');

if ($accion === 'crear') {
    $nombre      = campo('nombre');
    $descripcion = campo('descripcion');
    $precio      = filter_var(campo('precio'), FILTER_VALIDATE_FLOAT);
    $stockMinimo = entero(campo('stock_minimo'));
    $datos       = ['nombre' => $nombre, 'descripcion' => $descripcion, 'precio' => campo('precio'), 'stock_minimo' => campo('stock_minimo')];
    $errores     = [];

    if (mb_strlen($nombre) < 3) {
        $errores[] = 'El nombre del plato debe tener al menos 3 caracteres.';
    } elseif (mb_strlen($nombre) > 100) {
        $errores[] = 'El nombre del plato no puede superar 100 caracteres.';
    }

    if (mb_strlen($descripcion) < 5) {
        $errores[] = 'La descripcion debe tener al menos 5 caracteres.';
    } elseif (mb_strlen($descripcion) > 255) {
        $errores[] = 'La descripcion no puede superar 255 caracteres.';
    }

    if ($precio === false || $precio <= 0 || $precio > 99999) {
        $errores[] = 'El precio debe ser un numero mayor a 0.';
    }

    if ($stockMinimo === null || $stockMinimo < 0 || $stockMinimo > 999) {
        $errores[] = 'El stock minimo debe estar entre 0 y 999.';
    }

    if (!$errores && existe_producto($nombre)) {
        $errores[] = 'Ya existe un plato con ese nombre.';
    }

    if ($errores) {
        volver_con_errores('admin/menus.php', $errores, $datos);
    }

    crear_producto($nombre, $descripcion, (float) $precio, $stockMinimo);
    volver_con_exito('admin/menus.php', 'Plato agregado al menu.');
}

if ($accion === 'actualizar') {
    $precios = $_POST['precio'] ?? [];
    $activos = $_POST['activo'] ?? [];
    $errores = [];
    $validos = [];

    if (!is_array($precios) || !$precios) {
        volver_con_errores('admin/menus.php', ['No se recibieron precios para actualizar.']);
    }

    foreach ($precios as $productoId => $valor) {
        $productoId = entero($productoId);
        $precio     = filter_var(trim((string) $valor), FILTER_VALIDATE_FLOAT);

        if ($productoId === null || producto_por_id($productoId) === null) {
            $errores[] = 'Uno de los platos indicados no existe.';
            continue;
        }

        if ($precio === false || $precio <= 0 || $precio > 99999) {
            $errores[] = 'Los precios deben ser numeros mayores a 0.';
            continue;
        }

        $validos[$productoId] = ['precio' => (float) $precio, 'activo' => isset($activos[$productoId]) ? 1 : 0];
    }

    if ($errores) {
        volver_con_errores('admin/menus.php', array_values(array_unique($errores)));
    }

    foreach ($validos as $productoId => $valor) {
        actualizar_producto($productoId, $valor['precio'], $valor['activo']);
    }

    volver_con_exito('admin/menus.php', 'Menu actualizado correctamente.');
}

volver_con_errores('admin/menus.php', ['La accion solicitada no es valida.']);
