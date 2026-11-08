<?php

define('BD_HOST', getenv('ATRAX_BD_HOST') ?: 'localhost');
define('BD_PUERTO', getenv('ATRAX_BD_PUERTO') ?: '3306');
define('BD_NOMBRE', getenv('ATRAX_BD_NOMBRE') ?: 'atrax');
define('BD_USUARIO', getenv('ATRAX_BD_USUARIO') ?: 'root');
define('BD_CLAVE', getenv('ATRAX_BD_CLAVE') ?: '');

define('APP_NOMBRE', 'ViandaSegura');

// Ingreso: con ACCESO_MAX_FALLOS intentos fallidos para un mismo usuario (o ACCESO_MAX_FALLOS_DIRECCION desde
// una misma direccion) dentro de ACCESO_VENTANA_MINUTOS minutos no se prueban mas contrasenas hasta que pase.
define('ACCESO_MAX_FALLOS', 5);
define('ACCESO_MAX_FALLOS_DIRECCION', 20);
define('ACCESO_VENTANA_MINUTOS', 10);
define('APP_RAIZ', dirname(__DIR__));

// Con ATRAX_MANTENIMIENTO=1 o con el archivo sistema/MANTENIMIENTO el sistema muestra la pantalla 503.
define('APP_MANTENIMIENTO', getenv('ATRAX_MANTENIMIENTO') === '1' || is_file(APP_RAIZ . '/MANTENIMIENTO'));
