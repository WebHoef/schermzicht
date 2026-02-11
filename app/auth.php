<?php

declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/database.php';

/**
 * Decodes permissions from the DB role payload.
 *
 * @return array<int, string>
 */
function sz_decode_permissions(mixed $permissionsJson): array
{
    if (!is_string($permissionsJson) || trim($permissionsJson) === '') {
        return [];
    }

    try {
        $decoded = json_decode($permissionsJson, true, 512, JSON_THROW_ON_ERROR);
    } catch (\Throwable) {
        return [];
    }

    if (!is_array($decoded)) {
        return [];
    }

    $permissions = [];
    foreach ($decoded as $permission) {
        if (!is_string($permission)) {
            continue;
        }

        $trimmed = trim($permission);
        if ($trimmed !== '') {
            $permissions[] = $trimmed;
        }
    }

    return array_values(array_unique($permissions));
}

/**
 * Returns the active user with role and tenant context.
 *
 * @return array<string, mixed>|null
 */
function sz_current_user(bool $forceRefresh = false): ?array
{
    static $cachedUser = null;
    static $cacheInitialized = false;

    if ($cacheInitialized && !$forceRefresh) {
        return $cachedUser;
    }

    $cacheInitialized = true;
    $cachedUser = null;

    $userId = (int) ($_SESSION['auth_user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $statement = sz_db()->prepare(
        'SELECT
            u.id,
            u.customer_id,
            u.role_id,
            u.full_name,
            u.email,
            u.status AS user_status,
            u.last_login_at,
            r.slug AS role_slug,
            r.name AS role_name,
            r.permissions_json,
            c.name AS customer_name,
            c.status AS customer_status
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        LEFT JOIN customers c ON c.id = u.customer_id
        WHERE u.id = :user_id
        LIMIT 1'
    );
    $statement->execute([':user_id' => $userId]);
    $user = $statement->fetch();
    if (!is_array($user)) {
        unset($_SESSION['auth_user_id'], $_SESSION['auth_role_slug'], $_SESSION['auth_customer_id']);
        return null;
    }

    if (($user['user_status'] ?? 'disabled') !== 'active') {
        unset($_SESSION['auth_user_id'], $_SESSION['auth_role_slug'], $_SESSION['auth_customer_id']);
        return null;
    }

    $roleSlug = (string) ($user['role_slug'] ?? '');
    $customerStatus = (string) ($user['customer_status'] ?? '');
    if ($roleSlug === 'customer' && $customerStatus !== '' && $customerStatus === 'inactive') {
        unset($_SESSION['auth_user_id'], $_SESSION['auth_role_slug'], $_SESSION['auth_customer_id']);
        return null;
    }

    $user['id'] = (int) $user['id'];
    $user['role_id'] = (int) $user['role_id'];
    $user['customer_id'] = $user['customer_id'] !== null ? (int) $user['customer_id'] : null;
    $user['permissions'] = sz_decode_permissions($user['permissions_json'] ?? '[]');

    // Keep session role/customer in sync for quick checks.
    $_SESSION['auth_role_slug'] = $roleSlug;
    $_SESSION['auth_customer_id'] = $user['customer_id'];

    $cachedUser = $user;
    return $cachedUser;
}

/**
 * Matches a permission string including wildcard support.
 */
function sz_permission_matches(array $permissionSet, string $permission): bool
{
    if (in_array('*', $permissionSet, true) || in_array($permission, $permissionSet, true)) {
        return true;
    }

    $segments = explode('.', $permission);
    while (count($segments) > 1) {
        array_pop($segments);
        $wildcard = implode('.', $segments) . '.*';
        if (in_array($wildcard, $permissionSet, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Checks if a specific user has a permission.
 *
 * @param array<string, mixed> $user
 */
function sz_has_permission_for_user(array $user, string $permission): bool
{
    $permissions = $user['permissions'] ?? [];
    if (!is_array($permissions)) {
        return false;
    }

    $cleanPermissions = [];
    foreach ($permissions as $permissionValue) {
        if (is_string($permissionValue)) {
            $cleanPermissions[] = $permissionValue;
        }
    }

    return sz_permission_matches($cleanPermissions, $permission);
}

function sz_has_permission(string $permission): bool
{
    $user = sz_current_user();
    if (!is_array($user)) {
        return false;
    }

    return sz_has_permission_for_user($user, $permission);
}

function sz_is_admin_user(?array $user = null): bool
{
    $currentUser = $user;
    if ($currentUser === null) {
        $currentUser = sz_current_user();
    }

    if (!is_array($currentUser)) {
        return false;
    }

    return (($currentUser['role_slug'] ?? '') === 'admin');
}

/**
 * Attempts login with optional role hint ("admin" or "customer").
 */
function sz_login(string $email, string $password, ?string $roleHint = null): bool
{
    $normalizedEmail = strtolower(sz_normalize_input($email, 190));
    if (
        $normalizedEmail === '' ||
        filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false ||
        $password === ''
    ) {
        return false;
    }

    $statement = sz_db()->prepare(
        'SELECT
            u.id,
            u.customer_id,
            u.full_name,
            u.email,
            u.password_hash,
            u.status AS user_status,
            r.slug AS role_slug,
            c.status AS customer_status
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        LEFT JOIN customers c ON c.id = u.customer_id
        WHERE u.email = :email
        LIMIT 1'
    );
    $statement->execute([':email' => $normalizedEmail]);
    $row = $statement->fetch();
    if (!is_array($row)) {
        return false;
    }

    $roleSlug = (string) ($row['role_slug'] ?? '');
    if ($roleHint !== null && $roleHint !== '' && $roleSlug !== $roleHint) {
        return false;
    }

    if (($row['user_status'] ?? 'disabled') !== 'active') {
        return false;
    }

    $customerStatus = (string) ($row['customer_status'] ?? '');
    if ($roleSlug === 'customer' && $customerStatus === 'inactive') {
        return false;
    }

    $passwordHash = (string) ($row['password_hash'] ?? '');
    if ($passwordHash === '' || !password_verify($password, $passwordHash)) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['auth_user_id'] = (int) $row['id'];
    $_SESSION['auth_role_slug'] = $roleSlug;
    $_SESSION['auth_customer_id'] = $row['customer_id'] !== null ? (int) $row['customer_id'] : null;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $updateStatement = sz_db()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
    $updateStatement->execute([':id' => (int) $row['id']]);

    // Refresh static cache for the current request.
    sz_current_user(true);

    return true;
}

function sz_logout(): void
{
    $_SESSION = [];

    session_regenerate_id(true);
}

/**
 * Redirects visitors to login page if no session exists.
 *
 * @return array<string, mixed>
 */
function sz_require_user(): array
{
    $user = sz_current_user();
    if (!is_array($user)) {
        header('Location: login.php', true, 302);
        exit;
    }

    return $user;
}

/**
 * Throws when a permission is missing.
 *
 * @param array<string, mixed> $user
 */
function sz_assert_permission(array $user, string $permission): void
{
    if (!sz_has_permission_for_user($user, $permission)) {
        throw new \RuntimeException('Je hebt geen toegang voor deze actie.');
    }
}

/**
 * Checks if actor may access a specific customer scope.
 *
 * @param array<string, mixed> $user
 */
function sz_can_access_customer(array $user, int $customerId): bool
{
    if ($customerId <= 0) {
        return false;
    }

    if (sz_is_admin_user($user)) {
        return true;
    }

    return ((int) ($user['customer_id'] ?? 0) === $customerId);
}
