<?php
/**
 * Chessko JSON backend (PHP).
 *
 * The chess rules, the search, the learned evaluation and the WebAssembly Stockfish all run in the
 * visitor's browser. This endpoint only does what a browser cannot do for itself:
 *
 *   GET  /api/chessko/health         status, model + book metadata, number of games recorded
 *   GET  /api/chessko/config         difficulty levels + algorithm list (assets/chessko/data/levels.json)
 *   GET  /api/chessko/model          trained logistic-regression weights
 *   GET  /api/chessko/book           k-NN opening book
 *   GET  /api/chessko/games/stats    this visitor's record per level
 *   GET  /api/chessko/games/recent   this visitor's latest games
 *   POST /api/chessko/games          record a finished game
 *   POST /api/chessko/train          not available here: training happens offline (see the README)
 *
 * "This visitor" is the anonymous random id in the X-Chessko-Visitor header.
 */
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/setPath.php';

const CHESSKO_VERSION = '1.1.0';
const CHESSKO_MAX_BODY = 16384;
const CHESSKO_DATA_DIR = __DIR__ . '/../assets/chessko/data';

function chessko_json($payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function chessko_error(int $status, string $message): never {
    chessko_json(['error' => $message], $status);
}

/** Serve one of the static JSON files with an ETag so repeat visits cost a 304. */
function chessko_static(string $file, string $missing): never {
    $path = CHESSKO_DATA_DIR . '/' . $file;
    if (!is_file($path)) chessko_error(404, $missing);
    $etag = '"' . md5_file($path) . '"';
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('ETag: ' . $etag);
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function chessko_read_json(string $file): ?array {
    $path = CHESSKO_DATA_DIR . '/' . $file;
    if (!is_file($path)) return null;
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

$isDev = php_sapi_name() === 'cli-server' || getenv('MODE') === 'development';
$url = $isDev
    ? trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/')
    : trim((string)($_GET['url'] ?? ''), '/');
$route = preg_replace('#^api/chessko/?#', '', $url);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$visitor = $_SERVER['HTTP_X_CHESSKO_VISITOR'] ?? '';

switch ($route) {
    case 'health':
        if ($method !== 'GET') chessko_error(405, 'GET only');
        $model = chessko_read_json('model.json');
        $book = chessko_read_json('book.json');
        $levels = chessko_read_json('levels.json') ?? ['levels' => []];
        $recorded = null;
        try {
            require_once $projectRoot . '/models/ChesskoGames.php';
            $recorded = (new ChesskoGames())->totalGames();
        } catch (Throwable $e) {
            error_log('chessko health: ' . $e->getMessage());
        }
        chessko_json([
            'app' => 'chessko',
            'version' => CHESSKO_VERSION,
            'status' => 'ok',
            'backend' => 'php',
            'php' => PHP_VERSION,
            'can_train' => false,
            'model' => $model === null ? null : [
                'trained' => true,
                'trained_at' => $model['trained_at'] ?? null,
                'positions' => $model['positions'] ?? null,
                'val_log_loss' => $model['val_log_loss'] ?? null,
                'baseline_log_loss' => $model['baseline_log_loss'] ?? null,
                'feature_names' => $model['feature_names'] ?? null,
                'epochs' => $model['epochs'] ?? null,
            ],
            'book' => $book === null ? null : [
                'entries' => $book['entries'] ?? null,
                'games' => $book['games'] ?? null,
                'plies' => $book['plies'] ?? null,
            ],
            'levels' => count($levels['levels'] ?? []),
            'games_recorded' => $recorded ?? 0,
        ]);

    case 'config':
        if ($method !== 'GET') chessko_error(405, 'GET only');
        chessko_static('levels.json', 'levels.json is missing');

    case 'model':
        if ($method !== 'GET') chessko_error(405, 'GET only');
        chessko_static('model.json', 'no trained model is published');

    case 'book':
        if ($method !== 'GET') chessko_error(405, 'GET only');
        chessko_static('book.json', 'no opening book is published');

    case 'games/stats':
    case 'games/recent':
        if ($method !== 'GET') chessko_error(405, 'GET only');
        require_once $projectRoot . '/models/ChesskoGames.php';
        if (!ChesskoGames::validVisitor($visitor)) {
            // no id (storage blocked): an empty record, not an error
            chessko_json($route === 'games/stats'
                ? ['total' => ['win' => 0, 'loss' => 0, 'draw' => 0, 'games' => 0], 'per_level' => []]
                : ['games' => []]);
        }
        $games = new ChesskoGames();
        if ($route === 'games/stats') chessko_json($games->stats($visitor));
        chessko_json(['games' => $games->recent($visitor, (int)($_GET['limit'] ?? 10))]);

    case 'games':
        if ($method !== 'POST') chessko_error(405, 'POST only');
        require_once $projectRoot . '/models/ChesskoGames.php';
        if (!ChesskoGames::validVisitor($visitor)) {
            chessko_error(400, 'missing or malformed X-Chessko-Visitor header');
        }
        $raw = file_get_contents('php://input', false, null, 0, CHESSKO_MAX_BODY + 1);
        if ($raw === false || strlen($raw) > CHESSKO_MAX_BODY) chessko_error(413, 'body too large');
        $body = json_decode($raw, true);
        if (!is_array($body)) chessko_error(400, 'body must be a JSON object');
        $games = new ChesskoGames();
        if ($games->isRateLimited($visitor)) chessko_error(429, 'too many games recorded, try again in a minute');
        try {
            $row = $games->record($visitor, $body);
        } catch (InvalidArgumentException $e) {
            chessko_error(400, $e->getMessage());
        }
        chessko_json(['recorded' => true, 'result' => $row['result']], 201);

    case 'train':
        chessko_error(501, 'training runs offline (python3 -m engine.train); the site serves the published model');

    default:
        chessko_error(404, 'unknown endpoint');
}
