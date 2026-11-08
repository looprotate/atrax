# Flujos de navegacion

## Flujo de acceso (todos los roles)

```
index.php
   │
   ├── sin sesion ──> login.php ──> acciones/acceso.php
   │                                   │
   │                                   ├── credenciales invalidas ──> login.php + mensaje de error
   │                                   ├── cuenta inactiva ────────> login.php + mensaje de error
   │                                   └── credenciales validas
   │                                          │
   │                                          ├── administrador ──> admin/index.php
   │                                          ├── operador ──────> operador/index.php
   │                                          └── cliente ───────> cliente/index.php
   │
   └── con sesion ──> panel del rol

login.php ──> registro.php ──> acciones/registro.php ──> login.php + "cuenta creada"

cualquier pantalla ──> boton Salir (POST con token) ──> logout.php ──> destruye la sesion ──> login.php
```

`logout.php` solo acepta `POST` con el token de la sesion: un enlace o una imagen de otro sitio no puede cerrar la
sesion. En el ingreso, cinco fallos seguidos del mismo usuario (o veinte desde la misma direccion) en 10 minutos
bloquean nuevos intentos hasta que pase ese tiempo.

Guardas: cada pantalla llama a `requiere_rol(...)` antes de imprimir nada. Sin
sesion redirige a `login.php`; con el rol equivocado devuelve un 403. Cada archivo
de `acciones/` repite la misma guarda, asi que tampoco se puede llamar a la accion
directamente por POST.

## Flujo del cliente

```
cliente/index.php (menu y disponibilidad)
   └── cliente/pedido.php (elegir modalidad, fecha y cantidades)
          └── acciones/pedido_cliente.php
                 ├── validacion fallida ──> vuelve a cliente/pedido.php con los errores
                 └── pedido creado ──────> cliente/seguimiento.php?id=N

cliente/pedidos.php (historial)
   └── cliente/seguimiento.php?id=N (linea de tiempo del pedido)
```

## Flujo del operador

```
operador/index.php (panel del dia)
   ├── operador/produccion.php ──> acciones/produccion.php ──> nuevo lote + movimiento de entrada
   ├── operador/stock.php ──────> acciones/stock.php ──────> salida, ajuste o descarte de un lote
   ├── operador/pedidos.php ────> acciones/pedidos.php ────> cambio de estado del pedido
   └── operador/distribucion.php ──> acciones/distribucion.php ──> asignar o quitar vehiculo
```

## Flujo del administrador

```
admin/index.php (dashboard)
   ├── admin/pedidos.php (consulta con filtros y detalle)
   ├── admin/stock.php ──────> acciones/stock_minimo.php
   ├── admin/menus.php ──────> acciones/productos.php · acciones/planes.php
   ├── admin/zonas.php ──────> acciones/zonas.php
   ├── admin/vehiculos.php ──> acciones/vehiculos.php
   ├── admin/estadisticas.php (solo lectura)
   └── admin/usuarios.php ───> acciones/usuarios.php
```

## Flujo transversal del pedido

```
Cliente confirma el pedido
   └── Pendiente        (se descuenta el stock por FIFO)
          │ operador acepta
          └── Preparando
                 │ operador termina la produccion
                 └── Listo
                        │ operador asigna vehiculo (zona + capacidad)
                        └── En distribucion
                               │ operador confirma la entrega
                               └── Entregado

Desde Pendiente, Preparando o Listo el operador puede cancelar:
   └── Cancelado        (el stock vuelve a sus lotes y se libera el vehiculo)
```

Cada cambio queda registrado en `pedido_estados` con la fecha y el usuario que lo
hizo, y es lo que el cliente ve en la pantalla de seguimiento.

## Correspondencia con los wireframes de la segunda entrega

| Wireframe | Pantalla implementada | Observaciones |
|---|---|---|
| 01 Iniciar sesion | `login.php` | Se quitaron "Recordarme" y "Olvidaste tu contrasena" porque no estan implementados |
| 02 Registro | `registro.php` | Se agrego el tipo de cliente y la zona, que el sistema necesita |
| 03 Dashboard del administrador | `admin/index.php` | El grafico de barras se reemplazo por las tarjetas y la lista de ultimos pedidos |
| 04 Zonas de distribucion | `admin/zonas.php` | Sin el mapa: alta y edicion sobre la tabla |
| 05 Vehiculos | `admin/vehiculos.php` | Igual al diseno, con la carga real calculada |
| 06 Stock minimo | `admin/stock.php` | Igual al diseno |
| 07 Menus y precios | `admin/menus.php` | El precio esta a nivel de plato y el plan define cuantas viandas incluye |
| 08 Estadisticas | `admin/estadisticas.php` | Los graficos se resuelven con barras en CSS; falta exportar |
| 09 Panel del dia | `operador/index.php` | Igual al diseno |
| 10 Produccion / tickets | `operador/produccion.php` | Los tickets muestran pedido, producido y faltante; no hay impresion |
| 11 Stock + alertas | `operador/stock.php` | Igual al diseno; la trazabilidad se ve en los movimientos |
| 12 Distribucion por zonas | `operador/distribucion.php` | La asignacion es por selector en lugar de arrastrar |
| 13 Acceso del cliente | `login.php` y `registro.php` | Las mismas pantallas responden en movil |
| 14 Menu disponible | `cliente/index.php` | Igual al diseno, con la foto de cada plato |
| 15 Armar pedido | `cliente/pedido.php` | Carrito y confirmacion en una sola pantalla |
| 16 Seguimiento del pedido | `cliente/seguimiento.php` y `cliente/pedidos.php` | Sin mapa: linea de tiempo con fecha y responsable |
