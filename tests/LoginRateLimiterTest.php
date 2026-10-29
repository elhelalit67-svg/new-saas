<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/LoginRateLimiter.php';

putenv('AUTH_RATE_LIMIT_KEY=unit-test-only-key-with-at-least-32-characters');

$reflection = new ReflectionClass(LoginRateLimiter::class);
$method = $reflection->getMethod('bucketKeys');
$method->setAccessible(true);

$first = $method->invoke(null, ' Teacher@Example.test ', '192.0.2.10');
$normalized = $method->invoke(null, 'teacher@example.test', '192.0.2.10');
$otherIp = $method->invoke(null, 'teacher@example.test', '192.0.2.11');
if ($first !== $normalized) {
    throw new RuntimeException('Email normalization did not produce stable rate-limit buckets.');
}
if ($first[0] === $otherIp[0] || $first[1] === $otherIp[1]) {
    throw new RuntimeException('Different source IPs unexpectedly shared rate-limit buckets.');
}
if (strlen($first[0]) !== 64 || strlen($first[1]) !== 64) {
    throw new RuntimeException('Rate-limit bucket keys must be SHA-256 HMACs.');
}

putenv('AUTH_RATE_LIMIT_KEY=short');
$threw = false;
try {
    $method->invoke(null, 'teacher@example.test', '192.0.2.10');
} catch (ReflectionException|RuntimeException) {
    $threw = true;
}
if (!$threw) {
    throw new RuntimeException('Short HMAC key was accepted.');
}

$threw = false;
putenv('AUTH_RATE_LIMIT_KEY=unit-test-only-key-with-at-least-32-characters');
try {
    $method->invoke(null, 'teacher@example.test', 'not-an-ip');
} catch (ReflectionException|InvalidArgumentException) {
    $threw = true;
}
if (!$threw) {
    throw new RuntimeException('Invalid client IP was accepted.');
}

echo "Login rate limiter tests passed.\n";
