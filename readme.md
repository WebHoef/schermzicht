# schermzicht.nl (PHP versie)

De statische HTML-template is omgezet naar PHP zodat een veilige databasekoppeling mogelijk is.

## Wat is aangepast

- `index.html` omgezet naar `index.php`
- `cms.html` omgezet naar `cms.php`
- veilige PDO database-laag toegevoegd (`app/database.php`)
- security helpers toegevoegd (`app/security.php`)
- bootstrap met sessie- en security-headers (`app/bootstrap.php`)
- contactformulier verwerkt met:
  - server-side validatie
  - CSRF-protectie
  - prepared statements
  - eenvoudige anti-spam honeypot en throttling
- SQL schema toegevoegd in `database/schema.sql`

## Installatie

1. Maak een omgevingbestand:

   ```bash
   cp .env.example .env
   ```

2. Vul in `.env` de databasegegevens in.

3. Maak de tabel aan:

   ```sql
   -- voer database/schema.sql uit
   ```

4. Start lokaal:

   ```bash
   php -S 127.0.0.1:8080
   ```

5. Open:
   - Website: `http://127.0.0.1:8080/index.php`
   - CMS: `http://127.0.0.1:8080/cms.php`

## Beveiligingsbasis

- **Geen secrets in code** (alleen via `.env`)
- **Prepared statements** voor database writes
- **Output escaping** met `htmlspecialchars`
- **CSRF token validatie** op formulieren
- **Veilige sessie-cookies** (`HttpOnly`, `SameSite`, `Secure` op HTTPS)
- **Security headers** (`X-Frame-Options`, `X-Content-Type-Options`, etc.)
