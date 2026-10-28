<?php
/**
 * Admin — Abbinamento Foglio Catastale → Zona OMI
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$messaggio = null;
$tipoMsg   = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['elimina']) && $_POST['elimina'] !== '') {
        $foglio = $_POST['elimina'];
        DB::execute('DELETE FROM fogli_zone_omi WHERE foglio_catastale = ?', [$foglio]);
        $messaggio = "Abbinamento foglio \"$foglio\" eliminato.";
        $tipoMsg   = 'warning';

    } elseif (isset($_POST['salva'])) {
        $foglio   = trim($_POST['foglio_catastale'] ?? '');
        $zona_omi = trim($_POST['zona_omi'] ?? '');
        if ($foglio === '' || $zona_omi === '') {
            $messaggio = 'Inserire sia il foglio catastale che la zona OMI.';
            $tipoMsg   = 'danger';
        } else {
            try {
                DB::execute(
                    'INSERT INTO fogli_zone_omi (foglio_catastale, zona_omi) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE zona_omi = VALUES(zona_omi)',
                    [$foglio, $zona_omi]
                );
                $messaggio = "Abbinamento foglio $foglio → zona $zona_omi salvato.";
            } catch (Exception $e) {
                $messaggio = 'Errore: ' . htmlspecialchars($e->getMessage());
                $tipoMsg   = 'danger';
            }
        }
    }
}

$fogli = DB::query('SELECT foglio_catastale, zona_omi FROM fogli_zone_omi ORDER BY CAST(foglio_catastale AS UNSIGNED) ASC');
$zone  = DB::query('SELECT DISTINCT Zona FROM valori_omi ORDER BY Zona');

$isAdmin   = true;
$pageTitle = 'Fogli Catastali';
include __DIR__ . '/../layout/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Fogli Catastali</li>
  </ol>
</nav>

<h2 class="h4 fw-bold mb-2">🗺️ Abbinamento Fogli Catastali → Zone OMI</h2>
<p class="text-muted mb-4">Associa ogni foglio catastale alla corrispondente zona OMI per il calcolo della stima.</p>

<?php if ($messaggio): ?>
  <div class="alert alert-<?= $tipoMsg ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($messaggio) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-4">
  <!-- Tabella abbinamenti -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span>Abbinamenti presenti</span>
        <span class="badge bg-primary"><?= count($fogli) ?></span>
      </div>
      <?php if (!$fogli): ?>
        <div class="card-body text-center text-muted py-5">
          <span style="font-size:3rem">📭</span><br>
          Nessun abbinamento configurato.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th class="ps-3">Foglio Catastale</th>
                <th>Zona OMI</th>
                <th style="width:110px"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($fogli as $f): ?>
              <tr>
                <td class="ps-3 fw-bold">
                  <span class="badge bg-light text-dark border px-3 py-2" style="font-size:.95rem;">
                    <?= htmlspecialchars($f['foglio_catastale']) ?>
                  </span>
                </td>
                <td>
                  <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2">
                    Zona <?= htmlspecialchars($f['zona_omi']) ?>
                  </span>
                </td>
                <td>
                  <form method="post" action="<?= APP_URL ?>/admin/fogli_omi.php"
                        onsubmit="return confirm('Eliminare questo abbinamento?')">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <button type="submit" name="elimina" value="<?= htmlspecialchars($f['foglio_catastale']) ?>"
                            class="btn btn-sm btn-outline-danger w-100">🗑 Elimina</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Form aggiungi -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">➕ Nuovo Abbinamento</div>
      <div class="card-body p-4">
        <?php if (!$zone): ?>
          <div class="alert alert-warning">
            ⚠️ Nessuna zona OMI disponibile. Importa prima i valori OMI.
          </div>
        <?php endif; ?>
        <form method="post" action="<?= APP_URL ?>/admin/fogli_omi.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <div class="mb-3">
            <label for="foglio_catastale" class="form-label fw-semibold">Foglio Catastale</label>
            <input type="text" class="form-control" id="foglio_catastale" name="foglio_catastale"
                   placeholder="Es. 12" required>
            <div class="form-text">Numero del foglio catastale del Comune</div>
          </div>
          <div class="mb-4">
            <label for="zona_omi" class="form-label fw-semibold">Zona OMI</label>
            <select class="form-select" id="zona_omi" name="zona_omi" required <?= !$zone ? 'disabled' : '' ?>>
              <option value="">— Seleziona zona —</option>
              <?php foreach ($zone as $z): ?>
                <option value="<?= htmlspecialchars($z['Zona'] ?? '') ?>"><?= htmlspecialchars($z['Zona'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="d-grid">
            <button type="submit" name="salva" class="btn btn-primary" <?= !$zone ? 'disabled' : '' ?>>
              Salva Abbinamento
            </button>
          </div>
        </form>
        <div class="mt-3 p-3 rounded-3 bg-light">
          <small class="text-muted">
            <strong>Nota:</strong> Se il foglio è già presente, verrà aggiornata la zona OMI associata.
          </small>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
