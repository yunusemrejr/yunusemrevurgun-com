<?php
/**
 * Rate Limiter Cleanup Script
 * Removes old rate limiting data to prevent storage bloat
 * Run this via cron job daily
 */

require_once __DIR__ . '/../includes/rate_limiter.php';

// Clean up old data
RateLimiter::cleanup();

echo "Rate limiter cleanup completed at " . date('Y-m-d H:i:s') . "\n";
?>
