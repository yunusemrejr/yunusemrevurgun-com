<?php
require_once dirname(__DIR__) . '/models/YunoBotKnowledge.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405); header('Allow: GET'); echo '{"error":"Method not allowed"}'; exit;
}
try {
    $payload = json_encode((new YunoBotKnowledge())->snapshot(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $etag = '"'.hash('sha256', $payload).'"';
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('ETag: '.$etag);
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
    echo $payload;
} catch (Throwable $error) {
    error_log('YunoBot public snapshot unavailable');
    http_response_code(503); header('Cache-Control: no-store'); echo '{"error":"Source refresh unavailable"}';
}
