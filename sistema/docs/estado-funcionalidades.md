# Estado final de funcionalidades

Resumen de lo que paso con cada requerimiento de la especificacion de la segunda
entrega al llegar a la tercera. El detalle por pantalla y tabla esta en
[requerimientos](requerimientos.md).

Convenciones: **Implementado** = como se especifico; **Modificado** = existe pero
con una solucion distinta a la del diseno; **No implementado** = fuera de esta entrega.

## Requerimientos funcionales

| ID | Funcionalidad | Estado | Justificacion |
|---|---|---|---|
| RF1 | Gestion de usuarios con tres perfiles | Implementado | `admin/usuarios.php`; el admin no puede desactivarse a si mismo |
| RF2 | Inicio de sesion con redireccion por rol | Modificado | Funciona, pero se quitaron "Recordarme" y "Olvidaste tu contrasena" del diseno porque no estan implementados |
| RF3 | Registro autonomo de clientes | Modificado | Se agregaron tipo de cliente y zona, que el sistema necesita para distribuir |
| RF4 | Consulta del menu con disponibilidad | Implementado | Disponibilidad real desde los lotes y foto de cada plato (creditos en `assets/img/platos/CREDITOS.md`) |
| RF5 | Pedidos por modalidad con total | Modificado | Carrito y confirmacion en una sola pantalla, con resumen en ventana emergente |
| RF6 | Seguimiento e historial | Modificado | Linea de tiempo con fecha y responsable en lugar del mapa del diseno |
| RF7 | Produccion del dia | Modificado | Tickets con pedido, producido y faltante; sin impresion |
| RF8 | Stock por lote con FIFO | Implementado | `operador/stock.php`, con movimientos de entrada, salida y ajuste |
| RF9 | Alertas por stock bajo el minimo | Implementado | En ambos paneles y en la pantalla de stock |
| RF10 | Estados del pedido con transiciones validas | Implementado | `operador/pedidos.php`; al cancelar, el stock vuelve a su lote |
| RF11 | Distribucion por zona y capacidad | Modificado | Asignacion por selector en lugar de arrastrar; misma validacion en el servidor |
| RF12 | Parametrizacion de zonas, vehiculos y stock minimo | Modificado | Zonas sin mapa: alta y edicion sobre la tabla |
| RF13 | Menus y precios | Modificado | El precio esta a nivel de plato y el plan define cuantas viandas incluye |
| RF14 | Reportes y estadisticas | Modificado | Graficos con barras en CSS; falta exportar a PDF o Excel |
| RF15 | Notificaciones ante cambios de estado | No implementado | Ver abajo |
| RF16 | Bitacora de auditoria | Parcial | Hay trazabilidad de estados de pedido y de movimientos de stock; faltan los cambios sobre usuarios, zonas, vehiculos y precios |

## Requisitos de la tarea fullstack

Lo que pide el listado de tareas del curso (HTML/CSS, PHP y base de datos, programacion
orientada a objetos, JavaScript asincrono y pruebas) y como quedo cada punto.

| Punto | Estado | Justificacion |
|---|---|---|
| Consulta asincrona con `fetch()` y JSON | Implementado | `admin/pedidos.php` pide los pedidos a `api/pedidos.php` con `fetch()` y rehace la tabla sin recargar; maneja carga, errores (red, tiempo, sesion vencida, servidor) y reintento |
| Busqueda y filtros dinamicos | Implementado | Buscador por cliente o numero de pedido, mas filtros por estado y zona, con la direccion sincronizada; sin JavaScript sigue funcionando como una busqueda normal |
| Limite de intentos de ingreso | Implementado | Cinco fallos del mismo usuario (o veinte de la misma direccion) en 10 minutos bloquean el ingreso hasta que pase ese tiempo; tabla `intentos_acceso` |
| Eliminacion de registros | Modificado | No hay `DELETE` desde la interfaz: usuarios, zonas, vehiculos y platos se desactivan, y los pedidos se cancelan, para no perder el historial |
| Manejo de archivos | Modificado | Solo las fotos estaticas de los platos (`assets/img/platos/`); no hay carga de archivos por parte del usuario |
| Programacion orientada a objetos | No implementado | El modelo es procedural: una funcion por consulta, con PDO. La unica clase es la excepcion `StockInsuficiente`. No hay entidades ni repositorios como clases |
| Pruebas de caja negra y blanca, informe de defectos | Implementado | [Caja negra](pruebas-caja-negra.md), [caja blanca](pruebas-caja-blanca.md) e [informe de defectos](informe-de-defectos.md); los 13 defectos que encontraron estan corregidos y verificados |

## Casos puntuales

### Notificaciones (RF15)

**No implementado.** No hay codigo, tabla ni pantalla. El cliente ve el cambio de
estado solo al entrar a `cliente/seguimiento.php`. Se pospuso porque requiere un
servicio de envio de correo que no forma parte del alcance de esta entrega.
Nota: en el repo el requerimiento se llama RF15, no HU-15.

### Pasarela de pago

**No implementado, y no figura en ninguna lista de requerimientos del repo.** El
pedido guarda el total calculado, pero no hay cobro, estado de pago, ni
integracion con ningun proveedor. Si la especificacion original la pedia, falta
registrarla como pendiente en `requerimientos.md`.

### Recuperacion de contrasena

**No implementado.** El enlace "Olvidaste tu contrasena" existe en los
wireframes 01 y 13 (apunta a `#`), pero se quito de `login.php`. Depende de lo
mismo que RF15: sin envio de correo no se puede mandar un enlace de
restablecimiento. Hoy solo un administrador puede dar de alta cuentas; no hay
forma de cambiar la contrasena de un usuario existente desde la interfaz.

## Otras funcionalidades no implementadas

- Edicion del perfil del cliente.
- Mapa de zonas y mapa de seguimiento del reparto.
- Exportacion de reportes a PDF o Excel.
- Impresion de tickets de produccion.
- Bitacora de cambios de parametrizacion (parte de RF16).
