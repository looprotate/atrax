# Informe de defectos

Ingenieria de Software, tarea 7. Reune los defectos encontrados al ejecutar las pruebas de
[caja negra](pruebas-caja-negra.md) y de [caja blanca](pruebas-caja-blanca.md), con su
severidad, la forma de reproducirlos, la causa probable y una correccion sugerida, mas las
metricas y las conclusiones de las pruebas.

**Los 13 defectos se corrigieron** y se volvieron a correr las tres pruebas: los 373 casos pasan. Cada defecto
tiene la correccion que se sugirio al encontrarlo y, debajo, la que se aplico y como se verifico. Los casos y los
resultados de la primera corrida (antes de corregir) estan en los documentos de
[caja negra](pruebas-caja-negra.md) y [caja blanca](pruebas-caja-blanca.md), junto con los de la segunda.

## Escala de severidad

| Severidad | Criterio |
|---|---|
| Critica | Pierde o corrompe datos de forma silenciosa en el uso normal, o permite abusar del sistema sin iniciar sesion |
| Alta | Deja datos inconsistentes sin avisar, o impide un flujo central del negocio |
| Media | Un flujo falla con datos plausibles, o se expone informacion interna |
| Baja | Validacion floja, mejora de seguridad o molestia que no rompe el negocio |

No se encontro ningun defecto critico.

## Resumen

| Defecto | Titulo | Severidad | Tipo | Se encontro con | Estado |
|---|---|---|---|---|---|
| DEF-12 | Dos pedidos simultaneos pierden descuentos de stock | Alta | Integridad de datos | Caja blanca | Corregido |
| DEF-02 | Un movimiento de stock puede dejar el lote modificado sin dejar movimiento | Alta | Integridad de datos | Caja negra y blanca | Corregido |
| DEF-01 | No se valida el largo maximo de los textos: error 500 | Media | Validacion de entradas | Caja negra | Corregido |
| DEF-03 | No se puede cancelar un pedido si su lote fue repuesto con un ajuste | Media | Integridad de datos | Caja negra y blanca | Corregido |
| DEF-10 | El script de la base y los documentos se descargan desde el navegador | Media | Seguridad y configuracion | Caja negra | Corregido |
| DEF-04 | Reasignar un pedido al mismo vehiculo cuenta su carga dos veces | Baja | Logica de negocio | Caja negra | Corregido |
| DEF-05 | El registro acepta una zona desactivada | Baja | Validacion de entradas | Caja negra | Corregido |
| DEF-06 | Un telefono formado solo por guiones es valido | Baja | Validacion de entradas | Caja negra y blanca | Corregido |
| DEF-07 | Una contrasena de ocho espacios es valida | Baja | Validacion de entradas | Caja negra | Corregido |
| DEF-08 | Un simple GET a `logout.php` cierra la sesion | Baja | Seguridad y configuracion | Caja negra | Corregido |
| DEF-09 | No hay limite de intentos fallidos de ingreso | Baja | Seguridad y configuracion | Caja negra | Corregido |
| DEF-11 | Faltan las cabeceras de seguridad HTTP | Baja | Seguridad y configuracion | Caja negra | Corregido |
| DEF-13 | El mensaje interno de una excepcion se muestra al cliente | Baja | Seguridad y configuracion | Revision de codigo | Corregido |

## Metricas

| Medida | Primera corrida (antes de corregir) | Segunda corrida (despues de corregir) |
|---|---|---|
| Casos de caja negra | 274 (255 pasan, 19 fallan) | 301 (301 pasan) |
| Casos de caja blanca | 46 (43 pasan, 3 fallan) | 69 (69 pasan) |
| Corridas de concurrencia | 3 (1 de control y 2 con procesos en paralelo; 2 fallan) | 3 (ninguna falla) |
| Casos totales | 320 | 373 |
| Casos que fallan | 22 (6,9 %) | 0 |
| Defectos abiertos | 13 | 0 |
| Casos que fallan por defecto | 1,7 de promedio (el maximo es DEF-01, con 8) | 0 |
| Lineas de codigo revisadas | 1.901 sin contar lineas en blanco (`includes/`, `modelo/` y `acciones/`) | (no se volvio a medir) |
| Densidad de defectos | 6,8 por cada 1.000 lineas | 0 abiertos |

Los 50 casos nuevos de la segunda corrida (27 de caja negra y 23 de caja blanca) cubren el comportamiento que
agregaron las correcciones (limite de intentos, cierre de sesion por `POST`, carpetas bloqueadas, devolucion parcial y
reversion de transacciones) y la consulta asincrona de pedidos, que se agrego despues de los defectos.

Por severidad:

| Severidad | Defectos |
|---|---|
| Critica | 0 |
| Alta | 2 |
| Media | 3 |
| Baja | 8 |

Por tipo:

| Tipo | Defectos | Detalle |
|---|---|---|
| Validacion de entradas | 4 | DEF-01, DEF-05, DEF-06, DEF-07 |
| Integridad de datos | 3 | DEF-02, DEF-03, DEF-12 |
| Logica de negocio | 1 | DEF-04 |
| Seguridad y configuracion | 5 | DEF-08, DEF-09, DEF-10, DEF-11, DEF-13 |

Por forma de deteccion:

| Forma | Defectos | Detalle |
|---|---|---|
| Solo caja negra | 8 | DEF-01, DEF-04, DEF-05, DEF-07, DEF-08, DEF-09, DEF-10, DEF-11 |
| Caja negra y caja blanca | 3 | DEF-02, DEF-03, DEF-06 |
| Solo caja blanca o revision de codigo | 2 | DEF-12, DEF-13 |

## Detalle de los defectos

### DEF-12: dos pedidos simultaneos pierden descuentos de stock

- **Severidad**: alta. **Tipo**: integridad de datos. **Requerimientos**: RF8, RNF14.
- **Donde**: `descontar_fifo()` en `modelo/stock.php`, llamada desde `crear_pedido()`.
- **Como se encontro**: lectura del codigo y [prueba de concurrencia](pruebas-caja-blanca-concurrencia.php).
- **Como reproducirlo**:

  ```
  ATRAX_BD_HOST=127.0.0.1 ATRAX_BD_PUERTO=3306 ATRAX_SQL="mysql -u root atrax -e" \
    php docs/pruebas-caja-blanca-concurrencia.php 8 25
  ```

- **Esperado**: tras 200 pedidos de una vianda sobre un lote de 300, el lote queda con 100.
- **Obtenido**: el lote queda con 271. Con dos procesos se pierden 24 de 50 descuentos; con
  un solo proceso no se pierde ninguno. Los 200 pedidos y sus 200 movimientos quedan
  registrados, de modo que el lote y el libro de movimientos dejan de coincidir.
- **Causa probable**: la funcion lee el `disponible` con un `SELECT` sin bloqueo y escribe
  un valor calculado (`UPDATE lotes SET disponible = ?`). Dos transacciones que leen el
  mismo valor escriben el mismo resultado y una pisa a la otra.
- **Consecuencia**: el control de stock de `pedido_cliente.php` (lineas 66 a 71) usa ese
  numero inflado y sigue aceptando pedidos de viandas que ya no existen.
- **Correccion sugerida**: leer los lotes con `SELECT ... FOR UPDATE` dentro de la
  transaccion, o descontar con `UPDATE lotes SET disponible = disponible - ? WHERE id = ?
  AND disponible >= ?` y comprobar las filas afectadas.
- **Correccion aplicada**: `lotes_fifo()` lee los lotes con `SELECT ... FOR UPDATE`, asi que dentro de la
  transaccion de `crear_pedido()` un pedido espera a que el otro confirme antes de leer el disponible. Como
  dos pedidos con los mismos platos en distinto orden podrian bloquearse entre si, `crear_pedido()` ordena las
  lineas por plato antes de descontar.
- **Verificacion**: la misma prueba de concurrencia con 8 procesos deja el lote en 100 (antes 271) y con 2 en
  100 (antes 124); no hubo pedidos con error.

### DEF-02: un movimiento de stock puede dejar el lote modificado sin dejar movimiento

- **Severidad**: alta. **Tipo**: integridad de datos. **Requerimientos**: RNF11, RNF12.
- **Donde**: `ajustar_lote()` en `modelo/stock.php` y `acciones/stock.php`.
- **Casos**: CN-E15, CB-42.
- **Como reproducirlo**: como operador, en Stock, registrar una salida de 1 sobre un lote
  con un motivo de 151 caracteres.
- **Esperado**: el aviso "El motivo no puede superar 150 caracteres." y el lote sin cambios.
- **Obtenido**: error 500. El lote pasa de `8/8` a `7/8` y no queda ningun movimiento de
  salida en `movimientos_stock`.
- **Causa probable**: la funcion hace un `UPDATE lotes` y despues un `INSERT INTO
  movimientos_stock` sin transaccion, y el motivo no se valida contra su columna de 150
  caracteres. Cuando el `INSERT` falla, el `UPDATE` ya esta grabado. `descartar_lote()` tiene
  la misma estructura.
- **Correccion sugerida**: abrir una transaccion en `ajustar_lote()` y `descartar_lote()`
  como ya hace `cambiar_estado()`, y validar el largo del motivo en la accion.
- **Correccion aplicada**: nueva funcion `en_transaccion()` en `includes/conexion.php` (abre la transaccion, la
  confirma o la revierte y relanza el error). `ajustar_lote()` y `descartar_lote()` la usan, igual que ahora
  `crear_pedido()` y `cambiar_estado()`. Ademas `acciones/stock.php` valida que el motivo no supere 150 caracteres.
- **Verificacion**: CN-E15 da el aviso "El motivo no puede superar 150 caracteres." con el lote en 8/8 y sin
  movimientos; CB-42 y CB-48 comprueban que si el `INSERT` falla el lote queda como estaba.

### DEF-01: no se valida el largo maximo de los textos

- **Severidad**: media. **Tipo**: validacion de entradas. **Requerimiento**: RNF12.
- **Donde**: `acciones/zonas.php`, `vehiculos.php`, `productos.php`, `registro.php`.
- **Casos**: CN-H12, CN-H15, CN-I09, CN-J20, CN-J23, CN-B05, CN-B24, CN-K23.
- **Como reproducirlo**: como administrador, crear una zona con un nombre de 61 caracteres.
  Lo mismo con barrios de 151, nombre de vehiculo de 41, nombre de plato de 101 o
  descripcion de 256.
- **Esperado**: un aviso de validacion como el que ya existe para el minimo, por ejemplo
  "El nombre de la zona no puede superar 60 caracteres.".
- **Obtenido**: la pantalla "Algo salio mal" (500) con el detalle de la base ("Data too
  long"). En el registro de clientes, un nombre de 101 o una direccion de 201 caracteres da el
  aviso generico "No se pudo crear la cuenta. Intenta nuevamente.", que no dice que
  corregir.
- **Causa probable**: las acciones validan solo el minimo (`mb_strlen(...) < n`); el maximo
  lo termina poniendo la columna.
- **Correccion sugerida**: agregar `mb_strlen(...) > n` a cada validacion con los largos de
  [`base-de-datos.md`](base-de-datos.md).
- **Correccion aplicada**: cada validacion de minimo ganó su maximo con el patron `elseif (mb_strlen(...) > n)`:
  zonas (nombre 60, barrios 150), vehiculos (nombre 40), platos (nombre 100, descripcion 255), registro
  (nombre 100, direccion 200) y alta de usuarios (nombre 100). `es_email()` rechaza mas de 150 caracteres, el
  largo de la columna.
- **Verificacion**: CN-B05, CN-B24, CN-H12, CN-H15, CN-I09, CN-J20, CN-J23 y CN-K23 pasan con el aviso esperado;
  CB-54 y CB-55 prueban el borde de 150 y 151 caracteres del correo.

### DEF-03: no se puede cancelar un pedido si su lote fue repuesto con un ajuste

- **Severidad**: media. **Tipo**: integridad de datos. **Requerimiento**: regla de negocio 6.
- **Donde**: `devolver_stock()` en `modelo/pedidos.php`.
- **Casos**: CN-F44, CB-33 (y CB-37, que muestra que no se pierden datos).
- **Como reproducirlo**: con los datos de ejemplo, el pedido #5 (Pendiente) tomo 5 viandas
  del lote L-0994, que quedo en 25/30. Como operador, hacer un ajuste de +5 sobre ese lote
  (se permite: llega a 30/30) y despues cancelar el pedido #5.
- **Esperado**: el pedido pasa a `Cancelado` y el stock se devuelve, o se avisa con un
  mensaje claro por que no se puede.
- **Obtenido**: error 500 (`ck_lotes_disponible`). El pedido sigue `Pendiente` y los datos
  quedan consistentes porque `cambiar_estado()` revierte la transaccion, pero el operador no
  puede cancelarlo.
- **Causa probable**: `devolver_stock()` suma la cantidad devuelta sin tope, y el
  `CHECK` de la base impide que `disponible` supere la cantidad original del lote.
- **Correccion sugerida**: limitar la devolucion a lo que cabe en el lote y avisar del
  resto, o registrar una reposicion en un lote nuevo.
- **Correccion aplicada**: `devolver_stock()` devuelve como maximo lo que cabe en el lote (`cantidad - disponible`,
  leido con `FOR UPDATE`). Si el lote ya esta lleno no registra ninguna entrada, y si solo cabe una parte la
  registra con la nota "(parcial: el lote ya habia sido repuesto)". Es la primera de las dos opciones sugeridas: las
  viandas que el ajuste del operador ya repuso no se cuentan dos veces.
- **Verificacion**: CN-F44 cancela el pedido #5 y deja el lote L-0994 en 30/30; CB-33, CB-37 y CB-47 cubren el lote
  lleno, la cancelacion completa y la devolucion parcial.

### DEF-10: el script de la base y los documentos se descargan desde el navegador

- **Severidad**: media. **Tipo**: seguridad y configuracion. **Requerimiento**: RNF1, RNF15.
- **Donde**: `.htaccess` y la estructura de carpetas.
- **Casos**: CN-K16, CN-K18.
- **Como reproducirlo**: abrir `/bd/atrax.sql`, `/README.md` o `/docs/pruebas.sh` en el
  navegador, sin iniciar sesion.
- **Esperado**: acceso denegado (403) o no encontrado.
- **Obtenido**: 200 con el contenido. `atrax.sql` incluye el esquema y las cuentas de
  ejemplo con sus hashes de contrasena; los documentos listan los usuarios de prueba.
- **Causa probable**: el `.htaccess` solo reescribe lo que no existe a la pantalla 404; no
  bloquea ningun archivo existente. Se verifico primero con el servidor integrado de PHP y
  despues con Apache 2.4.67, que tambien respondio 200 para `bd/atrax.sql`, `docs/pruebas.sh` y `README.md`.
- **Correccion sugerida**: reglas `Require all denied` para `bd/`, `docs/` y las
  extensiones `.sql`, `.md`, `.sh` y `.txt`, o sacar esas carpetas de la raiz publica.
- **Correccion aplicada**: el `.htaccess` responde 403 para las carpetas `bd/`, `docs/`, `includes/` y `modelo/` y
  para cualquier archivo `.sql`, `.md`, `.sh` o `.txt`. Ademas los tres programas PHP de `docs/` terminan con 404
  si no se ejecutan desde la linea de comandos, porque escribirian en la base si alguien los abriera en el navegador.
- **Verificacion**: contra Apache 2.4.67, `bd/atrax.sql`, `docs/pruebas.sh` y `README.md` pasaron de 200 a 403
  (CN-K16, CN-K18 y CN-K25); CN-K24 comprueba el 404 de los programas de prueba. Los recursos de la aplicacion
  (`assets/`) siguen respondiendo 200. Las reglas solo valen en Apache con `AllowOverride All`: en otro servidor hay que
  repetirlas.

### DEF-04: reasignar un pedido al mismo vehiculo cuenta su carga dos veces

- **Severidad**: baja. **Tipo**: logica de negocio. **Requerimiento**: RF11, regla 8.
- **Donde**: `acciones/distribucion.php` (linea 53) y `vehiculo_por_id()`.
- **Caso**: CN-G10.
- **Como reproducirlo**: dejar a Moto 1 con capacidad 5, asignarle el pedido #3 (5
  viandas, `Listo`) y volver a asignarlo al mismo vehiculo.
- **Esperado**: se acepta, porque el pedido ya esta en ese vehiculo.
- **Obtenido**: "El vehiculo Moto 1 no tiene capacidad suficiente (5 + 5 supera 5)."
- **Causa probable**: la `carga` del vehiculo ya incluye al pedido asignado y la accion le
  suma sus viandas otra vez. Solo se nota cuando el pedido ocupa mas de la mitad de la
  capacidad; quitar la asignacion y asignar de nuevo lo resuelve.
- **Correccion sugerida**: no sumar las viandas del pedido si `$pedido['vehiculo_id']` ya es
  el vehiculo elegido.
- **Correccion aplicada**: `acciones/distribucion.php` no suma las viandas del pedido cuando ya esta asignado al
  vehiculo elegido, y el mensaje de capacidad muestra lo que realmente se sumo.
- **Verificacion**: CN-G10 acepta la reasignacion de #3 a Moto 1.

### DEF-05: el registro acepta una zona desactivada

- **Severidad**: baja. **Tipo**: validacion de entradas. **Requerimiento**: RF3, RF12.
- **Donde**: `acciones/registro.php` (lineas 52 a 56).
- **Caso**: CN-B28.
- **Como reproducirlo**: desactivar una zona y enviar el formulario de registro con el
  identificador de esa zona (por ejemplo con el formulario abierto desde antes).
- **Esperado**: "Selecciona una zona de entrega valida."
- **Obtenido**: la cuenta se crea con una zona desactivada. El formulario solo ofrece zonas
  activas, pero el servidor no lo verifica.
- **Correccion sugerida**: exigir `$zona['activa'] === 1` ademas de que exista.
- **Correccion aplicada**: `acciones/registro.php` exige que la zona exista y este activa.
- **Verificacion**: CN-B28.

### DEF-06: un telefono formado solo por guiones es valido

- **Severidad**: baja. **Tipo**: validacion de entradas. **Requerimiento**: RF3.
- **Donde**: `es_telefono()` en `includes/funciones.php`.
- **Casos**: CN-B19, CB-12.
- **Como reproducirlo**: registrarse con el telefono `--------` (tambien `++++++++`).
- **Esperado**: "El telefono no tiene un formato valido."
- **Obtenido**: el registro se completa.
- **Causa probable**: la expresion regular `^[0-9 +()-]{8,20}$` admite cualquier mezcla de
  los caracteres permitidos, sin exigir digitos.
- **Correccion sugerida**: ademas de la expresion, exigir al menos 8 digitos.
- **Correccion aplicada**: `es_telefono()` exige, ademas del formato, al menos 8 digitos.
- **Verificacion**: CN-B19 y CB-12 rechazan `--------`; CB-52 acepta 8 digitos con signos y CB-53 rechaza 7.

### DEF-07: una contrasena de ocho espacios es valida

- **Severidad**: baja. **Tipo**: validacion de entradas. **Requerimiento**: RNF1.
- **Donde**: `acciones/registro.php` (linea 58).
- **Caso**: CN-B31.
- **Como reproducirlo**: registrarse con una contrasena de ocho espacios.
- **Esperado**: rechazo.
- **Obtenido**: la cuenta se crea. La regla escrita ("al menos 8 caracteres") se cumple
  literalmente, pero la contrasena no ofrece proteccion.
- **Correccion sugerida**: exigir que la contrasena no este formada solo por espacios.
- **Correccion aplicada**: `registro.php` y la alta de usuarios de `usuarios.php` (que tenia la misma falla)
  rechazan una contrasena formada solo por espacios con el aviso "La contrasena no puede estar formada solo por
  espacios.". La regla de 8 caracteres se mantiene.
- **Verificacion**: CN-B31.

### DEF-08: un simple GET a `logout.php` cierra la sesion

- **Severidad**: baja. **Tipo**: seguridad y configuracion. **Requerimiento**: RNF1.
- **Donde**: `logout.php` y el boton "Salir" de `includes/cabecera.php`.
- **Caso**: CN-A21.
- **Como reproducirlo**: con la sesion abierta, cargar `/logout.php` (por ejemplo desde una
  imagen en otra pagina).
- **Esperado**: el cierre se hace con un `POST` y el token CSRF, como el resto de las
  acciones.
- **Obtenido**: la sesion se cierra con cualquier `GET`.
- **Correccion sugerida**: reemplazar el enlace por un formulario `POST` con token.
- **Correccion aplicada**: `logout.php` solo acepta `POST` con el token de la sesion (405 con `GET`, 400 sin
  token). El boton "Salir" de `includes/cabecera.php` pasó a ser un formulario `POST` que conserva la ventana de
  confirmacion. El caso P06 de `docs/pruebas.sh` se actualizo.
- **Verificacion**: CN-A21 (el `GET` responde 405 y la sesion sigue abierta), CN-A22 (sin token, 400) y CN-A23
  (con token, la sesion se cierra).

### DEF-09: no hay limite de intentos fallidos de ingreso

- **Severidad**: baja. **Tipo**: seguridad y configuracion. **Requerimiento**: RNF1 (no hay
  un requerimiento escrito para esto; es una mejora).
- **Donde**: `acciones/acceso.php`.
- **Caso**: CN-A18.
- **Como reproducirlo**: probar 30 contrasenas incorrectas seguidas para `gerente` y luego
  la correcta.
- **Esperado**: demora o bloqueo temporal despues de varios fallos.
- **Obtenido**: la cuenta sigue aceptando intentos y entra con la contrasena correcta.
- **Correccion sugerida**: contar los fallos por usuario y por direccion y demorar o bloquear
  unos minutos.
- **Correccion aplicada**: nueva tabla `intentos_acceso` y `modelo/accesos.php`. Cinco ingresos fallidos del mismo
  usuario en 10 minutos, o 20 desde la misma direccion, bloquean nuevos intentos durante la ventana, incluso con la
  contrasena correcta, con el aviso "Demasiados intentos fallidos. Espera 10 minutos antes de volver a intentar.". Un
  ingreso correcto borra los fallos del usuario y los registros de mas de un dia se limpian al guardar uno nuevo. Los
  limites estan en `includes/config.php`.
- **Verificacion**: CN-A18 (bloqueo), CN-A24 (otra cuenta sigue entrando), CN-A25 (el bloqueo vence), CN-A26 (un ingreso
  correcto borra los fallos) y CN-A27 (limite por direccion); CB-57 a CB-64 prueban las funciones.
- **Limites conocidos**: el bloqueo es por usuario, asi que alguien puede dejar sin ingresar 10 minutos a una cuenta
  ajena mandando cinco fallos; y detras de un proxy compartido todas las personas comparten la direccion.

### DEF-11: faltan las cabeceras de seguridad HTTP

- **Severidad**: baja. **Tipo**: seguridad y configuracion. **Requerimiento**: RNF1.
- **Donde**: respuestas de todas las paginas.
- **Caso**: CN-K19.
- **Como reproducirlo**: pedir `login.php` y mirar las cabeceras.
- **Esperado**: `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy` y
  `Referrer-Policy`.
- **Obtenido**: ninguna esta presente, asi que las paginas se pueden incrustar en otro
  sitio (clickjacking).
- **Correccion sugerida**: agregarlas desde `.htaccess` o desde `includes/auth.php`.
- **Correccion aplicada**: `enviar_cabeceras_seguridad()` (en `includes/funciones.php`, llamada desde
  `includes/auth.php`) envia `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: same-origin` y una `Content-Security-Policy` que solo deja cargar scripts de este sitio y de
  `cdn.jsdelivr.net` (Bootstrap), no permite incrustar las paginas y limita los formularios a este sitio. Los
  estilos admiten `'unsafe-inline'` porque las barras de avance usan `style="width: n%"`. El script que estaba dentro de
  la pantalla de error paso a `assets/js/pantalla_error.js` para no tener que permitir scripts en linea.
- **Verificacion**: CN-K19 revisa las cuatro cabeceras en cuatro paginas (publicas, interna y de error).

### DEF-13: el mensaje interno de una excepcion se muestra al cliente

- **Severidad**: baja. **Tipo**: seguridad y configuracion. **Requerimiento**: RNF15.
- **Donde**: `acciones/pedido_cliente.php`, lineas 103 a 105.
- **Como se encontro**: revision de codigo. No se logro provocar desde HTTP.
- **Descripcion**: el `catch` de `crear_pedido()` muestra `$error->getMessage()`. Para la
  excepcion de negocio ("Stock insuficiente...") esta bien, pero un error de la base (un
  bloqueo mutuo, por ejemplo) mostraria al cliente el texto interno de `SQLSTATE`.
- **Correccion sugerida**: mostrar el mensaje solo si la excepcion es la de negocio; para el
  resto, un aviso generico y el detalle al registro de errores.
- **Correccion aplicada**: nueva excepcion `StockInsuficiente` (en `modelo/stock.php`), que lanza `descontar_fifo()`.
  `acciones/pedido_cliente.php` muestra su mensaje al cliente; ante cualquier otra falla muestra "No se pudo registrar
  el pedido. Intenta nuevamente en unos minutos." y deja el detalle en el registro de errores con `error_log()`.
- **Verificacion**: CB-49 comprueba que la falta de stock lanza `StockInsuficiente`. La rama generica sigue sin poder
  provocarse por HTTP, igual que antes; se verifico leyendo el codigo.

## Conclusiones

- **Lo que funciona**: la logica principal respondio como pide la especificacion desde la primera corrida. Las
  36 transiciones de estado, el armado de pedidos con sus limites, el orden FIFO, la
  devolucion de stock y el control de acceso por rol pasaron todos sus casos, y no se
  encontro inyeccion SQL ni XSS en los puntos probados.
- **Lo mas grave era la concurrencia** (DEF-12). Ninguna prueba secuencial lo muestra y solo
  aparece leyendo el codigo y probando con procesos en paralelo. El sistema funcionaba bien
  con un usuario a la vez y fallaba en silencio con varios. Ya no pierde descuentos en la misma prueba
  (lote en 100 de 100 con ocho procesos).
- **La segunda familia de problemas eran las escrituras sin transaccion o sin validacion
  previa** (DEF-02, DEF-03, DEF-01): coincidian en que la base terminaba decidiendo lo que el
  codigo no valido. Se corrigio con una validacion de largo maxima y con una funcion unica de
  transacciones, `en_transaccion()`, que ahora usan las cuatro escrituras de varios pasos.
- **Los dos metodos se necesitan**: la caja negra encontro 11 de los 13 defectos, incluidos
  los de configuracion que el codigo no muestra (DEF-10, DEF-11); la caja blanca encontro
  DEF-12 y DEF-13, y completo cuatro casos de caja negra que faltaban.
- **Corregir tambien costo**: cada correccion de validacion agrego una decision a un script de `acciones/`
  y los diez que pasan de V(G) = 10 siguen igual de concentrados. Conviene separar esas validaciones en
  funciones si el sistema crece.

## Riesgos que quedan

No son defectos abiertos: ninguna prueba los muestra, pero conviene tenerlos presentes.

- `ajustar_lote()` recibe el `disponible` que la accion leyo antes, sin bloqueo. Dos operadores que ajusten el mismo lote
  a la vez podrian pisarse, con el mismo patron que DEF-12. Es un caso poco frecuente y no se probo ni se corrigio.
- La prueba de concurrencia solo muestra que en esas corridas no se perdio nada; la garantia viene del bloqueo de filas
  de InnoDB, no de la prueba.
- El limite de intentos permite que alguien bloquee la cuenta de otra persona durante 10 minutos, y detras de un proxy
  compartido todos usan la misma direccion.
- La politica de contenido admite estilos en linea (por las barras de avance) y las reglas del `.htaccess` solo valen en
  Apache con `AllowOverride All`.

## Verificacion de las correcciones

Los tres programas se corrieron de nuevo sobre el codigo corregido:

| Programa | Resultado |
|---|---|
| `docs/pruebas-caja-negra.sh` (con Apache para las reglas del `.htaccess`) | 301 casos, 301 pasan |
| `docs/pruebas-caja-blanca.php` | 69 casos, 69 pasan |
| `docs/pruebas-caja-blanca-concurrencia.php` (1, 2 y 8 procesos) | lote en 100 de 100 en las tres |

Cada defecto tiene al menos un caso que fallaba y ahora pasa; se listan en la seccion "Segunda corrida" de cada
documento de pruebas. La excepcion es DEF-13, que se encontro leyendo el codigo: su correccion se prueba con CB-49. Ademas, `docs/pruebas.sh` (casos basicos P01 a P45) se actualizo en P06 porque el cierre de sesion
ahora es un `POST`.
