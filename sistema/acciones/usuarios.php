<?php

require_once __DIR__ . '/../includes/auth.php';
requiere_rol('administrador');

require_once __DIR__ . '/../modelo/usuarios.php';

solo_post();
validar_token();

$accion = campo('accion');

if ($accion === 'estado') {
    $id     = entero(campo('id'));
    $activo = campo('activo') === '1' ? 1 : 0;

    if ($id === null || usuario_por_id($id) === null) {
        volver_con_errores('admin/usuarios.php', ['El usuario indicado no existe.']);
    }

    if ($id === (int) usuario()['id']) {
        volver_con_errores('admin/usuarios.php', ['No podes desactivar tu propia cuenta.']);
    }

    cambiar_estado_usuario($id, $activo);
    volver_con_exito('admin/usuarios.php', $activo ? 'Usuario activado.' : 'Usuario desactivado.');
}

if ($accion === 'crear') {
    $nombre     = campo('nombre');
    $nombreUsuario = strtolower(campo('usuario'));
    $email      = strtolower(campo('email'));
    $telefono   = campo('telefono');
    $rolId      = entero(campo('rol_id'));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $datos      = ['nombre' => $nombre, 'usuario' => $nombreUsuario, 'email' => $email, 'telefono' => $telefono, 'rol_id' => campo('rol_id')];
    $errores    = [];
    $rolesInternos = [rol_id('administrador'), rol_id('operador')];

    if (mb_strlen($nombre) < 3) {
        $errores[] = 'El nombre debe tener al menos 3 caracteres.';
    } elseif (mb_strlen($nombre) > 100) {
        $errores[] = 'El nombre no puede superar 100 caracteres.';
    }

    if (!preg_match('/^[A-Za-z0-9_.]{4,40}$/', $nombreUsuario)) {
        $errores[] = 'El usuario debe tener entre 4 y 40 caracteres validos.';
    } elseif (existe_usuario($nombreUsuario)) {
        $errores[] = 'El nombre de usuario ya esta en uso.';
    }

    if (!es_email($email)) {
        $errores[] = 'El correo electronico no es valido.';
    } elseif (existe_email($email)) {
        $errores[] = 'El correo ya esta registrado.';
    }

    if (!es_telefono($telefono)) {
        $errores[] = 'El telefono no es valido.';
    }

    if ($rolId === null || !in_array($rolId, $rolesInternos, true)) {
        $errores[] = 'Solo se pueden crear usuarios con rol administrador u operador.';
    }

    if (mb_strlen($contrasena) < 8) {
        $errores[] = 'La contrasena debe tener al menos 8 caracteres.';
    } elseif (trim($contrasena) === '') {
        $errores[] = 'La contrasena no puede estar formada solo por espacios.';
    }

    if ($errores) {
        volver_con_errores('admin/usuarios.php', $errores, $datos);
    }

    crear_usuario($rolId, $nombre, $nombreUsuario, $email, $telefono, $contrasena);
    volver_con_exito('admin/usuarios.php', 'Usuario creado correctamente.');
}

volver_con_errores('admin/usuarios.php', ['La accion solicitada no es valida.']);
