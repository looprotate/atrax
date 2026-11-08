<?php if (autenticado()): ?>
      </main>
    </div>
  </div>
</div>
<footer class="atrax-pie">
  <p class="mb-0"><?= APP_NOMBRE ?> &copy; 2026 — Produccion y distribucion de viandas, Montevideo.</p>
</footer>
<?php else: ?>
</main>
<?php endif; ?>

<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-labelledby="modalConfirmarTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content vs-modal">
      <div class="modal-body text-center p-4">
        <span class="vs-modal-icono" aria-hidden="true" data-modal-icono>?</span>
        <h2 class="h5 mb-2" id="modalConfirmarTitulo" data-modal-titulo>Confirmar</h2>
        <p class="text-secondary small mb-0" data-modal-texto></p>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
        <button type="button" class="btn btn-primary" data-modal-aceptar>Confirmar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/validaciones.js') ?>"></script>
<script src="<?= url('assets/js/avisos.js') ?>"></script>
<script src="<?= url('assets/js/esqueletos.js') ?>"></script>
<script src="<?= url('assets/js/efectos.js') ?>"></script>
</body>
</html>
