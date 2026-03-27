<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();
seedAdmin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        $next = filter_var($_GET['next'] ?? '', FILTER_SANITIZE_URL);
        // Accetta solo redirect interni
        if (str_starts_with($next, '/')) {
            header('Location: ' . APP_URL . $next);
        } else {
            header('Location: ' . APP_URL . '/admin/dashboard.php');
        }
        exit;
    }
    $error = 'Credenziali non valide. Riprovare.';
}

$pageTitle = 'Accesso Area Riservata';
include __DIR__ . '/../layout/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5 col-lg-4">

    <div class="card mt-4">
      <div class="card-header text-center py-3">
        <span style="font-size:2rem">🔐</span>
        <h2 class="h5 mb-0 mt-1">Accesso Area Amministrativa</h2>
      </div>
      <div class="card-body p-4">

        <?php if ($error): ?>
          <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <span>⚠️</span> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= APP_URL ?>/admin/login.php<?= isset($_GET['next']) ? '?next=' . urlencode($_GET['next']) : '' ?>" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

          <div class="mb-3">
            <label for="username" class="form-label fw-semibold">Nome utente</label>
            <input type="text" class="form-control form-control-lg" id="username" name="username"
                   autocomplete="username" required autofocus
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>

          <div class="mb-4">
            <label for="password" class="form-label fw-semibold">Password</label>
            <input type="password" class="form-control form-control-lg" id="password" name="password"
                   autocomplete="current-password" required>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">Accedi</button>
          </div>
        </form>

        <div class="text-center mt-3">
          <a href="<?= APP_URL ?>/" class="text-muted small">← Torna al calcolo stima</a>
        </div>
      </div>
    </div>

    <p class="text-center text-muted small mt-3">
      Area riservata agli operatori autorizzati.<br>
      Per assistenza contattare l'amministratore di sistema.
    </p>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
