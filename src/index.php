<?php
/**
 * Pagina pubblica — Calcolo Stima Valori Venali Aree Fabbricabili
 * Formula: Superficie × Valore OMI × Coeff. Destinazione × Coeff. Abbattimento
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
seedAdmin();

// ── Raccolta dati per i select ──────────────────────────────────────────────
$periodi    = DB::query('SELECT DISTINCT Periodo FROM valori_omi ORDER BY Periodo DESC');
$fogli      = DB::query('SELECT foglio_catastale, zona_omi FROM fogli_zone_omi ORDER BY CAST(foglio_catastale AS UNSIGNED) ASC');
$destinazioni = DB::query('SELECT DISTINCT destinazione FROM omi_destinazione_urbanistica ORDER BY destinazione');
$abbattimenti = DB::query('SELECT id_coefficiente, descrizione, valore FROM omi_abbattimenti ORDER BY descrizione');

// ── Elaborazione calcolo ────────────────────────────────────────────────────
$risultato = null;
$erroreCalcolo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calcola'])) {
    $foglio          = trim($_POST['foglio_catastale'] ?? '');
    $destinazione    = trim($_POST['destinazione'] ?? '');
    $superficie      = (float) str_replace(',', '.', $_POST['superficie'] ?? '0');
    $id_abbattimento = isset($_POST['id_abbattimento']) && $_POST['id_abbattimento'] !== ''
      ? (int) $_POST['id_abbattimento']
      : null;
    $periodo         = trim($_POST['periodo'] ?? '');

    if ($superficie <= 0) {
        $erroreCalcolo = 'Inserire una superficie valida (> 0 mq).';
    } elseif (!$foglio || !$destinazione || !$periodo) {
        $erroreCalcolo = 'Compilare tutti i campi obbligatori.';
    } else {
        // Trova zona OMI dal foglio catastale
        $foglio_row = DB::queryOne(
            'SELECT zona_omi FROM fogli_zone_omi WHERE foglio_catastale = ? LIMIT 1',
            [$foglio]
        );

        // Trova parametri destinazione urbanistica per il periodo
        $dest_row = DB::queryOne(
            'SELECT d.coefficiente_destinazione, d.Cod_Tip, d.Stato, d.Valore
               FROM omi_destinazione_urbanistica d
              WHERE d.destinazione = ?
              LIMIT 1',
            [$destinazione]
        );

        // Trova valore OMI
        $valore_omi = null;
        if ($foglio_row && $dest_row) {
            $valori_map = [1 => 'Compr_min', 2 => 'Compr_min', 3 => 'Compr_max'];
            // Usa media tra min e max per valore medio
            $omi_row = DB::queryOne(
                'SELECT Compr_min, Compr_max, Zona, Descr_Tipologia FROM valori_omi
                  WHERE Zona = ? AND Cod_Tip = ? AND Stato = ? AND Periodo = ?
                  LIMIT 1',
                [$foglio_row['zona_omi'], $dest_row['Cod_Tip'], $dest_row['Stato'], $periodo]
            );

            if ($omi_row) {
                $val_type = (int)$dest_row['Valore'];
                if ($val_type === 1)      $valore_omi = $omi_row['Compr_min'];
                elseif ($val_type === 3)  $valore_omi = $omi_row['Compr_max'];
                else                      $valore_omi = ($omi_row['Compr_min'] + $omi_row['Compr_max']) / 2;
            }
        }

        // Trova coefficiente abbattimento solo se selezionato, altrimenti usa valore neutro
        $abb_row = null;
        if ($id_abbattimento !== null) {
          $abb_row = DB::queryOne(
            'SELECT descrizione, valore FROM omi_abbattimenti WHERE id_coefficiente = ? LIMIT 1',
            [$id_abbattimento]
          );
        }

        if (!$foglio_row) {
            $erroreCalcolo = "Nessuna zona OMI associata al foglio catastale \"$foglio\".";
        } elseif (!$dest_row) {
            $erroreCalcolo = "Destinazione urbanistica non configurata.";
        } elseif ($valore_omi === null) {
            $erroreCalcolo = "Nessun valore OMI trovato per la combinazione selezionata nel periodo $periodo.";
        } else {
            $oneri_adattamento_check = isset($_POST['oneri_adattamento']);
            
            $coeff_dest = (float) $dest_row['coefficiente_destinazione'];
          $coeff_abb  = $abb_row ? (float) $abb_row['valore'] : 1.0;
            
            // Logica Montesilvano: VMR è il 20% del valore OMI
            $vmr = $valore_omi * 0.2;
            
            $valore_unitario = $vmr * $coeff_dest * $coeff_abb;
            
            // Detrazione oneri adattamento (5%)
            $sconto_oneri = 0;
            if ($oneri_adattamento_check) {
                $sconto_oneri = $valore_unitario * 0.05;
                $valore_unitario -= $sconto_oneri;
            }

            $valore_totale = $valore_unitario * $superficie;

            $risultato = [
                'periodo'            => $periodo,
                'foglio'             => $foglio,
                'zona_omi'           => $foglio_row['zona_omi'],
                'tipologia'          => $omi_row['Descr_Tipologia'] ?? '',
                'stato'              => $dest_row['Stato'],
                'destinazione'       => $destinazione,
                'superficie'         => $superficie,
                'valore_omi'         => $valore_omi,
                'vmr'                => $vmr,
                'coeff_dest'         => $coeff_dest,
                'coeff_abb'          => $coeff_abb,
                'desc_abb'           => $abb_row['descrizione'] ?? 'Non selezionato',
                'oneri_adattamento'  => $oneri_adattamento_check,
                'sconto_oneri'       => $sconto_oneri,
                'valore_unitario'    => $valore_unitario,
                'valore_totale'      => $valore_totale,
            ];
        }
    }
}

$isAdmin   = isLoggedIn();
$pageTitle = 'Calcolo Stima Valori Venali';
include __DIR__ . '/layout/header.php';
?>

<style>
  @media print {
    .d-print-none, .govbar, .site-header, .main-nav, .site-footer, .hero-section, #form-calcolo-container, .navbar, .breadcrumb {
      display: none !important;
    }
    body { background: #fff !important; padding: 0 !important; }
    .container { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
    .card-header { background: #f8f9fa !important; color: #000 !important; border-bottom: 1px solid #ddd !important; }
    .result-card { background: #fff !important; color: #000 !important; border: 2px solid #000 !important; }
    .result-card * { color: #000 !important; }
    .result-card hr { border-color: #000 !important; }
    .page-content { padding-top: 0 !important; }
    .print-header { display: block !important; margin-bottom: 2rem; border-bottom: 2px solid #000; padding-bottom: 1rem; }
  }
  .print-header { display: none; }
</style>

<div class="print-header">
    <div class="d-flex align-items-center gap-3">
        <div style="font-size: 2rem;">🏗️</div>
        <div>
            <div class="fw-bold text-uppercase" style="letter-spacing: 0.1em;"><?= htmlspecialchars(COMUNE_NOME) ?></div>
            <h1 class="h4 mb-0">Stima Valore Venale Area Fabbricabile</h1>
            <div class="small text-muted">Documento generato il <?= date('d/m/Y \a\l\l\e H:i') ?></div>
        </div>
    </div>
</div>

<!-- ── Hero section ── -->
<div class="py-4 mb-4 rounded-4 hero-section d-print-none" style="background: linear-gradient(135deg,#003d7a 0%,#0066cc 60%,#0099cc 100%); color:#fff; padding: 2.5rem 2rem;">
  <div class="row align-items-center">
    <div class="col-lg-8">
      <h2 class="fw-bold mb-2">📐 Stima Valori Venali Aree Fabbricabili</h2>
      <p class="mb-0 opacity-75 fs-5">
        Calcolo basato sui valori OMI dell'Agenzia delle Entrate con applicazione dei coefficienti di destinazione urbanistica e, se selezionato, dello stato conservativo.
      </p>
    </div>
    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
      <?php if ($periodi): ?>
        <span class="badge bg-white text-primary fs-6 px-3 py-2">
          📅 Ultimo periodo: <?= htmlspecialchars($periodi[0]['Periodo']) ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!$periodi): ?>
  <!-- Nessun dato OMI caricato -->
  <div class="alert alert-warning d-flex align-items-start gap-3" role="alert">
    <span style="font-size:2rem">⚠️</span>
    <div>
      <h5 class="mb-1">Nessun dato OMI disponibile</h5>
      <p class="mb-0">Il database non contiene ancora valori OMI. Un amministratore deve caricare i dati tramite l'area riservata.</p>
      <a href="<?= APP_URL ?>/admin/login.php" class="btn btn-sm btn-warning mt-2 d-none" data-admin-login-link>Accedi all'area admin</a>
    </div>
  </div>
<?php else: ?>

<div class="row g-4">
  <!-- ── Form calcolo ── -->
  <div class="col-lg-5" id="form-calcolo-container">
    <div class="card h-100">
      <div class="card-header">
        <h3 class="h6 mb-0">🔢 Dati per il Calcolo</h3>
      </div>
      <div class="card-body p-4">
        <?php if ($erroreCalcolo): ?>
          <div class="alert alert-danger">⚠️ <?= htmlspecialchars($erroreCalcolo) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= APP_URL ?>/index.php" id="form-calcolo" novalidate>
          <input type="hidden" name="calcola" value="1">

          <div class="mb-3">
            <label for="periodo" class="form-label fw-semibold">Periodo OMI <span class="text-danger">*</span></label>
            <select class="form-select" id="periodo" name="periodo" required>
              <option value="">— Seleziona periodo —</option>
              <?php foreach ($periodi as $p): ?>
                <option value="<?= htmlspecialchars($p['Periodo']) ?>"
                  <?= (($_POST['periodo'] ?? '') === $p['Periodo']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($p['Periodo']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="foglio_catastale" class="form-label fw-semibold">Foglio Catastale <span class="text-danger">*</span></label>
            <select class="form-select" id="foglio_catastale" name="foglio_catastale" required>
              <option value="">— Seleziona foglio —</option>
              <?php foreach ($fogli as $f): ?>
                <option value="<?= htmlspecialchars($f['foglio_catastale']) ?>"
                  <?= (($_POST['foglio_catastale'] ?? '') === $f['foglio_catastale']) ? 'selected' : '' ?>>
                  Foglio <?= htmlspecialchars($f['foglio_catastale']) ?>
                  (Zona <?= htmlspecialchars($f['zona_omi']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Il foglio determina la zona OMI di riferimento</div>
          </div>

          <div class="mb-3">
            <label for="destinazione" class="form-label fw-semibold">Destinazione Urbanistica <span class="text-danger">*</span></label>
            <select class="form-select" id="destinazione" name="destinazione" required>
              <option value="">— Seleziona destinazione —</option>
              <?php foreach ($destinazioni as $d): ?>
                <option value="<?= htmlspecialchars($d['destinazione']) ?>"
                  <?= (($_POST['destinazione'] ?? '') === $d['destinazione']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($d['destinazione']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="id_abbattimento" class="form-label fw-semibold">Stato Conservativo (opzionale)</label>
            <select class="form-select" id="id_abbattimento" name="id_abbattimento">
              <option value="">— Non selezionato —</option>
              <?php foreach ($abbattimenti as $a): ?>
                <option value="<?= (int)$a['id_coefficiente'] ?>"
                  <?= (($_POST['id_abbattimento'] ?? '') == $a['id_coefficiente']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($a['descrizione']) ?>
                  (coeff. <?= number_format($a['valore'], 2) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Se non selezionato, viene applicato coefficiente neutro 1,00</div>
          </div>

          <div class="mb-4">
            <label for="superficie" class="form-label fw-semibold">Superficie (mq) <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="number" class="form-control form-control-lg" id="superficie" name="superficie"
                     min="1" step="0.01" placeholder="Es. 500"
                     value="<?= htmlspecialchars($_POST['superficie'] ?? '') ?>" required>
              <span class="input-group-text">m²</span>
            </div>
          </div>

          <div class="mb-4 p-3 bg-light rounded-3 border">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="oneri_adattamento" name="oneri_adattamento"
                <?= isset($_POST['oneri_adattamento']) ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="oneri_adattamento">
                🛠️ Oneri di adattamento (-5%)
              </label>
              <div class="form-text small">
                Selezionare in caso di necessità di rilevanti interventi di adattamento ai fini edificatori (risultanti da perizia tecnica).
              </div>
            </div>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
              📊 Calcola Stima
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Risultato + Info ── -->
  <div class="col-lg-7">
    <?php if ($risultato): ?>
      <!-- Card risultato -->
      <div class="card border-0 mb-4 result-card" style="background: linear-gradient(135deg,#003d7a,#0066cc); color:#fff;">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="d-flex align-items-center gap-2">
                <span style="font-size:2rem">💰</span>
                <div>
                  <div class="small opacity-75">Valore Venale Stimato</div>
                  <div class="fw-bold" style="font-size:2.4rem; letter-spacing:-.02em;">
                    <?= number_format($risultato['valore_totale'], 2, ',', '.') ?> €
                  </div>
                </div>
            </div>
            <div class="d-print-none">
                <button onclick="window.print()" class="btn btn-light btn-sm fw-bold px-3">
                    🖨️ Stampa Risultato
                </button>
            </div>
          </div>
          <hr style="border-color:rgba(255,255,255,.3)">
          <div class="row g-2 small">
            <div class="col-6 opacity-75">Valore unitario</div>
            <div class="col-6 text-end fw-semibold"><?= number_format($risultato['valore_unitario'], 2, ',', '.') ?> €/m²</div>
            <div class="col-6 opacity-75">Superficie</div>
            <div class="col-6 text-end fw-semibold"><?= number_format($risultato['superficie'], 2, ',', '.') ?> m²</div>
          </div>
        </div>
      </div>

      <!-- Dettaglio calcolo -->
      <div class="card mb-4">
        <div class="card-header">📋 Dettaglio del Calcolo</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <tbody>
              <tr>
                <td class="text-muted ps-3" style="width:45%">Periodo OMI</td>
                <td class="fw-semibold"><?= htmlspecialchars($risultato['periodo']) ?></td>
              </tr>
              <tr>
                <td class="text-muted ps-3">Foglio Catastale</td>
                <td class="fw-semibold"><?= htmlspecialchars($risultato['foglio']) ?></td>
              </tr>
              <tr>
                <td class="text-muted ps-3">Zona OMI</td>
                <td class="fw-semibold"><?= htmlspecialchars($risultato['zona_omi']) ?></td>
              </tr>
              <tr>
                <td class="text-muted ps-3">Tipologia OMI</td>
                <td class="fw-semibold"><?= htmlspecialchars($risultato['tipologia']) ?> (<?= htmlspecialchars($risultato['stato']) ?>)</td>
              </tr>
              <tr>
                <td class="text-muted ps-3">Destinazione Urbanistica</td>
                <td class="fw-semibold"><?= htmlspecialchars($risultato['destinazione']) ?></td>
              </tr>
              <tr>
                <td class="text-muted ps-3">Valore OMI di riferimento</td>
                <td class="fw-semibold text-end"><?= number_format($risultato['valore_omi'], 2, ',', '.') ?> €/m²</td>
              </tr>
              <tr>
                <td class="text-muted ps-3">VMR (20% del Valore OMI)</td>
                <td class="fw-semibold text-end"><?= number_format($risultato['vmr'], 2, ',', '.') ?> €/m²</td>
              </tr>
              <tr>
                <td class="text-muted ps-3">Coefficiente destinazione (IDU)</td>
                <td class="fw-semibold text-end"><?= number_format($risultato['coeff_dest'], 4, ',', '.') ?></td>
              </tr>
              <tr>
                <td class="text-muted ps-3">Stato conservativo (CA, opzionale)</td>
                <td class="fw-semibold text-end">
                    <?= htmlspecialchars($risultato['desc_abb']) ?><br>
                    <span class="small text-muted">× <?= number_format($risultato['coeff_abb'], 2, ',', '.') ?></span>
                </td>
              </tr>
              <?php if ($risultato['oneri_adattamento']): ?>
                <tr class="table-warning">
                  <td class="text-muted ps-3 font-italic">Oneri adattamento (-5%)</td>
                  <td class="text-danger text-end fw-semibold">- <?= number_format($risultato['sconto_oneri'], 2, ',', '.') ?> €/m²</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-light">
          <small class="text-muted">
            <strong>Formula:</strong> [VMR
            × IDU
            × CA]
            <?= $risultato['oneri_adattamento'] ? ' - 5%' : '' ?>
            × Superfic. =
            <strong><?= number_format($risultato['valore_totale'], 2, ',', '.') ?> €</strong>
          </small>
        </div>
      </div>

      <div class="alert alert-info" role="alert">
        <strong>ℹ️ Nota legale:</strong>
        La stima è puramente indicativa e basata sui valori OMI pubblicati dall'Agenzia delle Entrate.
        Non sostituisce una perizia tecnica ufficiale.
      </div>

    <?php else: ?>

      <!-- Box informativo quando non c'è ancora un calcolo -->
      <div class="card mb-4 d-print-none" style="border-left: 4px solid #0066cc !important;">
        <div class="card-body p-4">
          <h4 class="h5 fw-bold mb-3">Come funziona il calcolo</h4>
          <p class="text-muted">La stima viene calcolata applicando la formula OMI:</p>
          <div class="p-3 rounded-3 mb-3" style="background:#f0f5ff; font-family:'Roboto Mono',monospace; font-size:.9rem;">
            Valore Venale = Superficie × Valore OMI × Coeff. Destinazione × (Coeff. Abbattimento se selezionato)
          </div>
          <div class="row g-3 mt-2">
            <div class="col-sm-6">
              <div class="d-flex gap-2 align-items-start">
                <span class="badge bg-primary rounded-circle p-2">1</span>
                <div><strong>Periodo OMI</strong><br><small class="text-muted">Semestre di riferimento (es. 2° sem. 2024)</small></div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="d-flex gap-2 align-items-start">
                <span class="badge bg-primary rounded-circle p-2">2</span>
                <div><strong>Foglio Catastale</strong><br><small class="text-muted">Determina la zona OMI dell'immobile</small></div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="d-flex gap-2 align-items-start">
                <span class="badge bg-primary rounded-circle p-2">3</span>
                <div><strong>Destinazione Urbanistica</strong><br><small class="text-muted">Zona PRG che determina il coefficiente</small></div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="d-flex gap-2 align-items-start">
                <span class="badge bg-primary rounded-circle p-2">4</span>
                <div><strong>Stato Conservativo (opzionale)</strong><br><small class="text-muted">Se non indicato, viene applicato coefficiente 1,00</small></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card d-print-none">
        <div class="card-body p-4">
          <h5 class="mb-2">📌 Riferimenti normativi</h5>
          <ul class="list-unstyled text-muted small">
            <li>• <strong>D.L. 504/1992</strong> — Determinazione valore venale aree edificabili</li>
            <li>• <strong>OMI</strong> — Osservatorio del Mercato Immobiliare (Agenzia delle Entrate)</li>
            <li>• Valori aggiornati semestralmente</li>
          </ul>
        </div>
      </div>

    <?php endif; ?>
  </div>
</div><!-- /row -->
<?php endif; ?>

<?php include __DIR__ . '/layout/footer.php'; ?>
