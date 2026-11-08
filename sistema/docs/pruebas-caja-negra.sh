#!/bin/bash
# Pruebas de caja negra de ViandaSegura: solo usan HTTP (curl) y miran el resultado visible,
# sin leer el codigo. Para volver a correrlas:
#
#   ATRAX_URL=http://localhost/atrax/sistema ATRAX_SQL="mysql -u root atrax -e" \
#     ATRAX_PHP=php bash docs/pruebas-caja-negra.sh
#
# ATRAX_SQL se usa solo para preparar datos (reimportar la base, cambiar un estado) y para
# comprobar un efecto en la base; nunca para decidir si un caso pasa por lo que dice el codigo.
# ATRAX_URL_MANTENIMIENTO (opcional) apunta a una copia con ATRAX_MANTENIMIENTO=1.
# ATRAX_URL_APACHE (opcional) apunta a un Apache que sirva esta carpeta con AllowOverride All:
# el servidor integrado de PHP ignora el .htaccess, y CN-K16, CN-K18 y CN-K25 dependen de el.

BASE=${ATRAX_URL:-http://localhost/atrax/sistema}
ESTATICO=${ATRAX_URL_APACHE:-$BASE}
SQL=${ATRAX_SQL:-"mysql -u root atrax -e"}
PHP=${ATRAX_PHP:-php}
RAIZ=$(cd "$(dirname "$0")/.." && pwd)
TMP=$(mktemp -d)
PASA=0
FALLA=0

dia() { "$PHP" -r 'echo date("Y-m-d", strtotime($argv[1] . " days"));' -- "$1"; }
rep() { printf '%*s' "$2" '' | tr ' ' "$1"; }
sql() { $SQL "$1" | tail -n +2; }
reset_bd() { $SQL "source $RAIZ/bd/atrax.sql" >/dev/null; }

entrar() {
  local ck=$TMP/ck_$1_$RANDOM.txt t
  t=$(curl -s -c "$ck" "$BASE/login.php" | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -b "$ck" -c "$ck" -X POST --data-urlencode "token=$t" \
       --data-urlencode "usuario=$1" --data-urlencode "contrasena=$2" "$BASE/acciones/acceso.php"
  echo "$ck"
}

anonimo() {
  local ck=$TMP/ck_anon_$RANDOM.txt
  curl -s -o /dev/null -c "$ck" "$BASE/login.php"
  echo "$ck"
}

tok() { curl -s -b "$1" "$2" | grep -o 'name="token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }

decodificar() { sed -e 's/&quot;/"/g' -e "s/&#039;/'/g" -e 's/&amp;/\&/g' -e 's/&lt;/</g' -e 's/&gt;/>/g'; }

# Lee los avisos de una pagina: errores (ERR: a ; b), exito (OK: texto) o el codigo HTTP si no fue 200.
mensajes() {
  local archivo=$TMP/pagina_$RANDOM.html codigo
  codigo=$(curl -s -o "$archivo" -w '%{http_code}' -b "$1" -c "$1" "$2")
  if [ "$codigo" != "200" ]; then echo "HTTP $codigo"; return; fi
  local errores exito
  errores=$(tr '\n' ' ' < "$archivo" | grep -o 'vs-toast error.*' | grep -o '<li>[^<]*</li>' | sed 's/<[^>]*>//g' | decodificar | paste -sd'~' - | sed 's/~/ ; /g')
  exito=$(tr '\n' ' ' < "$archivo" | grep -o 'data-mensaje="exito">[^<]*' | sed 's/.*">//' | decodificar)
  if [ -n "$errores" ]; then echo "ERR: $errores"; elif [ -n "$exito" ]; then echo "OK: $exito"; else echo "SIN AVISO"; fi
}

# Arma un formulario a partir de valores por defecto y reemplazos: devuelve lineas clave=valor.
formulario() {
  local salida=$1 kv k
  shift
  for kv in "$@"; do
    k=${kv%%=*}
    salida=$(printf '%s\n' "$salida" | grep -v "^$k=")
    salida="$salida"$'\n'"$kv"
  done
  printf '%s\n' "$salida" | sed '/^$/d'
}

# POST con token; devuelve "CODIGO DESTINO" (destino sin el host).
enviar() {
  local ck=$1 ruta=$2 ptok=$3 t linea
  shift 3
  t=$(tok "$ck" "$BASE/$ptok")
  local args=()
  for linea in "$@"; do args+=(--data-urlencode "$linea"); done
  curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$ck" -c "$ck" -X POST \
       --data-urlencode "token=$t" "${args[@]}" "$BASE/$ruta" | sed "s#$BASE##"
}

# POST con token y lectura del aviso resultante.
accion() {
  local resultado codigo destino
  resultado=$(enviar "$@")
  codigo=${resultado%% *}
  destino=${resultado#* }
  if [ "$codigo" != "302" ]; then echo "HTTP $codigo"; return; fi
  mensajes "$1" "$BASE$destino"
}

caso() {
  local estado
  case "$5" in
    *"$4"*) estado=PASA; PASA=$((PASA + 1)) ;;
    *) estado=FALLA; FALLA=$((FALLA + 1)) ;;
  esac
  printf '| %s | %s | %s | %s | %s | %s |\n' "$1" "$2" "${3//|/\\|}" "${4//|/\\|}" "${5//|/\\|}" "$estado"
}

seccion() {
  echo
  echo "### $1"
  echo
  echo "| Caso | Que se prueba | Datos | Resultado esperado | Resultado obtenido | Estado |"
  echo "|---|---|---|---|---|---|"
}

TOMORROW=$(dia +1)
reset_bd
CKADM=$(entrar admin Admin123)
CKOPE=$(entrar cocina Operador123)
CKCLI=$(entrar julio Cliente123)

# ------------------------------------------------------------------------------------------------
seccion "A. Inicio de sesion (acciones/acceso.php)"

intento() {
  local ck=$TMP/ck_login_$RANDOM.txt t
  t=$(curl -s -c "$ck" "$BASE/login.php" | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -b "$ck" -c "$ck" -X POST --data-urlencode "token=$t" \
       --data-urlencode "usuario=$1" --data-urlencode "contrasena=$2" "$BASE/acciones/acceso.php"
  mensajes "$ck" "$BASE/login.php"
}
destino_login() {
  local ck=$TMP/ck_dest_$RANDOM.txt t
  t=$(curl -s -c "$ck" "$BASE/login.php" | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$ck" -c "$ck" -X POST --data-urlencode "token=$t" \
       --data-urlencode "usuario=$1" --data-urlencode "contrasena=$2" "$BASE/acciones/acceso.php" | sed "s#$BASE##"
}

caso CN-A01 "Credenciales validas de administrador" "admin / Admin123" "302 /admin/index.php" "$(destino_login admin Admin123)"
caso CN-A02 "Credenciales validas de operador" "cocina / Operador123" "302 /operador/index.php" "$(destino_login cocina Operador123)"
caso CN-A03 "Credenciales validas de cliente" "julio / Cliente123" "302 /cliente/index.php" "$(destino_login julio Cliente123)"
caso CN-A04 "Ingreso con el correo en lugar del usuario" "julio@correo.com / Cliente123" "302 /cliente/index.php" "$(destino_login julio@correo.com Cliente123)"
caso CN-A05 "Usuario en mayusculas (el registro guarda minusculas)" "ADMIN / Admin123" "302 /admin/index.php" "$(destino_login ADMIN Admin123)"
caso CN-A06 "Usuario con espacios alrededor" "' admin ' / Admin123" "302 /admin/index.php" "$(destino_login ' admin ' Admin123)"
caso CN-A07 "Usuario vacio" "'' / Admin123" "Ingresa tu usuario o correo." "$(intento '' Admin123)"
caso CN-A08 "Contrasena vacia" "admin / ''" "Ingresa tu contrasena." "$(intento admin '')"
caso CN-A09 "Usuario y contrasena vacios" "'' / ''" "Ingresa tu usuario o correo. ; Ingresa tu contrasena." "$(intento '' '')"
caso CN-A10 "Contrasena incorrecta" "admin / 12345678" "Usuario o contrasena incorrectos." "$(intento admin 12345678)"
caso CN-A11 "Usuario inexistente da el mismo mensaje (no revela si existe)" "fantasma / Admin123" "Usuario o contrasena incorrectos." "$(intento fantasma Admin123)"
caso CN-A12 "La contrasena distingue espacios finales" "admin / 'Admin123 '" "Usuario o contrasena incorrectos." "$(intento admin 'Admin123 ')"
caso CN-A13 "Inyeccion SQL en el usuario" "admin' OR '1'='1 / x" "Usuario o contrasena incorrectos." "$(intento "admin' OR '1'='1" x)"
caso CN-A14 "Inyeccion SQL en la contrasena" "admin / ' OR '1'='1" "Usuario o contrasena incorrectos." "$(intento admin "' OR '1'='1")"
caso CN-A15 "Usuario de 5000 caracteres no rompe el sistema" "usuario = 'a' x 5000" "Usuario o contrasena incorrectos." "$(intento "$(rep a 5000)" x)"
caso CN-A16 "Contrasena de 70000 caracteres no rompe el sistema" "contrasena = 'a' x 70000" "Usuario o contrasena incorrectos." "$(intento admin "$(rep a 70000)")"
sql "UPDATE usuarios SET activo = 0 WHERE usuario = 'lucia'" >/dev/null
caso CN-A17 "Cuenta inactiva con la contrasena correcta" "lucia (inactiva) / Cliente123" "La cuenta esta inactiva." "$(intento lucia Cliente123)"
sql "UPDATE usuarios SET activo = 1 WHERE usuario = 'lucia'" >/dev/null

for i in $(seq 1 5); do intento gerente claveequivocada >/dev/null; done
caso CN-A18 "Limite de intentos fallidos: tras 5 fallos seguidos el usuario queda bloqueado aunque ahora use la contrasena correcta" "5 intentos con clave mala y luego gerente / Gerente123" "Demasiados intentos fallidos. Espera 10 minutos" "$(intento gerente Gerente123)"

ck=$TMP/ck_fijacion.txt
curl -s -o /dev/null -c "$ck" "$BASE/login.php"
antes=$(grep -o 'ATRAXSESION[[:space:]]*[a-z0-9]*' "$ck" | awk '{print $2}')
t=$(tok "$ck" "$BASE/login.php")
curl -s -o /dev/null -b "$ck" -c "$ck" -X POST --data-urlencode "token=$t" --data-urlencode "usuario=admin" --data-urlencode "contrasena=Admin123" "$BASE/acciones/acceso.php"
despues=$(grep -o 'ATRAXSESION[[:space:]]*[a-z0-9]*' "$ck" | awk '{print $2}')
if [ -n "$antes" ] && [ "$antes" != "$despues" ]; then cambio="el identificador de sesion cambio"; else cambio="el identificador de sesion no cambio"; fi
caso CN-A19 "El identificador de sesion cambia al iniciar sesion (fijacion de sesion)" "cookie antes y despues del login" "el identificador de sesion cambio" "$cambio"

cabecera=$(curl -s -D - -o /dev/null "$BASE/login.php" | tr -d '\r' | grep -i '^set-cookie: ATRAXSESION')
case "$cabecera" in *[Hh]ttp[Oo]nly*) cookie="HttpOnly presente" ;; *) cookie="sin HttpOnly" ;; esac
caso CN-A20 "La cookie de sesion no es legible desde JavaScript" "Set-Cookie de login.php" "HttpOnly presente" "$cookie"

ck=$(entrar admin Admin123)
sesion() { curl -s -o /dev/null -w '%{http_code}' -b "$ck" "$BASE/admin/index.php" | sed 's/200/sesion sigue abierta/;s/302/sesion cerrada/'; }
get=$(curl -s -o /dev/null -w '%{http_code}' -b "$ck" "$BASE/logout.php")
caso CN-A21 "Un GET a logout.php no cierra la sesion (CSRF de logout)" "GET logout.php con la cookie de la victima, como desde una imagen de otro sitio" "405, sesion sigue abierta" "$get, $(sesion)"
sintoken=$(curl -s -o /dev/null -w '%{http_code}' -b "$ck" -X POST "$BASE/logout.php")
caso CN-A22 "Un POST a logout.php sin token no cierra la sesion" "POST logout.php sin token" "400, sesion sigue abierta" "$sintoken, $(sesion)"
t=$(tok "$ck" "$BASE/admin/index.php")
contoken=$(curl -s -o /dev/null -w '%{http_code}' -b "$ck" -c "$ck" -X POST --data-urlencode "token=$t" "$BASE/logout.php")
caso CN-A23 "Un POST a logout.php con el token de la sesion la cierra" "boton Salir: POST logout.php con token" "302, sesion cerrada" "$contoken, $(sesion)"

caso CN-A24 "El bloqueo es por usuario: otra cuenta sigue entrando mientras gerente esta bloqueado" "cocina / Operador123 despues de bloquear a gerente" "302 /operador/index.php" "$(destino_login cocina Operador123)"
sql "UPDATE intentos_acceso SET fecha = fecha - INTERVAL 11 MINUTE" >/dev/null
caso CN-A25 "El bloqueo vence: pasados 10 minutos gerente vuelve a entrar" "fallos de gerente con 11 minutos de antiguedad, gerente / Gerente123" "302 /admin/index.php" "$(destino_login gerente Gerente123)"
for i in 1 2 3 4; do intento estudiosur claveequivocada >/dev/null; done
primero=$(destino_login estudiosur Cliente123)
for i in 1 2 3 4; do intento estudiosur claveequivocada >/dev/null; done
caso CN-A26 "Un ingreso correcto borra los fallos anteriores" "4 fallos, ingreso correcto, 4 fallos, ingreso correcto (sin borrar serian 8 seguidos)" "302 /cliente/index.php y 302 /cliente/index.php" "$primero y $(destino_login estudiosur Cliente123)"
sql "DELETE FROM intentos_acceso" >/dev/null
for i in $(seq 1 20); do intento "fantasma$i" claveequivocada >/dev/null; done
caso CN-A27 "Limite por direccion: 20 fallos con usuarios distintos bloquean tambien a una cuenta que no fallo" "20 usuarios inexistentes y luego cocina / Operador123" "Demasiados intentos fallidos." "$(intento cocina Operador123)"
sql "DELETE FROM intentos_acceso" >/dev/null

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "B. Registro de clientes (acciones/registro.php)"

REG_BASE=$'nombre=Prueba Caja Negra\ntelefono=+598 99 111 222\ndireccion=Calle Falsa 1234\ntipo=Particular\nzona_id=3\ncontrasena=Prueba2026\ncontrasena2=Prueba2026'
siguiente() { # contador en archivo: sobrevive a los subshells de $( )
  local v; v=$(( $(cat "$TMP/contador" 2>/dev/null || echo 0) + 1 ))
  echo "$v" > "$TMP/contador"
  echo "$v"
}
registrar() {
  local n; n=$(siguiente)
  local f; f=$(formulario "$REG_BASE"$'\n'"usuario=reg$n"$'\n'"email=reg$n@correo.com" "$@")
  local lineas=(); local IFS=$'\n'; for l in $f; do lineas+=("$l"); done; unset IFS
  accion "$(anonimo)" acciones/registro.php registro.php "${lineas[@]}"
}

caso CN-B01 "Registro con todos los datos validos" "datos de ejemplo" "OK: Cuenta creada correctamente." "$(registrar)"
caso CN-B02 "Nombre de 2 caracteres (limite inferior - 1)" "nombre = 'Al'" "El nombre debe tener al menos 3 caracteres." "$(registrar nombre=Al)"
caso CN-B03 "Nombre de 3 caracteres (limite inferior)" "nombre = 'Ana'" "OK: Cuenta creada correctamente." "$(registrar nombre=Ana)"
caso CN-B04 "Nombre de 100 caracteres (maximo de la columna)" "nombre = 'N' x 100" "OK: Cuenta creada correctamente." "$(registrar "nombre=$(rep N 100)")"
caso CN-B05 "Nombre de 101 caracteres (maximo + 1) debe rechazarse con un mensaje claro" "nombre = 'N' x 101" "El nombre no puede superar 100 caracteres." "$(registrar "nombre=$(rep N 101)")"
caso CN-B06 "Usuario de 3 caracteres" "usuario = 'abc'" "El usuario debe tener entre 4 y 40 caracteres" "$(registrar usuario=abc)"
caso CN-B07 "Usuario de 4 caracteres (limite inferior)" "usuario = 'abcd'" "OK: Cuenta creada correctamente." "$(registrar "usuario=$(printf 'q%03d' "$(siguiente)")")"
caso CN-B08 "Usuario de 40 caracteres (limite superior)" "usuario = 'u' x 40" "OK: Cuenta creada correctamente." "$(registrar "usuario=$(rep u 36)$(printf '%04d' "$(siguiente)")")"
caso CN-B09 "Usuario de 41 caracteres" "usuario = 'u' x 41" "El usuario debe tener entre 4 y 40 caracteres" "$(registrar "usuario=$(rep u 41)")"
caso CN-B10 "Usuario con espacio y signos" "usuario = 'a b!'" "El usuario debe tener entre 4 y 40 caracteres" "$(registrar 'usuario=a b!')"
caso CN-B11 "Usuario duplicado sin distinguir mayusculas" "usuario = 'JULIO'" "El nombre de usuario ya esta en uso." "$(registrar usuario=JULIO)"
caso CN-B12 "Correo sin arroba" "email = 'correo.com'" "El correo electronico no tiene un formato valido." "$(registrar email=correo.com)"
caso CN-B13 "Correo duplicado sin distinguir mayusculas" "email = 'JULIO@CORREO.COM'" "El correo electronico ya esta registrado." "$(registrar email=JULIO@CORREO.COM)"
caso CN-B14 "Correo de 151 caracteres (maximo de la columna + 1)" "email = 'a' x 140 + '@correo.com'" "El correo electronico no tiene un formato valido." "$(registrar "email=$(rep a 140)@correo.com")"
caso CN-B15 "Telefono de 7 caracteres" "telefono = '1234567'" "El telefono no tiene un formato valido." "$(registrar telefono=1234567)"
caso CN-B16 "Telefono de 8 caracteres (limite inferior)" "telefono = '12345678'" "OK: Cuenta creada correctamente." "$(registrar telefono=12345678)"
caso CN-B17 "Telefono de 20 caracteres (limite superior)" "telefono = '1' x 20" "OK: Cuenta creada correctamente." "$(registrar "telefono=$(rep 1 20)")"
caso CN-B18 "Telefono de 21 caracteres" "telefono = '1' x 21" "El telefono no tiene un formato valido." "$(registrar "telefono=$(rep 1 21)")"
caso CN-B19 "Telefono formado solo por signos no es un telefono" "telefono = '--------'" "El telefono no tiene un formato valido." "$(registrar telefono=--------)"
caso CN-B20 "Telefono con letras" "telefono = 'abcdefgh'" "El telefono no tiene un formato valido." "$(registrar telefono=abcdefgh)"
caso CN-B21 "Direccion de 4 caracteres" "direccion = 'Av 1'" "La direccion debe tener al menos 5 caracteres." "$(registrar 'direccion=Av 1')"
caso CN-B22 "Direccion de 5 caracteres (limite inferior)" "direccion = 'Av 12'" "OK: Cuenta creada correctamente." "$(registrar 'direccion=Av 12')"
caso CN-B23 "Direccion de 200 caracteres (maximo de la columna)" "direccion = 'd' x 200" "OK: Cuenta creada correctamente." "$(registrar "direccion=$(rep d 200)")"
caso CN-B24 "Direccion de 201 caracteres debe rechazarse con un mensaje claro" "direccion = 'd' x 201" "La direccion no puede superar 200 caracteres." "$(registrar "direccion=$(rep d 201)")"
caso CN-B25 "Tipo de cliente fuera de la lista" "tipo = 'Otro'" "El tipo de cliente no es valido." "$(registrar tipo=Otro)"
caso CN-B26 "Zona inexistente" "zona_id = 999" "Selecciona una zona de entrega valida." "$(registrar zona_id=999)"
caso CN-B27 "Zona no numerica" "zona_id = 'abc'" "Selecciona una zona de entrega valida." "$(registrar zona_id=abc)"
sql "UPDATE zonas SET activa = 0 WHERE id = 7" >/dev/null
caso CN-B28 "Zona desactivada no deberia ofrecerse para el registro" "zona_id = 7 (inactiva)" "Selecciona una zona de entrega valida." "$(registrar zona_id=7)"
sql "UPDATE zonas SET activa = 1 WHERE id = 7" >/dev/null
caso CN-B29 "Contrasena de 7 caracteres" "contrasena = 'Abcdef1'" "La contrasena debe tener al menos 8 caracteres." "$(registrar contrasena=Abcdef1 contrasena2=Abcdef1)"
caso CN-B30 "Contrasena de 8 caracteres (limite inferior)" "contrasena = 'Abcdefg1'" "OK: Cuenta creada correctamente." "$(registrar contrasena=Abcdefg1 contrasena2=Abcdefg1)"
caso CN-B31 "Contrasena de 8 espacios no deberia aceptarse" "contrasena = '        '" "La contrasena no puede estar formada solo por espacios." "$(registrar 'contrasena=        ' 'contrasena2=        ')"
caso CN-B32 "Las contrasenas no coinciden" "contrasena2 distinta" "Las contrasenas no coinciden." "$(registrar contrasena2=Distinta2026)"
caso CN-B33 "Todos los campos vacios" "nada completado" "El nombre debe tener al menos 3 caracteres." "$(registrar nombre= usuario= email= telefono= direccion= tipo= zona_id= contrasena= contrasena2=)"

registrar 'nombre=<script>alert(1)</script>' >/dev/null
pagina=$(curl -s -b "$CKADM" "$BASE/admin/usuarios.php")
if printf '%s' "$pagina" | grep -q '<script>alert(1)</script>'; then xss="el nombre se imprime sin escapar"; else xss="el nombre se imprime escapado"; fi
caso CN-B34 "Nombre con etiqueta script se escapa al mostrarse (XSS almacenado)" "nombre = '<script>alert(1)</script>'" "el nombre se imprime escapado" "$xss"

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "C. Armado de pedidos del cliente (acciones/pedido_cliente.php)"

pedido() {
  local f
  f=$(formulario $'plan_id=1\nfecha_entrega='"$TOMORROW"$'\ncantidad[1]=2\ncantidad[3]=3' "$@")
  local lineas=(); local IFS=$'\n'; for l in $f; do lineas+=("$l"); done; unset IFS
  accion "$CKCLI" acciones/pedido_cliente.php cliente/pedido.php "${lineas[@]}"
}
pedido_sin() { # reemplaza el formulario por completo
  accion "$CKCLI" acciones/pedido_cliente.php cliente/pedido.php "$@"
}

caso CN-C01 "Pedido valido: plan Semanal, 2 + 3 viandas, entrega manana" "plan 1, cantidad[1]=2, cantidad[3]=3" "OK: Pedido #6 confirmado." "$(pedido)"
caso CN-C02 "Modalidad inexistente" "plan_id = 99" "La modalidad seleccionada no esta disponible." "$(pedido plan_id=99)"
caso CN-C03 "Sin modalidad" "plan_id omitido" "La modalidad seleccionada no esta disponible." "$(pedido_sin "fecha_entrega=$TOMORROW" 'cantidad[1]=5')"
sql "UPDATE planes SET activo = 0 WHERE id = 2" >/dev/null
caso CN-C04 "Modalidad desactivada por el administrador" "plan_id = 2 (inactivo)" "La modalidad seleccionada no esta disponible." "$(pedido plan_id=2 'cantidad[1]=5' 'cantidad[3]=5')"
sql "UPDATE planes SET activo = 1 WHERE id = 2" >/dev/null
caso CN-C05 "Entrega hoy (limite inferior - 1)" "fecha = hoy" "La entrega debe programarse como minimo para manana." "$(pedido "fecha_entrega=$(dia +0)")"
caso CN-C06 "Entrega ayer" "fecha = ayer" "La entrega debe programarse como minimo para manana." "$(pedido "fecha_entrega=$(dia -1)")"
caso CN-C07 "Entrega dentro de 60 dias (limite superior)" "fecha = hoy + 60" "OK: Pedido #7 confirmado." "$(pedido "fecha_entrega=$(dia +60)")"
caso CN-C08 "Entrega dentro de 61 dias (limite superior + 1)" "fecha = hoy + 61" "La entrega no puede programarse a mas de 60 dias." "$(pedido "fecha_entrega=$(dia +61)")"
caso CN-C09 "Fecha con formato dia/mes/anio" "fecha = 31/12/2027" "La fecha de entrega no es valida." "$(pedido fecha_entrega=31/12/2027)"
caso CN-C10 "Fecha imposible" "fecha = 2027-02-30" "La fecha de entrega no es valida." "$(pedido fecha_entrega=2027-02-30)"
caso CN-C11 "Fecha vacia" "fecha = ''" "La fecha de entrega no es valida." "$(pedido fecha_entrega=)"
caso CN-C12 "Suma de viandas = plan - 1 (4 de 5)" "cantidades 2 + 2" "requiere exactamente 5 viandas y elegiste 4." "$(pedido 'cantidad[1]=2' 'cantidad[3]=2')"
caso CN-C13 "Suma de viandas = plan + 1 (6 de 5)" "cantidades 3 + 3" "requiere exactamente 5 viandas y elegiste 6." "$(pedido 'cantidad[1]=3' 'cantidad[3]=3')"
caso CN-C14 "Ningun plato elegido" "todas las cantidades en 0" "Tenes que elegir al menos un plato." "$(pedido 'cantidad[1]=0' 'cantidad[3]=0')"
caso CN-C15 "Cantidad negativa" "cantidad[1] = -1" "Las cantidades deben ser numeros enteros mayores o iguales a 0." "$(pedido 'cantidad[1]=-1')"
caso CN-C16 "Cantidad decimal" "cantidad[1] = 1.5" "Las cantidades deben ser numeros enteros mayores o iguales a 0." "$(pedido 'cantidad[1]=1.5')"
caso CN-C17 "Cantidad con texto" "cantidad[1] = 'abc'" "Las cantidades deben ser numeros enteros mayores o iguales a 0." "$(pedido 'cantidad[1]=abc')"
caso CN-C18 "Cantidad en notacion cientifica" "cantidad[1] = 1e1" "Las cantidades deben ser numeros enteros mayores o iguales a 0." "$(pedido 'cantidad[1]=1e1')"
caso CN-C19 "Cantidad desbordada" "cantidad[1] = 99999999999999999999" "Las cantidades deben ser numeros enteros mayores o iguales a 0." "$(pedido 'cantidad[1]=99999999999999999999')"
reset_bd
caso CN-C20 "Cantidad igual al stock disponible (limite): 5 ensaladas de quinoa, hay 5" "plan 1, cantidad[4] = 5" "OK: Pedido #6 confirmado." "$(pedido_sin plan_id=1 "fecha_entrega=$TOMORROW" 'cantidad[4]=5')"
caso CN-C21 "Cantidad = stock + 1: 6 ensaladas de quinoa, queda 0" "plan 2, cantidad[4] = 6 + cantidad[1] = 4" "No hay stock suficiente de Ensalada de quinoa" "$(pedido_sin plan_id=2 "fecha_entrega=$TOMORROW" 'cantidad[4]=6' 'cantidad[1]=4')"
caso CN-C22 "Plato inexistente" "cantidad[99] = 5" "Uno de los platos seleccionados ya no esta disponible." "$(pedido_sin plan_id=1 "fecha_entrega=$TOMORROW" 'cantidad[99]=5')"
sql "UPDATE productos SET activo = 0 WHERE id = 5" >/dev/null
caso CN-C23 "Plato dado de baja del menu" "cantidad[5] = 5 (inactivo)" "Uno de los platos seleccionados ya no esta disponible." "$(pedido_sin plan_id=1 "fecha_entrega=$TOMORROW" 'cantidad[5]=5')"
sql "UPDATE productos SET activo = 1 WHERE id = 5" >/dev/null
caso CN-C24 "Cantidades enviadas como valor simple en lugar de lista" "cantidad = 5" "Tenes que elegir al menos un plato." "$(pedido_sin plan_id=1 "fecha_entrega=$TOMORROW" cantidad=5)"
caso CN-C25 "Plan Mensual con 20 viandas de un solo plato (stock 30)" "plan 3, cantidad[5] = 20" "OK: Pedido #7 confirmado." "$(pedido_sin plan_id=3 "fecha_entrega=$TOMORROW" 'cantidad[5]=20')"

salida=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$CKCLI" "$BASE/cliente/seguimiento.php?id=4" | sed "s#$BASE##")
caso CN-C26 "Un cliente no puede ver el pedido de otro (id 4 es del Liceo Impulso)" "GET seguimiento.php?id=4" "302 /cliente/pedidos.php" "$salida"
caso CN-C27 "Seguimiento de un pedido inexistente" "GET seguimiento.php?id=9999" "302 /cliente/pedidos.php" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$CKCLI" "$BASE/cliente/seguimiento.php?id=9999" | sed "s#$BASE##")"
caso CN-C28 "Seguimiento con id no numerico" "GET seguimiento.php?id=abc" "302 /cliente/pedidos.php" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$CKCLI" "$BASE/cliente/seguimiento.php?id=abc" | sed "s#$BASE##")"
caso CN-C29 "Seguimiento con inyeccion SQL en el id" "GET seguimiento.php?id=1 OR 1=1" "302 /cliente/pedidos.php" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$CKCLI" -G --data-urlencode 'id=4 OR 1=1' "$BASE/cliente/seguimiento.php" | sed "s#$BASE##")"

# Agregados a partir del analisis de ramas de acciones/pedido_cliente.php (docs/pruebas-caja-blanca.md).
caso CN-C30 "Plato con identificador no numerico en las cantidades" "cantidad[abc] = 5" "Uno de los platos seleccionados ya no esta disponible." "$(pedido_sin plan_id=1 "fecha_entrega=$TOMORROW" 'cantidad[abc]=5')"
sql "INSERT INTO usuarios (rol_id, nombre, usuario, email, telefono, contrasena, activo) SELECT rol_id, 'Sin Ficha', 'sinficha', 'sinficha@correo.com', telefono, contrasena, activo FROM usuarios WHERE usuario = 'julio'" >/dev/null
CKSIN=$(entrar sinficha Cliente123)
caso CN-C31 "Usuario con rol cliente al que le falta la ficha de cliente" "sinficha (sin fila en clientes), plan 1, cantidad[1] = 5" "Tu cuenta no tiene una ficha de cliente asociada." "$(accion "$CKSIN" acciones/pedido_cliente.php cliente/pedido.php plan_id=1 "fecha_entrega=$TOMORROW" 'cantidad[1]=5')"

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "D. Registro de produccion (acciones/produccion.php)"

produccion() {
  local f; f=$(formulario $'producto_id=1\ncantidad=10\nfecha_vencimiento='"$(dia +4)" "$@")
  local lineas=(); local IFS=$'\n'; for l in $f; do lineas+=("$l"); done; unset IFS
  accion "$CKOPE" acciones/produccion.php operador/produccion.php "${lineas[@]}"
}

caso CN-D01 "Produccion valida" "plato 1, 10 viandas, vence en 4 dias" "OK: Lote L-1000 registrado: 10 viandas de Milanesa con ensalada." "$(produccion)"
caso CN-D02 "Cantidad 0 (limite inferior - 1)" "cantidad = 0" "La cantidad producida debe ser un numero entre 1 y 500." "$(produccion cantidad=0)"
caso CN-D03 "Cantidad 1 (limite inferior)" "cantidad = 1" "OK: Lote L-1001 registrado: 1 viandas" "$(produccion cantidad=1)"
caso CN-D04 "Cantidad 500 (limite superior)" "cantidad = 500" "OK: Lote L-1002 registrado: 500 viandas" "$(produccion cantidad=500)"
caso CN-D05 "Cantidad 501 (limite superior + 1)" "cantidad = 501" "La cantidad producida debe ser un numero entre 1 y 500." "$(produccion cantidad=501)"
caso CN-D06 "Cantidad negativa" "cantidad = -1" "La cantidad producida debe ser un numero entre 1 y 500." "$(produccion cantidad=-1)"
caso CN-D07 "Cantidad con texto" "cantidad = 'abc'" "La cantidad producida debe ser un numero entre 1 y 500." "$(produccion cantidad=abc)"
caso CN-D08 "Cantidad decimal" "cantidad = 1.5" "La cantidad producida debe ser un numero entre 1 y 500." "$(produccion cantidad=1.5)"
caso CN-D09 "Vencimiento ayer (limite inferior - 1)" "vence = hoy - 1" "La fecha de vencimiento no puede ser anterior a hoy." "$(produccion "fecha_vencimiento=$(dia -1)")"
caso CN-D10 "Vencimiento hoy (limite inferior)" "vence = hoy" "OK: Lote L-1003 registrado" "$(produccion "fecha_vencimiento=$(dia +0)")"
caso CN-D11 "Vencimiento a 30 dias (limite superior)" "vence = hoy + 30" "OK: Lote L-1004 registrado" "$(produccion "fecha_vencimiento=$(dia +30)")"
caso CN-D12 "Vencimiento a 31 dias (limite superior + 1)" "vence = hoy + 31" "La fecha de vencimiento no puede superar los 30 dias." "$(produccion "fecha_vencimiento=$(dia +31)")"
caso CN-D13 "Fecha de vencimiento invalida" "vence = 2027-13-45" "La fecha de vencimiento no es valida." "$(produccion fecha_vencimiento=2027-13-45)"
caso CN-D14 "Plato inexistente" "producto_id = 999" "El plato seleccionado no existe." "$(produccion producto_id=999)"
sql "UPDATE productos SET activo = 0 WHERE id = 3" >/dev/null
caso CN-D15 "Plato dado de baja del menu" "producto_id = 3 (inactivo)" "No se puede producir un plato dado de baja del menu." "$(produccion producto_id=3)"
sql "UPDATE productos SET activo = 1 WHERE id = 3" >/dev/null
caso CN-D16 "Formulario vacio" "todos los campos vacios" "El plato seleccionado no existe." "$(produccion producto_id= cantidad= fecha_vencimiento=)"
caso CN-D17 "La produccion crea el lote y su movimiento de entrada" "lote L-1000" "10 Entrada" "$(sql "SELECT CONCAT(cantidad, ' ', tipo) FROM movimientos_stock WHERE motivo = 'Produccion lote L-1000'")"

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "E. Movimientos de stock por lote (acciones/stock.php)"

movimiento() {
  local f; f=$(formulario $'lote_id=9\ntipo=Salida\ncantidad=1\nmotivo=Control de calidad' "$@")
  local lineas=(); local IFS=$'\n'; for l in $f; do lineas+=("$l"); done; unset IFS
  accion "$CKOPE" acciones/stock.php operador/stock.php "${lineas[@]}"
}
lote() { sql "SELECT CONCAT(disponible, '/', cantidad, ' ', estado) FROM lotes WHERE id = $1"; }

caso CN-E01 "Salida mayor al disponible (6 de 5)" "lote 9, Salida 6" "La salida (6) supera el stock disponible del lote (5)." "$(movimiento cantidad=6)"
caso CN-E02 "Salida igual al disponible (5 de 5, limite)" "lote 9, Salida 5" "OK: Movimiento registrado sobre el lote L-0998." "$(movimiento cantidad=5)"
caso CN-E03 "El lote agotado cambia de estado" "lote 9 tras la salida" "0/5 Agotado" "$(lote 9)"
caso CN-E04 "Salida sobre un lote ya agotado" "lote 9, Salida 1" "La salida (1) supera el stock disponible del lote (0)." "$(movimiento cantidad=1)"
caso CN-E05 "Ajuste hasta la cantidad original (25 + 5 = 30, limite)" "lote 7, Ajuste 5" "OK: Movimiento registrado sobre el lote L-0994." "$(movimiento lote_id=7 tipo=Ajuste cantidad=5)"
caso CN-E06 "Ajuste por encima de la cantidad original (30 + 1)" "lote 7, Ajuste 1" "El ajuste no puede superar la cantidad original producida en el lote (30)." "$(movimiento lote_id=7 tipo=Ajuste cantidad=1)"
caso CN-E07 "Cantidad 0" "cantidad = 0" "La cantidad debe ser un numero entre 1 y 500." "$(movimiento lote_id=7 cantidad=0)"
caso CN-E08 "Cantidad 501" "cantidad = 501" "La cantidad debe ser un numero entre 1 y 500." "$(movimiento lote_id=7 cantidad=501)"
caso CN-E09 "Cantidad negativa" "cantidad = -3" "La cantidad debe ser un numero entre 1 y 500." "$(movimiento lote_id=7 cantidad=-3)"
caso CN-E10 "Cantidad con texto" "cantidad = 'abc'" "La cantidad debe ser un numero entre 1 y 500." "$(movimiento lote_id=7 cantidad=abc)"
caso CN-E11 "Tipo de movimiento invalido" "tipo = 'Regalo'" "El tipo de movimiento no es valido." "$(movimiento lote_id=7 tipo=Regalo)"
caso CN-E12 "Lote inexistente" "lote_id = 999" "El lote seleccionado no existe." "$(movimiento lote_id=999)"
caso CN-E13 "Motivo de 2 caracteres" "motivo = 'ab'" "El motivo debe tener al menos 3 caracteres." "$(movimiento lote_id=7 motivo=ab)"
caso CN-E14 "Motivo de 3 caracteres (limite)" "motivo = 'abc'" "OK: Movimiento registrado sobre el lote L-0994." "$(movimiento lote_id=7 tipo=Salida motivo=abc)"
antes=$(lote 8)
resultado=$(movimiento lote_id=8 "motivo=$(rep m 151)")
despues=$(lote 8)
movs=$(sql "SELECT COUNT(*) FROM movimientos_stock WHERE lote_id = 8 AND tipo = 'Salida'")
caso CN-E15 "Motivo de 151 caracteres (maximo de la columna + 1): rechazo claro y sin cambios a medias" "lote 8, Salida 1, motivo = 'm' x 151" "El motivo no puede superar 150 caracteres." "$resultado (lote antes $antes, despues $despues, salidas registradas: $movs)"
caso CN-E16 "Descarte de un lote con stock" "lote 6, Descarte" "OK: Lote L-0991 descartado." "$(movimiento lote_id=6 tipo=Descarte cantidad= motivo=Vencido)"
caso CN-E17 "El descarte deja el lote en estado Descartado" "lote 6" "0/11 Descartado" "$(lote 6)"
caso CN-E18 "Movimiento sobre un lote descartado" "lote 6, Salida 1" "El lote ya fue descartado." "$(movimiento lote_id=6)"

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "F. Estados del pedido (acciones/pedidos.php)"

estados=("Pendiente" "Preparando" "Listo" "En distribucion" "Entregado" "Cancelado")
permitida() { # origen destino segun la especificacion (RF10)
  case "$1>$2" in
    "Pendiente>Preparando"|"Pendiente>Cancelado"|"Preparando>Listo"|"Preparando>Cancelado"|"Listo>En distribucion"|"Listo>Cancelado"|"En distribucion>Entregado") echo si ;;
    *) echo no ;;
  esac
}

echo
echo "Matriz de transiciones sobre el pedido #5 (fila = estado de origen, columna = estado de destino; ✔ aceptada, ✖ rechazada, ‼ resultado distinto al esperado):"
echo
printf '| Origen \\ Destino |'; for d in "${estados[@]}"; do printf ' %s |' "$d"; done; echo
printf '|---|'; for d in "${estados[@]}"; do printf '%s' '---|'; done; echo
for o in "${estados[@]}"; do
  printf '| **%s** |' "$o"
  for d in "${estados[@]}"; do
    reset_bd
    sql "UPDATE pedidos SET estado = '$o', vehiculo_id = 2 WHERE id = 5" >/dev/null
    obtenido=$(accion "$CKOPE" acciones/pedidos.php operador/pedidos.php id=5 "estado=$d")
    case "$obtenido" in OK:*) real=si ;; *) real=no ;; esac
    esperado=$(permitida "$o" "$d")
    if [ "$real" = "$esperado" ]; then PASA=$((PASA + 1)); marca=$([ "$real" = si ] && echo '✔' || echo '✖'); else FALLA=$((FALLA + 1)); marca='‼'; fi
    printf ' %s |' "$marca"
  done
  echo
done
echo
echo "36 combinaciones, cada una cuenta como un caso (CN-F01 a CN-F36). La base se reinicia antes de cada una."

reset_bd
seccion "F2. Otros casos de estados"
caso CN-F37 "Estado inexistente" "pedido 5, estado = 'Volando'" "El estado indicado no es valido." "$(accion "$CKOPE" acciones/pedidos.php operador/pedidos.php id=5 estado=Volando)"
caso CN-F38 "Pedido inexistente" "id = 999" "El pedido indicado no existe." "$(accion "$CKOPE" acciones/pedidos.php operador/pedidos.php id=999 estado=Preparando)"
caso CN-F39 "Id no numerico" "id = 'abc'" "El pedido indicado no existe." "$(accion "$CKOPE" acciones/pedidos.php operador/pedidos.php id=abc estado=Preparando)"
caso CN-F40 "Despachar un pedido sin vehiculo asignado" "pedido 3 (Listo, sin vehiculo) a En distribucion" "Antes de despachar el pedido #3 hay que asignarle un vehiculo en Distribucion." "$(accion "$CKOPE" acciones/pedidos.php operador/pedidos.php id=3 'estado=En distribucion')"
accion "$CKOPE" acciones/pedidos.php operador/pedidos.php id=4 estado=Cancelado >/dev/null
caso CN-F41 "Al cancelar un pedido el stock vuelve al lote" "pedido 4 cancelado: lote L-0983 (id 4)" "5/8 Disponible" "$(lote 4)"
caso CN-F42 "La cancelacion queda en el historial de movimientos" "pedido 4" "Devolucion por cancelacion del pedido #4" "$(sql "SELECT motivo FROM movimientos_stock WHERE pedido_id = 4 AND tipo = 'Entrada'")"
caso CN-F43 "Un pedido cancelado no puede reactivarse" "pedido 4: Cancelado a Pendiente" "No se puede pasar el pedido #4" "$(accion "$CKOPE" acciones/pedidos.php operador/pedidos.php id=4 estado=Pendiente)"
accion "$CKOPE" acciones/stock.php operador/stock.php lote_id=7 tipo=Ajuste cantidad=5 motivo=Recuento >/dev/null
resultado=$(accion "$CKOPE" acciones/pedidos.php operador/pedidos.php id=5 estado=Cancelado)
caso CN-F44 "Cancelar un pedido cuyo lote ya fue ajustado hasta su cantidad original" "pedido 5 (tomo 5 del lote L-0994); ajuste +5 sobre ese lote; luego Pendiente a Cancelado" "El pedido #5 paso a \"Cancelado\"." "$resultado (pedido: $(sql "SELECT estado FROM pedidos WHERE id = 5"); lote L-0994: $(lote 7))"

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "G. Distribucion y asignacion de vehiculos (acciones/distribucion.php)"

asignar() { accion "$CKOPE" acciones/distribucion.php operador/distribucion.php accion=asignar "pedido_id=$1" "vehiculo_id=$2"; }
caso CN-G01 "Asignacion valida: pedido 3 (Z3, 5 viandas) a Moto 1 (Z3, capacidad 18)" "pedido 3, vehiculo 3" "OK: Pedido #3 asignado a Moto 1." "$(asignar 3 3)"
caso CN-G02 "Vehiculo de otra zona" "pedido 3 (Z3) a Furgon 1 (Z1)" "El vehiculo no cubre la zona Z3 del pedido." "$(asignar 3 1)"
sql "UPDATE pedidos SET zona_id = 6, estado = 'Listo', vehiculo_id = NULL WHERE id = 4" >/dev/null
caso CN-G03 "Vehiculo en mantenimiento" "pedido 4 (Z6) a Moto 2 (Mantenimiento)" "El vehiculo Moto 2 esta en mantenimiento." "$(asignar 4 4)"
caso CN-G04 "Pedido que no esta Listo" "pedido 5 (Pendiente) a Furgon 2" "Solo se pueden asignar pedidos en estado" "$(asignar 5 2)"
caso CN-G05 "Vehiculo inexistente" "vehiculo_id = 999" "El vehiculo seleccionado no existe." "$(asignar 3 999)"
caso CN-G06 "Pedido inexistente" "pedido_id = 999" "El pedido indicado no existe." "$(asignar 999 3)"
caso CN-G07 "Accion desconocida" "accion = 'borrar'" "La accion solicitada no es valida." "$(accion "$CKOPE" acciones/distribucion.php operador/distribucion.php accion=borrar pedido_id=3)"
sql "UPDATE vehiculos SET capacidad = 10, estado = 'Disponible' WHERE id = 3; UPDATE pedidos SET vehiculo_id = NULL, estado = 'Listo' WHERE id = 3" >/dev/null
caso CN-G08 "Capacidad exacta (0 + 5 = 5 <= 5, limite)" "Moto 1 con capacidad 5, pedido 3 de 5 viandas" "OK: Pedido #3 asignado a Moto 1." "$(sql "UPDATE vehiculos SET capacidad = 5 WHERE id = 3" >/dev/null; asignar 3 3)"
sql "UPDATE vehiculos SET capacidad = 4 WHERE id = 3; UPDATE pedidos SET vehiculo_id = NULL WHERE id = 3" >/dev/null
caso CN-G09 "Capacidad superada por 1 (0 + 5 > 4)" "Moto 1 con capacidad 4" "no tiene capacidad suficiente (0 + 5 supera 4)." "$(asignar 3 3)"
sql "UPDATE vehiculos SET capacidad = 5 WHERE id = 3" >/dev/null
asignar 3 3 >/dev/null
caso CN-G10 "Reasignar un pedido al vehiculo que ya lo lleva no debe contar su carga dos veces" "pedido 3 ya en Moto 1 (capacidad 5), se vuelve a asignar" "OK: Pedido #3 asignado a Moto 1." "$(asignar 3 3)"
caso CN-G11 "Quitar la asignacion de un pedido Listo" "pedido 3" "OK: Se quito la asignacion del pedido #3." "$(accion "$CKOPE" acciones/distribucion.php operador/distribucion.php accion=quitar pedido_id=3)"
caso CN-G12 "Quitar la asignacion de un pedido que ya salio a reparto" "pedido 2 (En distribucion)" "Solo se puede quitar la asignacion de un pedido en estado \"Listo\"." "$(accion "$CKOPE" acciones/distribucion.php operador/distribucion.php accion=quitar pedido_id=2)"

# Agregados a partir del analisis de ramas de acciones/distribucion.php (docs/pruebas-caja-blanca.md).
caso CN-G13 "Pedido con identificador no numerico" "pedido_id = 'abc'" "El pedido indicado no existe." "$(accion "$CKOPE" acciones/distribucion.php operador/distribucion.php accion=asignar pedido_id=abc vehiculo_id=3)"
caso CN-G14 "Asignar sin indicar el vehiculo" "pedido 3 (Listo), vehiculo_id vacio" "El vehiculo seleccionado no existe." "$(asignar 3 '')"

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "H. Zonas (acciones/zonas.php)"

zona() {
  local f; f=$(formulario $'id=0\ncodigo=Z9\nnombre=Parque Rodo\nbarrios=Parque Rodo, Cordon' "$@")
  local lineas=(); local IFS=$'\n'; for l in $f; do lineas+=("$l"); done; unset IFS
  accion "$CKADM" acciones/zonas.php admin/zonas.php "${lineas[@]}"
}
caso CN-H01 "Alta de zona valida" "Z9 / Parque Rodo" "OK: Zona creada correctamente." "$(zona)"
caso CN-H02 "Codigo de 1 caracter" "codigo = 'Z'" "El codigo debe tener entre 2 y 5 caracteres" "$(zona codigo=Z nombre=Otra1)"
caso CN-H03 "Codigo de 2 caracteres (limite inferior)" "codigo = 'ZZ'" "OK: Zona creada correctamente." "$(zona codigo=ZZ nombre=Otra2)"
caso CN-H04 "Codigo de 5 caracteres (limite superior)" "codigo = 'ZZZZZ'" "OK: Zona creada correctamente." "$(zona codigo=ZZZZZ nombre=Otra3)"
caso CN-H05 "Codigo de 6 caracteres" "codigo = 'ZZZZZZ'" "El codigo debe tener entre 2 y 5 caracteres" "$(zona codigo=ZZZZZZ nombre=Otra4)"
caso CN-H06 "Codigo con signos" "codigo = 'Z-1'" "El codigo debe tener entre 2 y 5 caracteres" "$(zona codigo=Z-1 nombre=Otra5)"
caso CN-H07 "Codigo en minusculas se normaliza" "codigo = 'zy'" "OK: Zona creada correctamente." "$(zona codigo=zy nombre=Otra6)"
caso CN-H08 "Codigo duplicado" "codigo = 'Z1'" "Ya existe otra zona con ese codigo." "$(zona codigo=Z1 nombre=Otra7)"
caso CN-H09 "Nombre duplicado" "nombre = 'Centro'" "Ya existe otra zona con ese nombre." "$(zona codigo=ZA nombre=Centro)"
caso CN-H10 "Nombre de 2 caracteres" "nombre = 'Ab'" "El nombre de la zona debe tener al menos 3 caracteres." "$(zona codigo=ZB nombre=Ab)"
caso CN-H11 "Nombre de 60 caracteres (maximo de la columna)" "nombre = 'n' x 60" "OK: Zona creada correctamente." "$(zona codigo=ZC "nombre=$(rep n 60)")"
caso CN-H12 "Nombre de 61 caracteres debe rechazarse con un mensaje claro" "nombre = 'n' x 61" "El nombre de la zona no puede superar 60 caracteres." "$(zona codigo=ZD "nombre=$(rep n 61)")"
caso CN-H13 "Barrios de 2 caracteres" "barrios = 'ab'" "Indica al menos un barrio." "$(zona codigo=ZE nombre=Otra8 barrios=ab)"
caso CN-H14 "Barrios de 150 caracteres (maximo de la columna)" "barrios = 'b' x 150" "OK: Zona creada correctamente." "$(zona codigo=ZF nombre=Otra9 "barrios=$(rep b 150)")"
caso CN-H15 "Barrios de 151 caracteres debe rechazarse con un mensaje claro" "barrios = 'b' x 151" "Los barrios no pueden superar 150 caracteres." "$(zona codigo=ZG nombre=Otra10 "barrios=$(rep b 151)")"
caso CN-H16 "Edicion de una zona inexistente" "id = 999" "La zona indicada no existe." "$(zona id=999 codigo=ZH nombre=Otra11)"
caso CN-H17 "Edicion valida: desactivar una zona" "id = 7, activa = 0" "OK: Zona actualizada correctamente." "$(zona id=7 codigo=Z7 nombre=Sayago barrios=Sayago activa=0)"

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "I. Vehiculos (acciones/vehiculos.php)"

vehiculo() {
  local f; f=$(formulario $'id=0\nnombre=Furgon 3\nmatricula=DEF 4321\ncapacidad=45\nzona_id=4\nestado=Disponible' "$@")
  local lineas=(); local IFS=$'\n'; for l in $f; do lineas+=("$l"); done; unset IFS
  accion "$CKADM" acciones/vehiculos.php admin/vehiculos.php "${lineas[@]}"
}
caso CN-I01 "Alta de vehiculo valido" "Furgon 3 / DEF 4321 / 45 / Z4" "OK: Vehiculo creado correctamente." "$(vehiculo)"
caso CN-I02 "Matricula duplicada" "matricula = 'ABC 1234'" "Ya existe otro vehiculo con esa matricula." "$(vehiculo 'matricula=ABC 1234')"
caso CN-I03 "Matricula de 4 caracteres" "matricula = 'AB 1'" "La matricula debe tener entre 5 y 15 caracteres." "$(vehiculo 'matricula=AB 1')"
caso CN-I04 "Matricula de 5 caracteres (limite inferior)" "matricula = 'AB 12'" "OK: Vehiculo creado correctamente." "$(vehiculo 'matricula=AB 12' nombre=Moto5)"
caso CN-I05 "Matricula de 15 caracteres (limite superior)" "matricula = 'A' x 15" "OK: Vehiculo creado correctamente." "$(vehiculo "matricula=$(rep A 15)" nombre=Moto6)"
caso CN-I06 "Matricula de 16 caracteres" "matricula = 'A' x 16" "La matricula debe tener entre 5 y 15 caracteres." "$(vehiculo "matricula=$(rep A 16)" nombre=Moto7)"
caso CN-I07 "Nombre de 2 caracteres" "nombre = 'Ab'" "El nombre del vehiculo debe tener al menos 3 caracteres." "$(vehiculo nombre=Ab 'matricula=QQ 0001')"
caso CN-I08 "Nombre de 40 caracteres (maximo de la columna)" "nombre = 'n' x 40" "OK: Vehiculo creado correctamente." "$(vehiculo "nombre=$(rep n 40)" 'matricula=QQ 0002')"
caso CN-I09 "Nombre de 41 caracteres debe rechazarse con un mensaje claro" "nombre = 'n' x 41" "El nombre del vehiculo no puede superar 40 caracteres." "$(vehiculo "nombre=$(rep n 41)" 'matricula=QQ 0003')"
caso CN-I10 "Capacidad 0" "capacidad = 0" "La capacidad debe ser un numero entre 1 y 500." "$(vehiculo capacidad=0 'matricula=QQ 0004')"
caso CN-I11 "Capacidad 1 (limite inferior)" "capacidad = 1" "OK: Vehiculo creado correctamente." "$(vehiculo capacidad=1 nombre=Moto8 'matricula=QQ 0005')"
caso CN-I12 "Capacidad 500 (limite superior)" "capacidad = 500" "OK: Vehiculo creado correctamente." "$(vehiculo capacidad=500 nombre=Camion1 'matricula=QQ 0006')"
caso CN-I13 "Capacidad 501" "capacidad = 501" "La capacidad debe ser un numero entre 1 y 500." "$(vehiculo capacidad=501 'matricula=QQ 0007')"
caso CN-I14 "Capacidad decimal" "capacidad = 2.5" "La capacidad debe ser un numero entre 1 y 500." "$(vehiculo capacidad=2.5 'matricula=QQ 0008')"
caso CN-I15 "Zona inexistente" "zona_id = 999" "La zona seleccionada no existe." "$(vehiculo zona_id=999 'matricula=QQ 0009')"
caso CN-I16 "Estado fuera de la lista" "estado = 'Volando'" "El estado indicado no es valido." "$(vehiculo estado=Volando 'matricula=QQ 0010')"
caso CN-I17 "Capacidad menor que la carga asignada (Furgon 2 lleva 0 listos; se asigna el pedido 3 y se baja a 4)" "vehiculo 3 con 5 viandas asignadas, capacidad = 4" "La capacidad no puede ser menor que la carga asignada actualmente (5 viandas)." "$(sql "UPDATE pedidos SET vehiculo_id = 3 WHERE id = 3" >/dev/null; vehiculo id=3 nombre='Moto 1' 'matricula=XY 9012' capacidad=4 zona_id=3)"

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "J. Stock minimo, precios, planes y usuarios (acciones/stock_minimo.php, productos.php, planes.php, usuarios.php)"

minimo() { accion "$CKADM" acciones/stock_minimo.php admin/stock.php "minimo[1]=$1"; }
caso CN-J01 "Stock minimo 0 (limite inferior)" "minimo[1] = 0" "OK: Stock minimo actualizado para 1 platos." "$(minimo 0)"
caso CN-J02 "Stock minimo 999 (limite superior)" "minimo[1] = 999" "OK: Stock minimo actualizado para 1 platos." "$(minimo 999)"
caso CN-J03 "Stock minimo 1000 (limite superior + 1)" "minimo[1] = 1000" "El stock minimo debe ser un numero entre 0 y 999." "$(minimo 1000)"
caso CN-J04 "Stock minimo negativo" "minimo[1] = -1" "El stock minimo debe ser un numero entre 0 y 999." "$(minimo -1)"
caso CN-J05 "Stock minimo vacio" "minimo[1] = ''" "El stock minimo debe ser un numero entre 0 y 999." "$(minimo '')"
caso CN-J06 "Stock minimo con texto" "minimo[1] = 'diez'" "El stock minimo debe ser un numero entre 0 y 999." "$(minimo diez)"
caso CN-J07 "Plato inexistente" "minimo[99] = 5" "Uno de los platos indicados no existe." "$(accion "$CKADM" acciones/stock_minimo.php admin/stock.php 'minimo[99]=5')"
caso CN-J08 "Sin valores" "formulario vacio" "No se recibieron valores para actualizar." "$(accion "$CKADM" acciones/stock_minimo.php admin/stock.php x=1)"

precio() { accion "$CKADM" acciones/productos.php admin/menus.php accion=actualizar "precio[1]=$1" 'activo[1]=1'; }
caso CN-J09 "Precio 0" "precio = 0" "Los precios deben ser numeros mayores a 0." "$(precio 0)"
caso CN-J10 "Precio 0.01 (minimo positivo)" "precio = 0.01" "OK: Menu actualizado correctamente." "$(precio 0.01)"
caso CN-J11 "Precio 99999 (limite superior)" "precio = 99999" "OK: Menu actualizado correctamente." "$(precio 99999)"
caso CN-J12 "Precio 99999.01 (limite superior + 0.01)" "precio = 99999.01" "Los precios deben ser numeros mayores a 0." "$(precio 99999.01)"
caso CN-J13 "Precio negativo" "precio = -5" "Los precios deben ser numeros mayores a 0." "$(precio -5)"
caso CN-J14 "Precio con texto" "precio = 'caro'" "Los precios deben ser numeros mayores a 0." "$(precio caro)"
caso CN-J15 "Precio con coma decimal" "precio = '450,50'" "Los precios deben ser numeros mayores a 0." "$(precio '450,50')"

plato() {
  local f; f=$(formulario $'accion=crear\nnombre=Risotto de hongos\ndescripcion=Arroz arborio con hongos salteados\nprecio=520\nstock_minimo=10' "$@")
  local lineas=(); local IFS=$'\n'; for l in $f; do lineas+=("$l"); done; unset IFS
  accion "$CKADM" acciones/productos.php admin/menus.php "${lineas[@]}"
}
caso CN-J16 "Alta de plato valida" "Risotto de hongos, 520, minimo 10" "OK: Plato agregado al menu." "$(plato)"
caso CN-J17 "Plato duplicado" "nombre = 'Tarta de verdura'" "Ya existe un plato con ese nombre." "$(plato 'nombre=Tarta de verdura')"
caso CN-J18 "Nombre de plato de 2 caracteres" "nombre = 'Ab'" "El nombre del plato debe tener al menos 3 caracteres." "$(plato nombre=Ab)"
caso CN-J19 "Nombre de plato de 100 caracteres (maximo de la columna)" "nombre = 'n' x 100" "OK: Plato agregado al menu." "$(plato "nombre=$(rep n 100)")"
caso CN-J20 "Nombre de plato de 101 caracteres debe rechazarse con un mensaje claro" "nombre = 'n' x 101" "El nombre del plato no puede superar 100 caracteres." "$(plato "nombre=$(rep m 101)")"
caso CN-J21 "Descripcion de 4 caracteres" "descripcion = 'Arro'" "La descripcion debe tener al menos 5 caracteres." "$(plato nombre=Plato2 descripcion=Arro)"
caso CN-J22 "Descripcion de 255 caracteres (maximo de la columna)" "descripcion = 'd' x 255" "OK: Plato agregado al menu." "$(plato nombre=Plato3 "descripcion=$(rep d 255)")"
caso CN-J23 "Descripcion de 256 caracteres debe rechazarse con un mensaje claro" "descripcion = 'd' x 256" "La descripcion no puede superar 255 caracteres." "$(plato nombre=Plato4 "descripcion=$(rep d 256)")"
caso CN-J24 "Plato nuevo sin foto se muestra con la imagen de reemplazo" "menu del cliente" "sin-imagen.svg" "$(curl -s -b "$CKCLI" "$BASE/cliente/index.php" | grep -o 'sin-imagen.svg' | head -1)"

plan() { accion "$CKADM" acciones/planes.php admin/menus.php id=1 "viandas=$1" activo=1; }
caso CN-J25 "Plan con 0 viandas" "viandas = 0" "La cantidad de viandas debe estar entre 1 y 60." "$(plan 0)"
caso CN-J26 "Plan con 1 vianda (limite inferior)" "viandas = 1" "OK: Plan actualizado correctamente." "$(plan 1)"
caso CN-J27 "Plan con 60 viandas (limite superior)" "viandas = 60" "OK: Plan actualizado correctamente." "$(plan 60)"
caso CN-J28 "Plan con 61 viandas" "viandas = 61" "La cantidad de viandas debe estar entre 1 y 60." "$(plan 61)"
caso CN-J29 "Plan inexistente" "id = 99" "El plan indicado no existe." "$(accion "$CKADM" acciones/planes.php admin/menus.php id=99 viandas=5)"

usuario() {
  local f; f=$(formulario $'accion=crear\nnombre=Sofia Perez\nusuario=sofia\nemail=sofia@atrax.uy\ntelefono=+598 99 555 666\nrol_id=2\ncontrasena=Operador2026' "$@")
  local lineas=(); local IFS=$'\n'; for l in $f; do lineas+=("$l"); done; unset IFS
  accion "$CKADM" acciones/usuarios.php admin/usuarios.php "${lineas[@]}"
}
caso CN-J30 "Alta de operador valida" "Sofia Perez / rol 2" "OK: Usuario creado correctamente." "$(usuario)"
caso CN-J31 "Alta con rol cliente (3)" "rol_id = 3" "Solo se pueden crear usuarios con rol administrador u operador." "$(usuario usuario=sofia2 email=s2@atrax.uy rol_id=3)"
caso CN-J32 "Alta con rol inexistente" "rol_id = 99" "Solo se pueden crear usuarios con rol administrador u operador." "$(usuario usuario=sofia3 email=s3@atrax.uy rol_id=99)"
caso CN-J33 "Contrasena de 7 caracteres" "contrasena = 'Abcdef1'" "La contrasena debe tener al menos 8 caracteres." "$(usuario usuario=sofia4 email=s4@atrax.uy contrasena=Abcdef1)"
caso CN-J34 "Usuario duplicado" "usuario = 'sofia'" "El nombre de usuario ya esta en uso." "$(usuario email=s5@atrax.uy)"
caso CN-J35 "Autodesactivacion del administrador" "id = 1 (admin)" "No podes desactivar tu propia cuenta." "$(accion "$CKADM" acciones/usuarios.php admin/usuarios.php accion=estado id=1 activo=0)"
caso CN-J36 "Desactivar a otro usuario" "id = 3 (cocina)" "OK: Usuario desactivado." "$(accion "$CKADM" acciones/usuarios.php admin/usuarios.php accion=estado id=3 activo=0)"
caso CN-J37 "Un usuario desactivado no puede entrar" "cocina / Operador123" "La cuenta esta inactiva." "$(intento cocina Operador123)"
caso CN-J38 "Usuario inexistente" "id = 999" "El usuario indicado no existe." "$(accion "$CKADM" acciones/usuarios.php admin/usuarios.php accion=estado id=999 activo=0)"
sql "UPDATE usuarios SET activo = 1 WHERE id = 3" >/dev/null

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "K. Seguridad y control de acceso (conjetura de errores)"

CKADM=$(entrar admin Admin123); CKOPE=$(entrar cocina Operador123); CKCLI=$(entrar julio Cliente123)
sin_token=""
for a in zonas vehiculos productos planes stock_minimo usuarios; do
  c=$(curl -s -o /dev/null -w '%{http_code}' -b "$CKADM" -X POST -d 'token=falso' "$BASE/acciones/$a.php"); sin_token="$sin_token $a:$c"
done
for a in produccion stock pedidos distribucion; do
  c=$(curl -s -o /dev/null -w '%{http_code}' -b "$CKOPE" -X POST -d 'token=falso' "$BASE/acciones/$a.php"); sin_token="$sin_token $a:$c"
done
c=$(curl -s -o /dev/null -w '%{http_code}' -b "$CKCLI" -X POST -d 'token=falso' "$BASE/acciones/pedido_cliente.php"); sin_token="$sin_token pedido_cliente:$c"
for a in acceso registro; do
  c=$(curl -s -o /dev/null -w '%{http_code}' -X POST -d 'token=falso' "$BASE/acciones/$a.php"); sin_token="$sin_token $a:$c"
done
caso CN-K01 "Todas las acciones rechazan un token CSRF falso (13 acciones)" "POST con token=falso" "zonas:400 vehiculos:400 productos:400 planes:400 stock_minimo:400 usuarios:400 produccion:400 stock:400 pedidos:400 distribucion:400 pedido_cliente:400 acceso:400 registro:400" "$(echo $sin_token | sed 's/^ //')"

sin_token=""
for a in zonas vehiculos productos planes stock_minimo usuarios produccion stock pedidos distribucion pedido_cliente; do
  c=$(curl -s -o /dev/null -w '%{http_code}' -X POST -d 'token=x' "$BASE/acciones/$a.php"); sin_token="$sin_token $a:$c"
done
caso CN-K02 "Las acciones no se pueden usar sin sesion" "POST sin cookie" "zonas:302 vehiculos:302 productos:302 planes:302 stock_minimo:302 usuarios:302 produccion:302 stock:302 pedidos:302 distribucion:302 pedido_cliente:302" "$(echo $sin_token | sed 's/^ //')"

pantallas=""
for p in admin/index.php admin/pedidos.php admin/stock.php admin/menus.php admin/zonas.php admin/vehiculos.php admin/estadisticas.php admin/usuarios.php operador/index.php operador/produccion.php operador/stock.php operador/pedidos.php operador/distribucion.php cliente/index.php cliente/pedido.php cliente/pedidos.php; do
  c=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/$p"); pantallas="$pantallas $c"
done
caso CN-K03 "Las 16 pantallas internas redirigen al login sin sesion" "GET sin cookie" "302 302 302 302 302 302 302 302 302 302 302 302 302 302 302 302" "$(echo $pantallas | sed 's/^ //')"

rol() { # cookie, area
  local r=""
  for p in $2; do r="$r $(curl -s -o /dev/null -w '%{http_code}' -b "$1" "$BASE/$p")"; done
  echo $r
}
ADM="admin/index.php admin/pedidos.php admin/stock.php admin/menus.php admin/zonas.php admin/vehiculos.php admin/estadisticas.php admin/usuarios.php"
OPE="operador/index.php operador/produccion.php operador/stock.php operador/pedidos.php operador/distribucion.php"
CLI="cliente/index.php cliente/pedido.php cliente/pedidos.php"
caso CN-K04 "El administrador entra a sus 8 pantallas" "GET con cookie de admin" "200 200 200 200 200 200 200 200" "$(rol "$CKADM" "$ADM")"
caso CN-K05 "El administrador no entra a las pantallas del operador ni del cliente" "GET con cookie de admin" "403 403 403 403 403 403 403 403" "$(rol "$CKADM" "$OPE $CLI")"
caso CN-K06 "El operador entra a sus 5 pantallas" "GET con cookie de operador" "200 200 200 200 200" "$(rol "$CKOPE" "$OPE")"
caso CN-K07 "El operador no entra a las pantallas del administrador ni del cliente" "GET con cookie de operador" "403 403 403 403 403 403 403 403 403 403 403" "$(rol "$CKOPE" "$ADM $CLI")"
caso CN-K08 "El cliente entra a sus 3 pantallas" "GET con cookie de cliente" "200 200 200" "$(rol "$CKCLI" "$CLI")"
caso CN-K09 "El cliente no entra a las pantallas del administrador ni del operador" "GET con cookie de cliente" "403 403 403 403 403 403 403 403 403 403 403 403 403" "$(rol "$CKCLI" "$ADM $OPE")"
caso CN-K10 "Las acciones del administrador rechazan al operador y al cliente" "POST con token valido pero rol equivocado" "403 403" "$(a=$(enviar "$CKOPE" acciones/zonas.php operador/index.php codigo=ZZ nombre=Hack barrios=Hack | cut -d' ' -f1); b=$(enviar "$CKCLI" acciones/zonas.php cliente/index.php codigo=ZZ nombre=Hack barrios=Hack | cut -d' ' -f1); echo "$a $b")"
caso CN-K11 "Las acciones del operador rechazan al administrador y al cliente" "POST con token valido pero rol equivocado" "403 403" "$(a=$(enviar "$CKADM" acciones/pedidos.php admin/index.php id=5 estado=Cancelado | cut -d' ' -f1); b=$(enviar "$CKCLI" acciones/pedidos.php cliente/index.php id=5 estado=Cancelado | cut -d' ' -f1); echo "$a $b")"
caso CN-K12 "El pedido solo lo puede armar un cliente" "POST de pedido con cookie de operador" "403" "$(enviar "$CKOPE" acciones/pedido_cliente.php operador/index.php plan_id=1 | cut -d' ' -f1)"
caso CN-K13 "Las acciones solo aceptan POST" "GET a acciones/zonas.php" "405" "$(curl -s -o /dev/null -w '%{http_code}' -b "$CKADM" "$BASE/acciones/zonas.php")"

sql "UPDATE usuarios SET nombre = '\"><img src=x onerror=alert(1)>' WHERE id = 5" >/dev/null
ck2=$(entrar julio Cliente123)
cabecera=$(curl -s -b "$ck2" "$BASE/cliente/index.php")
if printf '%s' "$cabecera" | grep -q '<img src=x onerror=alert(1)>'; then xss="el nombre se imprime sin escapar"; else xss="el nombre se imprime escapado"; fi
caso CN-K14 "El nombre del usuario se escapa en la barra superior (XSS)" "nombre con comillas y etiqueta img" "el nombre se imprime escapado" "$xss"
sql "UPDATE usuarios SET nombre = 'Julio Rodriguez' WHERE id = 5" >/dev/null

sql_ok=$(curl -s -o /dev/null -w '%{http_code}' -b "$CKADM" -G --data-urlencode "estado=Pendiente' OR '1'='1" "$BASE/admin/pedidos.php")
caso CN-K15 "Filtro de pedidos con inyeccion SQL" "estado = Pendiente' OR '1'='1" "200" "$sql_ok"

exposicion=""
for p in bd/atrax.sql docs/pruebas.sh docs/pruebas.md README.md; do
  exposicion="$exposicion $p:$(curl -s -o /dev/null -w '%{http_code}' "$ESTATICO/$p")"
done
caso CN-K16 "Los archivos internos de texto (script de la base, documentos) no se pueden descargar desde el navegador" "GET de bd/atrax.sql, docs/pruebas.sh, docs/pruebas.md, README.md" "bd/atrax.sql:403 docs/pruebas.sh:403 docs/pruebas.md:403 README.md:403" "$(echo $exposicion | sed 's/^ //')"

directo=""
for p in includes/config.php includes/funciones.php includes/conexion.php modelo/usuarios.php modelo/pedidos.php; do
  directo="$directo $p:$(curl -s "$BASE/$p" | wc -c | tr -d ' ')"
done
caso CN-K17 "Los archivos PHP internos abiertos directo no devuelven contenido ni errores" "GET de includes/config.php, includes/funciones.php, includes/conexion.php, modelo/usuarios.php, modelo/pedidos.php (bytes devueltos)" "includes/config.php:0 includes/funciones.php:0 includes/conexion.php:0 modelo/usuarios.php:0 modelo/pedidos.php:0" "$(echo $directo | sed 's/^ //')"

caso CN-K18 "El volcado de la base no contiene datos de usuarios accesibles" "primeros bytes de bd/atrax.sql" "sin contenido" "$(curl -s "$ESTATICO/bd/atrax.sql" | grep -c 'INSERT INTO usuarios' | sed 's/^0$/sin contenido/;s/^[1-9].*/expone INSERT INTO usuarios con los hashes de contrasena/')"

faltan=""
for pagina in login.php registro.php admin/index.php no-existe.php; do
  cab=$(curl -s -D - -o /dev/null -b "$CKADM" "$BASE/$pagina" | tr -d '\r')
  for h in X-Frame-Options X-Content-Type-Options Content-Security-Policy Referrer-Policy; do
    printf '%s' "$cab" | grep -qi "^$h:" || faltan="$faltan $pagina:$h"
  done
done
caso CN-K19 "Cabeceras de seguridad HTTP presentes en paginas publicas, internas y de error" "login.php, registro.php, admin/index.php (con sesion) y una direccion inexistente" "ninguna falta" "$([ -z "$faltan" ] && echo 'ninguna falta' || echo "faltan:$faltan")"

caso CN-K20 "Una direccion inexistente muestra la pantalla 404" "GET /no-existe.php" "404" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/no-existe.php")"
caso CN-K21 "La pantalla 404 tiene el titulo esperado" "GET /no-existe.php" "Pagina no encontrada" "$(curl -s "$BASE/no-existe.php" | grep -o '<title>[^<]*' | sed 's/<title>//;s/ —.*//')"

if [ -n "$ATRAX_URL_MANTENIMIENTO" ]; then
  caso CN-K22 "Modo mantenimiento: 503 con reintento automatico" "ATRAX_MANTENIMIENTO=1" "503 Retry-After" "$(curl -s -D - -o /dev/null "$ATRAX_URL_MANTENIMIENTO/login.php" | tr -d '\r' | awk 'NR==1{c=$2} tolower($1)=="retry-after:"{r=$1} END{print c, r}' | sed 's/retry-after:/Retry-After/I')"
fi

caso CN-K23 "Cadena enorme en un campo de texto no produce un error 500" "nombre de zona de 100000 caracteres" "no puede superar" "$(accion "$CKADM" acciones/zonas.php admin/zonas.php id=0 codigo=ZQ "nombre=$(rep n 100000)" barrios=Barrio | cut -c1-80)"

caso CN-K24 "Los programas de prueba de docs/ no se ejecutan desde el navegador" "GET docs/pruebas-caja-blanca.php (escribe en la base si se ejecuta)" "404 0" "$(curl -s -o /dev/null -w '%{http_code} %{size_download}' "$BASE/docs/pruebas-caja-blanca.php")"

bloqueadas=""
for p in includes/config.php modelo/usuarios.php bd/ docs/ docs/pruebas-caja-blanca.php; do
  bloqueadas="$bloqueadas $p:$(curl -s -o /dev/null -w '%{http_code}' "$ESTATICO/$p")"
done
caso CN-K25 "Las carpetas internas (includes, modelo, bd, docs) responden 403" "GET de includes/config.php, modelo/usuarios.php, bd/, docs/ y docs/pruebas-caja-blanca.php" "includes/config.php:403 modelo/usuarios.php:403 bd/:403 docs/:403 docs/pruebas-caja-blanca.php:403" "$(echo $bloqueadas | sed 's/^ //')"

# ------------------------------------------------------------------------------------------------
reset_bd
seccion "L. Consulta asincrona de pedidos (api/pedidos.php, la usa admin/pedidos.php con fetch)"

api() { curl -s -w ' [%{http_code}]' -b "$1" "$BASE/api/pedidos.php?$2"; }
total_api() { api "$1" "$2" | grep -o '"total":[0-9]*' | head -1 | sed 's/"total"://'; }
total_sql() { sql "SELECT COUNT(*) FROM pedidos p INNER JOIN clientes c ON c.id = p.cliente_id INNER JOIN usuarios u ON u.id = c.usuario_id WHERE $1"; }

caso CN-L01 "Sin sesion responde 401 en JSON, no una pagina de login" "GET api/pedidos.php sin cookie" "\"error\":\"Tu sesion vencio. Vuelve a iniciar sesion.\"} [401]" "$(api "$(anonimo)" '')"
caso CN-L02 "Un cliente no puede consultar los pedidos de todos" "GET api/pedidos.php con la sesion de julio" "[403]" "$(api "$CKCLI" '')"
caso CN-L03 "Un operador tampoco (la consulta es del administrador)" "GET api/pedidos.php con la sesion de cocina" "[403]" "$(api "$CKOPE" '')"
caso CN-L04 "Sin filtros devuelve todos los pedidos" "GET api/pedidos.php (administrador)" "$(sql 'SELECT COUNT(*) FROM pedidos')" "$(total_api "$CKADM" '')"
caso CN-L05 "Filtro por estado" "estado=Pendiente" "$(total_sql "p.estado = 'Pendiente'")" "$(total_api "$CKADM" 'estado=Pendiente')"
caso CN-L06 "Filtro por zona" "zona_id=2" "$(total_sql 'p.zona_id = 2')" "$(total_api "$CKADM" 'zona_id=2')"
caso CN-L07 "Busqueda por nombre de cliente (sin importar mayusculas)" "buscar=JULIO" "$(total_sql "u.nombre LIKE '%julio%'")" "$(total_api "$CKADM" 'buscar=JULIO')"
caso CN-L08 "Busqueda por numero de pedido con #" "buscar=#5" "1" "$(total_api "$CKADM" 'buscar=%235')"
caso CN-L09 "Los tres filtros juntos" "estado=Pendiente, zona_id=2, buscar=julio" "$(total_sql "p.estado = 'Pendiente' AND p.zona_id = 2 AND u.nombre LIKE '%julio%'")" "$(total_api "$CKADM" 'estado=Pendiente&zona_id=2&buscar=julio')"
caso CN-L10 "Estado inexistente" "estado=Volando" "\"error\":\"El estado indicado no es valido.\"} [422]" "$(api "$CKADM" 'estado=Volando')"
caso CN-L11 "Zona que no es un numero" "zona_id=abc" "\"error\":\"La zona indicada no es valida.\"} [422]" "$(api "$CKADM" 'zona_id=abc')"
caso CN-L12 "Busqueda de 101 caracteres (maximo + 1)" "buscar = 'a' x 101" "\"error\":\"La busqueda no puede superar 100 caracteres.\"} [422]" "$(api "$CKADM" "buscar=$(rep a 101)")"
caso CN-L13 "Busqueda de 100 caracteres (maximo)" "buscar = 'a' x 100" "\"total\":0,\"pedidos\":[]} [200]" "$(api "$CKADM" "buscar=$(rep a 100)")"
caso CN-L14 "Inyeccion SQL en la busqueda" "buscar=' OR '1'='1" "\"total\":0,\"pedidos\":[]} [200]" "$(api "$CKADM" "buscar=%27%20OR%20%271%27%3D%271")"
caso CN-L15 "El comodin % de LIKE se trata como texto" "buscar=%" "\"total\":0,\"pedidos\":[]} [200]" "$(api "$CKADM" 'buscar=%25')"
caso CN-L16 "Un POST no esta permitido" "POST api/pedidos.php" "[405]" "$(curl -s -w ' [%{http_code}]' -X POST -b "$CKADM" "$BASE/api/pedidos.php")"
caso CN-L17 "La respuesta es JSON y no se guarda en cache" "cabeceras de api/pedidos.php" "application/json; charset=utf-8 no-store" "$(curl -s -D - -o /dev/null -b "$CKADM" "$BASE/api/pedidos.php" | tr -d '\r' | awk 'tolower($1)=="content-type:"{t=$2" "$3} tolower($1)=="cache-control:"{c=$2} END{print t, c}')"
pagina=$(curl -s -b "$CKADM" "$BASE/admin/pedidos.php?estado=Pendiente&buscar=julio" | tr '\n' ' ')
caso CN-L18 "La pagina sin JavaScript da el mismo resultado que la API" "admin/pedidos.php?estado=Pendiente&buscar=julio" "Resultados ($(total_api "$CKADM" 'estado=Pendiente&buscar=julio'))" "$(printf '%s' "$pagina" | grep -o 'Resultados ([^)]*)' | sed 's/<[^>]*>//g' | head -1)"
caso CN-L19 "La pagina conserva lo escrito en el buscador" "admin/pedidos.php?buscar=julio" "value=\"julio\"" "$(printf '%s' "$pagina" | grep -o 'value="julio"' | head -1)"

echo
echo "TOTAL: $((PASA + FALLA)) casos, $PASA pasan, $FALLA fallan."
rm -rf "$TMP"
