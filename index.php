<?php

declare(strict_types=1);

require_once __DIR__ . '/app/security.php';
require_once __DIR__ . '/app/database.php';

$contactForm = [
    'name' => '',
    'organization' => '',
    'email' => '',
    'phone' => '',
    'message' => '',
];

$contactErrors = [];
$formNotice = null;
$formNoticeType = 'danger';
$throttleWindowSeconds = 15;

if (isset($_SESSION['contact_flash']) && is_array($_SESSION['contact_flash'])) {
    $flash = $_SESSION['contact_flash'];
    unset($_SESSION['contact_flash']);

    if (
        isset($flash['message'], $flash['type']) &&
        is_string($flash['message']) &&
        is_string($flash['type'])
    ) {
        $formNotice = $flash['message'];
        $formNoticeType = $flash['type'] === 'success' ? 'success' : 'danger';
    }
}

if (sz_method_is_post()) {
    $contactForm['name'] = sz_normalize_input($_POST['name'] ?? '', 120);
    $contactForm['organization'] = sz_normalize_input($_POST['organization'] ?? '', 120);
    $contactForm['email'] = sz_normalize_input($_POST['email'] ?? '', 190);
    $contactForm['phone'] = sz_normalize_input($_POST['phone'] ?? '', 30);
    $contactForm['message'] = sz_normalize_input($_POST['message'] ?? '', 2000);

    if (!sz_validate_csrf($_POST['csrf_token'] ?? null)) {
        $contactErrors['general'] = 'De sessie is verlopen. Vernieuw de pagina en probeer opnieuw.';
    }

    $honeypot = sz_normalize_input($_POST['website'] ?? '', 120);
    if ($honeypot !== '') {
        $contactErrors['general'] = 'Bericht is geweigerd.';
    }

    $lastSubmitAt = (int) ($_SESSION['last_contact_submit_at'] ?? 0);
    if ($lastSubmitAt > 0 && (time() - $lastSubmitAt) < $throttleWindowSeconds) {
        $contactErrors['general'] = 'Wacht een paar seconden voordat je nog een aanvraag verstuurt.';
    }

    if ($contactForm['name'] === '' || strlen($contactForm['name']) < 2) {
        $contactErrors['name'] = 'Vul een geldige naam in.';
    }

    if (
        $contactForm['email'] === '' ||
        filter_var($contactForm['email'], FILTER_VALIDATE_EMAIL) === false
    ) {
        $contactErrors['email'] = 'Vul een geldig e-mailadres in.';
    }

    if ($contactForm['phone'] !== '' && !sz_is_valid_phone($contactForm['phone'])) {
        $contactErrors['phone'] = 'Gebruik alleen cijfers en tekens zoals +, -, spaties en haakjes.';
    }

    if ($contactForm['message'] === '' || strlen($contactForm['message']) < 10) {
        $contactErrors['message'] = 'Je bericht moet minimaal 10 tekens bevatten.';
    }

    if (empty($contactErrors)) {
        try {
            $statement = sz_db()->prepare(
                'INSERT INTO contact_requests
                (name, organization, email, phone, message, ip_address, user_agent)
                VALUES (:name, :organization, :email, :phone, :message, :ip_address, :user_agent)'
            );

            $statement->execute([
                ':name' => $contactForm['name'],
                ':organization' => $contactForm['organization'] !== '' ? $contactForm['organization'] : null,
                ':email' => strtolower($contactForm['email']),
                ':phone' => $contactForm['phone'] !== '' ? $contactForm['phone'] : null,
                ':message' => $contactForm['message'],
                ':ip_address' => sz_client_ip(),
                ':user_agent' => sz_normalize_input($_SERVER['HTTP_USER_AGENT'] ?? '', 255),
            ]);

            $_SESSION['last_contact_submit_at'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['contact_flash'] = [
                'type' => 'success',
                'message' => 'Bedankt! Je aanvraag is veilig ontvangen. We nemen snel contact op.',
            ];

            header('Location: index.php#contact', true, 303);
            exit;
        } catch (Throwable $exception) {
            error_log('Contact request save failed: ' . $exception->getMessage());
            $formNotice = 'Je aanvraag kon op dit moment niet worden opgeslagen. Probeer het later opnieuw.';
            $formNoticeType = 'danger';
        }
    } elseif (isset($contactErrors['general'])) {
        $formNotice = $contactErrors['general'];
        $formNoticeType = 'danger';
    } else {
        $formNotice = 'Controleer de gemarkeerde velden en probeer opnieuw.';
        $formNoticeType = 'danger';
    }
}
?>
<!doctype html>
<html lang="nl">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>schermzicht.nl | Narrowcasting oplossingen</title>
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
    <link rel="stylesheet" href="style.css">
  </head>
  <body>
    <!-- Header met logo en primaire navigatie -->
    <header class="site-header bg-white sticky-top">
      <nav class="navbar navbar-expand-lg" aria-label="Hoofdnavigatie">
        <div class="container py-2">
          <a class="navbar-brand d-flex align-items-center gap-2" href="#home">
            <span class="logo-placeholder" aria-hidden="true">SZ</span>
            <span>schermzicht.nl</span>
          </a>
          <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavigation"
            aria-controls="mainNavigation"
            aria-expanded="false"
            aria-label="Navigatie openen"
          >
            <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-lg-2">
              <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
              <li class="nav-item"><a class="nav-link" href="#over-ons">Over Ons</a></li>
              <li class="nav-item"><a class="nav-link" href="#diensten">Diensten</a></li>
              <li class="nav-item"><a class="nav-link" href="cms.php">CMS</a></li>
              <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>
          </div>
        </div>
      </nav>
    </header>

    <main>
      <!-- Hero-sectie -->
      <section id="home" class="hero-section">
        <div class="container">
          <div class="row align-items-center g-4">
            <div class="col-lg-7">
              <p class="text-uppercase text-primary fw-semibold mb-2">Narrowcasting voor elke locatie</p>
              <h1 class="hero-title display-5 mb-3">
                Gerichte digitale communicatie via narrowcasting
              </h1>
              <p class="hero-subtitle lead mb-4">
                Schermzicht helpt organisaties om relevante informatie, aanbiedingen en
                merkbeleving op het juiste moment op het juiste scherm te tonen.
              </p>
              <a href="#contact" class="btn btn-sz-primary btn-lg">Neem contact op</a>
            </div>
            <div class="col-lg-5">
              <div class="hero-highlight-card p-4 bg-white h-100">
                <h2 class="h5 fw-bold mb-3">Waarom kiezen voor schermzicht?</h2>
                <ul class="list-unstyled mb-0 d-grid gap-2">
                  <li class="d-flex align-items-start gap-2">
                    <i class="bi bi-check-circle-fill text-primary mt-1" aria-hidden="true"></i>
                    <span>Snelle implementatie op locatie met betrouwbare hardware.</span>
                  </li>
                  <li class="d-flex align-items-start gap-2">
                    <i class="bi bi-check-circle-fill text-primary mt-1" aria-hidden="true"></i>
                    <span>Eigen CMS voor eenvoudig contentbeheer door jouw team.</span>
                  </li>
                  <li class="d-flex align-items-start gap-2">
                    <i class="bi bi-check-circle-fill text-primary mt-1" aria-hidden="true"></i>
                    <span>Flexibele playlists en planning afgestemd op doelgroep en locatie.</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Korte uitleg van narrowcasting -->
      <section id="over-ons" class="py-5">
        <div class="container">
          <div class="row g-4 align-items-center">
            <div class="col-lg-7">
              <h2 class="section-heading mb-3">Wat is narrowcasting?</h2>
              <p class="section-muted mb-3">
                Narrowcasting is een krachtige manier om specifieke doelgroepen te bereiken met
                dynamische, visuele communicatie op schermen in bijvoorbeeld winkels, praktijken
                en wachtruimtes.
              </p>
              <p class="narrowcasting-quote mb-0">
                “Narrowcasting is het gericht uitzenden van digitale content naar specifieke
                schermen op locaties zoals winkels en wachtruimtes.”
              </p>
            </div>
            <div class="col-lg-5">
              <div class="section-card p-4 bg-white">
                <h3 class="h6 text-uppercase fw-bold text-primary mb-2">Eerste klant</h3>
                <h4 class="h5 fw-bold mb-3">VianenFysio</h4>
                <blockquote class="mb-0">
                  <p class="mb-2">
                    “Met schermzicht kunnen we patiënten direct informeren in de wachtruimte.
                    Het systeem werkt stabiel en is erg makkelijk te beheren.”
                  </p>
                  <footer class="small text-muted">Team VianenFysio</footer>
                </blockquote>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Diensten en systeemuitleg -->
      <section id="diensten" class="py-5 bg-light">
        <div class="container">
          <div class="text-center mb-5">
            <h2 class="section-heading mb-3">Ons zelfontwikkelde narrowcasting systeem</h2>
            <p class="section-muted mb-0">
              Een complete oplossing waarin hardware, software en contentbeheer naadloos samenwerken.
            </p>
          </div>
          <div class="row g-4">
            <div class="col-md-6 col-xl-4">
              <article class="section-card p-4 bg-white h-100">
                <span class="feature-icon mb-3"><i class="bi bi-cpu-fill" aria-hidden="true"></i></span>
                <h3 class="h5 fw-bold">Hardware: Raspberry Pi 3</h3>
                <p class="mb-0 section-muted">
                  Compacte en energiezuinige players op basis van Raspberry Pi 3 voor betrouwbare
                  weergave op elk aangesloten scherm.
                </p>
              </article>
            </div>
            <div class="col-md-6 col-xl-4">
              <article class="section-card p-4 bg-white h-100">
                <span class="feature-icon mb-3"><i class="bi bi-window" aria-hidden="true"></i></span>
                <h3 class="h5 fw-bold">Eigen CMS</h3>
                <p class="mb-0 section-muted">
                  Via het CMS beheer je eenvoudig afbeeldingen, video's en playlists met
                  planning per scherm of locatie.
                </p>
              </article>
            </div>
            <div class="col-md-6 col-xl-4">
              <article class="section-card p-4 bg-white h-100">
                <span class="feature-icon mb-3"><i class="bi bi-sliders" aria-hidden="true"></i></span>
                <h3 class="h5 fw-bold">Flexibiliteit voor klanten</h3>
                <p class="mb-0 section-muted">
                  Klanten kunnen zelfstandig content bijwerken en campagnes aanpassen zonder
                  technische kennis of externe ondersteuning.
                </p>
              </article>
            </div>
          </div>
        </div>
      </section>

      <!-- Contactformulier waar CTA naartoe verwijst -->
      <section id="contact" class="contact-section py-5">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-8">
              <div class="section-card p-4 p-md-5 bg-white">
                <h2 class="section-heading mb-3">Contactformulier</h2>
                <p class="section-muted mb-4">
                  Benieuwd wat narrowcasting voor jouw organisatie kan betekenen? Laat je gegevens achter.
                </p>

                <?php if ($formNotice !== null): ?>
                  <div class="alert alert-<?= $formNoticeType === 'success' ? 'success' : 'danger' ?>" role="alert">
                    <?= sz_escape($formNotice) ?>
                  </div>
                <?php endif; ?>

                <form method="post" action="index.php#contact" novalidate>
                  <input type="hidden" name="csrf_token" value="<?= sz_escape(sz_csrf_token()) ?>">
                  <div class="form-honeypot" aria-hidden="true">
                    <label for="website">Website</label>
                    <input
                      type="text"
                      id="website"
                      name="website"
                      autocomplete="off"
                      tabindex="-1"
                    >
                  </div>

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="naam" class="form-label">Naam</label>
                      <input
                        type="text"
                        class="form-control<?= isset($contactErrors['name']) ? ' is-invalid' : '' ?>"
                        id="naam"
                        name="name"
                        maxlength="120"
                        autocomplete="name"
                        value="<?= sz_escape($contactForm['name']) ?>"
                        placeholder="Jouw naam"
                        required
                      >
                      <?php if (isset($contactErrors['name'])): ?>
                        <div class="invalid-feedback"><?= sz_escape($contactErrors['name']) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                      <label for="organisatie" class="form-label">Organisatie</label>
                      <input
                        type="text"
                        class="form-control"
                        id="organisatie"
                        name="organization"
                        maxlength="120"
                        autocomplete="organization"
                        value="<?= sz_escape($contactForm['organization']) ?>"
                        placeholder="Bedrijfsnaam"
                      >
                    </div>
                    <div class="col-md-6">
                      <label for="email" class="form-label">E-mailadres</label>
                      <input
                        type="email"
                        class="form-control<?= isset($contactErrors['email']) ? ' is-invalid' : '' ?>"
                        id="email"
                        name="email"
                        maxlength="190"
                        autocomplete="email"
                        value="<?= sz_escape($contactForm['email']) ?>"
                        placeholder="naam@bedrijf.nl"
                        required
                      >
                      <?php if (isset($contactErrors['email'])): ?>
                        <div class="invalid-feedback"><?= sz_escape($contactErrors['email']) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                      <label for="telefoon" class="form-label">Telefoon</label>
                      <input
                        type="tel"
                        class="form-control<?= isset($contactErrors['phone']) ? ' is-invalid' : '' ?>"
                        id="telefoon"
                        name="phone"
                        maxlength="30"
                        autocomplete="tel"
                        value="<?= sz_escape($contactForm['phone']) ?>"
                        placeholder="+31 6 12345678"
                      >
                      <?php if (isset($contactErrors['phone'])): ?>
                        <div class="invalid-feedback"><?= sz_escape($contactErrors['phone']) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="col-12">
                      <label for="bericht" class="form-label">Bericht</label>
                      <textarea
                        id="bericht"
                        name="message"
                        class="form-control<?= isset($contactErrors['message']) ? ' is-invalid' : '' ?>"
                        rows="4"
                        maxlength="2000"
                        placeholder="Vertel kort je vraag of wens."
                        required
                      ><?= sz_escape($contactForm['message']) ?></textarea>
                      <?php if (isset($contactErrors['message'])): ?>
                        <div class="invalid-feedback"><?= sz_escape($contactErrors['message']) ?></div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-sz-primary mt-4">Verstuur aanvraag</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="footer py-4">
      <div class="container">
        <div class="row g-3 align-items-center">
          <div class="col-lg-6">
            <p class="mb-1 fw-semibold">schermzicht.nl</p>
            <p class="mb-0 small">
              info@schermzicht.nl | +31 (0)30 123 45 67 | Utrecht, Nederland
            </p>
          </div>
          <div class="col-lg-6">
            <div class="d-flex justify-content-lg-end gap-2">
              <a class="social-icon" href="#" aria-label="LinkedIn placeholder">
                <i class="bi bi-linkedin" aria-hidden="true"></i>
              </a>
              <a class="social-icon" href="#" aria-label="Instagram placeholder">
                <i class="bi bi-instagram" aria-hidden="true"></i>
              </a>
              <a class="social-icon" href="#" aria-label="Facebook placeholder">
                <i class="bi bi-facebook" aria-hidden="true"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </footer>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
  </body>
</html>
