<?php

declare(strict_types=1);

require_once __DIR__ . '/app/cms_service.php';

/**
 * Maps status values to existing badge styles.
 */
function sz_status_badge_class(string $status): string
{
    return in_array($status, ['active', 'success'], true) ? 'status-active' : 'status-planned';
}

/**
 * Prevents invalid hash targets in post-redirect-get cycle.
 */
function sz_safe_redirect_target(mixed $value, string $default = 'dashboard'): string
{
    $target = sz_normalize_input($value, 60);
    if (preg_match('/^[a-z0-9_-]+$/', $target) !== 1) {
        return $default;
    }

    return $target;
}

$currentUser = sz_require_user();
$isAdmin = sz_is_admin_user($currentUser);
$pdo = sz_db();

$flashMessage = null;
$flashType = 'success';
if (isset($_SESSION['cms_flash']) && is_array($_SESSION['cms_flash'])) {
    $flashMessage = is_string($_SESSION['cms_flash']['message'] ?? null)
        ? $_SESSION['cms_flash']['message']
        : null;
    $flashType = ($_SESSION['cms_flash']['type'] ?? 'success') === 'danger' ? 'danger' : 'success';
    unset($_SESSION['cms_flash']);
}

if (sz_method_is_post()) {
    $redirectTarget = sz_safe_redirect_target($_POST['redirect_to'] ?? 'dashboard');
    $action = sz_normalize_input($_POST['action'] ?? '', 80);
    $message = null;
    $type = 'success';

    try {
        if (!sz_validate_csrf($_POST['csrf_token'] ?? null)) {
            throw new \RuntimeException('Je sessie is verlopen. Vernieuw de pagina en probeer opnieuw.');
        }

        /**
         * Keep one centralized action switch so all POST operations
         * consistently enforce RBAC and tenant boundaries.
         */
        switch ($action) {
            case 'create_customer':
                $newCustomerId = sz_create_customer($pdo, $currentUser, $_POST);
                $message = 'Klant aangemaakt (ID ' . $newCustomerId . ').';
                break;
            case 'update_customer':
                sz_update_customer($pdo, $currentUser, (int) ($_POST['customer_id'] ?? 0), $_POST);
                $message = 'Klantgegevens bijgewerkt.';
                break;
            case 'delete_customer':
                sz_delete_customer($pdo, $currentUser, (int) ($_POST['customer_id'] ?? 0));
                $message = 'Klant en gekoppelde tenantdata verwijderd.';
                break;
            case 'create_user':
                $newUserId = sz_create_user($pdo, $currentUser, $_POST);
                $message = 'Gebruiker aangemaakt (ID ' . $newUserId . ').';
                break;
            case 'update_user':
                sz_update_user($pdo, $currentUser, (int) ($_POST['user_id'] ?? 0), $_POST);
                $message = 'Gebruiker bijgewerkt.';
                break;
            case 'delete_user':
                sz_delete_user($pdo, $currentUser, (int) ($_POST['user_id'] ?? 0));
                $message = 'Gebruiker verwijderd.';
                break;
            case 'update_role':
                sz_update_role($pdo, $currentUser, (int) ($_POST['role_id'] ?? 0), $_POST);
                $message = 'Rol en rechten bijgewerkt.';
                break;
            case 'create_content':
                $newContentId = sz_create_content($pdo, $currentUser, $_POST);
                $message = 'Content-item aangemaakt (ID ' . $newContentId . ').';
                break;
            case 'update_content':
                sz_update_content($pdo, $currentUser, (int) ($_POST['content_id'] ?? 0), $_POST);
                $message = 'Content-item bijgewerkt.';
                break;
            case 'delete_content':
                sz_delete_content($pdo, $currentUser, (int) ($_POST['content_id'] ?? 0));
                $message = 'Content-item verwijderd.';
                break;
            case 'create_playlist':
                $newPlaylistId = sz_create_playlist($pdo, $currentUser, $_POST);
                $message = 'Playlist aangemaakt (ID ' . $newPlaylistId . ').';
                break;
            case 'update_playlist':
                sz_update_playlist($pdo, $currentUser, (int) ($_POST['playlist_id'] ?? 0), $_POST);
                $message = 'Playlist bijgewerkt.';
                break;
            case 'delete_playlist':
                sz_delete_playlist($pdo, $currentUser, (int) ($_POST['playlist_id'] ?? 0));
                $message = 'Playlist verwijderd.';
                break;
            case 'update_profile':
                sz_update_profile($pdo, $currentUser, $_POST);
                $message = 'Jouw profielinstellingen zijn bijgewerkt.';
                break;
            default:
                throw new \InvalidArgumentException('Onbekende actie.');
        }
    } catch (\Throwable $exception) {
        error_log('CMS action failed: ' . $exception->getMessage());
        $type = 'danger';
        if ($exception instanceof \InvalidArgumentException || $exception instanceof \RuntimeException) {
            $message = $exception->getMessage();
        } else {
            $message = 'De actie kon niet worden uitgevoerd. Probeer het opnieuw.';
        }
    }

    $_SESSION['cms_flash'] = [
        'type' => $type,
        'message' => $message ?? 'Onbekende status na uitvoeren van de actie.',
    ];

    header('Location: cms.php#' . rawurlencode($redirectTarget), true, 303);
    exit;
}

$dbStatus = 'Verbonden';
$dbStatusClass = 'status-active';
$dbStatusDetail = 'Databaseverbinding actief, migraties automatisch uitgevoerd.';

try {
    $pdo->query('SELECT 1');
} catch (\Throwable $exception) {
    $dbStatus = 'Verbindingsprobleem';
    $dbStatusClass = 'status-planned';
    $dbStatusDetail = 'Controleer de databaseconfiguratie in .env.';
}

$dashboardStats = [
    'customers_total' => 0,
    'users_total' => 0,
    'content_total' => 0,
    'playlists_total' => 0,
];
$customers = [];
$roles = [];
$users = [];
$contentItems = [];
$playlistItems = [];

try {
    $dashboardStats = sz_dashboard_stats($pdo, $currentUser);
    $customers = sz_list_customers($pdo, $currentUser);
    $contentItems = sz_list_content($pdo, $currentUser);
    $playlistItems = sz_list_playlists($pdo, $currentUser);
    if ($isAdmin) {
        $roles = sz_list_roles($pdo, $currentUser);
        $users = sz_list_users($pdo, $currentUser);
    }
} catch (\Throwable $exception) {
    error_log('CMS load failed: ' . $exception->getMessage());
    if ($flashMessage === null) {
        $flashMessage = 'Niet alle dashboarddata kon worden geladen.';
        $flashType = 'danger';
    }
}

$customerStatusOptions = sz_customer_status_options();
$userStatusOptions = sz_user_status_options();
$contentTypeOptions = sz_content_type_options();
$contentStatusOptions = sz_content_status_options();
$playlistStatusOptions = sz_playlist_status_options();
?>
<!doctype html>
<html lang="nl">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>schermzicht CMS | Multi-tenant dashboard</title>
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
  <body>
    <header class="cms-topbar">
      <div class="container-fluid py-3 px-3 px-lg-4 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <span class="logo-placeholder" aria-hidden="true">SZ</span>
          <div>
            <p class="mb-0 fw-bold">schermzicht CMS</p>
            <small class="text-muted">
              <?= $isAdmin ? 'Admin omgeving - toegang tot alle klanten' : 'Klantomgeving - afgeschermde tenant scope' ?>
            </small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge text-bg-light border">
            <?= sz_escape((string) ($currentUser['full_name'] ?? 'Onbekende gebruiker')) ?>
            (<?= sz_escape((string) ($currentUser['role_name'] ?? '')) ?>)
          </span>
          <a class="btn btn-outline-secondary d-none d-md-inline-flex" href="index.php">Website</a>
          <a class="btn btn-outline-primary d-none d-md-inline-flex" href="api.php/auth/me">API status</a>
          <a class="btn btn-outline-danger" href="logout.php">
            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Uitloggen
          </a>
          <button
            class="btn btn-primary d-lg-none"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#sidebarMobile"
            aria-controls="sidebarMobile"
          >
            <i class="bi bi-list me-1" aria-hidden="true"></i> Menu
          </button>
        </div>
      </div>
    </header>

    <div class="admin-shell d-flex">
      <aside class="sidebar-desktop d-none d-lg-flex flex-column p-3">
        <p class="text-uppercase small mb-2 text-white-50">Navigatie</p>
        <nav class="nav flex-column gap-1">
          <a class="nav-link active" href="#dashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
          <?php if ($isAdmin): ?>
            <a class="nav-link" href="#klanten"><i class="bi bi-people me-2"></i>Klantenbeheer</a>
            <a class="nav-link" href="#gebruikers"><i class="bi bi-person-gear me-2"></i>Gebruikers</a>
            <a class="nav-link" href="#rollen"><i class="bi bi-shield-lock me-2"></i>Rollen en rechten</a>
          <?php endif; ?>
          <a class="nav-link" href="#content"><i class="bi bi-images me-2"></i>Content</a>
          <a class="nav-link" href="#playlists"><i class="bi bi-collection-play me-2"></i>Playlists</a>
          <a class="nav-link" href="#instellingen"><i class="bi bi-gear me-2"></i>Instellingen</a>
        </nav>
      </aside>

      <div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="sidebarMobile" aria-labelledby="sidebarMobileLabel">
        <div class="offcanvas-header">
          <h2 class="offcanvas-title h5 mb-0" id="sidebarMobileLabel">Menu</h2>
          <button
            type="button"
            class="btn-close btn-close-white"
            data-bs-dismiss="offcanvas"
            aria-label="Sluiten"
          ></button>
        </div>
        <div class="offcanvas-body">
          <nav class="nav flex-column gap-1">
            <a class="nav-link active" href="#dashboard" data-bs-dismiss="offcanvas">Dashboard</a>
            <?php if ($isAdmin): ?>
              <a class="nav-link" href="#klanten" data-bs-dismiss="offcanvas">Klantenbeheer</a>
              <a class="nav-link" href="#gebruikers" data-bs-dismiss="offcanvas">Gebruikers</a>
              <a class="nav-link" href="#rollen" data-bs-dismiss="offcanvas">Rollen en rechten</a>
            <?php endif; ?>
            <a class="nav-link" href="#content" data-bs-dismiss="offcanvas">Content</a>
            <a class="nav-link" href="#playlists" data-bs-dismiss="offcanvas">Playlists</a>
            <a class="nav-link" href="#instellingen" data-bs-dismiss="offcanvas">Instellingen</a>
          </nav>
        </div>
      </div>

      <main class="content-area p-3 p-md-4 p-xl-5">
        <?php if ($flashMessage !== null): ?>
          <div class="alert alert-<?= $flashType === 'danger' ? 'danger' : 'success' ?>" role="alert">
            <?= sz_escape($flashMessage) ?>
          </div>
        <?php endif; ?>

        <section id="dashboard" class="mb-5">
          <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
            <div>
              <h1 class="section-title h3 mb-1">Dashboard</h1>
              <p class="section-subtitle mb-0">
                <?= $isAdmin ? 'Overzicht van alle tenants, content en playlists.' : 'Overzicht van jouw eigen tenantomgeving.' ?>
              </p>
            </div>
            <span class="badge text-bg-light border">
              <?= $isAdmin ? 'Admin scope: alle klanten' : 'Tenant scope: ' . sz_escape((string) ($currentUser['customer_name'] ?? 'onbekend')) ?>
            </span>
          </div>

          <div class="row g-3">
            <div class="col-sm-6 col-xl-3">
              <article class="overview-card p-4 h-100">
                <p class="text-uppercase small text-muted mb-2"><?= $isAdmin ? 'Klanten' : 'Jouw tenant' ?></p>
                <p class="metric mb-1"><?= sz_escape((string) $dashboardStats['customers_total']) ?></p>
                <p class="mb-0 text-muted"><?= $isAdmin ? 'Actieve tenantomgevingen' : 'Toegang tot 1 afgeschermde omgeving' ?></p>
              </article>
            </div>
            <div class="col-sm-6 col-xl-3">
              <article class="overview-card p-4 h-100">
                <p class="text-uppercase small text-muted mb-2"><?= $isAdmin ? 'Gebruikers' : 'Mijn account' ?></p>
                <p class="metric mb-1"><?= sz_escape((string) $dashboardStats['users_total']) ?></p>
                <p class="mb-0 text-muted"><?= $isAdmin ? 'Admin + klantaccounts' : 'Beheer via instellingen' ?></p>
              </article>
            </div>
            <div class="col-sm-6 col-xl-3">
              <article class="overview-card p-4 h-100">
                <p class="text-uppercase small text-muted mb-2">Content-items</p>
                <p class="metric mb-1"><?= sz_escape((string) $dashboardStats['content_total']) ?></p>
                <p class="mb-0 text-muted"><?= $isAdmin ? 'Over alle klanten heen' : 'Alleen binnen jouw tenant' ?></p>
              </article>
            </div>
            <div class="col-sm-6 col-xl-3">
              <article class="overview-card p-4 h-100">
                <p class="text-uppercase small text-muted mb-2">Database status</p>
                <p class="mb-2">
                  <span class="status-pill <?= sz_escape($dbStatusClass) ?>"><?= sz_escape($dbStatus) ?></span>
                </p>
                <p class="mb-0 text-muted"><?= sz_escape($dbStatusDetail) ?></p>
              </article>
            </div>
          </div>
        </section>

        <?php if ($isAdmin): ?>
          <section id="klanten" class="mb-5">
            <div class="panel-card p-4">
              <h2 class="section-title h4 mb-1">Klantenbeheer (CRUD)</h2>
              <p class="section-subtitle mb-3">
                Maak tenants aan, wijzig klantgegevens en verwijder klantomgevingen.
              </p>

              <form method="post" action="cms.php#klanten" class="row g-2 mb-4">
                <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                <input type="hidden" name="action" value="create_customer">
                <input type="hidden" name="redirect_to" value="klanten">
                <div class="col-md-3">
                  <label class="form-label">Klantnaam</label>
                  <input type="text" name="name" class="form-control" maxlength="120" placeholder="Nieuwe klant" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Slug</label>
                  <input type="text" name="slug" class="form-control" maxlength="140" placeholder="optioneel">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Contactpersoon</label>
                  <input type="text" name="contact_person" class="form-control" maxlength="120">
                </div>
                <div class="col-md-3">
                  <label class="form-label">E-mail</label>
                  <input type="email" name="email" class="form-control" maxlength="190">
                </div>
                <div class="col-md-2">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-select">
                    <?php foreach ($customerStatusOptions as $status): ?>
                      <option value="<?= sz_escape($status) ?>"><?= sz_escape($status) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <button class="btn btn-primary" type="submit">Klant aanmaken</button>
                </div>
              </form>

              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>Klant</th>
                      <th>Slug</th>
                      <th>Contactpersoon</th>
                      <th>E-mail</th>
                      <th>Status</th>
                      <th>Users</th>
                      <th>Content</th>
                      <th>Playlists</th>
                      <th>Acties</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($customers as $customer): ?>
                      <?php $customerId = (int) ($customer['id'] ?? 0); ?>
                      <tr>
                        <td>
                          <input
                            class="form-control form-control-sm"
                            type="text"
                            name="name"
                            maxlength="120"
                            value="<?= sz_escape((string) ($customer['name'] ?? '')) ?>"
                            form="customer-form-<?= $customerId ?>"
                            required
                          >
                        </td>
                        <td>
                          <input
                            class="form-control form-control-sm"
                            type="text"
                            name="slug"
                            maxlength="140"
                            value="<?= sz_escape((string) ($customer['slug'] ?? '')) ?>"
                            form="customer-form-<?= $customerId ?>"
                          >
                        </td>
                        <td>
                          <input
                            class="form-control form-control-sm"
                            type="text"
                            name="contact_person"
                            maxlength="120"
                            value="<?= sz_escape((string) ($customer['contact_person'] ?? '')) ?>"
                            form="customer-form-<?= $customerId ?>"
                          >
                        </td>
                        <td>
                          <input
                            class="form-control form-control-sm"
                            type="email"
                            name="email"
                            maxlength="190"
                            value="<?= sz_escape((string) ($customer['email'] ?? '')) ?>"
                            form="customer-form-<?= $customerId ?>"
                          >
                        </td>
                        <td>
                          <select class="form-select form-select-sm" name="status" form="customer-form-<?= $customerId ?>">
                            <?php foreach ($customerStatusOptions as $status): ?>
                              <option
                                value="<?= sz_escape($status) ?>"
                                <?= ($status === ($customer['status'] ?? '')) ? 'selected' : '' ?>
                              >
                                <?= sz_escape($status) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td><?= sz_escape((string) ($customer['user_count'] ?? 0)) ?></td>
                        <td><?= sz_escape((string) ($customer['content_count'] ?? 0)) ?></td>
                        <td><?= sz_escape((string) ($customer['playlist_count'] ?? 0)) ?></td>
                        <td class="d-flex flex-wrap gap-1">
                          <form id="customer-form-<?= $customerId ?>" method="post" action="cms.php#klanten">
                            <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_customer">
                            <input type="hidden" name="redirect_to" value="klanten">
                            <input type="hidden" name="customer_id" value="<?= $customerId ?>">
                          </form>
                          <button class="btn btn-sm btn-outline-secondary" type="submit" form="customer-form-<?= $customerId ?>">
                            Opslaan
                          </button>
                          <form id="customer-delete-<?= $customerId ?>" method="post" action="cms.php#klanten">
                            <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_customer">
                            <input type="hidden" name="redirect_to" value="klanten">
                            <input type="hidden" name="customer_id" value="<?= $customerId ?>">
                          </form>
                          <button
                            class="btn btn-sm btn-outline-danger"
                            type="submit"
                            form="customer-delete-<?= $customerId ?>"
                            onclick="return confirm('Verwijder deze klant inclusief gekoppelde tenantdata?');"
                          >
                            Verwijderen
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <section id="gebruikers" class="mb-5">
            <div class="panel-card p-4">
              <h2 class="section-title h4 mb-1">Gebruikersbeheer (Admin + Klant)</h2>
              <p class="section-subtitle mb-3">
                Admin kan alle accounts beheren. Klantaccounts blijven tenant-gebonden.
              </p>

              <form method="post" action="cms.php#gebruikers" class="row g-2 mb-4">
                <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                <input type="hidden" name="action" value="create_user">
                <input type="hidden" name="redirect_to" value="gebruikers">
                <div class="col-md-2">
                  <label class="form-label">Naam</label>
                  <input type="text" name="full_name" class="form-control" maxlength="120" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">E-mail</label>
                  <input type="email" name="email" class="form-control" maxlength="190" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Wachtwoord</label>
                  <input type="password" name="password" class="form-control" minlength="8" required>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Rol</label>
                  <select name="role_id" class="form-select" required>
                    <?php foreach ($roles as $role): ?>
                      <option value="<?= sz_escape((string) ($role['id'] ?? '')) ?>">
                        <?= sz_escape((string) ($role['name'] ?? '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Klant (optioneel)</label>
                  <select name="customer_id" class="form-select">
                    <option value="">-</option>
                    <?php foreach ($customers as $customer): ?>
                      <option value="<?= sz_escape((string) ($customer['id'] ?? '')) ?>">
                        <?= sz_escape((string) ($customer['name'] ?? '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-select">
                    <?php foreach ($userStatusOptions as $status): ?>
                      <option value="<?= sz_escape($status) ?>"><?= sz_escape($status) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <button class="btn btn-primary" type="submit">Gebruiker aanmaken</button>
                </div>
              </form>

              <div class="table-responsive">
                <table class="table align-middle">
                  <thead>
                    <tr>
                      <th>Naam</th>
                      <th>E-mail</th>
                      <th>Rol</th>
                      <th>Klant</th>
                      <th>Status</th>
                      <th>Nieuw wachtwoord</th>
                      <th>Laatste login</th>
                      <th>Acties</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($users as $user): ?>
                      <?php $userId = (int) ($user['id'] ?? 0); ?>
                      <tr>
                        <td>
                          <input
                            class="form-control form-control-sm"
                            type="text"
                            name="full_name"
                            maxlength="120"
                            value="<?= sz_escape((string) ($user['full_name'] ?? '')) ?>"
                            form="user-form-<?= $userId ?>"
                            required
                          >
                        </td>
                        <td>
                          <input
                            class="form-control form-control-sm"
                            type="email"
                            name="email"
                            maxlength="190"
                            value="<?= sz_escape((string) ($user['email'] ?? '')) ?>"
                            form="user-form-<?= $userId ?>"
                            required
                          >
                        </td>
                        <td>
                          <select class="form-select form-select-sm" name="role_id" form="user-form-<?= $userId ?>">
                            <?php foreach ($roles as $role): ?>
                              <option
                                value="<?= sz_escape((string) ($role['id'] ?? '')) ?>"
                                <?= ((int) ($role['id'] ?? 0) === (int) ($user['role_id'] ?? 0)) ? 'selected' : '' ?>
                              >
                                <?= sz_escape((string) ($role['name'] ?? '')) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td>
                          <select class="form-select form-select-sm" name="customer_id" form="user-form-<?= $userId ?>">
                            <option value="">-</option>
                            <?php foreach ($customers as $customer): ?>
                              <option
                                value="<?= sz_escape((string) ($customer['id'] ?? '')) ?>"
                                <?= ((int) ($customer['id'] ?? 0) === (int) ($user['customer_id'] ?? 0)) ? 'selected' : '' ?>
                              >
                                <?= sz_escape((string) ($customer['name'] ?? '')) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td>
                          <select class="form-select form-select-sm" name="status" form="user-form-<?= $userId ?>">
                            <?php foreach ($userStatusOptions as $status): ?>
                              <option
                                value="<?= sz_escape($status) ?>"
                                <?= ($status === ($user['status'] ?? '')) ? 'selected' : '' ?>
                              >
                                <?= sz_escape($status) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td>
                          <input
                            class="form-control form-control-sm"
                            type="password"
                            name="password"
                            minlength="8"
                            placeholder="leeg = ongewijzigd"
                            form="user-form-<?= $userId ?>"
                          >
                        </td>
                        <td><?= sz_escape((string) ($user['last_login_at'] ?? '-')) ?></td>
                        <td class="d-flex flex-wrap gap-1">
                          <form id="user-form-<?= $userId ?>" method="post" action="cms.php#gebruikers">
                            <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_user">
                            <input type="hidden" name="redirect_to" value="gebruikers">
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                          </form>
                          <button class="btn btn-sm btn-outline-secondary" type="submit" form="user-form-<?= $userId ?>">
                            Opslaan
                          </button>
                          <form id="user-delete-<?= $userId ?>" method="post" action="cms.php#gebruikers">
                            <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="redirect_to" value="gebruikers">
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                          </form>
                          <button
                            class="btn btn-sm btn-outline-danger"
                            type="submit"
                            form="user-delete-<?= $userId ?>"
                            onclick="return confirm('Deze gebruiker verwijderen?');"
                          >
                            Verwijderen
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <section id="rollen" class="mb-5">
            <div class="panel-card p-4">
              <h2 class="section-title h4 mb-1">Rollen en rechten (RBAC)</h2>
              <p class="section-subtitle mb-3">
                Beheer permissies per rol. Gebruik per regel één permissie, bijvoorbeeld
                <code>content.manage.own</code> of <code>content.manage.all</code>.
              </p>

              <div class="row g-3">
                <?php foreach ($roles as $role): ?>
                  <?php $roleId = (int) ($role['id'] ?? 0); ?>
                  <div class="col-xl-6">
                    <form method="post" action="cms.php#rollen" class="border rounded-3 p-3 h-100">
                      <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                      <input type="hidden" name="action" value="update_role">
                      <input type="hidden" name="redirect_to" value="rollen">
                      <input type="hidden" name="role_id" value="<?= $roleId ?>">
                      <div class="mb-2">
                        <label class="form-label">Rolnaam</label>
                        <input
                          type="text"
                          name="name"
                          class="form-control"
                          maxlength="120"
                          value="<?= sz_escape((string) ($role['name'] ?? '')) ?>"
                          required
                        >
                      </div>
                      <div class="mb-2">
                        <label class="form-label">Permissies</label>
                        <textarea
                          name="permissions"
                          class="form-control"
                          rows="8"
                          placeholder="een permissie per regel"
                        ><?= sz_escape(implode("\n", is_array($role['permissions'] ?? null) ? $role['permissions'] : [])) ?></textarea>
                      </div>
                      <p class="small text-muted mb-2">Rol slug: <?= sz_escape((string) ($role['slug'] ?? '')) ?></p>
                      <button class="btn btn-outline-secondary btn-sm" type="submit">Rol opslaan</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </section>
        <?php endif; ?>

        <section id="content" class="mb-5">
          <div class="panel-card p-4">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
              <div>
                <h2 class="section-title h4 mb-1">Contentbeheer (tenant scoped)</h2>
                <p class="section-subtitle mb-0">
                  <?= $isAdmin ? 'Admin ziet en beheert content voor alle klanten.' : 'Je beheert alleen content binnen je eigen klantomgeving.' ?>
                </p>
              </div>
            </div>

            <form method="post" action="cms.php#content" class="row g-2 mb-4">
              <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
              <input type="hidden" name="action" value="create_content">
              <input type="hidden" name="redirect_to" value="content">
              <?php if ($isAdmin): ?>
                <div class="col-md-2">
                  <label class="form-label">Klant</label>
                  <select name="customer_id" class="form-select" required>
                    <option value="">Kies klant</option>
                    <?php foreach ($customers as $customer): ?>
                      <option value="<?= sz_escape((string) ($customer['id'] ?? '')) ?>">
                        <?= sz_escape((string) ($customer['name'] ?? '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
              <div class="<?= $isAdmin ? 'col-md-2' : 'col-md-3' ?>">
                <label class="form-label">Titel</label>
                <input type="text" name="title" class="form-control" maxlength="160" required>
              </div>
              <div class="<?= $isAdmin ? 'col-md-2' : 'col-md-3' ?>">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                  <?php foreach ($contentTypeOptions as $type): ?>
                    <option value="<?= sz_escape($type) ?>"><?= sz_escape($type) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="<?= $isAdmin ? 'col-md-2' : 'col-md-2' ?>">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <?php foreach ($contentStatusOptions as $status): ?>
                    <option value="<?= sz_escape($status) ?>"><?= sz_escape($status) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="<?= $isAdmin ? 'col-md-2' : 'col-md-2' ?>">
                <label class="form-label">Media URL</label>
                <input type="text" name="media_url" class="form-control" maxlength="255" placeholder="https://...">
              </div>
              <div class="<?= $isAdmin ? 'col-md-2' : 'col-md-2' ?>">
                <label class="form-label">Beschrijving</label>
                <input type="text" name="body_text" class="form-control" maxlength="2000" placeholder="placeholder tekst">
              </div>
              <div class="col-12">
                <button class="btn btn-primary" type="submit">Content toevoegen</button>
              </div>
            </form>

            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <?php if ($isAdmin): ?><th>Klant</th><?php endif; ?>
                    <th>Titel</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Beschrijving</th>
                    <th>Media URL</th>
                    <th>Acties</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($contentItems as $item): ?>
                    <?php $itemId = (int) ($item['id'] ?? 0); ?>
                    <tr>
                      <?php if ($isAdmin): ?>
                        <td><?= sz_escape((string) ($item['customer_name'] ?? '')) ?></td>
                      <?php endif; ?>
                      <td>
                        <input
                          class="form-control form-control-sm"
                          type="text"
                          name="title"
                          maxlength="160"
                          value="<?= sz_escape((string) ($item['title'] ?? '')) ?>"
                          form="content-form-<?= $itemId ?>"
                          required
                        >
                      </td>
                      <td>
                        <select class="form-select form-select-sm" name="type" form="content-form-<?= $itemId ?>">
                          <?php foreach ($contentTypeOptions as $type): ?>
                            <option
                              value="<?= sz_escape($type) ?>"
                              <?= ($type === ($item['type'] ?? '')) ? 'selected' : '' ?>
                            >
                              <?= sz_escape($type) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td>
                        <select class="form-select form-select-sm" name="status" form="content-form-<?= $itemId ?>">
                          <?php foreach ($contentStatusOptions as $status): ?>
                            <option
                              value="<?= sz_escape($status) ?>"
                              <?= ($status === ($item['status'] ?? '')) ? 'selected' : '' ?>
                            >
                              <?= sz_escape($status) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td>
                        <input
                          class="form-control form-control-sm"
                          type="text"
                          name="body_text"
                          maxlength="2000"
                          value="<?= sz_escape((string) ($item['body_text'] ?? '')) ?>"
                          form="content-form-<?= $itemId ?>"
                        >
                      </td>
                      <td>
                        <input
                          class="form-control form-control-sm"
                          type="text"
                          name="media_url"
                          maxlength="255"
                          value="<?= sz_escape((string) ($item['media_url'] ?? '')) ?>"
                          form="content-form-<?= $itemId ?>"
                        >
                      </td>
                      <td class="d-flex flex-wrap gap-1">
                        <form id="content-form-<?= $itemId ?>" method="post" action="cms.php#content">
                          <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                          <input type="hidden" name="action" value="update_content">
                          <input type="hidden" name="redirect_to" value="content">
                          <input type="hidden" name="content_id" value="<?= $itemId ?>">
                        </form>
                        <button class="btn btn-sm btn-outline-secondary" type="submit" form="content-form-<?= $itemId ?>">
                          Opslaan
                        </button>
                        <form id="content-delete-<?= $itemId ?>" method="post" action="cms.php#content">
                          <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                          <input type="hidden" name="action" value="delete_content">
                          <input type="hidden" name="redirect_to" value="content">
                          <input type="hidden" name="content_id" value="<?= $itemId ?>">
                        </form>
                        <button
                          class="btn btn-sm btn-outline-danger"
                          type="submit"
                          form="content-delete-<?= $itemId ?>"
                          onclick="return confirm('Dit content-item verwijderen?');"
                        >
                          Verwijderen
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section id="playlists" class="mb-5">
          <div class="panel-card p-4">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
              <div>
                <h2 class="section-title h4 mb-1">Playlists (tenant scoped)</h2>
                <p class="section-subtitle mb-0">
                  <?= $isAdmin ? 'Overzicht van alle playlists per klant.' : 'Beheer playlists binnen je eigen tenant.' ?>
                </p>
              </div>
            </div>

            <form method="post" action="cms.php#playlists" class="row g-2 mb-4">
              <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
              <input type="hidden" name="action" value="create_playlist">
              <input type="hidden" name="redirect_to" value="playlists">
              <?php if ($isAdmin): ?>
                <div class="col-md-2">
                  <label class="form-label">Klant</label>
                  <select name="customer_id" class="form-select" required>
                    <option value="">Kies klant</option>
                    <?php foreach ($customers as $customer): ?>
                      <option value="<?= sz_escape((string) ($customer['id'] ?? '')) ?>">
                        <?= sz_escape((string) ($customer['name'] ?? '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
              <div class="<?= $isAdmin ? 'col-md-3' : 'col-md-4' ?>">
                <label class="form-label">Titel</label>
                <input type="text" name="title" class="form-control" maxlength="160" required>
              </div>
              <div class="<?= $isAdmin ? 'col-md-4' : 'col-md-5' ?>">
                <label class="form-label">Beschrijving</label>
                <input type="text" name="description" class="form-control" maxlength="2000">
              </div>
              <div class="<?= $isAdmin ? 'col-md-3' : 'col-md-3' ?>">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <?php foreach ($playlistStatusOptions as $status): ?>
                    <option value="<?= sz_escape($status) ?>"><?= sz_escape($status) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <button class="btn btn-primary" type="submit">Playlist toevoegen</button>
              </div>
            </form>

            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <?php if ($isAdmin): ?><th>Klant</th><?php endif; ?>
                    <th>Titel</th>
                    <th>Beschrijving</th>
                    <th>Status</th>
                    <th>Acties</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($playlistItems as $playlist): ?>
                    <?php $playlistId = (int) ($playlist['id'] ?? 0); ?>
                    <tr>
                      <?php if ($isAdmin): ?>
                        <td><?= sz_escape((string) ($playlist['customer_name'] ?? '')) ?></td>
                      <?php endif; ?>
                      <td>
                        <input
                          class="form-control form-control-sm"
                          type="text"
                          name="title"
                          maxlength="160"
                          value="<?= sz_escape((string) ($playlist['title'] ?? '')) ?>"
                          form="playlist-form-<?= $playlistId ?>"
                          required
                        >
                      </td>
                      <td>
                        <input
                          class="form-control form-control-sm"
                          type="text"
                          name="description"
                          maxlength="2000"
                          value="<?= sz_escape((string) ($playlist['description'] ?? '')) ?>"
                          form="playlist-form-<?= $playlistId ?>"
                        >
                      </td>
                      <td>
                        <select class="form-select form-select-sm" name="status" form="playlist-form-<?= $playlistId ?>">
                          <?php foreach ($playlistStatusOptions as $status): ?>
                            <option
                              value="<?= sz_escape($status) ?>"
                              <?= ($status === ($playlist['status'] ?? '')) ? 'selected' : '' ?>
                            >
                              <?= sz_escape($status) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td class="d-flex flex-wrap gap-1">
                        <form id="playlist-form-<?= $playlistId ?>" method="post" action="cms.php#playlists">
                          <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                          <input type="hidden" name="action" value="update_playlist">
                          <input type="hidden" name="redirect_to" value="playlists">
                          <input type="hidden" name="playlist_id" value="<?= $playlistId ?>">
                        </form>
                        <button class="btn btn-sm btn-outline-secondary" type="submit" form="playlist-form-<?= $playlistId ?>">
                          Opslaan
                        </button>
                        <form id="playlist-delete-<?= $playlistId ?>" method="post" action="cms.php#playlists">
                          <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                          <input type="hidden" name="action" value="delete_playlist">
                          <input type="hidden" name="redirect_to" value="playlists">
                          <input type="hidden" name="playlist_id" value="<?= $playlistId ?>">
                        </form>
                        <button
                          class="btn btn-sm btn-outline-danger"
                          type="submit"
                          form="playlist-delete-<?= $playlistId ?>"
                          onclick="return confirm('Deze playlist verwijderen?');"
                        >
                          Verwijderen
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section id="instellingen" class="mb-4">
          <div class="panel-card p-4">
            <h2 class="section-title h4 mb-1">Instellingen</h2>
            <p class="section-subtitle mb-3">
              <?= $isAdmin
                ? 'Admin kan systeeminstellingen en rechten beheren. Hieronder beheer je je eigen profiel.'
                : 'Beperkte instellingen: beheer je profiel en wachtwoord (geen klantbeheer).'
              ?>
            </p>

            <form method="post" action="cms.php#instellingen" class="row g-3">
              <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
              <input type="hidden" name="action" value="update_profile">
              <input type="hidden" name="redirect_to" value="instellingen">
              <div class="col-md-4">
                <label class="form-label">Volledige naam</label>
                <input
                  type="text"
                  name="full_name"
                  maxlength="120"
                  class="form-control"
                  value="<?= sz_escape((string) ($currentUser['full_name'] ?? '')) ?>"
                  required
                >
              </div>
              <div class="col-md-4">
                <label class="form-label">Huidig wachtwoord</label>
                <input type="password" name="current_password" class="form-control" autocomplete="current-password">
              </div>
              <div class="col-md-4">
                <label class="form-label">Nieuw wachtwoord (optioneel)</label>
                <input type="password" name="new_password" class="form-control" minlength="8" autocomplete="new-password">
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">Instellingen opslaan</button>
              </div>
            </form>

            <hr>
            <p class="small text-muted mb-0">
              API-endpoint: <code>api.php</code> (RESTful routes zoals
              <code>/auth/login</code>, <code>/customers</code>, <code>/content</code>, <code>/playlists</code>).
            </p>
          </div>
        </section>
      </main>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
  </body>
</html>
