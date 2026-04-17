<?php

declare(strict_types=1);

function aurafit_start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function aurafit_get_csrf_token(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function aurafit_validate_csrf_token(?string $providedToken): bool {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    if (!is_string($providedToken) || $providedToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $providedToken);
}

function aurafit_request_csrf_token(): ?string {
    $token = $_POST['csrf_token'] ?? null;
    if (is_string($token) && $token !== '') {
        return $token;
    }

    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    return is_string($headerToken) ? $headerToken : null;
}

function aurafit_log_exception(string $context, Throwable $e): void {
    error_log(sprintf('[AuraFit][%s] %s in %s:%d', $context, $e->getMessage(), $e->getFile(), $e->getLine()));
}
