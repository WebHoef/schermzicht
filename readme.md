# schermzicht.nl (multi-tenant CMS in PHP)

Deze versie bevat een multi-tenant CMS met rolgebaseerde toegang (RBAC), veilige login en automatische database-migraties.

## Belangrijkste functionaliteiten

- **Multi-tenant gebruikersbeheer**
  - rollen: `Admin` en `Klant`
  - admin ziet en beheert alle klanten, gebruikers, content, playlists en rollen/rechten
  - klant ziet alleen content/playlists binnen de eigen tenant
- **Veilige authenticatie en sessies**
  - wachtwoorden met `password_hash()` / `password_verify()`
  - sessie-regeneratie bij login/logout
  - CSRF-validatie op muterende acties
- **Automatische migraties**
  - bij elke app-start via `sz_db()` worden migraties uitgevoerd (`app/migrations.php`)
  - geen handmatige SQL nodig tijdens ontwikkeling
  - tabellen: `users`, `customers`, `content`, `playlists`, `roles` (+ `schema_migrations`, `contact_requests`)
- **RESTful API backend**
  - `api.php` met routes voor auth, customers, users, roles, content, playlists en profile
- **Bootstrap 5 frontend**
  - login-scherm voor Admin/Klant (`login.php`)
  - CMS-dashboard met CRUD-secties (`cms.php`)

## Projectstructuur (relevant)

- `app/bootstrap.php` - env + sessie + security headers
- `app/database.php` - PDO + automatische migraties
- `app/migrations.php` - schema + dummy data seeding
- `app/auth.php` - login/sessie/RBAC helpers
- `app/cms_service.php` - CRUD en tenant-scope service-laag
- `login.php` - admin/klant inloggen
- `cms.php` - dashboard + beheer
- `api.php` - RESTful JSON API

## Installatie

1. Maak een env-bestand:

   ```bash
   cp .env.example .env
   ```

2. Vul je databasegegevens in `.env`.

3. Start lokaal:

   ```bash
   php -S 127.0.0.1:8080
   ```

4. Open:
   - Website: `http://127.0.0.1:8080/index.php`
   - Login: `http://127.0.0.1:8080/login.php`
   - CMS: `http://127.0.0.1:8080/cms.php`

## Demo accounts (dummy data)

- **Admin**
  - email: `admin@schermzicht.nl`
  - wachtwoord: `Admin123!`
- **Klant**
  - email: `manager@vianenfysio.nl`
  - wachtwoord: `Klant123!`

## API voorbeelden

- `POST /api.php/auth/login`
- `GET /api.php/auth/me`
- `POST /api.php/auth/logout`
- `GET /api.php/customers`
- `POST /api.php/content`
- `PATCH /api.php/playlists/{id}`

> Tip: je kunt ook `api.php?path=auth/login` gebruiken als je server geen PATH_INFO routing ondersteunt.

## Beveiliging

- Geen secrets in code (gebruik `.env`)
- Prepared statements via PDO
- Output escaping met `htmlspecialchars`
- CSRF token validatie
- Veilige sessiecookies (`HttpOnly`, `SameSite`, `Secure` op HTTPS)
- Security headers (`X-Frame-Options`, `X-Content-Type-Options`, etc.)
