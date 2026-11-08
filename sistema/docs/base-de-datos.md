# Base de datos

Motor: MySQL / MariaDB, InnoDB, `utf8mb4`. El script completo (estructura + datos
de prueba) esta en [`bd/atrax.sql`](../bd/atrax.sql).

## Tablas

| Tabla | Que guarda | Clave primaria | Claves foraneas | Unicos |
|---|---|---|---|---|
| `roles` | Los tres perfiles del sistema | `id` | — | `nombre` |
| `zonas` | Zonas de reparto y sus barrios | `id` | — | `codigo`, `nombre` |
| `usuarios` | Datos comunes de todos los usuarios | `id` | `rol_id` -> `roles` | `usuario`, `email` |
| `clientes` | Datos propios del cliente | `id` | `usuario_id` -> `usuarios`, `zona_id` -> `zonas` | `usuario_id` |
| `vehiculos` | Flota de reparto y su capacidad | `id` | `zona_id` -> `zonas` | `matricula` |
| `productos` | Platos del menu, su stock minimo y su foto (`imagen`, opcional) | `id` | — | `nombre` |
| `planes` | Modalidades semanal, quincenal y mensual | `id` | — | `nombre` |
| `lotes` | Produccion de un plato con su vencimiento | `id` | `producto_id` -> `productos`, `operador_id` -> `usuarios` | `numero_lote` |
| `pedidos` | Pedido de un cliente | `id` | `cliente_id`, `plan_id`, `zona_id`, `vehiculo_id` | — |
| `pedido_detalle` | Platos y cantidades de un pedido | `id` | `pedido_id` -> `pedidos`, `producto_id` -> `productos` | `pedido_id` + `producto_id` |
| `pedido_estados` | Historial de cambios de estado | `id` | `pedido_id` -> `pedidos`, `usuario_id` -> `usuarios` | — |
| `movimientos_stock` | Entradas, salidas y ajustes por lote | `id` | `lote_id` -> `lotes`, `usuario_id` -> `usuarios`, `pedido_id` -> `pedidos` | — |
| `intentos_acceso` | Ingresos fallidos recientes, para limitar los intentos | `id` | — | — |

## Relaciones

```
roles 1 ── N usuarios 1 ── 1 clientes N ── 1 zonas
                │                              │
                │                              ├── N vehiculos
                │                              │
                └── N lotes N ── 1 productos   └── N pedidos
                        │                            │
                        └── N movimientos_stock      ├── N pedido_detalle N ── 1 productos
                                                     ├── N pedido_estados
                                                     └── 1 planes
```

- La herencia del diagrama de clases (Usuario -> Cliente/Operador/Administrador)
  se resuelve con `usuarios.rol_id` mas la tabla `clientes` para los datos que solo
  tiene ese perfil. Operador y administrador no necesitan tabla propia en esta version.
- `pedido_detalle`, `pedido_estados` y `movimientos_stock` son composiciones: se
  borran con su pedido (`ON DELETE CASCADE`) porque no tienen sentido sin el.
- `Stock` del diccionario de datos no es una tabla: el stock disponible se calcula
  sumando `lotes.disponible` de los lotes vigentes. Asi no puede quedar desincronizado.

- `intentos_acceso` no tiene claves foraneas a proposito: guarda lo que se escribio en el formulario de ingreso
  (`identificador`, en minusculas) y la direccion (`direccion`), que puede ser de un usuario que no existe. Se
  consulta por `(identificador, fecha)` y por `(direccion, fecha)`, y los registros de mas de un dia se borran al
  guardar uno nuevo. Arranca vacia.

## Dominios cerrados (ENUM)

| Tabla.campo | Valores |
|---|---|
| `clientes.tipo` | `Particular`, `Empresa`, `Institucion` |
| `vehiculos.estado` | `Disponible`, `En ruta`, `Mantenimiento` |
| `lotes.estado` | `Disponible`, `Agotado`, `Descartado` |
| `pedidos.estado` | `Pendiente`, `Preparando`, `Listo`, `En distribucion`, `Entregado`, `Cancelado` |
| `movimientos_stock.tipo` | `Entrada`, `Salida`, `Ajuste` |

Respecto de la segunda entrega, los estados del pedido se redujeron a los seis que
el sistema realmente maneja. `Retrasado`, `Rechazado`, `Perdido` y `Completado`
quedan fuera de esta version porque ninguna pantalla los produce todavia.

## Reglas declaradas en la base

- `CHECK (capacidad > 0)` en `vehiculos`.
- `CHECK (precio > 0)` y `CHECK (stock_minimo >= 0)` en `productos`.
- `productos.imagen` guarda solo el nombre del archivo dentro de `assets/img/platos/`; si esta vacio
  o el archivo no existe, el sistema muestra `sin-imagen.svg`.
- `CHECK (viandas > 0)` en `planes`.
- `CHECK (cantidad > 0)` y `CHECK (disponible >= 0 AND disponible <= cantidad)` en `lotes`.
- `CHECK (cantidad > 0)` en `pedido_detalle` y en `movimientos_stock`.
- `UNIQUE (pedido_id, producto_id)` en `pedido_detalle`: un plato no se puede
  repetir dos veces en el mismo pedido.

Estas reglas se validan tambien en PHP antes de escribir, para poder mostrarle al
usuario un mensaje claro en lugar de un error del motor.

## Datos de prueba incluidos

- 3 roles, 7 zonas (Z1 a Z7), 4 vehiculos.
- 8 usuarios: 2 administradores, 2 operadores y 4 clientes (particular, empresa,
  institucion y un segundo particular).
- 5 platos con su stock minimo y 3 modalidades de plan.
- 10 lotes: 5 ya consumidos y 5 vigentes, con vencimientos escalonados para que se
  vea el orden FIFO y para que queden dos platos por debajo del minimo.
- 5 pedidos en distintos estados (Entregado, En distribucion, Listo y Pendiente),
  con su detalle, su historial de estados y sus movimientos de stock coherentes.

Las fechas de los datos de prueba son relativas a la fecha de importacion
(`CURDATE()`), asi que el sistema siempre muestra pedidos y produccion del dia.
