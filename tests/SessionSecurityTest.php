<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/SessionSecurity.php';

SessionSecurity::start(false);

try {
    if (ini_get('session.use_strict_mode') !== '1' ||
        ini_get('session.use_only_cookies') !== '1' ||
        ini_get('session.use_trans_sid') !== '0') {
        throw new RuntimeException('Session hardening ini settings were not applied.');
    }

    $cookie = session_get_cookie_params();
    if (($cookie['httponly'] ?? false) !== true ||
        ($cookie['samesite'] ?? '') !== 'Lax' ||
        ($cookie['secure'] ?? true) !== false) {
        throw new RuntimeException('Session cookie flags do not match the configured transport.');
    }

    $token = SessionSecurity::csrfToken();
    if (strlen($token) !== 64 || !SessionSecurity::verifyCsrfToken($token)) {
        throw new RuntimeException('Generated CSRF token did not validate.');
    }
    if (SessionSecurity::verifyCsrfToken('wrong-token') ||
        SessionSecurity::verifyCsrfToken(['unexpected' => 'array']) ||
        SessionSecurity::verifyCsrfToken(null)) {
        throw new RuntimeException('Invalid CSRF token input was accepted.');
    }
    if (SessionSecurity::csrfToken() !== $token) {
        throw new RuntimeException('CSRF token unexpectedly changed within one session.');
    }

    SessionSecurity::regenerateId();
    if (!SessionSecurity::verifyCsrfToken($token)) {
        throw new RuntimeException('Session ID rotation unexpectedly invalidated the CSRF token.');
    }
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        SessionSecurity::destroy();
    }
}

echo "Session security tests passed.\n";
