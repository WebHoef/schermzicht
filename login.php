<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

$activeTab = 'admin';
$loginError = null;
$notice = null;

if (sz_current_user() !== null) {
    header('Location: cms.php', true, 302);
    exit;
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $notice = 'Je bent veilig uitgelogd.';
}

if (sz_method_is_post()) {
    $activeTab = sz_normalize_input($_POST['portal'] ?? 'admin', 20);
    if (!in_array($activeTab, ['admin', 'customer'], true)) {
        $activeTab = 'admin';
    }

    if (!sz_validate_csrf($_POST['csrf_token'] ?? null)) {
        $loginError = 'Je sessie is verlopen. Vernieuw de pagina en probeer opnieuw.';
    } else {
        $email = sz_normalize_input($_POST['email'] ?? '', 190);
        $password = (string) ($_POST['password'] ?? '');
        $roleHint = $activeTab === 'admin' ? 'admin' : 'customer';

        if (!sz_login($email, $password, $roleHint)) {
            $loginError = 'Onjuiste inloggegevens of geen toegang voor dit portaal.';
        } else {
            header('Location: cms.php', true, 303);
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="nl">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen | schermzicht CMS</title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    >
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >
    <link rel="stylesheet" href="cms.css">
  </head>
  <body class="bg-light">
    <main class="container py-5">
      <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
          <div class="text-center mb-4">
            <span class="logo-placeholder mx-auto mb-3 d-inline-flex" aria-hidden="true">SZ</span>
            <h1 class="h3 mb-1">schermzicht CMS</h1>
            <p class="text-muted mb-0">
              Multi-tenant toegang met gescheiden Admin- en Klantomgeving.
            </p>
          </div>

          <?php if ($notice !== null): ?>
            <div class="alert alert-success" role="alert"><?= sz_escape($notice) ?></div>
          <?php endif; ?>
          <?php if ($loginError !== null): ?>
            <div class="alert alert-danger" role="alert"><?= sz_escape($loginError) ?></div>
          <?php endif; ?>

          <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
              <ul class="nav nav-pills mb-4 justify-content-center" role="tablist">
                <li class="nav-item" role="presentation">
                  <button
                    class="nav-link<?= $activeTab === 'admin' ? ' active' : '' ?>"
                    id="admin-tab"
                    data-bs-toggle="pill"
                    data-bs-target="#admin-pane"
                    type="button"
                    role="tab"
                    aria-controls="admin-pane"
                    aria-selected="<?= $activeTab === 'admin' ? 'true' : 'false' ?>"
                  >
                    <i class="bi bi-shield-lock me-1" aria-hidden="true"></i> Admin login
                  </button>
                </li>
                <li class="nav-item" role="presentation">
                  <button
                    class="nav-link<?= $activeTab === 'customer' ? ' active' : '' ?>"
                    id="customer-tab"
                    data-bs-toggle="pill"
                    data-bs-target="#customer-pane"
                    type="button"
                    role="tab"
                    aria-controls="customer-pane"
                    aria-selected="<?= $activeTab === 'customer' ? 'true' : 'false' ?>"
                  >
                    <i class="bi bi-building me-1" aria-hidden="true"></i> Klant login
                  </button>
                </li>
              </ul>

              <div class="tab-content">
                <div
                  class="tab-pane fade<?= $activeTab === 'admin' ? ' show active' : '' ?>"
                  id="admin-pane"
                  role="tabpanel"
                  aria-labelledby="admin-tab"
                >
                  <form method="post" action="login.php" class="row g-3" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                    <input type="hidden" name="portal" value="admin">
                    <div class="col-12">
                      <label class="form-label" for="admin-email">E-mailadres</label>
                      <input
                        id="admin-email"
                        name="email"
                        type="email"
                        maxlength="190"
                        autocomplete="email"
                        class="form-control"
                        placeholder="admin@schermzicht.nl"
                        required
                      >
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="admin-password">Wachtwoord</label>
                      <input
                        id="admin-password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        class="form-control"
                        placeholder="Voer je admin wachtwoord in"
                        required
                      >
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                      <small class="text-muted">Alleen platformbeheerders hebben toegang.</small>
                      <button type="submit" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Inloggen als admin
                      </button>
                    </div>
                  </form>
                </div>

                <div
                  class="tab-pane fade<?= $activeTab === 'customer' ? ' show active' : '' ?>"
                  id="customer-pane"
                  role="tabpanel"
                  aria-labelledby="customer-tab"
                >
                  <form method="post" action="login.php" class="row g-3" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                    <input type="hidden" name="portal" value="customer">
                    <div class="col-12">
                      <label class="form-label" for="customer-email">E-mailadres</label>
                      <input
                        id="customer-email"
                        name="email"
                        type="email"
                        maxlength="190"
                        autocomplete="email"
                        class="form-control"
                        placeholder="manager@vianenfysio.nl"
                        required
                      >
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="customer-password">Wachtwoord</label>
                      <input
                        id="customer-password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        class="form-control"
                        placeholder="Voer je klant wachtwoord in"
                        required
                      >
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                      <small class="text-muted">Je ziet alleen data van je eigen klantomgeving.</small>
                      <button type="submit" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Inloggen als klant
                      </button>
                    </div>
                  </form>
                </div>
              </div>

              <hr class="my-4">
              <p class="mb-1 fw-semibold">Demo accounts (dummy data)</p>
              <ul class="small text-muted mb-0">
                <li>Admin: <code>admin@schermzicht.nl</code> / <code>Admin123!</code></li>
                <li>Klant: <code>manager@vianenfysio.nl</code> / <code>Klant123!</code></li>
              </ul>
            </div>
          </div>

          <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none">Terug naar de website</a>
          </div>
        </div>
      </div>
    </main>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
  </body>
</html>
