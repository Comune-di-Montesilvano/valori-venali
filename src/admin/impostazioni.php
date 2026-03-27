<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

requireAuth();

$message = '';
$error = '';

// Assicuriamoci che la cartella uploads esista
$uploadsDir = ROOT_PATH . '/uploads';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gestione Logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $newName = 'logo.' . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadsDir . '/' . $newName)) {
            Settings::set('logo_path', $newName);
            $message = 'Impostazioni aggiornate con successo.';
        } else {
            $error = 'Errore durante il caricamento del logo.';
        }
    }

    // Gestione Favicon
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
        $newName = 'favicon.' . $ext;
        if (move_uploaded_file($_FILES['favicon']['tmp_name'], $uploadsDir . '/' . $newName)) {
            Settings::set('favicon_path', $newName);
            $message = 'Impostazioni aggiornate con successo.';
        } else {
            $error = 'Errore durante il caricamento della favicon.';
        }
    }
    
    // Ricarica per vedere i cambiamenti
    if (!$error) {
        header('Location: ' . APP_URL . '/admin/impostazioni.php?success=1');
        exit;
    }
}

if (isset($_GET['success'])) {
    $message = 'Impostazioni aggiornate con successo.';
}

$currentLogo = Settings::get('logo_path');
$currentFavicon = Settings::get('favicon_path');

$isAdmin   = true;
$pageTitle = 'Impostazioni Sito';
include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <h2 class="h4 fw-bold mb-0">🛠 Impostazioni Sito</h2>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
        ⬅ Torna alla Dashboard
    </a>
</div>

<?php if ($message): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    ✅ <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    ⚠️ <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">🖼 Personalizzazione Logo e Favicon</div>
      <div class="card-body p-4">
        <form method="post" enctype="multipart/form-data">
          
          <div class="mb-4">
            <label class="form-label fw-bold">Logo Istituzionale</label>
            <div class="d-flex align-items-center gap-4 mb-3 p-3 bg-light rounded border">
                <div class="text-center">
                    <div class="small text-muted mb-2">Anteprima Corrente</div>
                    <?php if ($currentLogo): ?>
                        <img src="<?= APP_URL ?>/uploads/<?= htmlspecialchars($currentLogo) ?>" alt="Logo" style="max-height: 80px; width: auto;" class="img-thumbnail">
                    <?php else: ?>
                        <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">🏗️</div>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/svg+xml">
                    <div class="form-text mt-2">
                        Formati consigliati: <strong>PNG, SVG</strong>.<br>
                        Altezza ottimale: 48-64px.
                    </div>
                </div>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-bold">Favicon (Icona Browser)</label>
            <div class="d-flex align-items-center gap-4 mb-3 p-3 bg-light rounded border">
                <div class="text-center">
                    <div class="small text-muted mb-2">Anteprima</div>
                    <?php if ($currentFavicon): ?>
                        <img src="<?= APP_URL ?>/uploads/<?= htmlspecialchars($currentFavicon) ?>" alt="Favicon" style="width: 32px; height: 32px;" class="img-thumbnail">
                    <?php else: ?>
                        <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 1rem;">📄</div>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <input type="file" name="favicon" class="form-control" accept="image/png,image/x-icon,image/vnd.microsoft.icon">
                    <div class="form-text mt-2">
                        Formati consigliati: <strong>ICO, PNG (32x32)</strong>.
                    </div>
                </div>
            </div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary btn-lg">
              💾 Salva Impostazioni
            </button>
          </div>

        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card bg-light border-0">
      <div class="card-body p-4">
        <h5 class="fw-bold mb-3">ℹ️ Istruzioni</h5>
        <p class="small text-muted">
            Queste impostazioni permettono di personalizzare l'aspetto visivo dell'applicazione per adattarla all'ente istituzionale di riferimento.
        </p>
        <ul class="small text-muted ps-3">
            <li class="mb-2"><strong>Logo:</strong> Viene visualizzato nell'intestazione di ogni pagina e nei documenti di stampa.</li>
            <li class="mb-2"><strong>Favicon:</strong> È la piccola icona che appare nelle schede del browser e nei segnalibri.</li>
        </ul>
        <div class="alert alert-info py-2 px-3 small mt-3">
            <strong>Nota:</strong> I file caricati sovrascriveranno quelli precedenti. Assicurati che le immagini abbiano uno sfondo trasparente per un miglior risultato estetico.
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
