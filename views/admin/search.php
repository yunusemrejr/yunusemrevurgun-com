<?php
// Get the project root directory using __DIR__

require_once dirname(__DIR__, 2) . '/config/setPath.php';

//prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Location: ' . FULL_BASE_PATH);
    exit;
}

// Enforce admin authentication
require_once __DIR__ . '/includes/verify.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkLogin();

require_once __DIR__ . '/../../models/Search.php';

// Get search parameters
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'all';

// Initialize search model
$search = new Search();

// Perform search based on type with admin mode enabled
$results = [];
$page = "search";
$pageTitle = "Search Results";

$totalResults = 0;

if (!empty($query) && strlen($query) >= 2) {
    switch ($type) {
        case 'blog':
            $blogResults = $search->searchBlogPosts($query, true);
            $results['blog'] = $search->formatSearchResults($blogResults, 'blog', true);
            $totalResults = count($results['blog']);
            break;
            
        case 'updates':
            $updatesResults = $search->searchUpdates($query, true);
            $results['updates'] = $search->formatSearchResults($updatesResults, 'updates', true);
            $totalResults = count($results['updates']);
            break;
            
        case 'portfolio':
            $portfolioResults = $search->searchPortfolio($query, true);
            $results['portfolio'] = $search->formatSearchResults($portfolioResults, 'portfolio', true);
            $totalResults = count($results['portfolio']);
            break;
            
        default:
            // Global search across all content types
            $globalResults = $search->globalSearch($query, true);
            $results = [
                'blog' => $search->formatSearchResults($globalResults['blog'], 'blog', true),
                'updates' => $search->formatSearchResults($globalResults['updates'], 'updates', true),
                'portfolio' => $search->formatSearchResults($globalResults['portfolio'], 'portfolio', true)
            ];
            $totalResults = count($results['blog']) + count($results['updates']) + count($results['portfolio']);
            break;
    }
}

// Include admin header
include __DIR__ . '/includes/header.php';
?>
    <div class="admin-card">
        <div class="admin-card-body">
            <?php if (empty($query) || strlen($query) < 2): ?>
                <div class="admin-alert admin-alert-info">
                    <i class="bi bi-info-circle"></i>
                    Please enter a search term with at least 2 characters.
                </div>
            <?php elseif ($totalResults === 0): ?>
                <div class="admin-alert admin-alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    No results found for "<?= htmlspecialchars($query) ?>".
                </div>
            <?php else: ?>
                <div style="margin-bottom: var(--space-lg);">
                    <p style="margin-bottom: var(--space-md); color: var(--admin-text-primary); font-family: var(--admin-font-body);">Found <?= $totalResults ?> result<?= $totalResults !== 1 ? 's' : '' ?> for "<?= htmlspecialchars($query) ?>"</p>
                    
                    <div style="display: flex; flex-wrap: wrap; gap: var(--space-sm); margin-bottom: var(--space-lg);">
                        <a href="<?= FULL_BASE_PATH ?>admin/search?q=<?= urlencode($query) ?>" class="admin-btn admin-btn-secondary admin-btn-sm <?= $type === 'all' ? 'active' : '' ?>" style="<?= $type === 'all' ? 'background: var(--admin-accent-subtle); border-color: var(--admin-accent);' : '' ?>">All</a>
                        <a href="<?= FULL_BASE_PATH ?>admin/search?q=<?= urlencode($query) ?>&type=blog" class="admin-btn admin-btn-secondary admin-btn-sm <?= $type === 'blog' ? 'active' : '' ?>" style="<?= $type === 'blog' ? 'background: var(--admin-accent-subtle); border-color: var(--admin-accent);' : '' ?>">Blog</a>
                        <a href="<?= FULL_BASE_PATH ?>admin/search?q=<?= urlencode($query) ?>&type=updates" class="admin-btn admin-btn-secondary admin-btn-sm <?= $type === 'updates' ? 'active' : '' ?>" style="<?= $type === 'updates' ? 'background: var(--admin-accent-subtle); border-color: var(--admin-accent);' : '' ?>">Updates</a>
                        <a href="<?= FULL_BASE_PATH ?>admin/search?q=<?= urlencode($query) ?>&type=portfolio" class="admin-btn admin-btn-secondary admin-btn-sm <?= $type === 'portfolio' ? 'active' : '' ?>" style="<?= $type === 'portfolio' ? 'background: var(--admin-accent-subtle); border-color: var(--admin-accent);' : '' ?>">Portfolio</a>
                    </div>
                </div>
                
                <?php if ($type === 'all' || $type === 'blog'): ?>
                    <?php if (!empty($results['blog'])): ?>
                        <h2 style="font-family: var(--admin-font-display); font-weight: 500; color: var(--admin-text-primary); margin-bottom: var(--space-md); font-size: 1.25rem;">Blog Posts</h2>
                        <div style="overflow-x: auto;">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results['blog'] as $post): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($post['title']) ?></td>
                                            <td>
                                                <?php if (isset($post['is_published']) && $post['is_published']): ?>
                                                    <span class="admin-badge admin-badge-success">Published</span>
                                                <?php else: ?>
                                                    <span class="admin-badge admin-badge-warning">Draft</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= !empty($post['created_at']) ? date('M j, Y', strtotime($post['created_at'])) : 'N/A' ?></td>
                                            <td>
                                                <div style="display: flex; gap: var(--space-xs); flex-wrap: wrap;">
                                                    <a href="<?= $post['url'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">Edit</a>
                                                    <?php if (isset($post['slug'])): ?>
                                                        <a href="<?= FULL_BASE_PATH ?>blog/<?= $post['slug'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" target="_blank">View</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($type === 'all' || $type === 'updates'): ?>
                    <?php if (!empty($results['updates'])): ?>
                        <h2 style="font-family: var(--admin-font-display); font-weight: 500; color: var(--admin-text-primary); margin-bottom: var(--space-md); margin-top: var(--space-lg); font-size: 1.25rem;">Updates</h2>
                        <div style="overflow-x: auto;">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results['updates'] as $update): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($update['title']) ?></td>
                                            <td>
                                                <?php if (isset($update['is_published']) && $update['is_published']): ?>
                                                    <span class="admin-badge admin-badge-success">Published</span>
                                                <?php else: ?>
                                                    <span class="admin-badge admin-badge-warning">Draft</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= !empty($update['created_at']) ? date('M j, Y', strtotime($update['created_at'])) : 'N/A' ?></td>
                                            <td>
                                                <div style="display: flex; gap: var(--space-xs); flex-wrap: wrap;">
                                                    <a href="<?= $update['url'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">Edit</a>
                                                    <a href="<?= FULL_BASE_PATH ?>updates" class="admin-btn admin-btn-secondary admin-btn-sm" target="_blank">View</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($type === 'all' || $type === 'portfolio'): ?>
                    <?php if (!empty($results['portfolio'])): ?>
                        <h2 style="font-family: var(--admin-font-display); font-weight: 500; color: var(--admin-text-primary); margin-bottom: var(--space-md); margin-top: var(--space-lg); font-size: 1.25rem;">Portfolio Items</h2>
                        <div style="overflow-x: auto;">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results['portfolio'] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['title']) ?></td>
                                            <td>
                                                <?php if (isset($item['is_visible']) && $item['is_visible']): ?>
                                                    <span class="admin-badge admin-badge-success">Visible</span>
                                                <?php else: ?>
                                                    <span class="admin-badge admin-badge-warning">Hidden</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= !empty($item['created_at']) ? date('M j, Y', strtotime($item['created_at'])) : 'N/A' ?></td>
                                            <td>
                                                <div style="display: flex; gap: var(--space-xs); flex-wrap: wrap;">
                                                    <a href="<?= $item['url'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">Edit</a>
                                                    <a href="<?= FULL_BASE_PATH ?>portfolio" class="admin-btn admin-btn-secondary admin-btn-sm" target="_blank">View</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php
// Include admin footer
include __DIR__ . '/includes/footer.php';
?> 
