<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/HttpApplication.php';

/**
 * Fast HTTP-boundary checks that do not require PostgreSQL or live credentials.
 * Full successful login/logout browser-flow tests remain a separate requirement.
 */
foreach ([null, 'off', '0', 'false'] as $httpsValue) {
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'HTTP_HOST' => 'tenant.example.test',
    ];
    if ($httpsValue !== null) {
        $_SERVER['HTTPS'] = $httpsValue;
    }

    http_response_code(200);
    ob_start();
    HttpApplication::run();
    $body = (string)ob_get_clean();

    if (http_response_code() !== 400) {
        throw new RuntimeException('Non-HTTPS external request was not rejected.');
    }
    if (!str_contains($body, 'HTTPS')) {
        throw new RuntimeException('Non-HTTPS rejection did not return the expected generic message.');
    }
}

echo "HTTP transport boundary tests passed.\n";
