# Guia de pantallas de ViandaSegura

Explica para que sirve cada pantalla del sistema y como se usa. Las capturas
estan en [`capturas/`](capturas) y el numero de wireframe remite al diseno de la
segunda entrega ([`wireframes/`](../../wireframes)).

| Pantalla | Rol | Ruta | Wireframe | Captura |
|---|---|---|---|---|
| [Iniciar sesion](#iniciar-sesion) | publico | `login.php` | 01, 13 | 01, 02, 24 |
| [Crear cuenta](#crear-cuenta) | publico | `registro.php` | 02, 13 | 03 |
| [Menu disponible](#menu-disponible) | cliente | `cliente/index.php` | 14 | 17, 22 |
| [Armar pedido](#armar-pedido) | cliente | `cliente/pedido.php` | 15 | 18, 27 |
| [Mis pedidos](#mis-pedidos) | cliente | `cliente/pedidos.php` | 16 | 19 |
| [Seguimiento del pedido](#seguimiento-del-pedido) | cliente | `cliente/seguimiento.php` | 16 | 20 |
| [Panel del dia](#panel-del-dia) | operador | `operador/index.php` | 09 | 12 |
| [Produccion](#produccion) | operador | `operador/produccion.php` | 10 | 13 |
| [Stock por lotes](#stock-por-lotes) | operador | `operador/stock.php` | 11 | 14 |
| [Pedidos (operador)](#pedidos-operador) | operador | `operador/pedidos.php` | — | 15 |
| [Distribucion](#distribucion) | operador | `operador/distribucion.php` | 12 | 16 |
| [Dashboard](#dashboard) | administrador | `admin/index.php` | 03 | 04, 23 |
| [Pedidos (administrador)](#pedidos-administrador) | administrador | `admin/pedidos.php` | — | 05 |
| [Stock minimo](#stock-minimo) | administrador | `admin/stock.php` | 06 | 06 |
| [Menus y precios](#menus-y-precios) | administrador | `admin/menus.php` | 07 | 07 |
| [Zonas](#zonas) | administrador | `admin/zonas.php` | 04 | 08 |
| [Vehiculos](#vehiculos) | administrador | `admin/vehiculos.php` | 05 | 09 |
| [Estadisticas](#estadisticas) | administrador | `admin/estadisticas.php` | 08 | 10 |
| [Usuarios](#usuarios) | administrador | `admin/usuarios.php` | — | 11 |
| [Pantallas de error](#pantallas-de-error) | todos | `403`, `404.php`, `503` | — | 21, 25, 26 |

## Elementos comunes

- **Barra superior** (azul marino): logo completo (el mismo del inicio de sesion y del icono de la pestana) y nombre, secciones del rol (el cliente y
  el operador las ven como pildoras; el administrador tiene un menu lateral que en
  pantallas angostas se abre con el boton ☰), nombre del usuario y boton **Salir**,
  que pide confirmacion antes de cerrar la sesion.
- **Avisos emergentes**: el resultado de cada accion aparece arriba a la derecha.
  Los avisos de exito (verde) se cierran solos a los 5 segundos; los de error
  (rojo) quedan hasta que se cierran con la ✕ y enumeran cada dato a corregir.
- **Ventanas de confirmacion**: las acciones delicadas (cerrar sesion, desactivar
  un usuario, quitar un pedido de un vehiculo, cancelar un pedido, descartar un
  lote) se confirman en una ventana. Las que no se pueden deshacer se marcan en
  rojo y dejan el foco en **Volver**.
- **Esqueletos de carga**: al pasar de una pantalla a otra o enviar un formulario,
  el contenido se reemplaza por bloques grises con la forma de la pantalla que
  viene, el boton muestra "Procesando..." y una barra fina avanza arriba (captura 28).
- **Colores de estado**: todas las pantallas usan la misma escala, y cada color
  lleva un simbolo para no depender solo de el:

  | Etiqueta | Significa | Ejemplos |
  |---|---|---|
  | ✓ verde | disponible o terminado bien | disponible, ok, activo, entregado, entrada |
  | ! ambar | requiere atencion | vence hoy, bajo minimo, falta producir, capacidad llena |
  | ✕ rojo | no se puede usar o se perdio | agotado, vencido, cancelado |
  | gris punteado | neutro o fuera de uso | inactivo, descartado, mantenimiento, salida de stock, sin pedidos |
  | azul | en curso | pendiente, preparando, listo, en distribucion, en ruta |

- **Validaciones**: los formularios marcan en el momento los campos vacios o
  invalidos; el servidor vuelve a validar todo y responde con un aviso de error.

## Publicas

### Iniciar sesion

Entrada al sistema (capturas 01 y 24). Se ingresa con el **usuario o el correo**
y la contrasena (el boton **ver** la muestra). Si los datos son correctos se abre
el panel del rol con un aviso de bienvenida; si no, aparece el aviso "Usuario o
contrasena incorrectos" (captura 02). Una cuenta desactivada no puede entrar.
Si la base de datos no responde, en lugar del formulario se muestra la pantalla
de servidores caidos. Debajo estan las cuentas de prueba y el acceso a crear
cuenta.

### Crear cuenta

Alta autonoma de clientes (captura 03). Pide nombre, tipo de cliente (particular,
empresa o institucion), usuario, correo, telefono, zona y direccion de entrega y
la contrasena dos veces (minimo 8 caracteres). Rechaza usuarios o correos ya
registrados. Al terminar vuelve al login con el aviso "Cuenta creada".

## Cliente

### Menu disponible

Pantalla inicial del cliente (capturas 17 y 22). Muestra la zona y direccion de
entrega, las **modalidades** (Semanal 5, Quincenal 10 y Mensual 20 viandas) y los
**platos** con su foto, precio y disponibilidad real segun el stock; los agotados se ven
atenuados y sin color. El boton **Armar pedido** lleva al formulario.

### Armar pedido

Formulario del pedido (captura 18). Se elige la modalidad, la fecha de entrega
(desde manana y hasta 60 dias) y cuantas viandas de cada plato; el contador
"Viandas elegidas" y una barra de progreso se ponen en verde cuando la suma coincide con la
modalidad, y cada plato elegido se marca en su fila.
Al tocar **Confirmar pedido** se abre una ventana con el **resumen**: platos,
subtotales, total, fecha y direccion (captura 27). Desde ahi se confirma o se
vuelve a editar. Si faltan platos o la suma no coincide con el plan, un aviso lo
explica sin enviar nada. Al confirmar se descuenta el stock de los lotes que
vencen primero y se abre el seguimiento del pedido nuevo.

### Mis pedidos

Historial del cliente (captura 19): una tarjeta por pedido con numero, estado,
fecha de entrega, cantidad de viandas, zona y total. **Ver seguimiento** abre el
detalle de cada uno y **Nuevo pedido** lleva al formulario.

### Seguimiento del pedido

Linea de tiempo del pedido (captura 20): Pendiente → Preparando → Listo → En
distribucion → Entregado, con la fecha y la persona de cada cambio y el vehiculo
asignado. Al costado esta el detalle de platos y el total. Un cliente solo puede
ver sus propios pedidos.

## Operador

### Panel del dia

Resumen de la cocina (captura 12): viandas a producir hoy, producidas, listas para
despacho y alertas de stock. Incluye el avance de produccion por plato, el
despacho por zona y accesos directos a Produccion y Distribucion.

### Produccion

Tickets del dia (captura 13): por cada plato muestra cuanto se pidio para hoy,
cuanto se produjo y cuanto falta. El formulario **Registrar produccion** pide el
plato, la cantidad y el vencimiento; cada registro crea un lote nuevo con su
movimiento de entrada. Abajo se listan los lotes producidos hoy.

### Stock por lotes

Inventario por lote (captura 14). Los lotes se ordenan por vencimiento y la
columna **Orden** indica cual sale primero; los vencidos, agotados o descartados
no tienen orden. El estado avisa si un lote **vence hoy**. Arriba aparecen las
alertas de stock bajo con un acceso a Produccion. El formulario de movimientos
permite registrar una **salida**, un **ajuste** (suma) o el **descarte** de todo el
lote, que se confirma en una ventana porque no se puede deshacer. Al final esta el
historial de movimientos con fecha, motivo y usuario.

### Pedidos (operador)

Lista de pedidos con filtro por estado (captura 15). En cada fila se elige el
siguiente estado permitido y se aplica; el sistema no deja saltear pasos.
Cancelar un pedido pide confirmacion: las viandas vuelven a sus lotes y se libera
el vehiculo.

### Distribucion

Asignacion de pedidos a vehiculos (captura 16). A la izquierda estan los pedidos
listos sin vehiculo; cada uno ofrece solo los vehiculos de su misma zona y el
servidor controla que haya capacidad. A la derecha, cada vehiculo con su barra de
carga y los pedidos asignados, que se pueden **Quitar** (con confirmacion)
mientras sigan en estado Listo.

## Administrador

### Dashboard

Pantalla inicial del administrador (capturas 04 y 23): pedidos del dia, viandas a
producir, viandas en reparto y alertas de stock (resaltadas si hay alguna),
ultimos pedidos, alertas con acceso a la configuracion del minimo y facturacion
acumulada.

### Pedidos (administrador)

Consulta de todos los pedidos (captura 05) con filtros por estado y zona y el
vehiculo asignado a cada uno. **Ver** abre el detalle: plan, zona, direccion,
fechas de pedido y de entrega, estado, total y los platos con sus subtotales.

### Stock minimo

Umbral de alerta por plato (captura 06). Se edita el minimo de cada plato y se
guarda todo junto; cuando el disponible queda por debajo aparece la alerta en el
dashboard y en el panel del operador.

### Menus y precios

Configuracion del catalogo (captura 07): cantidad de viandas y estado de cada
modalidad, precio y disponibilidad de cada plato y alta de platos nuevos con su
stock minimo. Los cambios se ven de inmediato en el menu del cliente.

### Zonas

Zonas de distribucion (captura 08) con barrios, vehiculos y clientes de cada una.
**Editar** carga la zona en el formulario; desde ahi tambien se da de alta una
zona nueva o se desactiva.

### Vehiculos

Flota de reparto (captura 09) con matricula, capacidad, carga actual, zona
habitual y estado. Se dan de alta y se editan con el mismo formulario.

### Estadisticas

Reportes (captura 10): plato mas pedido, facturacion acumulada, pedidos
pendientes, pedidos por zona y demanda por plato (barras), pedidos por estado y
evolucion mensual.

### Usuarios

Usuarios del sistema (captura 11) con rol, zona, ultimo acceso y estado. El
administrador da de alta operadores y administradores (los clientes se registran
solos) y puede activar o desactivar cuentas; desactivar pide confirmacion y no se
puede desactivar la propia cuenta.

## Pantallas de error

- **403 · Acceso denegado** (captura 21): aparece cuando un usuario entra a una
  pantalla de otro rol y ofrece volver a su panel.
- **404 · Pagina no encontrada** (captura 25): cualquier direccion que no existe.
  Muestra la direccion pedida, el boton para ir al panel (o al login) y, si hay
  sesion, accesos directos a las secciones del rol.
- **503 · Servidores caidos** (captura 26): se muestra cuando la base de datos no
  responde o el sistema esta en mantenimiento (ver el
  [README](../README.md#modo-mantenimiento)). Avisa que los datos estan a salvo y
  reintenta solo cada 30 segundos.
- **500 · Algo salio mal**: errores inesperados; quedan registrados en el log.
