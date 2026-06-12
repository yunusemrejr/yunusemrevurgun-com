<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';

$modules = [
    [
        'title' => 'Modern Coding & AI Era',
        'meta'  => 'Module 01',
        'category' => 'fundamentals',
        'tags'  => ['AI', 'Productivity', 'Workflow'],
        'text'  => 'The leverage moved from syntax velocity to system-level thinking. Models speed execution; fundamentals preserve correctness. Critical evaluation and architectural judgment become more valuable as the volume of generated code increases.',
        'hasMath' => false,
    ],
    [
        'title' => 'Software Architecture Core',
        'meta'  => 'Module 02',
        'category' => 'architecture',
        'tags'  => ['SOLID', 'Patterns', 'Design'],
        'text'  => 'Architecture is the constraint system for reliability, costs, and long-term maintainability across every implementation detail. Understanding SOLID principles, design patterns, and architectural styles enables informed decisions that transcend any specific stack.',
        'hasMath' => false,
    ],
    [
        'title' => 'Mathematics Foundation',
        'meta'  => 'Module 03',
        'category' => 'mathematics',
        'tags'  => ['Linear Algebra', 'Calculus', 'Probability'],
        'text'  => 'Complexity, vectors, statistics, and proofs remain core in the post-code era. Mathematics provides the logical framework for algorithmic thinking and the universal language connecting every domain from web development to machine learning.',
        'hasMath' => true,
        'math'  => [
            '\mathcal{L}(\theta)=\frac{1}{n}\sum_{i=1}^{n}\left(y_i-f_{\theta}(x_i)\right)^2',
            '\theta_j \leftarrow \theta_j-\alpha\frac{\partial \mathcal{L}}{\partial \theta_j}',
        ],
    ],
    [
        'title' => 'Algorithms & Data Structures',
        'meta'  => 'Module 04',
        'category' => 'fundamentals',
        'tags'  => ['Big O', 'Graphs', 'Trees'],
        'text'  => 'Algorithmic efficiency and structural choices determine the practical limits of any system. Understanding time and space complexity, graph theory, and advanced data structures enables developers to write scalable, performant code.',
        'hasMath' => true,
        'math'  => [
            'T(n) = 2T(n/2) + O(n) \;\Rightarrow\; T(n) = O(n \log n)',
            'h(k) = k \bmod m',
        ],
    ],
    [
        'title' => 'System Design',
        'meta'  => 'Module 05',
        'category' => 'architecture',
        'tags'  => ['Scalability', 'Distributed', 'Caching'],
        'text'  => 'Building systems that handle real-world load requires understanding horizontal scaling, load balancing, database sharding, caching strategies, and CAP theorem trade-offs. Design for failure—network partitions and node crashes are guarantees, not edge cases.',
        'hasMath' => false,
    ],
    [
        'title' => 'Code Quality & Testing',
        'meta'  => 'Module 06',
        'category' => 'fundamentals',
        'tags'  => ['TDD', 'CI/CD', 'Review'],
        'text'  => 'Test-driven development, continuous integration, and rigorous code review create a safety net that preserves velocity over time. Quality is not an afterthought—it is a structural property of the development process itself.',
        'hasMath' => false,
    ],
    [
        'title' => 'Security Fundamentals',
        'meta'  => 'Module 07',
        'category' => 'systems',
        'tags'  => ['OWASP', 'Crypto', 'Auth'],
        'text'  => 'Security is a mindset, not a feature. Understanding threat modeling, cryptographic primitives, authentication flows, and common vulnerability classes (OWASP Top 10) allows developers to build resilient systems that protect user data by design.',
        'hasMath' => false,
    ],
    [
        'title' => 'DevOps & Infrastructure',
        'meta'  => 'Module 08',
        'category' => 'systems',
        'tags'  => ['Containers', 'IaC', 'Observability'],
        'text'  => 'Infrastructure as code, container orchestration, and comprehensive observability form the operational backbone of modern applications. Automate everything—deployment, testing, recovery. If a process requires manual intervention, it will fail at the worst possible moment.',
        'hasMath' => false,
    ],
    [
        'title' => 'Database Design',
        'meta'  => 'Module 09',
        'category' => 'systems',
        'tags'  => ['SQL', 'NoSQL', 'Indexing'],
        'text'  => 'Data outlives code. Proper normalization, strategic indexing, query optimization, and choosing the right data model (relational, document, graph, columnar) for the access patterns are decisions with decades-long consequences.',
        'hasMath' => false,
    ],
    [
        'title' => 'Machine Learning Basics',
        'meta'  => 'Module 10',
        'category' => 'mathematics',
        'tags'  => ['Neural Nets', 'Optimization', 'Embeddings'],
        'text'  => 'From supervised learning to transformer architectures, ML fundamentals underpin the AI tools developers use daily. Understanding loss landscapes, gradient descent, and embedding spaces enables effective use and critical evaluation of AI-generated outputs.',
        'hasMath' => true,
        'math'  => [
            '\sigma(z) = \frac{1}{1+e^{-z}}',
            '\text{Attention}(Q,K,V)=\text{softmax}\left(\frac{QK^T}{\sqrt{d_k}}\right)V',
        ],
    ],
    [
        'title' => 'Good Advice',
        'meta'  => 'Module 11',
        'category' => 'fundamentals',
        'tags'  => ['Mindset', 'Learning', 'Career'],
        'text'  => 'Use AI for acceleration, not substitution. Validate generated code with tests, reason from first principles, and keep a math-first mindset for long-term quality. Build a deep foundation—surface-level knowledge evaporates when tools change.',
        'hasMath' => false,
    ],
    [
        'title' => 'AGI Age Relevance',
        'meta'  => 'Module 12',
        'category' => 'fundamentals',
        'tags'  => ['Future', 'Strategy', 'Skills'],
        'text'  => 'Time-resistant value: formal reasoning, architecture judgment, and ability to evaluate generated systems critically. The future belongs to developers who can think architecturally, reason mathematically, and guide AI systems toward correct, maintainable solutions.',
        'hasMath' => false,
    ],
];

$categories = [
    'all'         => 'All',
    'fundamentals'=> 'Fundamentals',
    'architecture'=> 'Architecture',
    'mathematics' => 'Mathematics',
    'systems'     => 'Systems',
];

$totalModules = count($modules);
$totalTopics  = count(array_unique(array_merge(...array_column($modules, 'tags'))));

ui_render_head(
    'Experiments | Post-Code Feed',
    'Unified feed for post-code concepts and mathematics foundations.'
);
?>
<link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/post-code.css?v=<?= filemtime(__DIR__ . '/../../assets/css/post-code.css') ?>">
<body>
<div class="ui-page">
    <?php ui_render_navbar('Experiments'); ?>

    <main class="ui-main">
        <section class="ui-section">
            <p class="ui-eyebrow">Feed</p>
            <h1 class="ui-section-title">Post-Code</h1>
            <p class="ui-section-text">High-signal notes on coding fundamentals, architecture, and mathematics foundation. Curated concepts that remain relevant regardless of which tools write the code.</p>
        </section>

        <section class="ui-section">
            <div class="ui-postcode-stat-bar">
                <div class="ui-stat-card">
                    <span class="ui-stat-value"><?= $totalModules ?></span>
                    <span class="ui-stat-label">Modules</span>
                </div>
                <div class="ui-stat-card">
                    <span class="ui-stat-value"><?= $totalTopics ?></span>
                    <span class="ui-stat-label">Topics</span>
                </div>
                <div class="ui-stat-card">
                    <span class="ui-stat-value">4</span>
                    <span class="ui-stat-label">Categories</span>
                </div>
            </div>
        </section>

        <section class="ui-section">
            <div class="ui-archive-filters" role="toolbar" aria-label="Post-code category filters">
                <?php foreach ($categories as $key => $label): ?>
                    <button class="ui-filter-pill<?= $key === 'all' ? ' is-active' : '' ?>" type="button" data-filter="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></button>
                <?php endforeach; ?>
            </div>

            <div class="ui-feed-list" id="postCodeFeed">
                <?php foreach ($modules as $module): ?>
                    <article class="ui-feed-card" data-category="<?= htmlspecialchars($module['category']) ?>">
                        <div class="ui-feed-card-header">
                            <div>
                                <h2 class="ui-feed-title"><?= htmlspecialchars($module['title']) ?></h2>
                                <p class="ui-feed-meta"><?= htmlspecialchars($module['meta']) ?></p>
                            </div>
                        </div>

                        <?php if (!empty($module['tags'])): ?>
                            <div class="ui-postcode-tags">
                                <?php foreach ($module['tags'] as $tag): ?>
                                    <span class="ui-postcode-tag"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <p class="ui-feed-text"><?= htmlspecialchars($module['text']) ?></p>

                        <?php if (!empty($module['hasMath']) && !empty($module['math'])): ?>
                            <div class="ui-code-block">
                                <?php foreach ($module['math'] as $formula): ?>
                                    \[<?= $formula ?>\]
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <nav class="ui-pagination" aria-label="Post-code pagination">
                <span class="ui-page-link is-disabled">Previous</span>
                <span class="ui-page-link is-active">1</span>
                <span class="ui-page-link is-disabled">Next</span>
            </nav>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__, 2)); ?>
<script>
window.MathJax = {
  tex: { inlineMath: [['\\(', '\\)'], ['$', '$']], displayMath: [['\\[', '\\]'], ['$$', '$$']] },
  svg: { fontCache: 'global' }
};
</script>
<script data-cfasync="false" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js"></script>
<script data-cfasync="false" src="<?= FULL_BASE_PATH ?>assets/js/post-code.js?v=<?= filemtime(__DIR__ . '/../../assets/js/post-code.js') ?>"></script>
</body>
</html>
