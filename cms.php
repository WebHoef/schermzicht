<?php

declare(strict_types=1);

require_once __DIR__ . '/app/security.php';
require_once __DIR__ . '/app/database.php';

$dbStatus = 'Niet verbonden';
$dbStatusClass = 'status-planned';
$dbStatusDetail = 'Database nog niet geconfigureerd. Vul .env in om te koppelen.';

try {
    $pdo = sz_db();
    $pdo->query('SELECT 1');
    $dbStatus = 'Verbonden';
    $dbStatusClass = 'status-active';
    $dbStatusDetail = 'Beveiligde PDO-verbinding actief.';
} catch (Throwable $exception) {
    error_log('CMS database status check failed: ' . $exception->getMessage());
}
?>
<!doctype html>
<html lang="nl">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>schermzicht CMS | Dashboard template</title>
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
    <!-- Topbar met snelle navigatie -->
    <header class="cms-topbar">
      <div class="container-fluid py-3 px-3 px-lg-4 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <span class="logo-placeholder" aria-hidden="true">SZ</span>
          <div>
            <p class="mb-0 fw-bold">schermzicht CMS</p>
            <small class="text-muted">Narrowcasting beheeromgeving</small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <a class="btn btn-outline-secondary d-none d-md-inline-flex" href="index.php">
            Terug naar website
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
      <!-- Sidebar voor desktop/tablet -->
      <aside class="sidebar-desktop d-none d-lg-flex flex-column p-3">
        <p class="text-uppercase small mb-2 text-white-50">Navigatie</p>
        <nav class="nav flex-column gap-1">
          <a class="nav-link active" href="#dashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
          <a class="nav-link" href="#contentbeheer"><i class="bi bi-images me-2"></i>Contentbeheer</a>
          <a class="nav-link" href="#playlists"><i class="bi bi-collection-play me-2"></i>Playlists</a>
          <a class="nav-link" href="#klanten"><i class="bi bi-people me-2"></i>Klanten</a>
          <a class="nav-link" href="#instellingen"><i class="bi bi-gear me-2"></i>Instellingen</a>
          <a class="nav-link" href="#uitloggen"><i class="bi bi-box-arrow-right me-2"></i>Uitloggen</a>
        </nav>
      </aside>

      <!-- Offcanvas sidebar voor compactere schermen -->
      <div
        class="offcanvas offcanvas-start text-bg-dark"
        tabindex="-1"
        id="sidebarMobile"
        aria-labelledby="sidebarMobileLabel"
      >
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
            <a class="nav-link" href="#contentbeheer" data-bs-dismiss="offcanvas">Contentbeheer</a>
            <a class="nav-link" href="#playlists" data-bs-dismiss="offcanvas">Playlists</a>
            <a class="nav-link" href="#klanten" data-bs-dismiss="offcanvas">Klanten</a>
            <a class="nav-link" href="#instellingen" data-bs-dismiss="offcanvas">Instellingen</a>
            <a class="nav-link" href="#uitloggen" data-bs-dismiss="offcanvas">Uitloggen</a>
          </nav>
        </div>
      </div>

      <!-- Hoofdinhoud met alle kernpagina-secties -->
      <main class="content-area p-3 p-md-4 p-xl-5">
        <section id="dashboard" class="mb-5">
          <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
            <div>
              <h1 class="section-title h3 mb-1">Dashboard</h1>
              <p class="section-subtitle mb-0">Overzicht van je narrowcasting omgeving.</p>
            </div>
            <button class="btn btn-primary">Nieuw scherm koppelen</button>
          </div>

          <div class="row g-3">
            <div class="col-sm-6 col-xl-3">
              <article class="overview-card p-4 h-100">
                <p class="text-uppercase small text-muted mb-2">Aantal schermen</p>
                <p class="metric mb-1">24</p>
                <p class="mb-0 text-muted">Online en gesynchroniseerd</p>
              </article>
            </div>
            <div class="col-sm-6 col-xl-3">
              <article class="overview-card p-4 h-100">
                <p class="text-uppercase small text-muted mb-2">Actieve klanten</p>
                <p class="metric mb-1">8</p>
                <p class="mb-0 text-muted">Met lopende campagnes</p>
              </article>
            </div>
            <div class="col-sm-6 col-xl-3">
              <article class="overview-card p-4 h-100">
                <p class="text-uppercase small text-muted mb-2">Geplande playlists</p>
                <p class="metric mb-1">17</p>
                <p class="mb-0 text-muted">Voor de komende 7 dagen</p>
              </article>
            </div>
            <div class="col-sm-6 col-xl-3">
              <article class="overview-card p-4 h-100">
                <p class="text-uppercase small text-muted mb-2">Database status</p>
                <p class="mb-2">
                  <span class="status-pill <?= sz_escape($dbStatusClass) ?>">
                    <?= sz_escape($dbStatus) ?>
                  </span>
                </p>
                <p class="mb-0 text-muted"><?= sz_escape($dbStatusDetail) ?></p>
              </article>
            </div>
          </div>
        </section>

        <section id="contentbeheer" class="mb-5">
          <div class="panel-card p-4">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
              <div>
                <h2 class="section-title h4 mb-1">Contentbeheer</h2>
                <p class="section-subtitle mb-0">Beheer geuploade media voor jouw schermen.</p>
              </div>
              <div class="quick-actions d-flex flex-wrap gap-2">
                <button class="btn btn-primary">Uploaden</button>
                <button class="btn btn-outline-secondary">Bewerken</button>
                <button class="btn btn-outline-danger">Verwijderen</button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th scope="col">Naam</th>
                    <th scope="col">Type</th>
                    <th scope="col">Datum</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>vianenfysio-welkomscherm.jpg</td>
                    <td>Afbeelding</td>
                    <td>09-02-2026</td>
                    <td><span class="status-pill status-active">Actief</span></td>
                  </tr>
                  <tr>
                    <td>actie-februari.mp4</td>
                    <td>Video</td>
                    <td>08-02-2026</td>
                    <td><span class="status-pill status-planned">Gepland</span></td>
                  </tr>
                  <tr>
                    <td>wachtruimte-informatie.png</td>
                    <td>Afbeelding</td>
                    <td>05-02-2026</td>
                    <td><span class="status-pill status-active">Actief</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section id="playlists" class="mb-5">
          <div class="panel-card p-4">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
              <div>
                <h2 class="section-title h4 mb-1">Playlists</h2>
                <p class="section-subtitle mb-0">
                  Maak playlists aan, bewerk volgorde en wijs ze toe aan klanten.
                </p>
              </div>
              <button class="btn btn-primary">Nieuwe playlist</button>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100">
                  <h3 class="h6 fw-bold">Actieve playlists</h3>
                  <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                      <span>Wachtruimte Ochtend</span>
                      <span class="text-muted">VianenFysio</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                      <span>Retail Promoties</span>
                      <span class="text-muted">Demo Klant A</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                      <span>Bedrijfnieuws</span>
                      <span class="text-muted">Demo Klant B</span>
                    </li>
                  </ul>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100">
                  <h3 class="h6 fw-bold">Playlist acties</h3>
                  <div class="d-grid gap-2">
                    <button class="btn btn-outline-secondary text-start">Playlist bewerken</button>
                    <button class="btn btn-outline-secondary text-start">Planning aanpassen</button>
                    <button class="btn btn-outline-secondary text-start">Toewijzen aan klant</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section id="klanten" class="mb-5">
          <div class="panel-card p-4">
            <h2 class="section-title h4 mb-1">Klanten</h2>
            <p class="section-subtitle mb-3">Lijst van klanten met basisinformatie.</p>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th scope="col">Klant</th>
                    <th scope="col">Contactpersoon</th>
                    <th scope="col">Aantal schermen</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>VianenFysio</td>
                    <td>S. van Dijk</td>
                    <td>3</td>
                    <td><span class="status-pill status-active">Actief</span></td>
                  </tr>
                  <tr>
                    <td>Demo Klant A</td>
                    <td>M. Jansen</td>
                    <td>6</td>
                    <td><span class="status-pill status-active">Actief</span></td>
                  </tr>
                  <tr>
                    <td>Demo Klant B</td>
                    <td>L. Peters</td>
                    <td>2</td>
                    <td><span class="status-pill status-planned">Onboarding</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Placeholder secties voor complete menu-ervaring -->
        <section id="instellingen" class="mb-4">
          <div class="panel-card p-4">
            <h2 class="section-title h4 mb-1">Instellingen</h2>
            <p class="section-subtitle mb-0">
              Placeholder: beheer hier gebruikersrechten, notificaties en systeemvoorkeuren.
            </p>
          </div>
        </section>

        <section id="uitloggen">
          <div class="panel-card p-4">
            <h2 class="section-title h4 mb-1">Uitloggen</h2>
            <p class="section-subtitle mb-3">Placeholder: beëindig je sessie veilig.</p>
            <button class="btn btn-outline-danger">Uitloggen</button>
          </div>
          <p class="cms-footer-note mt-3 mb-0">Templateversie met dummy data voor ontwerpdoeleinden.</p>
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
