(function () {
  var volver = document.querySelector('[data-volver]');
  if (volver) {
    if (window.history.length < 2) volver.hidden = true;
    volver.addEventListener('click', function () { window.history.back(); });
  }

  var reintentar = document.querySelector('[data-reintentar]');
  var cuenta = document.querySelector('[data-cuenta]');
  if (reintentar) {
    reintentar.addEventListener('click', function () { window.location.reload(); });
  }
  if (cuenta) {
    var segundos = parseInt(cuenta.textContent, 10);
    setInterval(function () {
      segundos -= 1;
      if (segundos <= 0) window.location.reload();
      cuenta.textContent = Math.max(segundos, 0);
    }, 1000);
  }
})();
