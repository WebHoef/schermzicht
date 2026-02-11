# Setup (lokale ontwikkeling)

Gebruik voor lokale ontwikkeling de variabelen uit `.env`.

1. Kopieer voorbeeldbestand:

```bash
cp .env.example .env
```

2. Vul je eigen databasegegevens in:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

3. Start de app:

```bash
php -S 127.0.0.1:8080
```

De applicatie draait automatische migraties bij het eerste databasecontact. Handmatige SQL scripts zijn niet nodig tijdens ontwikkeling.
