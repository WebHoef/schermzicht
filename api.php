<?php

declare(strict_types=1);

require_once __DIR__ . '/app/cms_service.php';

header('Content-Type: application/json; charset=utf-8');

function sz_api_respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sz_api_error_from_exception(\Throwable $exception): never
{
    $message = $exception->getMessage();
    $statusCode = 400;

    if ($exception instanceof \InvalidArgumentException) {
        $statusCode = 422;
    } elseif (str_contains(strtolower($message), 'inlog')) {
        $statusCode = 401;
    } elseif (
        str_contains(strtolower($message), 'geen toegang') ||
        str_contains(strtolower($message), 'toestemming')
    ) {
        $statusCode = 403;
    } elseif (str_contains(strtolower($message), 'bestaat niet')) {
        $statusCode = 404;
    }

    sz_api_respond($statusCode, [
        'ok' => false,
        'error' => $message !== '' ? $message : 'Onbekende fout opgetreden.',
    ]);
}

function sz_api_route_path(): string
{
    $queryPath = $_GET['path'] ?? null;
    if (is_string($queryPath) && $queryPath !== '') {
        return '/' . trim($queryPath, '/');
    }

    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    if (is_string($pathInfo) && $pathInfo !== '') {
        return '/' . trim($pathInfo, '/');
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!is_string($requestUri) || !is_string($scriptName)) {
        return '/';
    }

    $uriPath = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($uriPath)) {
        return '/';
    }

    if (str_starts_with($uriPath, $scriptName)) {
        $uriPath = substr($uriPath, strlen($scriptName));
    }

    return '/' . trim($uriPath, '/');
}

/**
 * @return array<string, mixed>
 */
function sz_api_input(): array
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        return $_GET;
    }

    $input = [];
    if (is_array($_POST) && !empty($_POST)) {
        $input = $_POST;
    }

    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || trim($rawBody) === '') {
        return $input;
    }

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $input = array_merge($input, $decoded);
            }
        } catch (\Throwable) {
            // Ignore parse errors to keep endpoint tolerant for form clients.
        }

        return $input;
    }

    $formData = [];
    parse_str($rawBody, $formData);
    if (is_array($formData)) {
        $input = array_merge($input, $formData);
    }

    return $input;
}

/**
 * @param array<string, mixed> $input
 */
function sz_api_require_csrf_for_write(array $input): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? null);
    if (!is_string($token) || !sz_validate_csrf($token)) {
        throw new \RuntimeException('De CSRF-token is ongeldig of verlopen.');
    }
}

/**
 * @param array<string, mixed> $user
 * @return array<string, mixed>
 */
function sz_api_public_user_payload(array $user): array
{
    return [
        'id' => (int) ($user['id'] ?? 0),
        'full_name' => (string) ($user['full_name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => (string) ($user['role_slug'] ?? ''),
        'role_name' => (string) ($user['role_name'] ?? ''),
        'customer_id' => $user['customer_id'] !== null ? (int) $user['customer_id'] : null,
        'customer_name' => (string) ($user['customer_name'] ?? ''),
        'permissions' => is_array($user['permissions'] ?? null) ? $user['permissions'] : [],
    ];
}

try {
    $pdo = sz_db();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $routePath = sz_api_route_path();
    $routeSegments = array_values(array_filter(explode('/', trim($routePath, '/'))));
    $input = sz_api_input();

    if (isset($input['_method']) && is_string($input['_method'])) {
        $override = strtoupper($input['_method']);
        if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            $method = $override;
        }
    }

    $resource = $routeSegments[0] ?? '';
    $resourceId = isset($routeSegments[1]) ? (int) $routeSegments[1] : 0;
    $subRoute = $routeSegments[1] ?? '';

    if ($resource === 'auth' && $subRoute === 'login') {
        if ($method !== 'POST') {
            sz_api_respond(405, ['ok' => false, 'error' => 'Gebruik POST voor /auth/login.']);
        }

        $email = sz_normalize_input($input['email'] ?? '', 190);
        $password = (string) ($input['password'] ?? '');
        $roleHint = isset($input['role_hint']) && is_string($input['role_hint']) ? $input['role_hint'] : null;
        if ($roleHint !== null && !in_array($roleHint, ['admin', 'customer'], true)) {
            throw new \InvalidArgumentException('Ongeldige role_hint. Gebruik "admin" of "customer".');
        }

        if (!sz_login($email, $password, $roleHint)) {
            sz_api_respond(401, ['ok' => false, 'error' => 'Onjuiste inloggegevens of geen toegang.']);
        }

        $user = sz_current_user(true);
        if (!is_array($user)) {
            throw new \RuntimeException('Inloggen gelukt, maar sessie kon niet worden opgebouwd.');
        }

        sz_api_respond(200, [
            'ok' => true,
            'data' => sz_api_public_user_payload($user),
            'csrf_token' => sz_csrf_token(),
        ]);
    }

    $actor = sz_current_user();
    if (!is_array($actor)) {
        sz_api_respond(401, ['ok' => false, 'error' => 'Inloggen is vereist voor deze API-endpoint.']);
    }

    $isWriteMethod = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    if ($isWriteMethod) {
        sz_api_require_csrf_for_write($input);
    }

    if ($resource === 'auth' && $subRoute === 'me') {
        if ($method !== 'GET') {
            sz_api_respond(405, ['ok' => false, 'error' => 'Gebruik GET voor /auth/me.']);
        }

        sz_api_respond(200, [
            'ok' => true,
            'data' => sz_api_public_user_payload($actor),
            'csrf_token' => sz_csrf_token(),
        ]);
    }

    if ($resource === 'auth' && $subRoute === 'logout') {
        if ($method !== 'POST') {
            sz_api_respond(405, ['ok' => false, 'error' => 'Gebruik POST voor /auth/logout.']);
        }

        sz_logout();
        sz_api_respond(200, ['ok' => true, 'message' => 'Je bent uitgelogd.']);
    }

    if ($resource === 'customers') {
        if ($method === 'GET') {
            $items = sz_list_customers($pdo, $actor);
            if ($resourceId > 0) {
                foreach ($items as $item) {
                    if ((int) ($item['id'] ?? 0) === $resourceId) {
                        sz_api_respond(200, ['ok' => true, 'data' => $item]);
                    }
                }
                sz_api_respond(404, ['ok' => false, 'error' => 'Klant niet gevonden.']);
            }

            sz_api_respond(200, ['ok' => true, 'data' => $items]);
        }

        if ($method === 'POST') {
            $id = sz_create_customer($pdo, $actor, $input);
            sz_api_respond(201, ['ok' => true, 'id' => $id]);
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            if ($resourceId <= 0) {
                throw new \InvalidArgumentException('Voor update is een klant-id vereist.');
            }
            sz_update_customer($pdo, $actor, $resourceId, $input);
            sz_api_respond(200, ['ok' => true]);
        }

        if ($method === 'DELETE') {
            if ($resourceId <= 0) {
                throw new \InvalidArgumentException('Voor verwijderen is een klant-id vereist.');
            }
            sz_delete_customer($pdo, $actor, $resourceId);
            sz_api_respond(200, ['ok' => true]);
        }

        sz_api_respond(405, ['ok' => false, 'error' => 'Methode niet toegestaan voor /customers.']);
    }

    if ($resource === 'users') {
        if ($method === 'GET') {
            $items = sz_list_users($pdo, $actor);
            if ($resourceId > 0) {
                foreach ($items as $item) {
                    if ((int) ($item['id'] ?? 0) === $resourceId) {
                        sz_api_respond(200, ['ok' => true, 'data' => $item]);
                    }
                }
                sz_api_respond(404, ['ok' => false, 'error' => 'Gebruiker niet gevonden.']);
            }

            sz_api_respond(200, ['ok' => true, 'data' => $items]);
        }

        if ($method === 'POST') {
            $id = sz_create_user($pdo, $actor, $input);
            sz_api_respond(201, ['ok' => true, 'id' => $id]);
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            if ($resourceId <= 0) {
                throw new \InvalidArgumentException('Voor update is een gebruiker-id vereist.');
            }
            sz_update_user($pdo, $actor, $resourceId, $input);
            sz_api_respond(200, ['ok' => true]);
        }

        if ($method === 'DELETE') {
            if ($resourceId <= 0) {
                throw new \InvalidArgumentException('Voor verwijderen is een gebruiker-id vereist.');
            }
            sz_delete_user($pdo, $actor, $resourceId);
            sz_api_respond(200, ['ok' => true]);
        }

        sz_api_respond(405, ['ok' => false, 'error' => 'Methode niet toegestaan voor /users.']);
    }

    if ($resource === 'roles') {
        if ($method === 'GET') {
            $items = sz_list_roles($pdo, $actor);
            if ($resourceId > 0) {
                foreach ($items as $item) {
                    if ((int) ($item['id'] ?? 0) === $resourceId) {
                        sz_api_respond(200, ['ok' => true, 'data' => $item]);
                    }
                }
                sz_api_respond(404, ['ok' => false, 'error' => 'Rol niet gevonden.']);
            }

            sz_api_respond(200, ['ok' => true, 'data' => $items]);
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            if ($resourceId <= 0) {
                throw new \InvalidArgumentException('Voor update is een rol-id vereist.');
            }
            sz_update_role($pdo, $actor, $resourceId, $input);
            sz_api_respond(200, ['ok' => true]);
        }

        sz_api_respond(405, ['ok' => false, 'error' => 'Methode niet toegestaan voor /roles.']);
    }

    if ($resource === 'content') {
        if ($method === 'GET') {
            $items = sz_list_content($pdo, $actor);
            if ($resourceId > 0) {
                foreach ($items as $item) {
                    if ((int) ($item['id'] ?? 0) === $resourceId) {
                        sz_api_respond(200, ['ok' => true, 'data' => $item]);
                    }
                }
                sz_api_respond(404, ['ok' => false, 'error' => 'Content-item niet gevonden.']);
            }

            sz_api_respond(200, ['ok' => true, 'data' => $items]);
        }

        if ($method === 'POST') {
            $id = sz_create_content($pdo, $actor, $input);
            sz_api_respond(201, ['ok' => true, 'id' => $id]);
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            if ($resourceId <= 0) {
                throw new \InvalidArgumentException('Voor update is een content-id vereist.');
            }
            sz_update_content($pdo, $actor, $resourceId, $input);
            sz_api_respond(200, ['ok' => true]);
        }

        if ($method === 'DELETE') {
            if ($resourceId <= 0) {
                throw new \InvalidArgumentException('Voor verwijderen is een content-id vereist.');
            }
            sz_delete_content($pdo, $actor, $resourceId);
            sz_api_respond(200, ['ok' => true]);
        }

        sz_api_respond(405, ['ok' => false, 'error' => 'Methode niet toegestaan voor /content.']);
    }

    if ($resource === 'playlists') {
        if ($method === 'GET') {
            $items = sz_list_playlists($pdo, $actor);
            if ($resourceId > 0) {
                foreach ($items as $item) {
                    if ((int) ($item['id'] ?? 0) === $resourceId) {
                        sz_api_respond(200, ['ok' => true, 'data' => $item]);
                    }
                }
                sz_api_respond(404, ['ok' => false, 'error' => 'Playlist niet gevonden.']);
            }

            sz_api_respond(200, ['ok' => true, 'data' => $items]);
        }

        if ($method === 'POST') {
            $id = sz_create_playlist($pdo, $actor, $input);
            sz_api_respond(201, ['ok' => true, 'id' => $id]);
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            if ($resourceId <= 0) {
                throw new \InvalidArgumentException('Voor update is een playlist-id vereist.');
            }
            sz_update_playlist($pdo, $actor, $resourceId, $input);
            sz_api_respond(200, ['ok' => true]);
        }

        if ($method === 'DELETE') {
            if ($resourceId <= 0) {
                throw new \InvalidArgumentException('Voor verwijderen is een playlist-id vereist.');
            }
            sz_delete_playlist($pdo, $actor, $resourceId);
            sz_api_respond(200, ['ok' => true]);
        }

        sz_api_respond(405, ['ok' => false, 'error' => 'Methode niet toegestaan voor /playlists.']);
    }

    if ($resource === 'profile') {
        if (!in_array($method, ['PUT', 'PATCH'], true)) {
            sz_api_respond(405, ['ok' => false, 'error' => 'Gebruik PATCH of PUT voor /profile.']);
        }

        sz_update_profile($pdo, $actor, $input);
        $updatedActor = sz_current_user(true);
        sz_api_respond(200, [
            'ok' => true,
            'data' => is_array($updatedActor) ? sz_api_public_user_payload($updatedActor) : null,
        ]);
    }

    sz_api_respond(404, ['ok' => false, 'error' => 'Endpoint niet gevonden.']);
} catch (\Throwable $exception) {
    error_log('API error: ' . $exception->getMessage());
    sz_api_error_from_exception($exception);
}
