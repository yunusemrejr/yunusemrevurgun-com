<?php
require_once __DIR__ . '/../config/setPath.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../models/Auth.php';

// Restrict to authenticated admin only
Auth::checkLogin();

header('Content-Type: text/plain');

echo "=== DIAGNOSTIC: Updates Page ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    echo "1. Database connection: SUCCESS\n";
    echo "   Driver: " . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n\n";
    
    echo "2. Updates table structure:\n";
    $cols = $db->query("DESCRIBE updates")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "   {$c['Field']} | {$c['Type']} | Default: {$c['Default']}\n";
    }
    echo "\n";
    
    $total = $db->query("SELECT COUNT(*) FROM updates")->fetchColumn();
    echo "3. Total rows in updates table: $total\n\n";
    
    $hasIsPublished = false;
    foreach ($cols as $c) {
        if ($c['Field'] === 'is_published') $hasIsPublished = true;
    }
    echo "4. Has 'is_published' column: " . ($hasIsPublished ? "YES" : "NO") . "\n\n";
    
    echo "5. Running getPaginatedUpdates query (offset=0, limit=10):\n";
    $stmt = $db->prepare("SELECT * FROM updates ORDER BY update_date DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', 10, PDO::PARAM_INT);
    $stmt->bindValue(':offset', 0, PDO::PARAM_INT);
    $result = $stmt->execute();
    echo "   Execute: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Rows returned: " . count($rows) . "\n";
    if (count($rows) > 0) {
        echo "   First row keys: " . implode(', ', array_keys($rows[0])) . "\n";
        echo "   First row title: " . ($rows[0]['title'] ?? 'N/A') . "\n";
    }
    echo "\n";
    
    echo "6. Running getTotalUpdates query:\n";
    $stmt2 = $db->prepare("SELECT COUNT(*) FROM updates");
    $stmt2->execute();
    $count = $stmt2->fetchColumn();
    echo "   Total: $count\n\n";
    
    if ($hasIsPublished) {
        echo "7. is_published values:\n";
        $stmt3 = $db->query("SELECT COUNT(*) FROM updates WHERE is_published = 1 OR is_published = '1' OR is_published = 'true'");
        $pubCount = $stmt3->fetchColumn();
        echo "   Rows where is_published is truthy: $pubCount\n";
        $stmt3b = $db->query("SELECT DISTINCT is_published FROM updates LIMIT 5");
        $distinctVals = $stmt3b->fetchAll(PDO::FETCH_COLUMN);
        echo "   Distinct is_published values: " . implode(', ', $distinctVals) . "\n\n";
    }
    
    echo "8. Raw sample (first 3 rows):\n";
    $raw = $db->query("SELECT * FROM updates ORDER BY update_date DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw as $i => $row) {
        echo "   Row " . ($i+1) . ": id={$row['id']}, title=" . substr($row['title'], 0, 50) . ", update_date={$row['update_date']}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
