<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#69b0f9">
  <title>403 | Access denied</title>
  <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32.png">
  <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
  <style>
    /* Self-contained on purpose: this page is served when the app itself is
       unavailable, so it depends on nothing but static files. */
    @font-face {
      font-family: "Fredoka";
      src: url("/assets/fonts/fredoka-latin.woff2") format("woff2");
      font-weight: 300 700;
      font-display: swap;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
      background: #69b0f9;
      color: #1b2140;
      font-family: "Fredoka", ui-rounded, "SF Pro Rounded", system-ui, sans-serif;
    }
    .box {
      max-width: 460px;
      width: 100%;
      text-align: center;
      background: #fbf8f4;
      border-radius: 28px;
      padding: 8px 32px 36px;
      box-shadow: inset 0 1px 0 #fff, 0 3px 0 rgba(27,33,64,.06), 0 28px 60px -20px rgba(20,26,51,.5);
    }
    img { display: block; margin: -56px auto 0; width: 168px; height: 168px; filter: drop-shadow(0 16px 14px rgba(27,33,64,.25)); transform: rotate(-8deg); }
    .code { margin: 12px 0 0; font-size: 64px; line-height: 1; font-weight: 600; color: #c8110f; }
    h1 { margin: 8px 0 8px; font-size: 26px; font-weight: 600; }
    p { margin: 0 0 24px; font-family: "Atkinson Hyperlegible Next", system-ui, sans-serif; font-size: 17px; line-height: 1.5; color: #3d4770; }
    a {
      display: inline-block; padding: 11px 28px 12px; border-radius: 999px;
      background: #db1816; color: #fff; text-decoration: none; font-weight: 600; font-size: 17px;
      box-shadow: inset 0 2px 0 rgba(255,255,255,.35), inset 0 -3px 0 rgba(120,8,8,.28), 0 6px 14px -6px rgba(184,15,13,.55);
    }
    a:hover { background: #b80f0d; }
    a:focus-visible { outline: 3px solid #1b2140; outline-offset: 3px; }
  </style>
</head>
<body>
  <main class="box">
    <img src="/assets/images/mascot-160.webp" width="168" height="168" alt="">
    <p class="code">403</p>
    <h1>Access denied</h1>
    <p>You do not have permission to open this page.</p>
    <a href="/">Back to the homepage</a>
  </main>
</body>
</html>
