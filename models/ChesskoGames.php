<?php
/**
 * ChesskoGames model
 *
 * "Your jelly record" for /chessko. Finished games are stored under an anonymous random visitor
 * id that the browser generates and keeps in localStorage (no account, no IP address, no user
 * agent). The chess itself runs in the browser; this table only remembers results.
 *
 * Tables are created lazily, with separate SQLite and MySQL definitions like the other models.
 */
require_once __DIR__ . '/../config/setPath.php';
require_once __DIR__ . '/Database.php';

class ChesskoGames {
    /** Newest games kept per visitor; older rows are pruned on write. */
    const MAX_PER_VISITOR = 200;
    /** A visitor cannot record more than this many games inside RATE_WINDOW seconds. */
    const RATE_LIMIT = 8;
    const RATE_WINDOW = 60;
    /** Rows older than this are dropped (checked occasionally on write). */
    const RETENTION_DAYS = 400;

    const RESULTS = ['win', 'loss', 'draw'];
    const ENGINES = ['jelly', 'stockfish'];

    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists(): void {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->db->exec("CREATE TABLE IF NOT EXISTS chessko_games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                visitor TEXT NOT NULL,
                level INTEGER NOT NULL,
                level_name TEXT,
                effective_level INTEGER,
                nudge INTEGER NOT NULL DEFAULT 0,
                engine TEXT NOT NULL DEFAULT 'jelly',
                result TEXT NOT NULL,
                player_color TEXT NOT NULL DEFAULT 'w',
                plies INTEGER NOT NULL DEFAULT 0,
                created_at INTEGER NOT NULL
            )");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_chessko_visitor ON chessko_games (visitor, created_at)");
        } else {
            $this->db->exec("CREATE TABLE IF NOT EXISTS chessko_games (
                id INT AUTO_INCREMENT PRIMARY KEY,
                visitor CHAR(32) NOT NULL,
                level TINYINT NOT NULL,
                level_name VARCHAR(40) NULL,
                effective_level TINYINT NULL,
                nudge TINYINT NOT NULL DEFAULT 0,
                engine VARCHAR(12) NOT NULL DEFAULT 'jelly',
                result VARCHAR(4) NOT NULL,
                player_color CHAR(1) NOT NULL DEFAULT 'w',
                plies SMALLINT NOT NULL DEFAULT 0,
                created_at INT UNSIGNED NOT NULL,
                INDEX idx_chessko_visitor (visitor, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public static function validVisitor(?string $id): bool {
        return is_string($id) && preg_match('/^[a-f0-9]{32}$/', $id) === 1;
    }

    /** True when this visitor already recorded RATE_LIMIT games in the last RATE_WINDOW seconds. */
    public function isRateLimited(string $visitor): bool {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM chessko_games WHERE visitor = ? AND created_at > ?');
        $stmt->execute([$visitor, time() - self::RATE_WINDOW]);
        return (int)$stmt->fetchColumn() >= self::RATE_LIMIT;
    }

    /**
     * Validate a client payload and store it. Returns the cleaned row, or throws
     * InvalidArgumentException with a message safe to show the client.
     */
    public function record(string $visitor, array $body): array {
        $result = $body['result'] ?? null;
        if (!in_array($result, self::RESULTS, true)) {
            throw new InvalidArgumentException('result must be one of win, loss, draw');
        }
        $level = $body['level'] ?? null;
        if (!is_int($level) || $level < 1 || $level > 20) {
            throw new InvalidArgumentException('level must be an integer from 1 to 20');
        }
        $engine = $body['engine'] ?? 'jelly';
        if (!is_string($engine) || !in_array($engine, self::ENGINES, true)) $engine = 'jelly';
        $levelName = isset($body['level_name']) && is_string($body['level_name'])
            ? mb_substr(trim($body['level_name']), 0, 40) : null;
        $effective = isset($body['effective_level']) && is_int($body['effective_level'])
            ? max(1, min(20, $body['effective_level'])) : $level;
        $nudge = isset($body['nudge']) && is_int($body['nudge']) ? max(-3, min(3, $body['nudge'])) : 0;
        $color = ($body['player_color'] ?? 'w') === 'b' ? 'b' : 'w';
        $plies = isset($body['plies']) && is_int($body['plies']) ? max(0, min(2000, $body['plies'])) : 0;

        $row = [
            'visitor' => $visitor, 'level' => $level, 'level_name' => $levelName,
            'effective_level' => $effective, 'nudge' => $nudge, 'engine' => $engine,
            'result' => $result, 'player_color' => $color, 'plies' => $plies, 'created_at' => time(),
        ];
        $stmt = $this->db->prepare('INSERT INTO chessko_games
            (visitor, level, level_name, effective_level, nudge, engine, result, player_color, plies, created_at)
            VALUES (:visitor, :level, :level_name, :effective_level, :nudge, :engine, :result, :player_color, :plies, :created_at)');
        $stmt->execute($row);
        $this->prune($visitor);
        return $row;
    }

    private function prune(string $visitor): void {
        $stmt = $this->db->prepare('SELECT id FROM chessko_games WHERE visitor = ? ORDER BY created_at DESC, id DESC LIMIT 1 OFFSET ' . (int)self::MAX_PER_VISITOR);
        $stmt->execute([$visitor]);
        $cut = $stmt->fetchColumn();
        if ($cut !== false) {
            $del = $this->db->prepare('DELETE FROM chessko_games WHERE visitor = ? AND id <= ?');
            $del->execute([$visitor, (int)$cut]);
        }
        if (random_int(1, 50) === 1) {
            $old = $this->db->prepare('DELETE FROM chessko_games WHERE created_at < ?');
            $old->execute([time() - self::RETENTION_DAYS * 86400]);
        }
    }

    /** Same shape as the python backend's /api/games/stats. */
    public function stats(string $visitor): array {
        $stmt = $this->db->prepare('SELECT level, MAX(level_name) AS name, result, COUNT(*) AS n
            FROM chessko_games WHERE visitor = ? GROUP BY level, result');
        $stmt->execute([$visitor]);
        $perLevel = [];
        $total = ['win' => 0, 'loss' => 0, 'draw' => 0, 'games' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (int)$row['level'];
            $perLevel[$key] ??= ['level' => $key, 'name' => $row['name'], 'win' => 0, 'loss' => 0, 'draw' => 0, 'games' => 0];
            $perLevel[$key][$row['result']] += (int)$row['n'];
            $perLevel[$key]['games'] += (int)$row['n'];
            $total[$row['result']] += (int)$row['n'];
            $total['games'] += (int)$row['n'];
        }
        ksort($perLevel);
        return ['total' => $total, 'per_level' => array_values($perLevel)];
    }

    /** Newest first, same fields the python backend returns. */
    public function recent(string $visitor, int $limit): array {
        $limit = max(1, min(50, $limit));
        $stmt = $this->db->prepare('SELECT created_at, level, level_name, engine, result, player_color, plies
            FROM chessko_games WHERE visitor = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
        $stmt->execute([$visitor]);
        return array_map(static fn(array $r): array => [
            't' => (int)$r['created_at'], 'level' => (int)$r['level'], 'level_name' => $r['level_name'],
            'engine' => $r['engine'], 'result' => $r['result'], 'player_color' => $r['player_color'],
            'plies' => (int)$r['plies'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** All games on the site, for the health endpoint. */
    public function totalGames(): int {
        return (int)$this->db->query('SELECT COUNT(*) FROM chessko_games')->fetchColumn();
    }
}
