<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/TenantContext.php';
require_once __DIR__ . '/SessionSecurity.php';
require_once __DIR__ . '/AuthService.php';
require_once __DIR__ . '/LoginRateLimiter.php';

/**
 * Minimal HTTP adapter for the existing authentication services.
 * This is an early vertical slice, not a production-ready application.
 */
final class HttpApplication
{
    public static function run(): void
    {
        self::securityHeaders();

        $host = is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : '';
        $secure = self::isSecureTransport();
        $normalizedHost = TenantContext::normalizeHost($host);
        $isLocal = $normalizedHost !== null && in_array($normalizedHost, ['localhost', '127.0.0.1'], true);

        // Plain HTTP is permitted only for explicit local development.
        if (!$secure && !$isLocal) {
            self::respond(400, 'يلزم اتصال HTTPS آمن.');
            return;
        }

        try {
            SessionSecurity::start($secure);
            $pdo = Database::connect();
            $tenant = TenantContext::resolve($pdo, $host);
            if ($tenant === null) {
                self::respond(404, 'الصفحة غير متاحة لهذا النطاق.');
                return;
            }

            $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'login';
            if ($action === 'login') {
                self::login($pdo, $host, $tenant['id']);
                return;
            }
            if ($action === 'logout') {
                self::logout();
                return;
            }
            if ($action === 'dashboard') {
                self::dashboard($pdo, $host);
                return;
            }

            self::respond(404, 'الصفحة غير موجودة.');
        } catch (Throwable $exception) {
            // Do not expose database details, credentials, or stack traces.
            error_log('HTTP application request failed: ' . get_class($exception));
            if (!headers_sent()) {
                self::respond(503, 'الخدمة غير متاحة مؤقتًا. حاول مرة أخرى لاحقًا.');
            }
        }
    }

    private static function login(PDO $pdo, string $host, int $tenantId): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!SessionSecurity::verifyCsrfToken($_POST['_csrf'] ?? null)) {
                self::respond(403, 'انتهت صلاحية الطلب. حدّث الصفحة وحاول مرة أخرى.');
                return;
            }

            $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $clientIp = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';

            if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
                self::respond(400, 'تعذر التحقق من الطلب.');
                return;
            }

            if (LoginRateLimiter::isBlocked($pdo, $tenantId, $email, $clientIp)) {
                self::respond(429, 'محاولات دخول كثيرة. انتظر قليلًا ثم حاول مرة أخرى.');
                return;
            }

            if (AuthService::login($pdo, $host, $email, $password)) {
                LoginRateLimiter::clearIdentityFailures($pdo, $tenantId, $email, $clientIp);
                header('Location: ?action=dashboard', true, 303);
                return;
            }

            LoginRateLimiter::recordFailure($pdo, $tenantId, $email, $clientIp);
            self::renderLogin(401, 'بيانات الدخول غير صحيحة أو الحساب غير متاح.');
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET, POST');
            self::respond(405, 'طريقة الطلب غير مسموح بها.');
            return;
        }

        $userId = AuthService::currentUserId($pdo, $host);
        if ($userId !== null) {
            header('Location: ?action=dashboard', true, 303);
            return;
        }
        self::renderLogin(200, '');
    }

    private static function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            self::respond(405, 'تسجيل الخروج يتطلب طلب POST.');
            return;
        }

        if (!SessionSecurity::verifyCsrfToken($_POST['_csrf'] ?? null)) {
            self::respond(403, 'انتهت صلاحية الطلب. حدّث الصفحة وحاول مرة أخرى.');
            return;
        }

        SessionSecurity::destroy();
        header('Location: ?action=login', true, 303);
    }

    private static function dashboard(PDO $pdo, string $host): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            self::respond(405, 'طريقة الطلب غير مسموح بها.');
            return;
        }

        $userId = AuthService::currentUserId($pdo, $host);
        if ($userId === null) {
            header('Location: ?action=login', true, 303);
            return;
        }

        $token = self::escape(SessionSecurity::csrfToken());
        header('Content-Type: text/html; charset=UTF-8');
        echo self::page('لوحة التحكم', '<h1>أهلًا بك</h1><p>تم تسجيل الدخول بنجاح.</p><p>معرّف المستخدم: ' .
            self::escape((string)$userId) . '</p><p>هذه لوحة أولية؛ إدارة المدرسين والطلاب لم تُنفّذ بعد.</p>' .
            '<form method="post" action="?action=logout"><input type="hidden" name="_csrf" value="' . $token .
            '"><button type="submit">تسجيل الخروج</button></form>');
    }

    private static function renderLogin(int $status, string $error): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        $token = self::escape(SessionSecurity::csrfToken());
        $message = $error === '' ? '' : '<p class="error">' . self::escape($error) . '</p>';
        echo self::page('تسجيل الدخول', '<h1>تسجيل الدخول</h1>' . $message .
            '<form method="post" action="?action=login" autocomplete="on">' .
            '<label for="email">البريد الإلكتروني</label>' .
            '<input id="email" name="email" type="email" maxlength="254" autocomplete="username" required>' .
            '<label for="password">كلمة المرور</label>' .
            '<input id="password" name="password" type="password" autocomplete="current-password" required>' .
            '<input type="hidden" name="_csrf" value="' . $token . '">' .
            '<button type="submit">دخول</button></form>');
    }

    private static function page(string $title, string $body): string
    {
        return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">' .
            '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . self::escape($title) .
            ' | القمة التعليمية</title><style>' .
            'body{font-family:Tahoma,Arial,sans-serif;background:#f5f7fb;color:#18243b;margin:0;padding:24px}' .
            'main{max-width:520px;margin:8vh auto;background:#fff;padding:32px;border:1px solid #e0e6f0;border-radius:18px}' .
            'label{display:block;margin-top:16px}input{width:100%;box-sizing:border-box;padding:12px;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px}' .
            'button{margin-top:20px;padding:12px 20px;border:0;border-radius:8px;background:#2458a6;color:#fff;cursor:pointer}' .
            '.error{color:#a11d2b}</style></head><body><main>' . $body . '</main></body></html>';
    }

    private static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        header('Cache-Control: no-store');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }

    private static function isSecureTransport(): bool
    {
        if (!isset($_SERVER['HTTPS']) || !is_string($_SERVER['HTTPS'])) {
            return false;
        }
        return in_array(strtolower($_SERVER['HTTPS']), ['on', '1', 'true'], true);
    }

    private static function respond(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo self::page('تنبيه', '<h1>' . self::escape($message) . '</h1>');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
