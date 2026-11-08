# Pruebas

Las pruebas se ejecutaron sobre la base recien importada desde `bd/atrax.sql`,
con PHP 8.2 y MariaDB 10.4. Cada caso se corrio contra el sistema en
funcionamiento; la salida literal de todos los casos esta en
[`pruebas-consola.txt`](pruebas-consola.txt) y las capturas en
[`capturas/`](capturas).

Para volver a ejecutarlas:

```
ATRAX_URL=http://localhost/atrax/sistema ATRAX_SQL="mysql -u root atrax -e" bash docs/pruebas.sh
```

Conviene reimportar `bd/atrax.sql` antes de correrlas, porque varios casos
modifican datos.

## Autenticacion y control de acceso

| # | Funcionalidad | Datos utilizados | Resultado esperado | Resultado obtenido | Evidencia |
|---|---|---|---|---|---|
| P01 | Login correcto | `admin` / `Admin123`, `cocina` / `Operador123`, `julio` / `Cliente123` | Sesion creada y redireccion al panel de cada rol | 302 a `admin/index.php`, `operador/index.php` y `cliente/index.php` respectivamente | consola P01, capturas 04, 12, 17 |
| P02 | Login incorrecto | `admin` / `12345678` | Rechazo con mensaje generico | "Usuario o contrasena incorrectos." y vuelta al login | consola P02, captura 02 |
| P03 | Login con inyeccion SQL | usuario `admin' OR '1'='1`, contrasena `x` | La consulta preparada trata el texto como dato: no hay acceso | "Usuario o contrasena incorrectos." | consola P03 |
| P04 | Acceso sin sesion | GET a `admin/index.php` sin cookie | Redireccion al login | 302 a `login.php` | consola P04 |
| P05 | Acceso con rol incorrecto | Sesion de cliente sobre `admin/index.php` y `operador/stock.php` | Acceso denegado | 403 con la pantalla "Acceso denegado" | consola P05, captura 21 |
| P06 | Cierre de sesion | Sesion de `admin`, luego `POST` a `logout.php` con el token | La sesion se destruye y no se puede volver | 200 antes del logout y 302 al login despues | consola P06 |
| P07 | Proteccion CSRF | POST a `acciones/zonas.php` con `token=falso` | La accion se rechaza | HTTP 400 "Solicitud invalida" | consola P07 |
| P08 | Usuario inactivo | `lucia` desactivada por SQL, contrasena correcta | No puede entrar | "La cuenta esta inactiva. Consulta con el administrador." | consola P08 |
| P43 | Restricciones cruzadas | Operador sobre pantallas y acciones del admin, admin sobre acciones del operador | Todas rechazadas | 403 en los tres intentos | consola P43 |

## Registro de clientes

| # | Funcionalidad | Datos utilizados | Resultado esperado | Resultado obtenido | Evidencia |
|---|---|---|---|---|---|
| P09 | Registro valido | Martina Silva · `martina` · `martina@correo.com` · +598 99 777 888 · Av. Italia 3400 · Particular · Z3 · contrasena de 12 caracteres | Usuario creado con rol cliente y ficha de cliente asociada | "Cuenta creada correctamente." y fila en `usuarios` + `clientes` con rol `cliente` | consola P09, captura 03 |
| P10 | Duplicados | El mismo usuario y el mismo correo que P09 | Rechazo por duplicado | "El nombre de usuario ya esta en uso." y "El correo electronico ya esta registrado." | consola P10 |
| P11 | Datos invalidos | nombre `Al`, usuario `ab`, correo `correo-invalido`, telefono `abc`, direccion `xy`, tipo `Otro`, zona `999`, contrasenas `123` y `456` | Un mensaje por cada regla incumplida | 9 mensajes de error, uno por regla | consola P11 |

## Cliente: consulta y pedidos

| # | Funcionalidad | Datos utilizados | Resultado esperado | Resultado obtenido | Evidencia |
|---|---|---|---|---|---|
| P12 | Alta de pedido | `julio`, plan Semanal (5 viandas), 2 milanesas + 3 tartas, entrega a dos dias | Pedido creado, total calculado y stock descontado por FIFO | Pedido #6 creado; salidas de 2 sobre `L-0994` y de 3 sobre `L-0991` (el lote que vence primero) | consola P12, captura 18 |
| P13 | Stock insuficiente | Plan Mensual, 20 ensaladas de quinoa (hay 5) | Rechazo por falta de stock | "No hay stock suficiente de Ensalada de quinoa: pediste 20 y hay 5." | consola P13 |
| P14 | Cantidad distinta a la del plan | Plan Semanal con 2 viandas | Rechazo | "El plan Semanal requiere exactamente 5 viandas y elegiste 2." | consola P14 |
| P15 | Fecha invalida | Fecha de entrega de ayer | Rechazo | "La entrega debe programarse como minimo para manana." | consola P15 |
| P16 | Pedido de otro cliente | `julio` consultando `seguimiento.php?id=4` (del Liceo Impulso) | No puede verlo | 302 a `cliente/pedidos.php` con "El pedido solicitado no existe o no te pertenece." | consola P16 |
| — | Seguimiento | `julio`, pedido #1 | Linea de tiempo con los cinco estados y su fecha | Se muestran los cinco pasos con fecha y responsable | captura 20 |

## Operador: produccion, stock y distribucion

| # | Funcionalidad | Datos utilizados | Resultado esperado | Resultado obtenido | Evidencia |
|---|---|---|---|---|---|
| P17 | Registro de produccion | `cocina`, Pollo grillado, 25 unidades, vence en 4 dias | Se crea el lote y sube el stock | "Lote L-1000 registrado: 25 viandas de Pollo grillado con pure." y movimiento de entrada | consola P17, captura 13 |
| P18 | Produccion invalida | Producto 999, cantidad 0, vencimiento de anteayer | Tres rechazos | "El plato seleccionado no existe.", "La cantidad producida debe ser un numero entre 1 y 500.", "La fecha de vencimiento no puede ser anterior a hoy." | consola P18 |
| P19 | Salida mayor al disponible | Lote `L-0998` (5 disponibles), salida de 50 | Rechazo | "La salida (50) supera el stock disponible del lote (5)." | consola P19 |
| P20 | Salida valida | Lote `L-0998`, salida de 2, motivo "Control de calidad" | Se descuenta y queda registrada | Disponible pasa de 5 a 3 y se registra el movimiento | consola P20, captura 14 |
| P21 | Transicion invalida | Pedido #5 de `Pendiente` a `Entregado` | Rechazo | "No se puede pasar el pedido #5 de \"Pendiente\" a \"Entregado\"." | consola P21 |
| P22 | Transiciones validas | Pedido #5: `Preparando` y luego `Listo` | Ambas aceptadas y registradas en el historial | Historial: Pendiente, Preparando, Listo | consola P22, captura 15 |
| P23 | Vehiculo de otra zona | Pedido #5 (Z2) a Moto 1 (Z3) | Rechazo | "El vehiculo no cubre la zona Z2 del pedido." | consola P23 |
| P24 | Capacidad insuficiente | Furgon 2 con capacidad 3, pedido de 5 viandas | Rechazo | "El vehiculo Furgon 2 no tiene capacidad suficiente (0 + 5 supera 3)." | consola P24 |
| P25 | Asignacion y despacho | Pedido #5 a Furgon 2 (Z2, capacidad 60) y paso a `En distribucion` | Aceptado | "Pedido #5 asignado a Furgon 2." y el pedido queda en distribucion | consola P25, captura 16 |
| P26 | Despacho sin vehiculo | Pedido #3 (sin vehiculo) a `En distribucion` | Rechazo | "Antes de despachar el pedido #3 hay que asignarle un vehiculo en Distribucion." | consola P26 |
| P27 | Cancelacion | Pedido #4 a `Cancelado` | El stock vuelve a sus lotes | `L-0983` pasa de 0/Agotado a 5/Disponible y se registra la entrada "Devolucion por cancelacion del pedido #4" | consola P27 |
| P28 | Vehiculo en mantenimiento | Pedido de Z6 a Moto 2 (en mantenimiento) | Rechazo | "El vehiculo Moto 2 esta en mantenimiento." | consola P28 |

## Administrador: parametrizacion y gestion

| # | Funcionalidad | Datos utilizados | Resultado esperado | Resultado obtenido | Evidencia |
|---|---|---|---|---|---|
| P29 | Alta de zona | Z8 · Buceo · "Buceo, Parque Batlle" | Zona creada | "Zona creada correctamente." y fila nueva en `zonas` | consola P29, captura 08 |
| P30 | Zona duplicada | El mismo codigo y nombre que P29 | Rechazo por codigo y por nombre | "Ya existe otra zona con ese codigo." y "Ya existe otra zona con ese nombre." | consola P30 |
| P31 | Edicion de zona | Z8 con barrios ampliados y estado inactivo | Se guardan los cambios | Barrios actualizados y `activa = 0` | consola P31, captura 08 |
| P32 | Matricula duplicada | Nuevo vehiculo con matricula `ABC 1234` | Rechazo | "Ya existe otro vehiculo con esa matricula." | consola P32 |
| P33 | Vehiculo invalido | nombre `X`, matricula `AB`, capacidad `-5`, zona `999`, estado `Volando` | Cinco rechazos | Cinco mensajes, uno por regla | consola P33 |
| P34 | Vehiculo valido | Furgon 3 · `DEF 4321` · 45 · Z4 · Disponible | Vehiculo creado | "Vehiculo creado correctamente." | consola P34, captura 09 |
| P35 | Stock minimo | Quinoa de 12 a 2, resto sin cambios | Se guarda y desaparece la alerta de quinoa | "Stock minimo actualizado para 5 platos." y la alerta baja de 2 a 1 | consola P35, captura 06 |
| P36 | Stock minimo invalido | `-4` para el plato 1 y un plato inexistente | Dos rechazos | "El stock minimo debe ser un numero entre 0 y 999." y "Uno de los platos indicados no existe." | consola P36 |
| P37 | Precios y disponibilidad | Milanesa a 520, quinoa desactivada | Se guardan los cambios | Precio 520.00 y `activo = 0` en quinoa | consola P37, captura 07 |
| P38 | Impacto en el catalogo | Cliente `julio` entra al menu | La quinoa ya no aparece | 0 coincidencias de "Ensalada de quinoa" | consola P38, captura 17 |
| P39 | Alta de operador | Sofia Perez · `sofia` · rol operador | Usuario creado y puede entrar | "Usuario creado correctamente." y login redirigido a `operador/index.php` | consola P39, captura 11 |
| P40 | Alta con rol cliente | El mismo formulario con `rol_id = 3` | Rechazo | "Solo se pueden crear usuarios con rol administrador u operador." | consola P40 |
| P41 | Desactivacion | `sofia` desactivada y luego intento de login | No puede entrar | "Usuario desactivado." y despues "La cuenta esta inactiva." | consola P41 |
| P42 | Autodesactivacion | El admin intenta desactivarse | Rechazo | "No podes desactivar tu propia cuenta." | consola P42 |

## Interfaz

| # | Funcionalidad | Datos utilizados | Resultado esperado | Resultado obtenido | Evidencia |
|---|---|---|---|---|---|
| P44 | Diseno responsivo | Las mismas pantallas a 1440 px y a 500 px de ancho | El contenido se reordena sin scroll horizontal de pagina | En pantalla angosta aparece el menu hamburguesa, las tarjetas se apilan y las tablas anchas se desplazan dentro de su contenedor | capturas 22, 23, 24 |
| P45 | Validacion del lado del cliente | Enviar el login vacio | El navegador marca los campos obligatorios antes de enviar | Bootstrap marca los campos y no se envia el formulario; si se saltea, el servidor igual valida (P02, P11) | captura 02 |

## Resumen

46 casos ejecutados, 46 con el resultado esperado. Las validaciones se probaron
siempre desde el servidor (con `curl`, sin pasar por el navegador), justamente
para verificar que el sistema no depende de la validacion de JavaScript.
