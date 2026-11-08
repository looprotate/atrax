<?php

require_once __DIR__ . '/../includes/conexion.php';

function clave_de_acceso(string $identificador): string
{
    return mb_substr(mb_strtolower(trim($identificador)), 0, 150);
}

function acceso_bloqueado(string $identificador, string $direccion): bool
{
    $fallos = consultarFila(
        'SELECT COALESCE(SUM(identificador = ?), 0) AS por_usuario, COALESCE(SUM(direccion = ?), 0) AS por_direccion
         FROM intentos_acceso
         WHERE fecha > NOW() - INTERVAL ' . (int) ACCESO_VENTANA_MINUTOS . ' MINUTE',
        [clave_de_acceso($identificador), $direccion]
    );

    return (int) $fallos['por_usuario'] >= ACCESO_MAX_FALLOS
        || (int) $fallos['por_direccion'] >= ACCESO_MAX_FALLOS_DIRECCION;
}

function registrar_intento_fallido(string $identificador, string $direccion): void
{
    ejecutar('DELETE FROM intentos_acceso WHERE fecha < NOW() - INTERVAL 1 DAY');
    ejecutar(
        'INSERT INTO intentos_acceso (identificador, direccion) VALUES (?, ?)',
        [clave_de_acceso($identificador), mb_substr($direccion, 0, 45)]
    );
}

function limpiar_intentos(string $identificador): void
{
    ejecutar('DELETE FROM intentos_acceso WHERE identificador = ?', [clave_de_acceso($identificador)]);
}
