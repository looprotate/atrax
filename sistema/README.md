<img src="assets/img/logo.svg" alt="" width="96" height="96">

# ViandaSegura — Sistema de gestion de viandas

Tercera entrega: primera version funcional del sistema web, con autenticacion,
control de acceso por roles y operaciones reales contra MySQL.

> El sistema se llamaba **Atrax** en las entregas anteriores. El nombre visible
> ahora es **ViandaSegura**; los nombres tecnicos (carpeta `atrax`, base `atrax`,
> `atrax.css`, variables `ATRAX_*`) se mantienen para no romper instalaciones.

Stack: HTML5, CSS3, JavaScript, Bootstrap 5, PHP 8 y MySQL/MariaDB.

## Puesta en marcha

1. Copiar la carpeta `atrax` dentro de `htdocs` (XAMPP) y arrancar Apache y MySQL.
2. Importar `sistema/bd/atrax.sql` desde phpMyAdmin o desde la consola:

   ```
   mysql -u root -p < sistema/bd/atrax.sql
   ```

   El script crea la base `atrax`, todas las tablas y los datos de prueba.
3. Si el usuario o la contrasena de MySQL no son los de XAMPP por defecto
   (`root` sin contrasena), editar `sistema/includes/config.php`.
4. Entrar a `http://localhost/atrax/sistema/`.

Las direcciones que no existen muestran la pantalla 404 gracias a
`sistema/.htaccess` (requiere `mod_rewrite`, activo por defecto en XAMPP).

### Modo mantenimiento

Para avisar a los usuarios que el sistema no esta disponible (pantalla 503 con
reintento automatico) alcanza con crear un archivo vacio llamado
`sistema/MANTENIMIENTO` o definir la variable de entorno `ATRAX_MANTENIMIENTO=1`.
Al borrar el archivo el sistema vuelve a funcionar. Si la base de datos no
responde se muestra la misma pantalla de "servidores caidos" automaticamente.

## Cuentas de prueba

| Rol | Usuario | Contrasena |
|---|---|---|
| Administrador | `admin` | `Admin123` |
| Administrador | `gerente` | `Gerente123` |
| Operador | `cocina` | `Operador123` |
| Operador | `cocina2` | `Operador123` |
| Cliente | `julio` | `Cliente123` |
| Cliente (empresa) | `estudiosur` | `Cliente123` |
| Cliente | `lucia` | `Cliente123` |
| Cliente (institucion) | `liceoimpulso` | `Cliente123` |

Tambien se puede crear una cuenta nueva de cliente desde `registro.php`.

## Estructura del proyecto

```
atrax/
  wireframes/            disenos de la segunda entrega (16 pantallas, ver su README)
  sistema/               sistema funcional de la tercera entrega
    index.php            entrada: redirige al panel segun el rol
    404.php              pantalla de pagina no encontrada
    .htaccess            envia las direcciones inexistentes a 404.php
    login.php            inicio de sesion
    registro.php         alta de clientes
    logout.php           cierre de sesion
    includes/            infraestructura comun
      config.php         datos de conexion y constantes
      conexion.php       PDO + funciones de consulta preparada
      funciones.php      escape, validaciones, mensajes, token CSRF
      auth.php           sesion, roles y guardas de acceso
      cabecera.php       encabezado, navegacion y mensajes
      pie.php            cierre de pagina y scripts
      denegado.php       pantalla 403
      error.php          pantallas 404, 500 y 503, mantenimiento y errores no controlados
      pantalla_error.php plantilla de las pantallas de error
    modelo/              acceso a datos (una funcion por consulta)
      usuarios.php       usuarios, roles y clientes
      zonas.php          zonas y vehiculos
      catalogo.php       productos y planes
      stock.php          lotes, movimientos y FIFO
      pedidos.php        pedidos, detalle, estados y reportes
    acciones/            logica de escritura (validan, guardan y redirigen)
      acceso.php  registro.php  zonas.php  vehiculos.php
      productos.php  planes.php  stock_minimo.php  usuarios.php
      produccion.php  stock.php  pedidos.php  distribucion.php
      pedido_cliente.php
    admin/               pantallas del administrador
    operador/            pantallas del operador
    cliente/             pantallas del cliente
    assets/css/atrax.css estilos propios sobre Bootstrap
    assets/img/          logo completo (logo.svg), usado tambien como favicon
    assets/js/validaciones.js  validaciones y ayudas del lado del cliente
    assets/js/avisos.js  pop ups: avisos flotantes y ventanas de confirmacion
    assets/js/esqueletos.js  esqueletos de carga al navegar entre pantallas
    bd/atrax.sql         estructura + datos de prueba
    docs/                documentacion, pruebas y capturas
```

La separacion es: las paginas (`admin/`, `operador/`, `cliente/`) solo arman la
presentacion y hacen consultas de lectura a traves de `modelo/`; toda la escritura
pasa por `acciones/`, que valida en el servidor y vuelve a la pagina con el
resultado. Ningun archivo de presentacion escribe SQL.

## Documentacion

- [Guia de pantallas: que hace cada una y como se usa](docs/pantallas.md)
- [Requerimientos y roles actualizados](docs/requerimientos.md)
- [Base de datos](docs/base-de-datos.md)
- [Flujos de navegacion](docs/flujo-navegacion.md)
- [Pruebas](docs/pruebas.md) · [salida de consola](docs/pruebas-consola.txt) · [capturas](docs/capturas)

## Funcionalidades implementadas

**Compartidas**

- Login con usuario o correo, validacion contra la base, identificacion del rol,
  creacion de sesion y redireccion al panel correspondiente.
- Registro autonomo de clientes con validacion de datos y control de duplicados.
- Cierre de sesion con destruccion de la sesion y de su cookie.
- Bloqueo de pantallas no autorizadas (403) y de acciones no autorizadas.
- Esqueletos de carga: al pasar de una pantalla a otra o enviar un formulario, el
  contenido se reemplaza por un esqueleto (placeholders de Bootstrap 5) con la forma
  de la pantalla de destino (panel, tabla, tarjetas o formulario), el boton muestra
  "Procesando..." y una barra fina indica el progreso, en lugar de quedar congelado.
- Pantalla 404 para direcciones inexistentes y pantalla 503 cuando los servidores
  estan caidos (la base no responde) o el sistema esta en mantenimiento, con
  reintento automatico; los errores inesperados muestran una pantalla 500 y quedan
  registrados en el log de PHP.
- Pop ups: los mensajes de exito y error aparecen como avisos flotantes (bienvenida
  al iniciar sesion, errores de login, confirmaciones de cada accion) y las acciones
  delicadas piden confirmacion en una ventana (cerrar sesion, desactivar usuarios,
  quitar pedidos de un vehiculo, cancelar pedidos y descartar lotes).

**Administrador**

- Dashboard con pedidos del dia, viandas a producir, viandas en reparto y alertas.
- Consulta de pedidos con filtros por estado y zona, y detalle de cada pedido.
- Configuracion del stock minimo por plato (alta el disparador de las alertas).
- Menus y precios: precio y disponibilidad de cada plato, alta de platos nuevos
  y cantidad de viandas de cada modalidad.
- Zonas: alta, edicion y activacion/desactivacion.
- Vehiculos: alta, edicion, capacidad y estado.
- Estadisticas: pedidos por zona, demanda por plato, pedidos por estado y evolucion mensual.
- Usuarios: listado, alta de operadores y administradores, activacion y desactivacion.

**Operador**

- Panel del dia con lo pedido, lo producido, lo listo para despacho y las alertas.
- Produccion: registro de lotes con fecha de vencimiento (genera el movimiento de entrada).
- Stock por lotes: lotes ordenados por vencimiento (criterio FIFO), alertas de stock bajo, movimientos
  de salida, ajuste y descarte, e historial de movimientos.
- Pedidos: cambio de estado respetando las transiciones validas.
- Distribucion: asignacion de pedidos a vehiculos validando zona, estado y capacidad.

**Cliente**

- Menu con las modalidades y los platos disponibles segun el stock real.
- Armado de pedido: eleccion de modalidad, fecha de entrega y cantidades por plato,
  con un resumen en ventana emergente (platos, subtotales y total) antes de
  confirmar y descuento del stock por FIFO al confirmar.
- Mis pedidos: historial con estado y total.
- Seguimiento: linea de tiempo del pedido con fecha y responsable de cada cambio.

## Funcionalidades pendientes

- Notificaciones al cliente (RF15): hoy el cambio de estado se ve al entrar al
  seguimiento, pero no se envia aviso por correo ni push.
- Registro de auditoria general (RF16): se guarda el historial de estados de los
  pedidos y todos los movimientos de stock, pero no hay bitacora de cambios sobre
  usuarios, zonas, vehiculos y precios.
- Mapa de zonas y mapa de seguimiento del reparto: en los disenos aparecen como
  mapa; en esta version se resuelven con listados y una linea de tiempo.
- Recuperacion de contrasena y edicion del perfil del cliente.
- Exportacion de reportes a PDF o Excel desde Estadisticas.
- Asignacion de pedidos a vehiculos arrastrando (drag and drop): se resolvio con
  un selector, que hace la misma operacion con validacion del lado del servidor.
