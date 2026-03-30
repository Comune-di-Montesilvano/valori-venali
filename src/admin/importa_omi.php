<?php
/**
 * Admin — Importazione valori OMI da CSV Sister (Portale Comuni)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$messaggio  = null;
$tipoMsg    = 'success';

// ── Elimina un periodo ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['elimina_periodo'])) {
    verifyCsrf();
    $periodo = $_POST['elimina_periodo'];
    $n = DB::execute('DELETE FROM valori_omi WHERE Periodo = ?', [$periodo]);
    $messaggio = "Eliminati $n record del periodo \"" . htmlspecialchars($periodo) . "\".";
    $tipoMsg   = 'warning';
}

// ── Upload CSV ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === UPLOAD_ERR_OK) {
    verifyCsrf();
    $tmpFile = $_FILES['file_csv']['tmp_name'];
    $handle  = fopen($tmpFile, 'r');

    if ($handle) {
        // Prima riga: "- Semestre - AAAA" o simile — estraiamo il periodo
        $headerLine = fgets($handle);
        $parti      = explode('-', $headerLine);
        $Periodo    = isset($parti[1]) ? trim($parti[1]) : 'Sconosciuto';
        if (isset($parti[2]) && !str_starts_with(trim(strtolower($parti[2])), 'elaborazione')) {
            $Periodo = trim($parti[1]) . '-' . trim($parti[2]);
        }
        $Periodo = trim($Periodo);

        // Seconda riga: intestazione colonne CSV
        $struttura  = fgetcsv($handle, 3000, ';');
        if (!$struttura) {
            $messaggio = 'Il file CSV sembra vuoto o non valido.';
            $tipoMsg   = 'danger';
        } else {
            // Colonne valide nella tabella (escluso id_valore auto)
            $campiDB = array_column(
                DB::query("SHOW COLUMNS FROM valori_omi WHERE Field <> 'id_valore'"),
                'Field'
            );

            $caricati  = 0;
            $ignorati  = 0;
            $pdo       = DB::get();
            $pdo->beginTransaction();

            try {
                while (($data = fgetcsv($handle, 3000, ';')) !== false) {
                    $colonne = [];
                    $valori  = [];
                    foreach ($struttura as $idx => $campo) {
                        $campo = trim($campo);
                        if (in_array($campo, $campiDB, true)) {
                            $colonne[] = "`$campo`";
                            $valori[]  = isset($data[$idx]) ? trim($data[$idx]) : null;
                        }
                    }
                    if (!empty($colonne)) {
                        $placeholders = implode(',', array_fill(0, count($valori), '?'));
                        $sql = 'INSERT INTO valori_omi (`Periodo`,' . implode(',', $colonne)
                             . ") VALUES (?,{$placeholders})";
                        DB::execute($sql, array_merge([$Periodo], $valori));
                        $caricati++;
                    } else {
                        $ignorati++;
                    }
                }
                $pdo->commit();
                $messaggio = "✅ Importati <strong>$caricati</strong> record per il periodo <strong>" . htmlspecialchars($Periodo) . "</strong>."
                           . ($ignorati ? " ($ignorati righe ignorate)" : '');
            } catch (Exception $e) {
                $pdo->rollBack();
                $messaggio = 'Errore durante l\'importazione: ' . htmlspecialchars($e->getMessage());
                $tipoMsg   = 'danger';
            }
        }
        fclose($handle);
    }
}

$periodi = DB::query('SELECT Periodo, COUNT(*) as n_record FROM valori_omi GROUP BY Periodo ORDER BY Periodo DESC');

$isAdmin   = true;
$pageTitle = 'Importazione Valori OMI';
include __DIR__ . '/../layout/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Importazione OMI</li>
  </ol>
</nav>

<h2 class="h4 fw-bold mb-4">📂 Gestione Valori OMI</h2>

<?php if ($messaggio): ?>
  <div class="alert alert-<?= $tipoMsg ?> alert-dismissible fade show" role="alert">
    <?= $messaggio ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-4">
  <!-- ── Upload CSV ── -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">📤 Importa file CSV Sister</div>
      <div class="card-body p-4">
        <p class="text-muted small">
          Carica il file CSV esportato dal <strong>Portale Sister</strong> (Agenzia delle Entrate).
          Il formato prevede una prima riga con il periodo, seguita dall'intestazione colonne e dai dati.
        </p>
        <form method="post" enctype="multipart/form-data" action="<?= APP_URL ?>/admin/importa_omi.php">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <div class="mb-3">
            <label for="file_csv" class="form-label fw-semibold">File CSV OMI</label>
            <input type="file" class="form-control" id="file_csv" name="file_csv"
                   accept=".csv,.txt" required>
            <div class="form-text">Formato: CSV con separatore «;» · Max 20 MB</div>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary">
              📂 Carica e Importa
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Periodi presenti ── -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span>📅 Periodi OMI in Archivio</span>
        <span class="badge bg-primary"><?= count($periodi) ?> periodi</span>
      </div>
      <div class="card-body p-0">
        <?php if (!$periodi): ?>
          <div class="p-4 text-muted text-center">
            <span style="font-size:3rem">📭</span><br>
            Nessun dato OMI importato. Carica il primo file CSV.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th class="ps-3">Periodo</th>
                  <th class="text-center">N° Record</th>
                  <th style="width:120px"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($periodi as $p): ?>
                <tr>
                  <td class="ps-3 fw-semibold"><?= htmlspecialchars($p['Periodo']) ?></td>
                  <td class="text-center">
                    <span class="badge bg-light text-dark border">
                      <?= number_format($p['n_record']) ?>
                    </span>
                  </td>
                  <td>
                    <form method="post" action="<?= APP_URL ?>/admin/importa_omi.php"
                          onsubmit="return confirm('Eliminare tutti i record del periodo «<?= htmlspecialchars(addslashes($p['Periodo'])) ?>»?')">
                      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                      <input type="hidden" name="elimina_periodo" value="<?= htmlspecialchars($p['Periodo']) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger w-100">🗑 Elimina</button>
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
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
