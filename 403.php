<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>403 | Access Denied</title>
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      background: #000;
      color: #fff;
      font-family: "General Sans", sans-serif;
    }
    .box {
      border: 0.6px solid rgba(255, 255, 255, 0.5);
      padding: 18px 22px;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 12px;
    }
    h1 { margin: 0 0 8px; font-size: 22px; }
    p { margin: 0; opacity: 0.8; font-size: 14px; }
  </style>
</head>
<body>
  <div class="box">
    <h1>403</h1>
    <p>Access Denied</p>
  </div>

</body>
</html>
