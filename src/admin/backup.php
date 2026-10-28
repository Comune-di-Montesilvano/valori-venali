<?php
/**
 * Admin — Backup e Ripristino Database
 *
 * Funzioni:
 *  - Download backup completo (tutte le tabelle dati)
 *  - Download backup singola tabella
 *  - Ripristino da file SQL
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$tables = [
    'valori_omi'                    => 'Valori OMI',
    'omi_abbattimenti'              => 'Coefficienti Abbattimento',
    'omi_destinazione_urbanistica'  => 'Destinazioni Urbanistiche',
    'fogli_zone_omi'                => 'Fogli Catastali → Zone OMI',
];

// ═══════════════════════════════════════════════════════════════════════
// OPERAZIONI
// ═══════════════════════════════════════════════════════════════════════

// ── Download Backup ────────────────────────────────────────────────────
if (isset($_GET['download'])) {
    $target = $_GET['download'];
    $tablesToExport = ($target === 'all') ? array_keys($tables) : [$target];

    // Verifica che sia una tabella valida
    foreach ($tablesToExport as $t) {
        if (!array_key_exists($t, $tables)) {
            die('Tabella non valida: ' . htmlspecialchars($t));
        }
    }

    $filename = 'backup_valori_venali_' . date('Y-m-d_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');

    echo "-- =================================================================\n";
    echo "-- Backup Valori Venali Aree Fabbricabili\n";
    echo "-- Data: " . date('d/m/Y H:i:s') . "\n";
    echo "-- =================================================================\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tablesToExport as $tableName) {
        echo "-- ─── Tabella: $tableName ─────────────────────────────────\n";

        // Struttura tabella
        $create = DB::queryOne("SHOW CREATE TABLE `$tableName`");
        echo "DROP TABLE IF EXISTS `$tableName`;\n";
        echo $create['Create Table'] . ";\n\n";

        // Dati
        $rows = DB::query("SELECT * FROM `$tableName`");
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $colList = implode('`, `', $columns);

            echo "INSERT INTO `$tableName` (`$colList`) VALUES\n";
            $lastIdx = count($rows) - 1;
            foreach ($rows as $idx => $row) {
                $values = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($val)) {
                        $values[] = $val;
                    } else {
                        $values[] = "'" . addslashes($val) . "'";
                    }
                }
                $line = "\t(" . implode(', ', $values) . ")";
                echo $line . ($idx < $lastIdx ? ",\n" : ";\n");
            }
            echo "\n";
        } else {
            echo "-- (tabella vuota)\n\n";
        }
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    echo "-- Fine backup\n";
    exit;
}

// ── Ripristino da file SQL ─────────────────────────────────────────────
$messaggio = null;
$tipoMsg   = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ripristina'])) {
    verifyCsrf();

    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $messaggio = 'Selezionare un file SQL valido.';
        $tipoMsg   = 'danger';
    } else {
        $tmpFile = $_FILES['backup_file']['tmp_name'];
        $sql     = file_get_contents($tmpFile);

        if (empty(trim($sql))) {
            $messaggio = 'Il file SQL è vuoto.';
            $tipoMsg   = 'danger';
        } else {
            $pdo = DB::get();
            try {
                // Esegui tutte le istruzioni SQL del backup
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                $pdo->exec($sql);
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

                // Conta record dopo il ripristino
                $counts = [];
                foreach ($tables as $t => $label) {
                    $row = DB::queryOne("SELECT COUNT(*) as n FROM `$t`");
                    $counts[] = "$label: " . number_format($row['n'] ?? 0);
                }

                $messaggio = '✅ Ripristino completato con successo!<br><small>' . implode(' · ', $counts) . '</small>';
            } catch (Exception $e) {
                $messaggio = 'Errore durante il ripristino: ' . htmlspecialchars($e->getMessage());
                $tipoMsg   = 'danger';
            }
        }
    }
}

// ── Statistiche correnti ───────────────────────────────────────────────
$stats = [];
foreach ($tables as $t => $label) {
    $row = DB::queryOne("SELECT COUNT(*) as n FROM `$t`");
    $stats[$t] = $row['n'] ?? 0;
}

$isAdmin   = true;
$pageTitle = 'Backup e Ripristino';
include __DIR__ . '/../layout/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Backup e Ripristino</li>
  </ol>
</nav>

<h2 class="h4 fw-bold mb-4">💾 Backup e Ripristino Database</h2>

<?php if ($messaggio): ?>
  <div class="alert alert-<?= $tipoMsg ?> alert-dismissible fade show" role="alert">
    <?= $messaggio ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row g-4">
  <!-- ── Download Backup ── -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>📥 Download Backup</span>
        <a href="<?= APP_URL ?>/admin/backup.php?download=all" class="btn btn-sm btn-primary">
          ⬇️ Scarica Backup Completo
        </a>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th class="ps-3">Tabella</th>
              <th class="text-center">Record</th>
              <th style="width:160px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tables as $tableName => $label): ?>
            <tr>
              <td class="ps-3">
                <strong><?= htmlspecialchars($label) ?></strong><br>
                <code class="small text-muted"><?= $tableName ?></code>
              </td>
              <td class="text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2" style="font-size:.95rem">
                  <?= number_format($stats[$tableName]) ?>
                </span>
              </td>
              <td>
                <a href="<?= APP_URL ?>/admin/backup.php?download=<?= urlencode($tableName) ?>"
                   class="btn btn-sm btn-outline-primary w-100">
                  ⬇️ Scarica
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-light">
        <small class="text-muted">
          <strong>Formato:</strong> dump SQL compatibile con MariaDB/MySQL. Include struttura + dati.
        </small>
      </div>
    </div>

    <!-- Info card -->
    <div class="card mt-4" style="border-left: 4px solid #0066cc !important;">
      <div class="card-body">
        <h5 class="h6 fw-bold">ℹ️ Informazioni sul backup</h5>
        <ul class="list-unstyled text-muted small mb-0">
          <li>• Il backup include <strong>struttura e dati</strong> delle tabelle selezionate</li>
          <li>• Il file scaricato è in formato SQL, importabile con qualsiasi client MySQL/MariaDB</li>
          <li>• Il <strong>backup completo</strong> include tutte le 4 tabelle dati (esclude la tabella users)</li>
          <li>• Puoi anche importare il backup dalla riga di comando:
            <code class="d-block mt-1">docker compose exec -T db mariadb -u root -p valori_venali &lt; backup.sql</code>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <!-- ── Ripristino ── -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header bg-warning text-dark">
        <span>⚠️ Ripristino Database</span>
      </div>
      <div class="card-body p-4">
        <div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
          <span style="font-size:1.3rem">🔶</span>
          <div class="small">
            <strong>Attenzione!</strong> Il ripristino sovrascriverà i dati attualmente presenti nel database.
            Si consiglia di scaricare un backup prima di procedere.
          </div>
        </div>

        <form method="post" enctype="multipart/form-data" action="<?= APP_URL ?>/admin/backup.php"
              onsubmit="return confirm('⚠️ Confermi il ripristino?\n\nI dati attuali verranno sovrascritti con il contenuto del file di backup.')">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

          <div class="mb-3">
            <label for="backup_file" class="form-label fw-semibold">File di backup (.sql)</label>
            <input type="file" class="form-control" id="backup_file" name="backup_file"
                   accept=".sql" required>
            <div class="form-text">File SQL generato dalla funzione di backup</div>
          </div>

          <div class="d-grid">
            <button type="submit" name="ripristina" class="btn btn-warning">
              🔄 Ripristina Database
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Riepilogo corrente -->
    <div class="card mt-4">
      <div class="card-header">📊 Stato attuale database</div>
      <div class="card-body p-3">
        <?php
          $ultimoPeriodo = DB::queryOne('SELECT Periodo FROM valori_omi ORDER BY Periodo DESC LIMIT 1');
          $primoPeriodo  = DB::queryOne('SELECT Periodo FROM valori_omi ORDER BY Periodo ASC LIMIT 1');
          $nPeriodi      = DB::queryOne('SELECT COUNT(DISTINCT Periodo) as n FROM valori_omi')['n'] ?? 0;
          $nZone         = DB::queryOne('SELECT COUNT(DISTINCT Zona) as n FROM valori_omi')['n'] ?? 0;
        ?>
        <div class="row g-2 text-center">
          <div class="col-6">
            <div class="p-2 rounded bg-light">
              <div class="small text-muted">Totale record</div>
              <div class="fw-bold fs-4 text-primary">
                <?= number_format(array_sum($stats)) ?>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="p-2 rounded bg-light">
              <div class="small text-muted">Periodi OMI</div>
              <div class="fw-bold fs-4 text-success"><?= $nPeriodi ?></div>
            </div>
          </div>
          <div class="col-12">
            <div class="p-2 rounded bg-light text-start">
              <div class="small text-muted mb-1">Copertura temporale</div>
              <?php if ($primoPeriodo && $ultimoPeriodo): ?>
                <span class="badge bg-info text-dark">
                  <?= htmlspecialchars($primoPeriodo['Periodo'] ?? '') ?> → <?= htmlspecialchars($ultimoPeriodo['Periodo'] ?? '') ?>
                </span>
              <?php else: ?>
                <span class="text-muted small">Nessun dato</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
