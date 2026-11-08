<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/error.php';

function bd(): PDO
{
    static $conexion = null;

    if ($conexion === null) {
        $dsn = 'mysql:host=' . BD_HOST . ';port=' . BD_PUERTO . ';dbname=' . BD_NOMBRE . ';charset=utf8mb4';
        $conexion = new PDO($dsn, BD_USUARIO, BD_CLAVE, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $conexion;
}

function en_transaccion(callable $trabajo)
{
    $conexion = bd();
    $conexion->beginTransaction();

    try {
        $resultado = $trabajo();
        $conexion->commit();

        return $resultado;
    } catch (Throwable $error) {
        $conexion->rollBack();
        throw $error;
    }
}

function consultar(string $sql, array $parametros = []): array
{
    $sentencia = bd()->prepare($sql);
    $sentencia->execute($parametros);

    return $sentencia->fetchAll();
}

function consultarFila(string $sql, array $parametros = []): ?array
{
    $sentencia = bd()->prepare($sql);
    $sentencia->execute($parametros);
    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila;
}

function consultarValor(string $sql, array $parametros = [], $porDefecto = null)
{
    $sentencia = bd()->prepare($sql);
    $sentencia->execute($parametros);
    $valor = $sentencia->fetchColumn();

    return $valor === false ? $porDefecto : $valor;
}

function ejecutar(string $sql, array $parametros = []): int
{
    $sentencia = bd()->prepare($sql);
    $sentencia->execute($parametros);

    return $sentencia->rowCount();
}

function insertar(string $sql, array $parametros = []): int
{
    ejecutar($sql, $parametros);

    return (int) bd()->lastInsertId();
}
