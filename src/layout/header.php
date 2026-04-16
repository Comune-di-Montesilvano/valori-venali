<?php
/**
 * Header Bootstrap Italia — layout istituzionale
 * 
 * Variabili attese:
 *  $pageTitle  — titolo pagina (opzionale)
 *  $isAdmin    — bool (opzionale)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings.php';

Settings::load();
$customLogo = Settings::get('logo_path');
$customFavicon = Settings::get('favicon_path');


$pageTitle = $pageTitle ?? 'Valori Venali Aree Fabbricabili';
$comuneNome = COMUNE_NOME;
$comuneProv = COMUNE_PROVINCIA ? ' (' . COMUNE_PROVINCIA . ')' : '';

// SEO Logic
$seoDescription = SEO_DESCRIPTION;
$seoKeywords    = SEO_KEYWORDS;
$robots         = !empty($isAdmin) ? 'noindex, nofollow' : 'index, follow';
$canonicalUrl   = APP_URL . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="it" data-bs-theme="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
  <meta name="robots" content="<?= $robots ?>"/>
  <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>"/>
  <meta name="keywords" content="<?= htmlspecialchars($seoKeywords) ?>"/>
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>"/>

  <?php if ($customFavicon): ?>
    <link rel="icon" href="<?= APP_URL ?>/uploads/<?= htmlspecialchars($customFavicon) ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= APP_URL ?>/uploads/<?= htmlspecialchars($customFavicon) ?>" type="image/x-icon">
  <?php else: ?>
    <link rel="icon" href="<?= APP_URL ?>/favicon.png" type="image/png">
  <?php endif; ?>


  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website"/>
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>"/>
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle . ' — ' . $comuneNome) ?>"/>
  <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>"/>
  <meta property="og:image" content="<?= $customFavicon ? APP_URL . '/uploads/' . $customFavicon : APP_URL . '/favicon.png' ?>"/>


  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image"/>
  <meta property="twitter:url" content="<?= htmlspecialchars($canonicalUrl) ?>"/>
  <meta property="twitter:title" content="<?= htmlspecialchars($pageTitle . ' — ' . $comuneNome) ?>"/>
  <meta property="twitter:description" content="<?= htmlspecialchars($seoDescription) ?>"/>

  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($comuneNome) ?></title>

  <!-- Bootstrap Italia -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-italia@2.13.0/dist/css/bootstrap-italia.min.css"
        crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700;900&family=Lora:wght@400;700&family=Roboto+Mono&display=swap"
        rel="stylesheet">

  <style>
    :root {
      --bs-primary:    #06c;
      --accent:        #004d99;
      --hero-bg:       linear-gradient(135deg, #003d7a 0%, #0066cc 50%, #0099cc 100%);
      --card-radius:   12px;
    }

    body {
      font-family: 'Titillium Web', sans-serif;
      background: #f5f7fa;
    }

    /* ── Slim govbar ── */
    .govbar {
      background: #003d7a;
      color: #fff;
      font-size: .8rem;
      padding: .3rem 0;
      letter-spacing: .03em;
    }
    .govbar a { color: rgba(255,255,255,.75); text-decoration: none; }
    .govbar a:hover { color: #fff; }

    /* ── Site header ── */
    .site-header {
      background: #fff;
      border-bottom: 3px solid var(--bs-primary);
      padding: 1rem 0;
      box-shadow: 0 2px 12px rgba(0,0,0,.06);
    }
    .site-header .comune-label {
      font-size: .78rem;
      color: #555;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .08em;
    }
    .site-header h1 {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--accent);
      margin: 0;
      line-height: 1.2;
    }
    .site-header .logo-icon {
      width: 48px; height: 48px;
      background: var(--hero-bg);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 1.5rem;
      flex-shrink: 0;
    }

    /* ── Main navbar ── */
    .main-nav {
      background: var(--accent);
    }
    .main-nav .nav-link {
      color: rgba(255,255,255,.85) !important;
      font-weight: 600;
      font-size: .9rem;
      padding: .6rem 1.2rem !important;
      transition: all .2s;
    }
    .main-nav .nav-link:hover,
    .main-nav .nav-link.active {
      color: #fff !important;
      background: rgba(255,255,255,.15);
      border-radius: 6px;
    }

    /* ── Admin badge ── */
    .admin-badge {
      background: rgba(255,200,0,.25);
      border: 1px solid rgba(255,200,0,.5);
      color: #ffe066;
      font-size: .72rem;
      padding: .15rem .5rem;
      border-radius: 20px;
      font-weight: 700;
      letter-spacing: .05em;
    }

    /* ── Cards ── */
    .card {
      border: none;
      border-radius: var(--card-radius);
      box-shadow: 0 2px 16px rgba(0,0,0,.07);
      transition: box-shadow .2s;
    }
    .card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.12); }
    .card-header {
      background: var(--hero-bg);
      color: #fff;
      border-radius: var(--card-radius) var(--card-radius) 0 0 !important;
      font-weight: 700;
    }

    /* ── Alerts ── */
    .alert { border-radius: 10px; border: none; }

    /* ── Tables ── */
    .table thead th {
      background: var(--accent);
      color: #fff;
      font-weight: 600;
      border: none;
    }
    .table tbody tr:hover { background: #eef4fb; }

    /* ── Buttons ── */
    .btn-primary { background: var(--bs-primary); border-color: var(--bs-primary); }
    .btn-primary:hover { background: var(--accent); border-color: var(--accent); }

    /* ── Main content ── */
    .page-content { min-height: calc(100vh - 260px); padding: 2rem 0 3rem; }

    /* ── Footer ── */
    .site-footer {
      background: #1a2d4a;
      color: rgba(255,255,255,.7);
      font-size: .85rem;
      padding: 1.5rem 0;
      margin-top: 3rem;
    }
    .site-footer a { color: rgba(255,255,255,.6); }
    .site-footer .version-badge {
      background: rgba(255,255,255,.1);
      border-radius: 20px;
      padding: .15rem .6rem;
      font-size: .75rem;
    }
  </style>
</head>
<body>

<!-- ── Govbar ── -->
<div class="govbar">
  <div class="container d-flex align-items-center gap-3">
    <span>🇮🇹 Repubblica Italiana</span>
    <span class="ms-auto"><?= htmlspecialchars($comuneNome . $comuneProv) ?></span>
  </div>
</div>

<!-- ── Site Header ── -->
<div class="site-header">
  <div class="container">
    <div class="d-flex align-items-center gap-3">
      <?php if ($customLogo): ?>
        <img src="<?= APP_URL ?>/uploads/<?= htmlspecialchars($customLogo) ?>" alt="Logo <?= htmlspecialchars($comuneNome) ?>" style="max-height: 48px; width: auto; border-radius: 8px;">
      <?php else: ?>
        <div class="logo-icon">🏗️</div>
      <?php endif; ?>
      <div>

        <div class="comune-label"><?= htmlspecialchars($comuneNome . $comuneProv) ?></div>
        <h1>Valori Venali Aree Fabbricabili</h1>
      </div>
      <?php if (!empty($isAdmin)): ?>
        <div class="ms-auto"><span class="admin-badge">⚙ AREA ADMIN</span></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Main Nav ── -->
<nav class="main-nav">
  <div class="container">
    <ul class="nav">
      <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>"
           href="<?= APP_URL ?>/">📊 Calcolo Stima</a>
      </li>
      <?php if (!empty($isAdmin)): ?>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"
             href="<?= APP_URL ?>/admin/dashboard.php">🏠 Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'importa_omi.php' ? 'active' : '' ?>"
             href="<?= APP_URL ?>/admin/importa_omi.php">📂 Importa OMI</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'parametri_omi.php' ? 'active' : '' ?>"
             href="<?= APP_URL ?>/admin/parametri_omi.php">⚙ Parametri</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'coefficienti_abbattimento.php' ? 'active' : '' ?>"
             href="<?= APP_URL ?>/admin/coefficienti_abbattimento.php">📉 Coefficienti</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'fogli_omi.php' ? 'active' : '' ?>"
             href="<?= APP_URL ?>/admin/fogli_omi.php">🗺 Fogli Catastali</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'impostazioni.php' ? 'active' : '' ?>"
             href="<?= APP_URL ?>/admin/impostazioni.php">🛠 Impostazioni</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'backup.php' ? 'active' : '' ?>"

             href="<?= APP_URL ?>/admin/backup.php">💾 Backup</a>
        </li>
        <li class="nav-item ms-auto">
          <a class="nav-link" href="<?= APP_URL ?>/admin/logout.php">🚪 Esci</a>
        </li>
      <?php else: ?>
        <li class="nav-item ms-auto d-none" data-admin-login-link>
          <a class="nav-link" href="<?= APP_URL ?>/admin/login.php">🔐 Admin</a>
        </li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const adminLinks = document.querySelectorAll('[data-admin-login-link]');
    if (!adminLinks.length) return;

    fetch('<?= APP_URL ?>/admin/login.php', {
      method: 'GET',
      credentials: 'same-origin',
      redirect: 'follow'
    })
      .then(function (response) {
        const reachable = response.ok || [301, 302, 303, 307, 308, 401].includes(response.status);
        if (!reachable) return;
        adminLinks.forEach(function (el) {
          el.classList.remove('d-none');
        });
      })
      .catch(function () {
        // Se la rotta non è raggiungibile da questa rete, il link resta nascosto.
      });
  });
</script>

<!-- ── Page Content ── -->
<main class="page-content">
  <div class="container">
