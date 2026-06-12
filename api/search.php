<?php
$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/setPath.php';
require_once $projectRoot . '/models/Search.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$query = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['type'] ?? 'all'));
$allowedTypes = ['all', 'blog', 'updates', 'portfolio'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

if ($query === '' || mb_strlen($query) < 2 || mb_strlen($query) > 80) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Search query must be between 2 and 80 characters.',
        'results' => ['blog' => [], 'updates' => [], 'portfolio' => []],
    ]);
    exit;
}

$adminMode = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;

try {
    $search = new Search();
    $results = ['blog' => [], 'updates' => [], 'portfolio' => []];

    if ($type === 'blog') {
        $results['blog'] = $search->formatSearchResults($search->searchBlogPosts($query, $adminMode), 'blog', $adminMode);
    } elseif ($type === 'updates') {
        $results['updates'] = $search->formatSearchResults($search->searchUpdates($query, $adminMode), 'updates', $adminMode);
    } elseif ($type === 'portfolio') {
        $results['portfolio'] = $search->formatSearchResults($search->searchPortfolio($query, $adminMode), 'portfolio', $adminMode);
    } else {
        $global = $search->globalSearch($query, $adminMode);
        $results['blog'] = $search->formatSearchResults($global['blog'], 'blog', $adminMode);
        $results['updates'] = $search->formatSearchResults($global['updates'], 'updates', $adminMode);
        $results['portfolio'] = $search->formatSearchResults($global['portfolio'], 'portfolio', $adminMode);
    }

    $total = count($results['blog']) + count($results['updates']) + count($results['portfolio']);

    echo json_encode([
        'success' => true,
        'error' => null,
        'query' => $query,
        'type' => $type,
        'admin_mode' => $adminMode,
        'total_results' => $total,
        'results' => $results,
        'timestamp' => time(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Search API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while searching. Please try again.',
        'results' => ['blog' => [], 'updates' => [], 'portfolio' => []],
    ]);
}
