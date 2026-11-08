(function () {
  var sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Los numeros de las tarjetas suben desde cero hasta su valor.

  var formatoMiles = function (valor) {
    return String(valor).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  };

  var contarHasta = function (elemento) {
    var original = elemento.textContent.trim();
    var coincidencia = /^(\$ )?(\d{1,3}(?:\.\d{3})*|\d+)$/.exec(original);
    if (!coincidencia) return;

    var prefijo = coincidencia[1] || '';
    var meta = parseInt(coincidencia[2].replace(/\./g, ''), 10);
    if (!(meta > 0)) return;

    var duracion = Math.min(1100, 450 + Math.log10(meta + 1) * 160);
    var inicio = null;

    var paso = function (ahora) {
      if (inicio === null) inicio = ahora;
      var avance = Math.min(1, (ahora - inicio) / duracion);
      var suave = 1 - Math.pow(1 - avance, 3);
      elemento.textContent = prefijo + formatoMiles(Math.round(meta * suave));
      if (avance < 1) {
        window.requestAnimationFrame(paso);
      } else {
        elemento.textContent = original;
      }
    };

    elemento.textContent = prefijo + '0';
    window.requestAnimationFrame(paso);
    // Si la pestana esta en segundo plano los cuadros se pausan: el valor final no puede depender de ellos.
    window.setTimeout(function () { elemento.textContent = original; }, duracion + 150);
  };

  if (!sinMovimiento) {
    document.querySelectorAll('.tarjeta-numero').forEach(contarHasta);
  }

  // La barra superior se levanta apenas se baja la pagina.

  var barra = document.querySelector('.atrax-topbar');
  if (barra) {
    var marcar = function () { barra.classList.toggle('elevada', window.scrollY > 4); };
    window.addEventListener('scroll', marcar, { passive: true });
    marcar();
  }

  // Armado del pedido: la barra de viandas se llena y la fila elegida se marca.

  var contador = document.querySelector('[data-total-viandas]');
  if (contador) {
    var pista = document.createElement('div');
    var relleno = document.createElement('span');
    pista.className = 'barra vs-barra-viandas';
    pista.setAttribute('aria-hidden', 'true');
    pista.appendChild(relleno);
    contador.closest('header').after(pista);

    var ultimo = '';

    var medir = function () {
      var partes = contador.textContent.split('/');
      var elegidas = parseInt(partes[0], 10) || 0;
      var esperadas = parseInt(partes[1], 10) || 0;
      var completo = esperadas > 0 && elegidas === esperadas;

      relleno.style.width = (esperadas > 0 ? Math.min(100, (elegidas / esperadas) * 100) : 0) + '%';

      if (completo && !pista.classList.contains('completo')) {
        pista.classList.remove('completo');
        void pista.offsetWidth;
      }
      pista.classList.toggle('completo', completo);

      if (contador.textContent !== ultimo) {
        ultimo = contador.textContent;
        contador.classList.remove('vs-cambio');
        void contador.offsetWidth;
        contador.classList.add('vs-cambio');
      }
    };

    new MutationObserver(medir).observe(contador, { childList: true, characterData: true, subtree: true });
    medir();

    document.querySelectorAll('input[data-vianda]').forEach(function (campo) {
      var fila = campo.closest('tr');
      var marcarFila = function () {
        if (fila) fila.classList.toggle('elegido', (parseInt(campo.value || '0', 10) || 0) > 0);
      };
      campo.addEventListener('input', marcarFila);
      marcarFila();
    });
  }

  // Celebracion al confirmar un pedido: confeti con los colores de la marca.

  var celebrar = function (toast) {
    var icono = toast.querySelector('.vs-toast-icono');
    var caja = (icono || toast).getBoundingClientRect();
    var origen = document.createElement('div');
    var colores = ['#d0b178', '#a9bad6', '#3e8036', '#ffffff', '#3d5e91'];

    origen.className = 'vs-confeti';
    origen.setAttribute('aria-hidden', 'true');
    origen.style.left = (caja.left + caja.width / 2) + 'px';
    origen.style.top = (caja.top + caja.height / 2) + 'px';

    for (var i = 0; i < 34; i++) {
      var pieza = document.createElement('i');
      var angulo = Math.random() * Math.PI * 2;
      var fuerza = 70 + Math.random() * 150;

      pieza.style.setProperty('--color', colores[i % colores.length]);
      pieza.style.setProperty('--dx', Math.round(Math.cos(angulo) * fuerza) + 'px');
      pieza.style.setProperty('--dy', Math.round(Math.sin(angulo) * fuerza + 60) + 'px');
      pieza.style.setProperty('--giro', Math.round(Math.random() * 720 - 360) + 'deg');
      pieza.style.setProperty('--duracion', (900 + Math.random() * 500) + 'ms');
      pieza.style.setProperty('--demora', Math.round(Math.random() * 120) + 'ms');
      origen.appendChild(pieza);
    }

    document.body.appendChild(origen);
    window.setTimeout(function () { origen.remove(); }, 1700);
  };

  if (!sinMovimiento) {
    var festejo = document.querySelector('.vs-toast[data-celebrar]');
    if (festejo) window.setTimeout(function () { celebrar(festejo); }, 250);
  }
})();
