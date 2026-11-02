<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; manifest-src 'self'; frame-ancestors 'none'");
$v = KK_VERSION;
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#F5B700">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="KotKot">
  <title>KotKot</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="assets/icon-192.png">
  <link rel="preload" href="assets/fonts/atkinson-regular.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="assets/app.css?v=<?= $v ?>">
  <script src="assets/app.js?v=<?= $v ?>" defer></script>
</head>
<body>
  <main id="app" aria-live="polite"><p class="loading">Chargement…</p></main>
  <div id="toast" role="status" aria-live="assertive"></div>
  <noscript>KotKot a besoin de JavaScript.</noscript>
</body>
</html>
