// Filtro de pedidos sin recargar (admin/pedidos.php):
// escribir o elegir un filtro -> fetch() -> api/pedidos.php -> MySQL -> JSON -> se rehace la tabla.
// Sin JavaScript el formulario sigue funcionando como una busqueda normal (GET).
(function () {
  var formulario = document.querySelector('form[data-pedidos-en-vivo]');
  var cuerpo = document.querySelector('[data-pedidos-cuerpo]');
  var total = document.querySelector('[data-pedidos-total]');
  var estado = document.querySelector('[data-pedidos-estado]');
  var panel = document.querySelector('[data-pedidos-resultados]');

  if (!formulario || !cuerpo || !total || !estado || !panel || !window.fetch || !window.AbortController) return;

  var ESPERA_ESCRITURA = 300;
  var TIEMPO_MAXIMO = 10000;
  var pedido = null;
  var temporizador = null;

  var celda = function (texto, clase) {
    var td = document.createElement('td');
    if (clase) td.className = clase;
    td.textContent = texto;
    return td;
  };

  var fila = function (dato) {
    var tr = document.createElement('tr');

    var etiqueta = document.createElement('span');
    etiqueta.className = 'etiqueta ' + dato.clase;
    etiqueta.textContent = dato.estado;
    var celdaEstado = document.createElement('td');
    celdaEstado.appendChild(etiqueta);

    var enlace = document.createElement('a');
    enlace.className = 'btn btn-sm btn-outline-secondary';
    enlace.href = formulario.dataset.ver + encodeURIComponent(dato.id);
    enlace.textContent = 'Ver';
    var celdaVer = document.createElement('td');
    celdaVer.className = 'text-end';
    celdaVer.appendChild(enlace);

    tr.append(
      celda('#' + dato.id, 'fw-semibold'),
      celda(dato.cliente),
      celda(dato.tipo),
      celda(dato.zona),
      celda(dato.entrega),
      celda(String(dato.viandas)),
      celdaEstado,
      celda(dato.vehiculo),
      celda(dato.total, 'text-end'),
      celdaVer
    );
    return tr;
  };

  var dibujar = function (datos) {
    var filas = datos.pedidos.map(fila);

    if (!filas.length) {
      var vacia = document.createElement('tr');
      var td = celda('No hay pedidos que coincidan con el filtro.', 'text-secondary');
      td.colSpan = 10;
      vacia.appendChild(td);
      filas = [vacia];
    }

    cuerpo.replaceChildren.apply(cuerpo, filas);
    total.textContent = String(datos.total);
  };

  var mostrarEstado = function (tipo, texto, reintentar) {
    estado.className = 'en-vivo-estado mb-0 ' + tipo;
    estado.textContent = texto;

    if (reintentar) {
      var boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'btn btn-sm btn-link p-0 ms-2 align-baseline';
      boton.textContent = 'Reintentar';
      boton.addEventListener('click', cargar);
      estado.appendChild(boton);
    }
  };

  var mensajeDeError = function (respuesta, datos) {
    if (datos && datos.error) return datos.error;
    return 'El servidor respondio con un error (' + respuesta.status + ').';
  };

  var parametros = function () {
    var consulta = new URLSearchParams();
    new FormData(formulario).forEach(function (valor, nombre) {
      if (String(valor).trim() !== '') consulta.set(nombre, String(valor).trim());
    });
    return consulta;
  };

  var cargar = function () {
    if (pedido) pedido.abort();

    var controlador = new AbortController();
    var vencio = false;
    var plazo = window.setTimeout(function () { vencio = true; controlador.abort(); }, TIEMPO_MAXIMO);
    pedido = controlador;

    var consulta = parametros();
    panel.setAttribute('aria-busy', 'true');
    mostrarEstado('cargando', 'Cargando pedidos...');

    fetch(formulario.dataset.api + (consulta.toString() ? '?' + consulta.toString() : ''), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      signal: controlador.signal
    })
      .then(function (respuesta) {
        return respuesta.json().catch(function () { return null; }).then(function (datos) {
          if (!respuesta.ok) throw { respuesta: respuesta, datos: datos };
          return datos;
        });
      })
      .then(function (datos) {
        dibujar(datos);
        mostrarEstado('listo', datos.total === 0 ? 'No se encontraron pedidos.' : datos.total === 1 ? 'Se muestra 1 pedido.' : 'Se muestran ' + datos.total + ' pedidos.');
        window.history.replaceState(null, '', consulta.toString() ? '?' + consulta.toString() : window.location.pathname);
      })
      .catch(function (falla) {
        if (falla && falla.name === 'AbortError' && !vencio) return;

        var texto;
        if (falla && falla.respuesta) {
          texto = mensajeDeError(falla.respuesta, falla.datos);
        } else if (vencio) {
          texto = 'La respuesta tardo demasiado. Intenta de nuevo.';
        } else {
          texto = 'No se pudo conectar con el servidor. Revisa tu conexion e intenta de nuevo.';
        }

        mostrarEstado('error', texto, !(falla && falla.respuesta && falla.respuesta.status === 401));
        if (window.ViandaSegura && window.ViandaSegura.aviso) {
          window.ViandaSegura.aviso('error', 'No se pudo actualizar la lista', texto);
        }
      })
      .then(function () {
        window.clearTimeout(plazo);
        if (pedido === controlador) {
          pedido = null;
          panel.removeAttribute('aria-busy');
        }
      });
  };

  formulario.addEventListener('submit', function (evento) {
    evento.preventDefault();
    window.clearTimeout(temporizador);
    cargar();
  });

  formulario.addEventListener('change', function () {
    window.clearTimeout(temporizador);
    cargar();
  });

  formulario.addEventListener('input', function (evento) {
    if (evento.target.type !== 'search') return;
    window.clearTimeout(temporizador);
    temporizador = window.setTimeout(cargar, ESPERA_ESCRITURA);
  });

  var limpiar = formulario.querySelector('[data-limpiar]');
  if (limpiar) {
    limpiar.addEventListener('click', function (evento) {
      evento.preventDefault();
      window.clearTimeout(temporizador);
      formulario.reset();
      Array.prototype.forEach.call(formulario.elements, function (campo) {
        if (campo.name) campo.value = '';
      });
      cargar();
    });
  }
})();
