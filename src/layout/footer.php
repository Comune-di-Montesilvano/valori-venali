  </div><!-- /container -->
</main><!-- /page-content -->

<!-- ── Site Footer ── -->
<footer class="site-footer">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-6">
        <strong class="text-white"><?= htmlspecialchars(COMUNE_NOME) ?></strong>
        — Valori Venali Aree Fabbricabili
      </div>
      <div class="col-md-6 text-md-end mt-2 mt-md-0">
        <a href="<?= GITHUB_URL ?>" target="_blank" class="text-white text-decoration-none opacity-75">
          <?= htmlspecialchars(COMUNE_NOME) ?> — Valori OMI
        </a>
        &nbsp;
        <a href="<?= GITHUB_URL ?>/<?= APP_IS_TAG ? 'releases/tag/' . APP_REVISION : 'commit/' . APP_REVISION ?>" 
           target="_blank" class="text-decoration-none" title="Visualizza su GitHub">
          <span class="version-badge">
            <?= (APP_IS_TAG && !str_starts_with(APP_VERSION, 'v')) ? 'v' . APP_VERSION : APP_VERSION ?>
          </span>
        </a>
      </div>
    </div>
  </div>
</footer>

<!-- Bootstrap Italia JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap-italia@2.13.0/dist/js/bootstrap-italia.bundle.min.js"
        crossorigin="anonymous"></script>
</body>
</html>
