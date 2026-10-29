<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/TenantContext.php';

$cases = [
    ['center.example.com', 'center.example.com'],
    ['CENTER.EXAMPLE.COM:443', 'center.example.com'],
    ['center.example.com.', 'center.example.com'],
    ['', null],
    ['center.example.com/path', null],
    ['center.example.com,evil.example', null],
    ['user@center.example.com', null],
    ['-bad.example.com', null],
    ['bad-.example.com', null],
    ['center.example.com:99999', null],
    ['center.example.com:abc', null],
    ['[::1]', null],
];

foreach ($cases as [$input, $expected]) {
    $actual = TenantContext::normalizeHost($input);
    if ($actual !== $expected) {
        fwrite(STDERR, sprintf("FAIL: %s expected %s got %s\n", var_export($input, true), var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
}
echo "TenantContext hostname tests passed (" . count($cases) . " cases).\n";
