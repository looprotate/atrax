#!/bin/bash

BASE=${ATRAX_URL:-http://localhost/atrax/sistema}
SQL=${ATRAX_SQL:-"mysql -u root atrax -e"}
TMP=$(mktemp -d)

entrar() {
  local ck=$TMP/ck_$1.txt
  rm -f "$ck"
  local t=$(curl -s -c "$ck" $BASE/login.php | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -b "$ck" -c "$ck" -X POST --data-urlencode "token=$t" \
       --data-urlencode "usuario=$1" --data-urlencode "contrasena=$2" $BASE/acciones/acceso.php
  echo "$ck"
}

tok() { curl -s -b "$1" "$2" | grep -o 'name="token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
errores() { curl -s -b "$1" "$2" | tr '\n' ' ' | grep -o '<li>[^<]*</li>' | sed 's/<[^>]*>//g'; }
exito() { curl -s -b "$1" "$2" | tr '\n' ' ' | grep -o 'data-mensaje="exito">[^<]*' | sed 's/.*">//'; }
titulo() { echo; echo "=== $1"; }

titulo "P01 Login correcto por rol"
for par in "admin:Admin123" "cocina:Operador123" "julio:Cliente123"; do
  usuario=${par%%:*}; clave=${par##*:}
  rm -f $TMP/t.txt
  t=$(curl -s -c $TMP/t.txt $BASE/login.php | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
  echo "  $usuario -> $(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b $TMP/t.txt -c $TMP/t.txt \
      -X POST -d "token=$t&usuario=$usuario&contrasena=$clave" $BASE/acciones/acceso.php)"
done

titulo "P02 Login incorrecto"
rm -f $TMP/t.txt
t=$(curl -s -c $TMP/t.txt $BASE/login.php | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b $TMP/t.txt -c $TMP/t.txt -X POST -d "token=$t&usuario=admin&contrasena=12345678" $BASE/acciones/acceso.php
errores $TMP/t.txt $BASE/login.php

titulo "P03 Inyeccion SQL en el login"
rm -f $TMP/t.txt
t=$(curl -s -c $TMP/t.txt $BASE/login.php | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b $TMP/t.txt -c $TMP/t.txt -X POST --data-urlencode "token=$t" \
     --data-urlencode "usuario=admin' OR '1'='1" --data-urlencode "contrasena=x" $BASE/acciones/acceso.php
errores $TMP/t.txt $BASE/login.php

titulo "P04 Acceso sin sesion"
curl -s -o /dev/null -w '  admin/index.php -> %{http_code} %{redirect_url}\n' $BASE/admin/index.php

titulo "P05 Acceso con rol incorrecto"
CKC=$(entrar julio Cliente123)
echo "  cliente -> admin/index.php: $(curl -s -o /dev/null -w '%{http_code}' -b $CKC $BASE/admin/index.php)"
echo "  cliente -> operador/stock.php: $(curl -s -o /dev/null -w '%{http_code}' -b $CKC $BASE/operador/stock.php)"

titulo "P06 Logout"
CKA=$(entrar admin Admin123)
echo "  con sesion: $(curl -s -o /dev/null -w '%{http_code}' -b $CKA $BASE/admin/index.php)"
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$(tok $CKA $BASE/admin/index.php)" $BASE/logout.php
echo "  tras logout: $(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b $CKA $BASE/admin/index.php)"

titulo "P07 Token CSRF invalido"
CKA=$(entrar admin Admin123)
echo "  POST sin token valido: $(curl -s -o /dev/null -w '%{http_code}' -b $CKA -X POST -d 'token=falso' $BASE/acciones/zonas.php)"

titulo "P08 Usuario inactivo"
$SQL "UPDATE usuarios SET activo = 0 WHERE usuario = 'lucia';"
rm -f $TMP/t.txt
t=$(curl -s -c $TMP/t.txt $BASE/login.php | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b $TMP/t.txt -c $TMP/t.txt -X POST -d "token=$t&usuario=lucia&contrasena=Cliente123" $BASE/acciones/acceso.php
errores $TMP/t.txt $BASE/login.php
$SQL "UPDATE usuarios SET activo = 1 WHERE usuario = 'lucia';"

titulo "P09 Registro de cliente valido"
rm -f $TMP/reg.txt
t=$(curl -s -c $TMP/reg.txt $BASE/registro.php | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b $TMP/reg.txt -c $TMP/reg.txt -X POST --data-urlencode "token=$t" \
  --data-urlencode "nombre=Martina Silva" --data-urlencode "usuario=martina" --data-urlencode "email=martina@correo.com" \
  --data-urlencode "telefono=+598 99 777 888" --data-urlencode "direccion=Av. Italia 3400 apto 2" \
  --data-urlencode "tipo=Particular" --data-urlencode "zona_id=3" \
  --data-urlencode "contrasena=Martina2026" --data-urlencode "contrasena2=Martina2026" $BASE/acciones/registro.php
exito $TMP/reg.txt $BASE/login.php

titulo "P10 Registro con usuario y correo duplicados"
rm -f $TMP/reg2.txt
t=$(curl -s -c $TMP/reg2.txt $BASE/registro.php | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b $TMP/reg2.txt -c $TMP/reg2.txt -X POST --data-urlencode "token=$t" \
  --data-urlencode "nombre=Otro Usuario" --data-urlencode "usuario=martina" --data-urlencode "email=martina@correo.com" \
  --data-urlencode "telefono=+598 99 777 888" --data-urlencode "direccion=Otra direccion 123" \
  --data-urlencode "tipo=Particular" --data-urlencode "zona_id=3" \
  --data-urlencode "contrasena=Martina2026" --data-urlencode "contrasena2=Martina2026" $BASE/acciones/registro.php
errores $TMP/reg2.txt $BASE/registro.php

titulo "P11 Registro con datos invalidos"
rm -f $TMP/reg3.txt
t=$(curl -s -c $TMP/reg3.txt $BASE/registro.php | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b $TMP/reg3.txt -c $TMP/reg3.txt -X POST --data-urlencode "token=$t" \
  --data-urlencode "nombre=Al" --data-urlencode "usuario=ab" --data-urlencode "email=correo-invalido" \
  --data-urlencode "telefono=abc" --data-urlencode "direccion=xy" --data-urlencode "tipo=Otro" \
  --data-urlencode "zona_id=999" --data-urlencode "contrasena=123" --data-urlencode "contrasena2=456" $BASE/acciones/registro.php
errores $TMP/reg3.txt $BASE/registro.php

titulo "P12 Cliente arma un pedido valido"
CKC=$(entrar julio Cliente123)
t=$(tok $CKC $BASE/cliente/pedido.php)
destino=$(curl -s -o /dev/null -w '%{redirect_url}' -b $CKC -c $CKC -X POST --data-urlencode "token=$t" \
  --data-urlencode "plan_id=1" --data-urlencode "fecha_entrega=$(date -v+2d +%Y-%m-%d 2>/dev/null || date -d '+2 days' +%Y-%m-%d)" \
  --data-urlencode "cantidad[1]=2" --data-urlencode "cantidad[3]=3" $BASE/acciones/pedido_cliente.php)
exito $CKC "$destino"
$SQL "SELECT m.tipo, m.cantidad, l.numero_lote FROM movimientos_stock m JOIN lotes l ON l.id = m.lote_id WHERE m.pedido_id = (SELECT MAX(id) FROM pedidos);"

titulo "P13 Cliente pide mas de lo disponible"
t=$(tok $CKC $BASE/cliente/pedido.php)
curl -s -o /dev/null -b $CKC -c $CKC -X POST --data-urlencode "token=$t" --data-urlencode "plan_id=3" \
  --data-urlencode "fecha_entrega=$(date -v+2d +%Y-%m-%d 2>/dev/null || date -d '+2 days' +%Y-%m-%d)" \
  --data-urlencode "cantidad[4]=20" $BASE/acciones/pedido_cliente.php
errores $CKC $BASE/cliente/pedido.php

titulo "P14 Cantidad distinta a la del plan"
t=$(tok $CKC $BASE/cliente/pedido.php)
curl -s -o /dev/null -b $CKC -c $CKC -X POST --data-urlencode "token=$t" --data-urlencode "plan_id=1" \
  --data-urlencode "fecha_entrega=$(date -v+2d +%Y-%m-%d 2>/dev/null || date -d '+2 days' +%Y-%m-%d)" \
  --data-urlencode "cantidad[1]=2" $BASE/acciones/pedido_cliente.php
errores $CKC $BASE/cliente/pedido.php

titulo "P15 Fecha de entrega invalida"
t=$(tok $CKC $BASE/cliente/pedido.php)
curl -s -o /dev/null -b $CKC -c $CKC -X POST --data-urlencode "token=$t" --data-urlencode "plan_id=1" \
  --data-urlencode "fecha_entrega=$(date -v-1d +%Y-%m-%d 2>/dev/null || date -d '-1 day' +%Y-%m-%d)" \
  --data-urlencode "cantidad[1]=5" $BASE/acciones/pedido_cliente.php
errores $CKC $BASE/cliente/pedido.php

titulo "P16 Cliente consulta el pedido de otro cliente"
curl -s -o /dev/null -w '  %{http_code} %{redirect_url}\n' -b $CKC "$BASE/cliente/seguimiento.php?id=4"
errores $CKC $BASE/cliente/pedidos.php

titulo "P17 Operador registra produccion"
CKO=$(entrar cocina Operador123)
t=$(tok $CKO $BASE/operador/produccion.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "producto_id=2" \
  --data-urlencode "cantidad=25" --data-urlencode "fecha_vencimiento=$(date -v+4d +%Y-%m-%d 2>/dev/null || date -d '+4 days' +%Y-%m-%d)" \
  $BASE/acciones/produccion.php
exito $CKO $BASE/operador/produccion.php

titulo "P18 Produccion con datos invalidos"
t=$(tok $CKO $BASE/operador/produccion.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "producto_id=999" \
  --data-urlencode "cantidad=0" --data-urlencode "fecha_vencimiento=$(date -v-2d +%Y-%m-%d 2>/dev/null || date -d '-2 days' +%Y-%m-%d)" \
  $BASE/acciones/produccion.php
errores $CKO $BASE/operador/produccion.php

titulo "P19 Salida mayor al disponible del lote"
t=$(tok $CKO $BASE/operador/stock.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "lote_id=9" \
  --data-urlencode "tipo=Salida" --data-urlencode "cantidad=50" --data-urlencode "motivo=Prueba de control" $BASE/acciones/stock.php
errores $CKO $BASE/operador/stock.php

titulo "P20 Salida de stock valida"
t=$(tok $CKO $BASE/operador/stock.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "lote_id=9" \
  --data-urlencode "tipo=Salida" --data-urlencode "cantidad=2" --data-urlencode "motivo=Control de calidad" $BASE/acciones/stock.php
exito $CKO $BASE/operador/stock.php
$SQL "SELECT numero_lote, disponible, estado FROM lotes WHERE id = 9;"

titulo "P21 Transicion de estado invalida"
t=$(tok $CKO $BASE/operador/pedidos.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "accion=estado" \
  --data-urlencode "id=5" --data-urlencode "estado=Entregado" $BASE/acciones/pedidos.php
errores $CKO $BASE/operador/pedidos.php

titulo "P22 Transiciones validas Pendiente -> Preparando -> Listo"
for estado in Preparando Listo; do
  t=$(tok $CKO $BASE/operador/pedidos.php)
  curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "accion=estado" \
    --data-urlencode "id=5" --data-urlencode "estado=$estado" $BASE/acciones/pedidos.php
  exito $CKO $BASE/operador/pedidos.php
done
$SQL "SELECT estado_anterior, estado_nuevo FROM pedido_estados WHERE pedido_id = 5 ORDER BY id;"

titulo "P23 Asignacion a vehiculo de otra zona"
t=$(tok $CKO $BASE/operador/distribucion.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "accion=asignar" \
  --data-urlencode "pedido_id=5" --data-urlencode "vehiculo_id=3" $BASE/acciones/distribucion.php
errores $CKO $BASE/operador/distribucion.php

titulo "P24 Asignacion sin capacidad suficiente"
$SQL "UPDATE vehiculos SET capacidad = 3 WHERE id = 2;"
t=$(tok $CKO $BASE/operador/distribucion.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "accion=asignar" \
  --data-urlencode "pedido_id=5" --data-urlencode "vehiculo_id=2" $BASE/acciones/distribucion.php
errores $CKO $BASE/operador/distribucion.php
$SQL "UPDATE vehiculos SET capacidad = 60 WHERE id = 2;"

titulo "P25 Asignacion valida y despacho"
t=$(tok $CKO $BASE/operador/distribucion.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "accion=asignar" \
  --data-urlencode "pedido_id=5" --data-urlencode "vehiculo_id=2" $BASE/acciones/distribucion.php
exito $CKO $BASE/operador/distribucion.php
t=$(tok $CKO $BASE/operador/pedidos.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "accion=estado" \
  --data-urlencode "id=5" --data-urlencode "estado=En distribucion" $BASE/acciones/pedidos.php
exito $CKO $BASE/operador/pedidos.php

titulo "P26 Despacho sin vehiculo asignado"
t=$(tok $CKO $BASE/operador/pedidos.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "accion=estado" \
  --data-urlencode "id=3" --data-urlencode "estado=En distribucion" $BASE/acciones/pedidos.php
errores $CKO $BASE/operador/pedidos.php

titulo "P27 Cancelacion devuelve el stock"
$SQL "SELECT numero_lote, disponible, estado FROM lotes WHERE id = 4;"
t=$(tok $CKO $BASE/operador/pedidos.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "accion=estado" \
  --data-urlencode "id=4" --data-urlencode "estado=Cancelado" $BASE/acciones/pedidos.php
exito $CKO $BASE/operador/pedidos.php
$SQL "SELECT numero_lote, disponible, estado FROM lotes WHERE id = 4;"
$SQL "SELECT tipo, cantidad, motivo FROM movimientos_stock WHERE pedido_id = 4 ORDER BY id;"

titulo "P28 Vehiculo en mantenimiento"
$SQL "UPDATE pedidos SET zona_id = 6, vehiculo_id = NULL, estado = 'Listo' WHERE id = 5;"
t=$(tok $CKO $BASE/operador/distribucion.php)
curl -s -o /dev/null -b $CKO -c $CKO -X POST --data-urlencode "token=$t" --data-urlencode "accion=asignar" \
  --data-urlencode "pedido_id=5" --data-urlencode "vehiculo_id=4" $BASE/acciones/distribucion.php
errores $CKO $BASE/operador/distribucion.php

titulo "P29 Admin crea una zona"
CKA=$(entrar admin Admin123)
t=$(tok $CKA $BASE/admin/zonas.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "id=0" \
  --data-urlencode "codigo=Z8" --data-urlencode "nombre=Buceo" --data-urlencode "barrios=Buceo, Parque Batlle" $BASE/acciones/zonas.php
exito $CKA $BASE/admin/zonas.php

titulo "P30 Zona duplicada"
t=$(tok $CKA $BASE/admin/zonas.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "id=0" \
  --data-urlencode "codigo=Z8" --data-urlencode "nombre=Buceo" --data-urlencode "barrios=Otro" $BASE/acciones/zonas.php
errores $CKA $BASE/admin/zonas.php

titulo "P31 Admin edita una zona"
id=$($SQL "SELECT id FROM zonas WHERE codigo = 'Z8';" | tail -1 | tr -cd '0-9')
t=$(tok $CKA "$BASE/admin/zonas.php?editar=$id")
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "id=$id" \
  --data-urlencode "codigo=Z8" --data-urlencode "nombre=Buceo" \
  --data-urlencode "barrios=Buceo, Parque Batlle, Villa Dolores" --data-urlencode "activa=0" $BASE/acciones/zonas.php
exito $CKA $BASE/admin/zonas.php
$SQL "SELECT codigo, nombre, barrios, activa FROM zonas WHERE id = $id;"

titulo "P32 Vehiculo con matricula duplicada"
t=$(tok $CKA $BASE/admin/vehiculos.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "id=0" \
  --data-urlencode "nombre=Furgon 3" --data-urlencode "matricula=ABC 1234" --data-urlencode "capacidad=40" \
  --data-urlencode "zona_id=4" --data-urlencode "estado=Disponible" $BASE/acciones/vehiculos.php
errores $CKA $BASE/admin/vehiculos.php

titulo "P33 Vehiculo con datos invalidos"
t=$(tok $CKA $BASE/admin/vehiculos.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "id=0" \
  --data-urlencode "nombre=X" --data-urlencode "matricula=AB" --data-urlencode "capacidad=-5" \
  --data-urlencode "zona_id=999" --data-urlencode "estado=Volando" $BASE/acciones/vehiculos.php
errores $CKA $BASE/admin/vehiculos.php

titulo "P34 Vehiculo valido"
t=$(tok $CKA $BASE/admin/vehiculos.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "id=0" \
  --data-urlencode "nombre=Furgon 3" --data-urlencode "matricula=DEF 4321" --data-urlencode "capacidad=45" \
  --data-urlencode "zona_id=4" --data-urlencode "estado=Disponible" $BASE/acciones/vehiculos.php
exito $CKA $BASE/admin/vehiculos.php

titulo "P35 Admin actualiza el stock minimo"
t=$(tok $CKA $BASE/admin/stock.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "minimo[1]=15" \
  --data-urlencode "minimo[2]=15" --data-urlencode "minimo[3]=10" --data-urlencode "minimo[4]=2" \
  --data-urlencode "minimo[5]=15" $BASE/acciones/stock_minimo.php
exito $CKA $BASE/admin/stock.php
$SQL "SELECT nombre, stock_minimo FROM productos ORDER BY id;"

titulo "P36 Stock minimo invalido"
t=$(tok $CKA $BASE/admin/stock.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "minimo[1]=-4" \
  --data-urlencode "minimo[999]=5" $BASE/acciones/stock_minimo.php
errores $CKA $BASE/admin/stock.php

titulo "P37 Admin cambia precios y desactiva un plato"
t=$(tok $CKA $BASE/admin/menus.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "accion=actualizar" \
  --data-urlencode "precio[1]=520" --data-urlencode "precio[2]=490" --data-urlencode "precio[3]=460" \
  --data-urlencode "precio[4]=470" --data-urlencode "precio[5]=470" --data-urlencode "activo[1]=1" \
  --data-urlencode "activo[2]=1" --data-urlencode "activo[3]=1" --data-urlencode "activo[5]=1" $BASE/acciones/productos.php
exito $CKA $BASE/admin/menus.php
$SQL "SELECT nombre, precio, activo FROM productos ORDER BY id;"

titulo "P38 El catalogo del cliente refleja el cambio"
CKC=$(entrar julio Cliente123)
echo "  quinoa en el catalogo: $(curl -s -b $CKC $BASE/cliente/index.php | grep -c 'Ensalada de quinoa') coincidencias"

titulo "P39 Admin crea un operador"
t=$(tok $CKA $BASE/admin/usuarios.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "accion=crear" \
  --data-urlencode "nombre=Sofia Perez" --data-urlencode "usuario=sofia" --data-urlencode "email=sofia@atrax.uy" \
  --data-urlencode "telefono=+598 99 555 444" --data-urlencode "rol_id=2" --data-urlencode "contrasena=Cocina2026" \
  $BASE/acciones/usuarios.php
exito $CKA $BASE/admin/usuarios.php

titulo "P40 Admin intenta crear un usuario con rol cliente"
t=$(tok $CKA $BASE/admin/usuarios.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "accion=crear" \
  --data-urlencode "nombre=Cliente Falso" --data-urlencode "usuario=falso1" --data-urlencode "email=falso@correo.com" \
  --data-urlencode "telefono=+598 99 000 111" --data-urlencode "rol_id=3" --data-urlencode "contrasena=Cliente2026" \
  $BASE/acciones/usuarios.php
errores $CKA $BASE/admin/usuarios.php

titulo "P41 Usuario desactivado no puede entrar"
id=$($SQL "SELECT id FROM usuarios WHERE usuario = 'sofia';" | tail -1 | tr -d ' |id')
t=$(tok $CKA $BASE/admin/usuarios.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "accion=estado" \
  --data-urlencode "id=$id" --data-urlencode "activo=0" $BASE/acciones/usuarios.php
exito $CKA $BASE/admin/usuarios.php
rm -f $TMP/t.txt
t=$(curl -s -c $TMP/t.txt $BASE/login.php | grep -o 'name="token" value="[a-f0-9]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b $TMP/t.txt -c $TMP/t.txt -X POST -d "token=$t&usuario=sofia&contrasena=Cocina2026" $BASE/acciones/acceso.php
errores $TMP/t.txt $BASE/login.php

titulo "P42 El admin no puede desactivarse a si mismo"
t=$(tok $CKA $BASE/admin/usuarios.php)
curl -s -o /dev/null -b $CKA -c $CKA -X POST --data-urlencode "token=$t" --data-urlencode "accion=estado" \
  --data-urlencode "id=1" --data-urlencode "activo=0" $BASE/acciones/usuarios.php
errores $CKA $BASE/admin/usuarios.php

titulo "P43 Restricciones cruzadas entre roles"
CKO=$(entrar cocina Operador123)
echo "  operador -> admin/usuarios.php: $(curl -s -o /dev/null -w '%{http_code}' -b $CKO $BASE/admin/usuarios.php)"
echo "  operador -> acciones/usuarios.php: $(curl -s -o /dev/null -w '%{http_code}' -b $CKO -X POST -d 'accion=crear' $BASE/acciones/usuarios.php)"
echo "  admin -> acciones/pedidos.php: $(curl -s -o /dev/null -w '%{http_code}' -b $CKA -X POST -d 'accion=estado' $BASE/acciones/pedidos.php)"

rm -rf $TMP
echo
echo "=== Fin de las pruebas"
