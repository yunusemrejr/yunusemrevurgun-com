<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#1c2b20">
  <title>403 | Access denied</title>
  <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32.png">
  <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
  <style>
    /* Self-contained on purpose: this page is served when the app itself is
       unavailable, so it depends on nothing but static files. */
    @font-face {
      font-family: "Barlow Semi Condensed";
      src: url("/assets/fonts/barlow-semicondensed-600-latin.woff2") format("woff2");
      font-weight: 600;
      font-display: swap;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
      background: #cddcea;
      color: #142019;
      font-family: "Barlow Semi Condensed", "Arial Narrow", system-ui, sans-serif;
    }
    .box {
      max-width: 460px;
      width: 100%;
      text-align: center;
      background: #f7f8f6;
      border-radius: 28px;
      padding: 8px 32px 36px;
      box-shadow: inset 0 1px 0 #fff, 0 3px 0 rgba(16,26,19,.06), 0 28px 60px -20px rgba(16,26,19,.5);
    }
    img { display: block; margin: -56px auto 0; width: 120px; height: 120px; border-radius: 22px; box-shadow: 0 14px 24px -12px rgba(16,26,19,.5); }
    .code { margin: 12px 0 0; font-size: 64px; line-height: 1; font-weight: 600; color: #355238; }
    h1 { margin: 8px 0 8px; font-size: 26px; font-weight: 600; }
    p { margin: 0 0 24px; font-family: "Atkinson Hyperlegible Next", system-ui, sans-serif; font-size: 17px; line-height: 1.5; color: #34493a; }
    a {
      display: inline-block; padding: 11px 28px 12px; border-radius: 999px;
      background: #355238; color: #fff; text-decoration: none; font-weight: 600; font-size: 17px;
      box-shadow: inset 0 2px 0 rgba(255,255,255,.35), inset 0 -3px 0 rgba(12,22,15,.28), 0 6px 14px -6px rgba(40,64,43,.55);
    }
    a:hover { background: #263a2b; }
    a:focus-visible { outline: 3px solid #142019; outline-offset: 3px; }
  </style>
</head>
<body>
  <main class="box">
    <img src="/assets/images/brand/logo-mark.svg" width="120" height="120" alt="" style="border-radius:22px">
    <p class="code">403</p>
    <h1>Access denied</h1>
    <p>You do not have permission to open this page.</p>
    <a href="/">Back to the homepage</a>
  </main>
</body>
</html>
