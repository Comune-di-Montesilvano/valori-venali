<?php
/**
 * Admin — Gestione Destinazioni Urbanistiche (coefficienti PRG)
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
        $id = (int) $_POST['elimina'];
        DB::execute('DELETE FROM omi_destinazione_urbanistica WHERE id_destinazione = ?', [$id]);
        $messaggio = 'Destinazione urbanistica eliminata.';
        $tipoMsg   = 'warning';

    } elseif (isset($_POST['salva'])) {
        $destinazione = trim($_POST['destinazione'] ?? '');
        $coeff        = str_replace(',', '.', trim($_POST['coefficiente'] ?? ''));
        $zona_omi_raw = $_POST['zona_omi'] ?? '';
        $valore       = (int) ($_POST['valore'] ?? 2);

        [$cod_tip, $stato] = array_pad(explode('_', $zona_omi_raw, 2), 2, '');
        $cod_tip = trim($cod_tip);
        $stato   = trim($stato);

        if ($destinazione === '' || !is_numeric($coeff) || $coeff < 0 || $cod_tip === '') {
            $messaggio = 'Compilare tutti i campi in modo corretto.';
            $tipoMsg   = 'danger';
        } else {
            DB::execute(
                'INSERT INTO omi_destinazione_urbanistica
                    (destinazione, coefficiente_destinazione, Cod_Tip, Stato, Valore)
                 VALUES (?, ?, ?, ?, ?)',
                [$destinazione, (float) $coeff, $cod_tip, $stato, $valore]
            );
            $messaggio = "Destinazione \"$destinazione\" aggiunta.";
        }
    }
}

$destinazioni = DB::query('SELECT * FROM omi_destinazione_urbanistica ORDER BY destinazione');
$valori_map   = [1 => 'Minimo', 2 => 'Medio', 3 => 'Massimo'];

// Tipologie OMI disponibili per il select
$tipologie = DB::query(
    'SELECT DISTINCT Cod_Tip, Descr_Tipologia, Stato FROM valori_omi ORDER BY Descr_Tipologia, Stato DESC'
);

// Mappa cod_tip → descrizione per la tabella
$desc_tip = [];
foreach ($tipologie as $t) {
    $desc_tip[$t['Cod_Tip']] = $t['Descr_Tipologia'];
}

$isAdmin   = true;
$pageTitle = 'Destinazioni Urbanistiche';
include __DIR__ . '/../layout/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Destinazioni Urbanistiche</li>
  </ol>
</nav>

<h2 class="h4 fw-bold mb-2">⚙️ Parametri Destinazione Urbanistica</h2>
<p class="text-muted mb-4">Abbinamento tra zone PRG (Piano Regolatore) e tipologie OMI, con coefficiente e valore di riferimento.</p>

<?php if ($messaggio): ?>
  <div class="alert alert-<?= $tipoMsg ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($messaggio) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Tabella destinazioni -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Destinazioni configurate</span>
    <span class="badge bg-primary"><?= count($destinazioni) ?></span>
  </div>
  <?php if (!$destinazioni): ?>
    <div class="card-body text-center text-muted py-5">
      <span style="font-size:3rem">📭</span><br>
      Nessuna destinazione urbanistica configurata.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th class="text-center" style="width:90px">ID</th>
            <th class="ps-3">Zona PRG</th>
            <th>Tipologia OMI</th>
            <th class="text-center">Stato</th>
            <th class="text-center">Coeff.</th>
            <th class="text-center">Valore</th>
            <th style="width:110px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($destinazioni as $d): ?>
          <tr>
            <td class="text-center"><code><?= (int) $d['id_destinazione'] ?></code></td>
            <td class="ps-3 fw-bold"><?= htmlspecialchars($d['destinazione']) ?></td>
            <td><small><?= htmlspecialchars($desc_tip[$d['Cod_Tip']] ?? $d['Cod_Tip']) ?></small></td>
            <td class="text-center">
              <span class="badge <?= $d['Stato'] === 'N' ? 'bg-success' : 'bg-secondary' ?>">
                <?= htmlspecialchars($d['Stato']) ?>
              </span>
            </td>
            <td class="text-center"><code><?= number_format((float)$d['coefficiente_destinazione'], 4, ',', '.') ?></code></td>
            <td class="text-center">
              <span class="badge bg-info text-dark"><?= $valori_map[(int)$d['Valore']] ?? '-' ?></span>
            </td>
            <td>
              <form method="post" action="<?= APP_URL ?>/admin/parametri_omi.php"
                    onsubmit="return confirm('Eliminare questa destinazione?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <button type="submit" name="elimina" value="<?= (int)$d['id_destinazione'] ?>"
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

<!-- Form aggiungi -->
<div class="card">
  <div class="card-header">➕ Nuova Destinazione Urbanistica</div>
  <div class="card-body p-4">
    <form method="post" action="<?= APP_URL ?>/admin/parametri_omi.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="destinazione" class="form-label fw-semibold">Zona PRG</label>
          <input type="text" class="form-control" id="destinazione" name="destinazione"
                 placeholder="Es. B1 — Residenziale consolidato" required>
          <div class="form-text">Descrizione della zona di Piano Regolatore</div>
        </div>
        <div class="col-md-6">
          <label for="coefficiente" class="form-label fw-semibold">Coefficiente destinazione</label>
          <input type="text" class="form-control" id="coefficiente" name="coefficiente"
                 placeholder="Es. 0.5000" required>
          <div class="form-text">Valore moltiplicativo (usa «.» come separatore decimale)</div>
        </div>
        <div class="col-md-5">
          <label for="zona_omi" class="form-label fw-semibold">Tipologia OMI</label>
          <select class="form-select" id="zona_omi" name="zona_omi" required>
            <option value="">— Seleziona tipologia —</option>
            <?php foreach ($tipologie as $t): ?>
              <option value="<?= htmlspecialchars($t['Cod_Tip'] . '_' . $t['Stato']) ?>">
                <?= htmlspecialchars($t['Descr_Tipologia']) ?> (<?= htmlspecialchars($t['Stato']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (!$tipologie): ?>
            <div class="form-text text-danger">⚠️ Importa prima i valori OMI</div>
          <?php endif; ?>
        </div>
        <div class="col-md-4">
          <label for="valore" class="form-label fw-semibold">Valore da usare</label>
          <select class="form-select" id="valore" name="valore">
            <option value="2" selected>Medio</option>
            <option value="1">Minimo</option>
            <option value="3">Massimo</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" name="salva" class="btn btn-primary w-100">Aggiungi</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
