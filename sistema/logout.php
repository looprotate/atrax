<?php

require_once __DIR__ . '/includes/auth.php';

solo_post();
validar_token();

cerrar_sesion();
session_start();
session_regenerate_id(true);
flash_guardar('exito', 'Cerraste sesion correctamente.');
redirigir('login.php');
