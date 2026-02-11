<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Safe HTML escaping helper.
 */
function sz_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Creates or returns the CSRF token for the active session.
 */
function sz_csrf_token(): string
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        $_SESSION['csrf_token'] === ''
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function sz_validate_csrf(?string $submittedToken): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    if (!is_string($submittedToken) || !is_string($sessionToken)) {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}

function sz_method_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Trims and limits user input to avoid oversized payloads.
 */
function sz_normalize_input(mixed $value, int $maxLength): string
{
    if (!is_string($value)) {
        return '';
    }

    $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($normalized) > $maxLength) {
            return mb_substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    if (strlen($normalized) > $maxLength) {
        return substr($normalized, 0, $maxLength);
    }

    return $normalized;
}

function sz_is_valid_phone(string $phone): bool
{
    return preg_match('/^[0-9+\s().-]{6,30}$/', $phone) === 1;
}

function sz_client_ip(): string
{
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($remoteAddress)) {
        return '0.0.0.0';
    }

    $ip = trim($remoteAddress);
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return '0.0.0.0';
    }

    return substr($ip, 0, 45);
}
