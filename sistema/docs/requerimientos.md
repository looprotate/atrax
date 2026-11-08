# Requerimientos y roles (version actualizada — tercera entrega)

Esta version revisa la especificacion de la segunda entrega. Cada requerimiento
funcional indica si quedo implementado en esta entrega, en que pantalla y contra
que tablas trabaja.

## Roles del sistema

| Rol | Quien es | Que hace | Pantalla inicial |
|---|---|---|---|
| Administrador | Gerente o dueno de la empresa | Parametriza zonas, vehiculos, stock minimo, menus y precios; gestiona usuarios; consulta pedidos y estadisticas | `admin/index.php` |
| Operador | Jefe de cocina | Registra la produccion, controla el stock con criterio FIFO, cambia los estados de los pedidos y organiza la distribucion | `operador/index.php` |
| Cliente | Particular, empresa o institucion | Se registra, consulta el menu, arma pedidos y sigue su estado | `cliente/index.php` |

El rol se guarda en la sesion al iniciarla y se verifica en cada pantalla y en
cada accion de escritura. Un usuario que entra a una pantalla que no le
corresponde recibe un 403.

## Requerimientos funcionales

| ID | Requerimiento | Estado | Pantalla | Tablas |
|---|---|---|---|---|
| RF1 | Gestion de usuarios con tres perfiles y control de acceso por rol | Implementado | `admin/usuarios.php` | `usuarios`, `roles`, `clientes` |
| RF2 | Inicio de sesion con validacion de credenciales y redireccion por rol | Implementado | `login.php` | `usuarios`, `roles` |
| RF3 | Registro autonomo de clientes con tipo, contacto y zona | Implementado | `registro.php` | `usuarios`, `clientes`, `zonas` |
| RF4 | Consulta del menu con precios y disponibilidad | Implementado | `cliente/index.php` | `productos`, `planes`, `lotes` |
| RF5 | Realizacion de pedidos por modalidad con calculo del total | Implementado | `cliente/pedido.php` | `pedidos`, `pedido_detalle`, `planes` |
| RF6 | Seguimiento e historial de pedidos del cliente | Implementado | `cliente/pedidos.php`, `cliente/seguimiento.php` | `pedidos`, `pedido_estados` |
| RF7 | Generacion de la produccion del dia a partir de los pedidos | Implementado | `operador/produccion.php` | `pedidos`, `pedido_detalle`, `lotes` |
| RF8 | Control de stock por lote con criterio FIFO y registro de movimientos | Implementado | `operador/stock.php` | `lotes`, `movimientos_stock` |
| RF9 | Alertas automaticas por stock bajo el minimo | Implementado | `admin/index.php`, `operador/index.php`, `operador/stock.php` | `productos`, `lotes` |
| RF10 | Control de los estados del pedido, impidiendo transiciones invalidas | Implementado | `operador/pedidos.php` | `pedidos`, `pedido_estados` |
| RF11 | Distribucion por zona respetando la capacidad de cada vehiculo | Implementado | `operador/distribucion.php` | `pedidos`, `vehiculos`, `zonas` |
| RF12 | Parametrizacion de zonas, vehiculos y stock minimo sin tocar el codigo | Implementado | `admin/zonas.php`, `admin/vehiculos.php`, `admin/stock.php` | `zonas`, `vehiculos`, `productos` |
| RF13 | Gestion de menus y precios reflejada de inmediato en el catalogo | Implementado | `admin/menus.php` | `productos`, `planes` |
| RF14 | Reportes y estadisticas de ventas, demanda y distribucion | Implementado | `admin/estadisticas.php` | `pedidos`, `pedido_detalle`, `zonas` |
| RF15 | Notificaciones a los usuarios ante cambios de estado | Pendiente | — | — |
| RF16 | Bitacora de auditoria de todas las operaciones | Parcial | `operador/stock.php`, `cliente/seguimiento.php` | `pedido_estados`, `movimientos_stock` |

RF15 y RF16 se posponen: RF15 requiere un servicio de envio de correo que no
forma parte del alcance de esta entrega, y de RF16 se implemento la trazabilidad
de lo que cambia el stock y el estado de los pedidos, que es lo que el negocio
necesita seguir; falta la bitacora de los cambios de parametrizacion.

## Requerimientos no funcionales

| ID | Requerimiento | Como se cumple en esta version |
|---|---|---|
| RNF1 | Seguridad | Contrasenas con `password_hash` (bcrypt), consultas con sentencias preparadas, token CSRF en cada formulario, cookie de sesion `HttpOnly` y `SameSite=Lax`, y regeneracion del identificador de sesion al entrar |
| RNF2 | Usabilidad | Navegacion fija por rol, mensajes de exito y de error en la misma pantalla, y formularios que conservan lo escrito cuando hay un error |
| RNF3 | Escalabilidad | Zonas, vehiculos, platos, planes y stock minimo son datos, no codigo |
| RNF4 | Mantenibilidad | Separacion en `includes` (infraestructura), `modelo` (datos), `acciones` (escritura) y paginas (presentacion) |
| RNF5 | Accesibilidad | HTML semantico (`header`, `nav`, `main`, `section`, `article`, `footer`), `label` asociado a cada campo, `aria-label` en los controles sin texto visible y textos alternativos en los mensajes |
| RNF6 | Disponibilidad | Aplicacion sin estado en el servidor mas alla de la sesion; se puede reiniciar sin perder datos |
| RNF7 | Rendimiento | Consultas agregadas resueltas en la base con indices sobre las claves primarias y foraneas |
| RNF8 | Compatibilidad | Diseno responsivo con Bootstrap 5, verificado en 1440 px y en pantalla angosta |
| RNF9 | Respaldo | La base se exporta e importa desde `bd/atrax.sql` |
| RNF10 | Integridad de los datos | Claves foraneas, `UNIQUE` en usuario, correo, matricula, codigo y nombre de zona, `CHECK` sobre cantidades y precios, y transacciones (`en_transaccion()`) en el alta de pedidos, los cambios de estado y los movimientos de stock |
| RNF11 | Trazabilidad | `pedido_estados` guarda cada cambio con fecha y responsable; `movimientos_stock` guarda cada entrada, salida y ajuste con su lote y su motivo |
| RNF12 | Confiabilidad | Validacion en el servidor de todas las operaciones y transacciones que se revierten si algo falla |
| RNF13 | Recuperacion ante fallos | El alta de pedidos y el cambio de estado usan `beginTransaction` y `rollBack` |
| RNF14 | Consistencia en tiempo real | El stock disponible se calcula siempre desde los lotes; no hay contadores duplicados |
| RNF15 | Confidencialidad | Cada rol solo ve sus pantallas y el cliente solo puede consultar sus propios pedidos |

## Reglas de negocio implementadas

1. Un pedido debe tener exactamente la cantidad de viandas de la modalidad elegida.
2. No se puede pedir mas de lo que hay en stock disponible y sin vencer.
3. El stock se descuenta por FIFO: primero el lote que vence antes.
4. La entrega se programa como minimo para el dia siguiente y como maximo a 60 dias.
5. Los estados del pedido avanzan solo por transiciones validas:
   Pendiente -> Preparando -> Listo -> En distribucion -> Entregado,
   y se puede cancelar mientras no este en distribucion.
6. Al cancelar un pedido, el stock vuelve a los lotes de los que habia salido.
7. Un pedido solo pasa a "En distribucion" si tiene un vehiculo asignado.
8. Un vehiculo solo recibe pedidos de su zona, si no esta en mantenimiento y si le
   queda capacidad.
9. Una salida de stock no puede superar lo disponible del lote y un ajuste no puede
   superar la cantidad original producida.
10. El correo y el nombre de usuario son unicos en todo el sistema.
11. El registro publico crea siempre un usuario con rol cliente; los operadores y
    administradores los da de alta el administrador.
12. Un administrador no puede desactivar su propia cuenta.
