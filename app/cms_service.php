<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Small helper to keep input parsing consistent.
 */
function sz_input_string(mixed $value, int $maxLength): string
{
    return sz_normalize_input($value, $maxLength);
}

/**
 * @return array<int, string>
 */
function sz_customer_status_options(): array
{
    return ['active', 'onboarding', 'inactive'];
}

/**
 * @return array<int, string>
 */
function sz_user_status_options(): array
{
    return ['active', 'disabled'];
}

/**
 * @return array<int, string>
 */
function sz_content_type_options(): array
{
    return ['image', 'video', 'text'];
}

/**
 * @return array<int, string>
 */
function sz_content_status_options(): array
{
    return ['active', 'planned', 'archived'];
}

/**
 * @return array<int, string>
 */
function sz_playlist_status_options(): array
{
    return ['active', 'planned', 'archived'];
}

function sz_slugify(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? substr($normalized, 0, 140) : '';
}

function sz_parse_nullable_customer_id(mixed $value): ?int
{
    if ($value === null || $value === '' || $value === '0' || $value === 0) {
        return null;
    }

    $customerId = (int) $value;
    return $customerId > 0 ? $customerId : null;
}

function sz_validate_status(string $status, array $allowed, string $label): string
{
    if (!in_array($status, $allowed, true)) {
        throw new \InvalidArgumentException(sprintf('Ongeldige waarde voor %s.', $label));
    }

    return $status;
}

/**
 * @return array<string, mixed>
 */
function sz_dashboard_stats(\PDO $pdo, array $actor): array
{
    if (sz_is_admin_user($actor)) {
        sz_assert_permission($actor, 'customers.read');
        $row = $pdo->query(
            'SELECT
                (SELECT COUNT(*) FROM customers) AS customers_total,
                (SELECT COUNT(*) FROM users) AS users_total,
                (SELECT COUNT(*) FROM content) AS content_total,
                (SELECT COUNT(*) FROM playlists) AS playlists_total'
        )->fetch();

        return [
            'customers_total' => (int) ($row['customers_total'] ?? 0),
            'users_total' => (int) ($row['users_total'] ?? 0),
            'content_total' => (int) ($row['content_total'] ?? 0),
            'playlists_total' => (int) ($row['playlists_total'] ?? 0),
        ];
    }

    sz_assert_permission($actor, 'content.read.own');
    $customerId = (int) ($actor['customer_id'] ?? 0);
    if ($customerId <= 0) {
        throw new \RuntimeException('Deze gebruiker heeft geen gekoppelde klantomgeving.');
    }

    $statement = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM content WHERE customer_id = :customer_id) AS content_total,
            (SELECT COUNT(*) FROM playlists WHERE customer_id = :customer_id) AS playlists_total'
    );
    $statement->execute([':customer_id' => $customerId]);
    $row = $statement->fetch();

    return [
        'customers_total' => 1,
        'users_total' => 1,
        'content_total' => (int) ($row['content_total'] ?? 0),
        'playlists_total' => (int) ($row['playlists_total'] ?? 0),
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function sz_list_customers(\PDO $pdo, array $actor): array
{
    if (sz_is_admin_user($actor)) {
        sz_assert_permission($actor, 'customers.read');
        $rows = $pdo->query(
            'SELECT
                c.*,
                (SELECT COUNT(*) FROM users u WHERE u.customer_id = c.id) AS user_count,
                (SELECT COUNT(*) FROM content ct WHERE ct.customer_id = c.id) AS content_count,
                (SELECT COUNT(*) FROM playlists p WHERE p.customer_id = c.id) AS playlist_count
            FROM customers c
            ORDER BY c.name ASC'
        )->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    $customerId = (int) ($actor['customer_id'] ?? 0);
    if ($customerId <= 0) {
        return [];
    }

    $statement = $pdo->prepare(
        'SELECT
            c.*,
            (SELECT COUNT(*) FROM users u WHERE u.customer_id = c.id) AS user_count,
            (SELECT COUNT(*) FROM content ct WHERE ct.customer_id = c.id) AS content_count,
            (SELECT COUNT(*) FROM playlists p WHERE p.customer_id = c.id) AS playlist_count
        FROM customers c
        WHERE c.id = :customer_id
        ORDER BY c.name ASC'
    );
    $statement->execute([':customer_id' => $customerId]);
    $rows = $statement->fetchAll();
    return is_array($rows) ? $rows : [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function sz_list_roles(\PDO $pdo, array $actor): array
{
    sz_assert_permission($actor, 'roles.read');
    $rows = $pdo->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll();
    if (!is_array($rows)) {
        return [];
    }

    foreach ($rows as &$row) {
        $row['permissions'] = sz_decode_permissions($row['permissions_json'] ?? '[]');
    }
    unset($row);

    return $rows;
}

/**
 * @return array<int, array<string, mixed>>
 */
function sz_list_users(\PDO $pdo, array $actor): array
{
    sz_assert_permission($actor, 'users.read');
    $rows = $pdo->query(
        'SELECT
            u.id,
            u.full_name,
            u.email,
            u.status,
            u.customer_id,
            u.role_id,
            u.last_login_at,
            r.slug AS role_slug,
            r.name AS role_name,
            c.name AS customer_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        LEFT JOIN customers c ON c.id = u.customer_id
        ORDER BY u.created_at DESC'
    )->fetchAll();

    return is_array($rows) ? $rows : [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function sz_list_content(\PDO $pdo, array $actor): array
{
    if (sz_is_admin_user($actor)) {
        sz_assert_permission($actor, 'content.read.all');
        $rows = $pdo->query(
            'SELECT
                ct.id,
                ct.customer_id,
                ct.title,
                ct.type,
                ct.status,
                ct.body_text,
                ct.media_url,
                ct.created_at,
                ct.updated_at,
                c.name AS customer_name
            FROM content ct
            INNER JOIN customers c ON c.id = ct.customer_id
            ORDER BY ct.updated_at DESC'
        )->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    sz_assert_permission($actor, 'content.read.own');
    $customerId = (int) ($actor['customer_id'] ?? 0);
    if ($customerId <= 0) {
        return [];
    }

    $statement = $pdo->prepare(
        'SELECT
            ct.id,
            ct.customer_id,
            ct.title,
            ct.type,
            ct.status,
            ct.body_text,
            ct.media_url,
            ct.created_at,
            ct.updated_at,
            c.name AS customer_name
        FROM content ct
        INNER JOIN customers c ON c.id = ct.customer_id
        WHERE ct.customer_id = :customer_id
        ORDER BY ct.updated_at DESC'
    );
    $statement->execute([':customer_id' => $customerId]);

    $rows = $statement->fetchAll();
    return is_array($rows) ? $rows : [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function sz_list_playlists(\PDO $pdo, array $actor): array
{
    if (sz_is_admin_user($actor)) {
        sz_assert_permission($actor, 'playlists.read.all');
        $rows = $pdo->query(
            'SELECT
                p.id,
                p.customer_id,
                p.title,
                p.description,
                p.status,
                p.created_at,
                p.updated_at,
                c.name AS customer_name
            FROM playlists p
            INNER JOIN customers c ON c.id = p.customer_id
            ORDER BY p.updated_at DESC'
        )->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    sz_assert_permission($actor, 'playlists.read.own');
    $customerId = (int) ($actor['customer_id'] ?? 0);
    if ($customerId <= 0) {
        return [];
    }

    $statement = $pdo->prepare(
        'SELECT
            p.id,
            p.customer_id,
            p.title,
            p.description,
            p.status,
            p.created_at,
            p.updated_at,
            c.name AS customer_name
        FROM playlists p
        INNER JOIN customers c ON c.id = p.customer_id
        WHERE p.customer_id = :customer_id
        ORDER BY p.updated_at DESC'
    );
    $statement->execute([':customer_id' => $customerId]);
    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function sz_customer_exists(\PDO $pdo, int $customerId): bool
{
    $statement = $pdo->prepare('SELECT id FROM customers WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $customerId]);
    $row = $statement->fetch();
    return is_array($row);
}

function sz_unique_customer_slug(\PDO $pdo, string $preferredSlug, ?int $ignoreId = null): string
{
    $baseSlug = sz_slugify($preferredSlug);
    if ($baseSlug === '') {
        $baseSlug = 'klant-' . bin2hex(random_bytes(3));
    }

    $candidate = $baseSlug;
    $suffix = 2;
    while (true) {
        if ($ignoreId !== null) {
            $statement = $pdo->prepare(
                'SELECT id FROM customers WHERE slug = :slug AND id <> :ignore_id LIMIT 1'
            );
            $statement->execute([':slug' => $candidate, ':ignore_id' => $ignoreId]);
        } else {
            $statement = $pdo->prepare('SELECT id FROM customers WHERE slug = :slug LIMIT 1');
            $statement->execute([':slug' => $candidate]);
        }

        $row = $statement->fetch();
        if (!is_array($row)) {
            return $candidate;
        }

        $candidate = substr($baseSlug, 0, 130) . '-' . $suffix;
        $suffix++;
    }
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $input
 */
function sz_create_customer(\PDO $pdo, array $actor, array $input): int
{
    sz_assert_permission($actor, 'customers.create');

    $name = sz_input_string($input['name'] ?? '', 120);
    if ($name === '') {
        throw new \InvalidArgumentException('Klantnaam is verplicht.');
    }

    $slugInput = sz_input_string($input['slug'] ?? '', 140);
    $slug = sz_unique_customer_slug($pdo, $slugInput !== '' ? $slugInput : $name);

    $contactPerson = sz_input_string($input['contact_person'] ?? '', 120);
    $email = strtolower(sz_input_string($input['email'] ?? '', 190));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new \InvalidArgumentException('Vul een geldig klant e-mailadres in.');
    }

    $status = sz_validate_status(
        sz_input_string($input['status'] ?? 'active', 20),
        sz_customer_status_options(),
        'klantstatus'
    );

    $statement = $pdo->prepare(
        'INSERT INTO customers (name, slug, contact_person, email, status)
        VALUES (:name, :slug, :contact_person, :email, :status)'
    );
    $statement->execute([
        ':name' => $name,
        ':slug' => $slug,
        ':contact_person' => $contactPerson !== '' ? $contactPerson : null,
        ':email' => $email !== '' ? $email : null,
        ':status' => $status,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $input
 */
function sz_update_customer(\PDO $pdo, array $actor, int $customerId, array $input): void
{
    sz_assert_permission($actor, 'customers.update');
    if ($customerId <= 0) {
        throw new \InvalidArgumentException('Ongeldige klant geselecteerd.');
    }

    $statement = $pdo->prepare('SELECT id, name FROM customers WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $customerId]);
    $existing = $statement->fetch();
    if (!is_array($existing)) {
        throw new \RuntimeException('Klant bestaat niet meer.');
    }

    $name = sz_input_string($input['name'] ?? '', 120);
    if ($name === '') {
        throw new \InvalidArgumentException('Klantnaam is verplicht.');
    }

    $slugInput = sz_input_string($input['slug'] ?? '', 140);
    $slug = sz_unique_customer_slug(
        $pdo,
        $slugInput !== '' ? $slugInput : $name,
        $customerId
    );

    $contactPerson = sz_input_string($input['contact_person'] ?? '', 120);
    $email = strtolower(sz_input_string($input['email'] ?? '', 190));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new \InvalidArgumentException('Vul een geldig klant e-mailadres in.');
    }

    $status = sz_validate_status(
        sz_input_string($input['status'] ?? 'active', 20),
        sz_customer_status_options(),
        'klantstatus'
    );

    $update = $pdo->prepare(
        'UPDATE customers
        SET name = :name, slug = :slug, contact_person = :contact_person, email = :email, status = :status
        WHERE id = :id'
    );
    $update->execute([
        ':name' => $name,
        ':slug' => $slug,
        ':contact_person' => $contactPerson !== '' ? $contactPerson : null,
        ':email' => $email !== '' ? $email : null,
        ':status' => $status,
        ':id' => $customerId,
    ]);
}

/**
 * @param array<string, mixed> $actor
 */
function sz_delete_customer(\PDO $pdo, array $actor, int $customerId): void
{
    sz_assert_permission($actor, 'customers.delete');
    if ($customerId <= 0) {
        throw new \InvalidArgumentException('Ongeldige klant geselecteerd.');
    }

    $statement = $pdo->prepare('SELECT id FROM customers WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $customerId]);
    if (!is_array($statement->fetch())) {
        throw new \RuntimeException('Klant bestaat niet meer.');
    }

    // Remove tenant users first so no orphaned customer-login accounts remain.
    $deleteUsers = $pdo->prepare('DELETE FROM users WHERE customer_id = :customer_id');
    $deleteUsers->execute([':customer_id' => $customerId]);

    $deleteCustomer = $pdo->prepare('DELETE FROM customers WHERE id = :id');
    $deleteCustomer->execute([':id' => $customerId]);
}

/**
 * @return array<string, mixed>|null
 */
function sz_role_by_id(\PDO $pdo, int $roleId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $roleId]);
    $role = $statement->fetch();
    return is_array($role) ? $role : null;
}

function sz_count_active_admin_users(\PDO $pdo): int
{
    $count = $pdo->query(
        'SELECT COUNT(*)
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE r.slug = \'admin\' AND u.status = \'active\''
    )->fetchColumn();

    return (int) $count;
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $input
 */
function sz_create_user(\PDO $pdo, array $actor, array $input): int
{
    sz_assert_permission($actor, 'users.create');

    $fullName = sz_input_string($input['full_name'] ?? '', 120);
    if ($fullName === '' || strlen($fullName) < 2) {
        throw new \InvalidArgumentException('Volledige naam is verplicht.');
    }

    $email = strtolower(sz_input_string($input['email'] ?? '', 190));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new \InvalidArgumentException('Vul een geldig e-mailadres in.');
    }

    $password = (string) ($input['password'] ?? '');
    if (strlen($password) < 8) {
        throw new \InvalidArgumentException('Wachtwoord moet minimaal 8 tekens bevatten.');
    }

    $roleId = (int) ($input['role_id'] ?? 0);
    $role = sz_role_by_id($pdo, $roleId);
    if ($role === null) {
        throw new \InvalidArgumentException('Kies een geldige rol.');
    }

    $status = sz_validate_status(
        sz_input_string($input['status'] ?? 'active', 20),
        sz_user_status_options(),
        'gebruikersstatus'
    );

    $customerId = sz_parse_nullable_customer_id($input['customer_id'] ?? null);
    $roleSlug = (string) ($role['slug'] ?? '');
    if ($roleSlug === 'customer') {
        if ($customerId === null || !sz_customer_exists($pdo, $customerId)) {
            throw new \InvalidArgumentException('Voor een klantgebruiker is een bestaande klant verplicht.');
        }
    } else {
        $customerId = null;
    }

    $statement = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $statement->execute([':email' => $email]);
    if (is_array($statement->fetch())) {
        throw new \InvalidArgumentException('Dit e-mailadres is al in gebruik.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (customer_id, role_id, full_name, email, password_hash, status)
        VALUES (:customer_id, :role_id, :full_name, :email, :password_hash, :status)'
    );
    $insert->execute([
        ':customer_id' => $customerId,
        ':role_id' => $roleId,
        ':full_name' => $fullName,
        ':email' => $email,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':status' => $status,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $input
 */
function sz_update_user(\PDO $pdo, array $actor, int $userId, array $input): void
{
    sz_assert_permission($actor, 'users.update');
    if ($userId <= 0) {
        throw new \InvalidArgumentException('Ongeldige gebruiker geselecteerd.');
    }

    $existingStatement = $pdo->prepare(
        'SELECT u.*, r.slug AS role_slug
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id = :id
        LIMIT 1'
    );
    $existingStatement->execute([':id' => $userId]);
    $existing = $existingStatement->fetch();
    if (!is_array($existing)) {
        throw new \RuntimeException('Gebruiker bestaat niet meer.');
    }

    $fullName = sz_input_string($input['full_name'] ?? '', 120);
    if ($fullName === '' || strlen($fullName) < 2) {
        throw new \InvalidArgumentException('Volledige naam is verplicht.');
    }

    $email = strtolower(sz_input_string($input['email'] ?? '', 190));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new \InvalidArgumentException('Vul een geldig e-mailadres in.');
    }

    $roleId = (int) ($input['role_id'] ?? 0);
    $role = sz_role_by_id($pdo, $roleId);
    if ($role === null) {
        throw new \InvalidArgumentException('Kies een geldige rol.');
    }
    $roleSlug = (string) ($role['slug'] ?? '');

    $status = sz_validate_status(
        sz_input_string($input['status'] ?? 'active', 20),
        sz_user_status_options(),
        'gebruikersstatus'
    );

    $customerId = sz_parse_nullable_customer_id($input['customer_id'] ?? null);
    if ($roleSlug === 'customer') {
        if ($customerId === null || !sz_customer_exists($pdo, $customerId)) {
            throw new \InvalidArgumentException('Voor een klantgebruiker is een bestaande klant verplicht.');
        }
    } else {
        $customerId = null;
    }

    $duplicateStatement = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $duplicateStatement->execute([
        ':email' => $email,
        ':id' => $userId,
    ]);
    if (is_array($duplicateStatement->fetch())) {
        throw new \InvalidArgumentException('Dit e-mailadres is al in gebruik.');
    }

    $existingWasActiveAdmin = (
        (string) ($existing['role_slug'] ?? '') === 'admin' &&
        (string) ($existing['status'] ?? 'disabled') === 'active'
    );
    $newWillBeActiveAdmin = ($roleSlug === 'admin' && $status === 'active');

    if ($existingWasActiveAdmin && !$newWillBeActiveAdmin && sz_count_active_admin_users($pdo) <= 1) {
        throw new \RuntimeException('Minimaal een actieve admin-gebruiker is verplicht.');
    }

    if ((int) ($actor['id'] ?? 0) === $userId && $status !== 'active') {
        throw new \RuntimeException('Je kunt je eigen account niet uitschakelen.');
    }

    $newPassword = (string) ($input['password'] ?? '');
    $passwordSql = '';
    $params = [
        ':id' => $userId,
        ':customer_id' => $customerId,
        ':role_id' => $roleId,
        ':full_name' => $fullName,
        ':email' => $email,
        ':status' => $status,
    ];
    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            throw new \InvalidArgumentException('Nieuw wachtwoord moet minimaal 8 tekens bevatten.');
        }

        $passwordSql = ', password_hash = :password_hash';
        $params[':password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    $update = $pdo->prepare(
        'UPDATE users
        SET customer_id = :customer_id, role_id = :role_id, full_name = :full_name, email = :email, status = :status' .
        $passwordSql .
        ' WHERE id = :id'
    );
    $update->execute($params);
}

/**
 * @param array<string, mixed> $actor
 */
function sz_delete_user(\PDO $pdo, array $actor, int $userId): void
{
    sz_assert_permission($actor, 'users.delete');
    if ($userId <= 0) {
        throw new \InvalidArgumentException('Ongeldige gebruiker geselecteerd.');
    }

    if ((int) ($actor['id'] ?? 0) === $userId) {
        throw new \RuntimeException('Je kunt je eigen account niet verwijderen.');
    }

    $statement = $pdo->prepare(
        'SELECT u.id, u.status, r.slug AS role_slug
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id = :id
        LIMIT 1'
    );
    $statement->execute([':id' => $userId]);
    $target = $statement->fetch();
    if (!is_array($target)) {
        throw new \RuntimeException('Gebruiker bestaat niet meer.');
    }

    if (
        (string) ($target['role_slug'] ?? '') === 'admin' &&
        (string) ($target['status'] ?? 'disabled') === 'active' &&
        sz_count_active_admin_users($pdo) <= 1
    ) {
        throw new \RuntimeException('Minimaal een actieve admin-gebruiker is verplicht.');
    }

    $delete = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $delete->execute([':id' => $userId]);
}

/**
 * @return array<int, string>
 */
function sz_parse_permissions_input(mixed $input): array
{
    if (is_array($input)) {
        $raw = [];
        foreach ($input as $value) {
            if (is_string($value)) {
                $raw[] = $value;
            }
        }
    } else {
        $raw = preg_split('/[\n,]+/', (string) $input) ?: [];
    }

    $permissions = [];
    foreach ($raw as $value) {
        $permission = strtolower(trim((string) $value));
        if ($permission === '') {
            continue;
        }

        if (preg_match('/^[a-z0-9.*_-]+$/', $permission) !== 1) {
            throw new \InvalidArgumentException('Een of meer permissies bevatten ongeldige tekens.');
        }

        $permissions[] = $permission;
    }

    return array_values(array_unique($permissions));
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $input
 */
function sz_update_role(\PDO $pdo, array $actor, int $roleId, array $input): void
{
    sz_assert_permission($actor, 'roles.update');
    if ($roleId <= 0) {
        throw new \InvalidArgumentException('Ongeldige rol geselecteerd.');
    }

    $role = sz_role_by_id($pdo, $roleId);
    if ($role === null) {
        throw new \RuntimeException('Rol bestaat niet meer.');
    }

    $name = sz_input_string($input['name'] ?? '', 120);
    if ($name === '') {
        throw new \InvalidArgumentException('Rolnaam is verplicht.');
    }

    $permissions = sz_parse_permissions_input($input['permissions'] ?? '');
    $update = $pdo->prepare(
        'UPDATE roles
        SET name = :name, permissions_json = :permissions_json
        WHERE id = :id'
    );
    $update->execute([
        ':name' => $name,
        ':permissions_json' => json_encode($permissions, JSON_UNESCAPED_SLASHES),
        ':id' => $roleId,
    ]);
}

/**
 * @return array<string, mixed>|null
 */
function sz_load_content_row(\PDO $pdo, int $contentId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM content WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $contentId]);
    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

/**
 * @param array<string, mixed> $actor
 */
function sz_assert_content_scope(array $actor, int $customerId): void
{
    if (sz_is_admin_user($actor)) {
        sz_assert_permission($actor, 'content.manage.all');
        return;
    }

    sz_assert_permission($actor, 'content.manage.own');
    if (!sz_can_access_customer($actor, $customerId)) {
        throw new \RuntimeException('Je mag alleen content binnen je eigen klantomgeving beheren.');
    }
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $input
 */
function sz_create_content(\PDO $pdo, array $actor, array $input): int
{
    $customerId = sz_parse_nullable_customer_id($input['customer_id'] ?? null);
    if (sz_is_admin_user($actor)) {
        if ($customerId === null || !sz_customer_exists($pdo, $customerId)) {
            throw new \InvalidArgumentException('Selecteer een geldige klant voor dit content-item.');
        }
    } else {
        $customerId = (int) ($actor['customer_id'] ?? 0);
    }

    if ($customerId === null || $customerId <= 0) {
        throw new \RuntimeException('Geen geldige klantscope beschikbaar.');
    }

    sz_assert_content_scope($actor, $customerId);

    $title = sz_input_string($input['title'] ?? '', 160);
    if ($title === '') {
        throw new \InvalidArgumentException('Titel is verplicht.');
    }

    $type = sz_validate_status(
        sz_input_string($input['type'] ?? 'image', 20),
        sz_content_type_options(),
        'contenttype'
    );
    $status = sz_validate_status(
        sz_input_string($input['status'] ?? 'planned', 20),
        sz_content_status_options(),
        'contentstatus'
    );

    $bodyText = sz_input_string($input['body_text'] ?? '', 2000);
    $mediaUrl = sz_input_string($input['media_url'] ?? '', 255);

    $insert = $pdo->prepare(
        'INSERT INTO content
        (customer_id, created_by_user_id, title, type, status, body_text, media_url)
        VALUES (:customer_id, :created_by_user_id, :title, :type, :status, :body_text, :media_url)'
    );
    $insert->execute([
        ':customer_id' => $customerId,
        ':created_by_user_id' => (int) ($actor['id'] ?? 0),
        ':title' => $title,
        ':type' => $type,
        ':status' => $status,
        ':body_text' => $bodyText !== '' ? $bodyText : null,
        ':media_url' => $mediaUrl !== '' ? $mediaUrl : null,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $input
 */
function sz_update_content(\PDO $pdo, array $actor, int $contentId, array $input): void
{
    if ($contentId <= 0) {
        throw new \InvalidArgumentException('Ongeldig content-item geselecteerd.');
    }

    $existing = sz_load_content_row($pdo, $contentId);
    if ($existing === null) {
        throw new \RuntimeException('Content-item bestaat niet meer.');
    }

    $customerId = (int) ($existing['customer_id'] ?? 0);
    sz_assert_content_scope($actor, $customerId);

    $title = sz_input_string($input['title'] ?? '', 160);
    if ($title === '') {
        throw new \InvalidArgumentException('Titel is verplicht.');
    }
    $type = sz_validate_status(
        sz_input_string($input['type'] ?? 'image', 20),
        sz_content_type_options(),
        'contenttype'
    );
    $status = sz_validate_status(
        sz_input_string($input['status'] ?? 'planned', 20),
        sz_content_status_options(),
        'contentstatus'
    );
    $bodyText = sz_input_string($input['body_text'] ?? '', 2000);
    $mediaUrl = sz_input_string($input['media_url'] ?? '', 255);

    $update = $pdo->prepare(
        'UPDATE content
        SET title = :title, type = :type, status = :status, body_text = :body_text, media_url = :media_url
        WHERE id = :id'
    );
    $update->execute([
        ':title' => $title,
        ':type' => $type,
        ':status' => $status,
        ':body_text' => $bodyText !== '' ? $bodyText : null,
        ':media_url' => $mediaUrl !== '' ? $mediaUrl : null,
        ':id' => $contentId,
    ]);
}

/**
 * @param array<string, mixed> $actor
 */
function sz_delete_content(\PDO $pdo, array $actor, int $contentId): void
{
    if ($contentId <= 0) {
        throw new \InvalidArgumentException('Ongeldig content-item geselecteerd.');
    }

    $existing = sz_load_content_row($pdo, $contentId);
    if ($existing === null) {
        throw new \RuntimeException('Content-item bestaat niet meer.');
    }

    $customerId = (int) ($existing['customer_id'] ?? 0);
    sz_assert_content_scope($actor, $customerId);

    $delete = $pdo->prepare('DELETE FROM content WHERE id = :id');
    $delete->execute([':id' => $contentId]);
}

/**
 * @return array<string, mixed>|null
 */
function sz_load_playlist_row(\PDO $pdo, int $playlistId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM playlists WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $playlistId]);
    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

/**
 * @param array<string, mixed> $actor
 */
function sz_assert_playlist_scope(array $actor, int $customerId): void
{
    if (sz_is_admin_user($actor)) {
        sz_assert_permission($actor, 'playlists.manage.all');
        return;
    }

    sz_assert_permission($actor, 'playlists.manage.own');
    if (!sz_can_access_customer($actor, $customerId)) {
        throw new \RuntimeException('Je mag alleen playlists binnen je eigen klantomgeving beheren.');
    }
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $input
 */
function sz_create_playlist(\PDO $pdo, array $actor, array $input): int
{
    $customerId = sz_parse_nullable_customer_id($input['customer_id'] ?? null);
    if (sz_is_admin_user($actor)) {
        if ($customerId === null || !sz_customer_exists($pdo, $customerId)) {
            throw new \InvalidArgumentException('Selecteer een geldige klant voor deze playlist.');
        }
    } else {
        $customerId = (int) ($actor['customer_id'] ?? 0);
    }

    if ($customerId === null || $customerId <= 0) {
        throw new \RuntimeException('Geen geldige klantscope beschikbaar.');
    }

    sz_assert_playlist_scope($actor, $customerId);

    $title = sz_input_string($input['title'] ?? '', 160);
    if ($title === '') {
        throw new \InvalidArgumentException('Titel is verplicht.');
    }

    $description = sz_input_string($input['description'] ?? '', 2000);
    $status = sz_validate_status(
        sz_input_string($input['status'] ?? 'planned', 20),
        sz_playlist_status_options(),
        'playliststatus'
    );

    $insert = $pdo->prepare(
        'INSERT INTO playlists (customer_id, created_by_user_id, title, description, status)
        VALUES (:customer_id, :created_by_user_id, :title, :description, :status)'
    );
    $insert->execute([
        ':customer_id' => $customerId,
        ':created_by_user_id' => (int) ($actor['id'] ?? 0),
        ':title' => $title,
        ':description' => $description !== '' ? $description : null,
        ':status' => $status,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $input
 */
function sz_update_playlist(\PDO $pdo, array $actor, int $playlistId, array $input): void
{
    if ($playlistId <= 0) {
        throw new \InvalidArgumentException('Ongeldige playlist geselecteerd.');
    }

    $existing = sz_load_playlist_row($pdo, $playlistId);
    if ($existing === null) {
        throw new \RuntimeException('Playlist bestaat niet meer.');
    }

    $customerId = (int) ($existing['customer_id'] ?? 0);
    sz_assert_playlist_scope($actor, $customerId);

    $title = sz_input_string($input['title'] ?? '', 160);
    if ($title === '') {
        throw new \InvalidArgumentException('Titel is verplicht.');
    }

    $description = sz_input_string($input['description'] ?? '', 2000);
    $status = sz_validate_status(
        sz_input_string($input['status'] ?? 'planned', 20),
        sz_playlist_status_options(),
        'playliststatus'
    );

    $update = $pdo->prepare(
        'UPDATE playlists
        SET title = :title, description = :description, status = :status
        WHERE id = :id'
    );
    $update->execute([
        ':title' => $title,
        ':description' => $description !== '' ? $description : null,
        ':status' => $status,
        ':id' => $playlistId,
    ]);
}

/**
 * @param array<string, mixed> $actor
 */
function sz_delete_playlist(\PDO $pdo, array $actor, int $playlistId): void
{
    if ($playlistId <= 0) {
        throw new \InvalidArgumentException('Ongeldige playlist geselecteerd.');
    }

    $existing = sz_load_playlist_row($pdo, $playlistId);
    if ($existing === null) {
        throw new \RuntimeException('Playlist bestaat niet meer.');
    }

    $customerId = (int) ($existing['customer_id'] ?? 0);
    sz_assert_playlist_scope($actor, $customerId);

    $delete = $pdo->prepare('DELETE FROM playlists WHERE id = :id');
    $delete->execute([':id' => $playlistId]);
}

/**
 * @param array<string, mixed> $actor
 * @param array<string, mixed> $input
 */
function sz_update_profile(\PDO $pdo, array $actor, array $input): void
{
    if (!sz_has_permission_for_user($actor, 'settings.update.own') && !sz_is_admin_user($actor)) {
        throw new \RuntimeException('Je hebt geen toestemming om je instellingen te wijzigen.');
    }

    $userId = (int) ($actor['id'] ?? 0);
    if ($userId <= 0) {
        throw new \RuntimeException('Ongeldige gebruikerssessie.');
    }

    $fullName = sz_input_string($input['full_name'] ?? '', 120);
    if ($fullName === '' || strlen($fullName) < 2) {
        throw new \InvalidArgumentException('Volledige naam is verplicht.');
    }

    $newPassword = (string) ($input['new_password'] ?? '');
    $currentPassword = (string) ($input['current_password'] ?? '');
    $passwordSql = '';
    $params = [
        ':id' => $userId,
        ':full_name' => $fullName,
    ];

    if ($newPassword !== '') {
        if (strlen($newPassword) < 8) {
            throw new \InvalidArgumentException('Nieuw wachtwoord moet minimaal 8 tekens bevatten.');
        }

        $statement = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $userId]);
        $row = $statement->fetch();
        if (!is_array($row) || !password_verify($currentPassword, (string) ($row['password_hash'] ?? ''))) {
            throw new \RuntimeException('Huidig wachtwoord is onjuist.');
        }

        $passwordSql = ', password_hash = :password_hash';
        $params[':password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    $update = $pdo->prepare(
        'UPDATE users
        SET full_name = :full_name' . $passwordSql . '
        WHERE id = :id'
    );
    $update->execute($params);
}
