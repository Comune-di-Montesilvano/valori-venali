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

<!-- Bootstrap Italia JS + font (self-hosted, niente CDN/Google Fonts) -->
<script>window.__PUBLIC_PATH__ = '<?= APP_URL ?>/assets/bootstrap-italia/fonts';</script>
<script src="<?= APP_URL ?>/assets/bootstrap-italia/js/bootstrap-italia.bundle.min.js"></script>
<script>bootstrap.loadFonts();</script>
</body>
</html>
