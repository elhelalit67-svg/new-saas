<?php
declare(strict_types=1);

/*
 * AlQuma Education — temporary safe baseline.
 * This page is informational only; it does not provide SaaS functionality.
 * Replace it with the routed application after persistence, authentication,
 * authorization, tenant isolation, and automated tests are implemented.
 */
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store');

?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="القمة التعليمية — أساس منصة إدارة السناتر التعليمية">
  <title>القمة التعليمية | AlQuma Education</title>
  <style>
    :root { color-scheme: light; font-family: Tahoma, Arial, sans-serif; background: #f5f7fb; color: #18243b; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; }
    main { width: min(100%, 760px); padding: clamp(28px, 6vw, 56px); border: 1px solid #e0e6f0; border-radius: 24px; background: #fff; box-shadow: 0 18px 50px rgba(24, 36, 59, .08); }
    .brand { display: inline-flex; align-items: center; gap: 10px; color: #2458a6; font-size: 14px; font-weight: 700; letter-spacing: .02em; }
    .mark { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 13px; background: #eaf1ff; color: #2458a6; font-size: 20px; }
    h1 { margin: 28px 0 14px; font-size: clamp(28px, 5vw, 42px); line-height: 1.35; }
    p { color: #536179; font-size: 17px; line-height: 1.9; margin: 0 0 18px; }
    .status { display: inline-block; margin: 8px 0 18px; padding: 7px 12px; border-radius: 999px; background: #fff4d6; color: #79520a; font-size: 13px; font-weight: 700; }
    .note { padding-top: 18px; border-top: 1px solid #e8edf5; font-size: 13px; }
  </style>
</head>
<body>
  <main>
    <div class="brand"><span class="mark" aria-hidden="true">ق</span><span>ALQUMA EDUCATION</span></div>
    <h1>إدارة تعليمية أوضح، من مكان واحد</h1>
    <span class="status">المنصة قيد التأسيس</span>
    <p>نعمل على بناء منصة القمة التعليمية لإدارة السناتر والطلاب والمدرسين والحضور والحسابات، مع فصل بيانات كل سنتر وحماية خصوصية المستخدمين.</p>
    <p class="note">هذه صفحة تعريفية مؤقتة فقط. لم تُفعّل بعد وظائف تسجيل الدخول أو إدارة البيانات أو التحصيل المالي، لذلك لا تُدخل بيانات شخصية أو مالية هنا.</p>
  </main>
</body>
</html>
