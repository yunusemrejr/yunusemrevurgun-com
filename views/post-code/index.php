<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';

$modules = [
    // ── Original 12 modules (preserved) ──
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

    // ── New enriched modules ──

    [
        'title' => 'Software as General Power',
        'meta'  => 'Module 13',
        'category' => 'philosophy',
        'tags'  => ['Philosophy', 'Power', 'Civilization'],
        'text'  => 'Software is not merely a technical artifact—it is a general-purpose transformative force that rewrites the grammar of reality. As the philosopher David Deutsch wrote, "All problems are eventually solvable through the acquisition of knowledge." Code is knowledge crystallized into executable form; it is the closest we have come to alchemy turning abstract thought into concrete causation. When you write software, you are not instructing a machine—you are encoding a causal model of some fragment of the world and instantiating it at near-zero marginal cost. This is power of a kind previously reserved for natural laws: the ability to define new possible worlds and bring them into being.',
        'hasMath' => true,
        'math'  => [
            'P(\text{world}) = \sum_{\text{programs } p} 2^{-|p|} \cdot [\text{execute}(p) = \text{world}]',
            'K(x) = \min\{|p| : U(p) = x\}',
        ],
    ],
    [
        'title' => 'Artificial Channel Intelligence',
        'meta'  => 'Module 14',
        'category' => 'mathematics',
        'tags'  => ['Information Theory', 'Intelligence', 'Channels'],
        'text'  => 'Artificial Channel Intelligence re-frames intelligence not as a property of isolated agents but as a phenomenon of constrained information flow across channels. Shannon\'s channel capacity theorem gives us the fundamental limit: C = B \log_2(1 + S/N). Intelligence, in this view, is the optimization of throughput through bounded channels—whether synaptic, digital, or social. The coding theorem tells us that for any channel with capacity C, there exists a coding scheme that achieves arbitrarily reliable communication at any rate R < C. What we call "understanding" may be nothing more than the discovery of the optimal codec for the channel between reality and action. The entropy of a system is the measure of our ignorance about its microstate; learning is the process of reducing conditional entropy H(System | Observations). The mathematics of communication is thus the mathematics of intelligence itself.',
        'hasMath' => true,
        'math'  => [
            'C = B \log_2\left(1 + \frac{S}{N}\right)',
            'H(X) = -\sum_{i} p(x_i) \log_2 p(x_i)',
            'I(X;Y) = H(X) - H(X \mid Y)',
        ],
    ],
    [
        'title' => 'The Nature of Post-Code Development',
        'meta'  => 'Module 15',
        'category' => 'philosophy',
        'tags'  => ['Post-Code', 'Abstraction', 'Paradigm'],
        'text'  => 'The post-code era does not mean the end of programming—it means programming has become so deeply embedded in the fabric of reality that it ceases to be a specialist activity and becomes a mode of being. "The most profound technologies are those that disappear," wrote Mark Weiser. Programming is disappearing into the infrastructure of thought. The new coding is not about syntax—it is about composing systems from mathematical primitives, about understanding the geometry of loss landscapes, about designing reward structures that align with human values. It is, in Alan Perlis\'s words, "the art of organising complexity." The programmer of tomorrow is as much philosopher, mathematician, and ethicist as engineer. The skill is no longer knowing which library to import, but knowing which computation is worth doing at all.',
        'hasMath' => true,
        'math'  => [
            'R_{\text{total}} = \sum_{t=0}^{T} \gamma^t r_t',
            'V^*(s) = \max_{a} \left[ R(s,a) + \gamma \sum_{s\'} P(s\' \mid s,a) V^*(s\') \right]',
        ],
    ],
    [
        'title' => 'Information, Entropy and Meaning',
        'meta'  => 'Module 16',
        'category' => 'mathematics',
        'tags'  => ['Entropy', 'Information', 'Semantics'],
        'text'  => 'Claude Shannon gave us a mathematical theory of communication but explicitly set aside the problem of meaning. "The fundamental problem of communication," he wrote, "is that of reproducing at one point either exactly or approximately a message selected at another point." Meaning remained the province of philosophy—until we realized that meaning itself can be operationalized as mutual information between a message and a model of the world. The Kullback-Leibler divergence measures the cost of using one distribution to approximate another: D_{KL}(P \parallel Q) = \sum_i P(i) \log(P(i)/Q(i)). Every time we train a neural network, we are minimizing this divergence—we are buying meaning at the price of information. The philosopher\'s question "What does it mean?" becomes the engineer\'s question "How many bits of mutual information exist between this representation and the ground truth?" This is the unification of meaning and mathematics.',
        'hasMath' => true,
        'math'  => [
            'D_{\text{KL}}(P \parallel Q) = \sum_{i} P(i) \log\left(\frac{P(i)}{Q(i)}\right)',
            'H(X \mid Y) = -\sum_{x,y} p(x,y) \log p(x \mid y)',
        ],
    ],
    [
        'title' => 'Philosophical Foundations of Computation',
        'meta'  => 'Module 17',
        'category' => 'philosophy',
        'tags'  => ['Philosophy', 'Computation', 'Reality'],
        'text'  => 'The universe may, at its deepest level, be a computational process. This is the central hypothesis of digital physics: "It from bit," as John Archibald Wheeler put it. "Every it—every particle, every field of force, even the spacetime continuum itself—derives its function, its meaning, its very existence entirely from the binary oppositions." If this is true, then software is not a metaphor for reality; it is reality. The distinction between the map and the territory collapses when the map is executable. Edsger Dijkstra said, "Computer science is no more about computers than astronomy is about telescopes." Computer science is about the laws of information, just as physics is about the laws of matter. And information may be more fundamental than matter. This is why software is a general power: it operates at the level where the distinction between the ideal and the real dissolves.',
        'hasMath' => true,
        'math'  => [
            'S = -k_B \sum_i p_i \ln p_i',
            '|\psi\rangle = \sum_i \alpha_i |i\rangle, \quad \sum_i |\alpha_i|^2 = 1',
        ],
    ],
    [
        'title' => 'The Mathematical Unity of Knowledge',
        'meta'  => 'Module 18',
        'category' => 'mathematics',
        'tags'  => ['Unity', 'Cross-Domain', 'Abstraction'],
        'text'  => '"Mathematics is the language in which God has written the universe," Galileo said. Every domain of human knowledge, when sufficiently formalized, becomes mathematical—and every sufficiently rich mathematical structure can be interpreted as a model of computation. Gödel\'s incompleteness theorems showed that any consistent formal system capable of encoding arithmetic contains true statements that cannot be proved within the system. Turing\'s halting problem showed that there are well-defined computational problems that no algorithm can solve. These are not limitations; they are the shape of knowable reality. The software engineer works at the intersection of these constraints: building systems that are correct within the limits of formal decidability, useful within the bounds of computational complexity, and meaningful within the bandwidth of human understanding. This is why the post-code era is not a technical shift—it is a philosophical maturation of the discipline.',
        'hasMath' => true,
        'math'  => [
            '\text{Con}(T) \Rightarrow T \nvdash \text{Con}(T)',
            '\nexists \text{ program } H(p,i) \text{ s.t. } H(p,i) = 1 \iff \text{ program } p \text{ halts on input } i',
        ],
    ],
    [
        'title' => 'Software as the Extended Phenotype of Mind',
        'meta'  => 'Module 19',
        'category' => 'philosophy',
        'tags'  => ['Mind', 'Culture', 'Evolution'],
        'text'  => 'Richard Dawkins\'s concept of the extended phenotype—that the effects of a gene extend beyond the organism\'s body into the environment—finds a perfect analogue in software. Code is the extended phenotype of human cognition. A beaver\'s dam is an extended phenotype; so is a web browser. But software is different: it is a Lamarckian inheritance mechanism. We can pass acquired knowledge directly to our artifacts, and those artifacts improve without the death of their creators. "We are like dwarfs sitting on the shoulders of giants," said Bernard of Chartres. In software, every commit is a shoulder for the next dwarf. This cumulative, non-genetic inheritance is what makes software a general power that alters life. It accelerates cultural evolution by orders of magnitude. The line between biological evolution and technological evolution blurs when both can be described by the same mathematics of variation, selection, and retention.',
        'hasMath' => true,
        'math'  => [
            '\frac{dp}{dt} = p(1-p)(\text{fitness}_A - \text{fitness}_B)',
            'R_0 = \beta \cdot \tau \cdot D',
        ],
    ],
    [
        'title' => 'The Economics of Zero-Marginal-Cost Production',
        'meta'  => 'Module 20',
        'category' => 'philosophy',
        'tags'  => ['Economics', 'Abundance', 'Value'],
        'text'  => 'Software is the only human product with near-zero marginal cost of reproduction. A steel mill cannot duplicate its output at the click of a button; a software company can. This has profound economic and philosophical implications. Carl Shapiro and Hal Varian wrote that "information is costly to produce but cheap to reproduce." But software goes further—it is not just information; it is executable capability. Every copy of a program is a new factory. This inverts the classical economic relationship between scarcity and value. In a world where capability can be replicated at zero marginal cost, the limiting factor is not production but attention, meaning, and alignment. Herbert Simon anticipated this: "A wealth of information creates a poverty of attention." The post-code developer is not a producer of code but a director of attention—deciding which capabilities to instantiate, which problems to solve, and which values to embed.',
        'hasMath' => true,
        'math'  => [
            'MC(q) = \frac{dC}{dq} \approx 0 \text{ for digital goods}',
            'V = \sum_{i=1}^{n} u_i(x_i) \text{ subject to } \sum_{i} p_i x_i \leq w',
        ],
    ],
    [
        'title' => 'Emergence, Complexity and Life',
        'meta'  => 'Module 21',
        'category' => 'mathematics',
        'tags'  => ['Emergence', 'Complexity', 'Life'],
        'text'  => 'John von Neumann asked what it takes for a system to be a self-reproducing automaton. His answer—a universal constructor coupled with a description tape—foreshadowed both DNA and software. Life and computation are deeply intertwined: both are about the storage, transmission, and processing of information. Schrödinger, in "What Is Life?", speculated that the hereditary molecule must be an "aperiodic crystal"—a structure capable of encoding an immense variety of states in a stable form. He was describing, decades before its discovery, the logic of DNA. And he was also describing the logic of software: a compact representation that, when interpreted by the right machinery, produces infinite variety. The complexity of a system is not the number of its parts but the length of the shortest description of its behavior—Kolmogorov complexity. Software is the art of finding short descriptions for complex desired behaviors. In this sense, programming is the most direct expression of the principle of life itself.',
        'hasMath' => true,
        'math'  => [
            'K(x) \leq |x| + c',
            'C(s) = \min\{ |p| : U(p) = s \} + \log_2 |\{p : U(p) = s\}|',
        ],
    ],
    [
        'title' => 'The Ethics of Algorithmic Causality',
        'meta'  => 'Module 22',
        'category' => 'philosophy',
        'tags'  => ['Ethics', 'Causality', 'Responsibility'],
        'text'  => 'When software mediates every major domain of human life—communication, commerce, health, governance, warfare—the ethics of code become the ethics of civilization. "With great power comes great responsibility" is not a platitude here; it is a theorem. If software is a general power that can alter life, then the software engineer is a de facto legislator of the possible. Every API defines a boundary of permitted action; every algorithm encodes a value judgment about what matters; every database schema is a metaphysical commitment about what exists. The philosopher Luciano Floridi calls this the "fourth revolution"—after Copernicus, Darwin, and Freud, the information revolution displaces humans from the center of the infosphere. We are not users of technology; we are inhabitants of an increasingly informational environment. The design of this environment is the most consequential ethical task of our time. The post-code developer must be not only competent but wise.',
        'hasMath' => true,
        'math'  => [
            'U(a) = \sum_{i} w_i \cdot u_i(a)',
            'P(\text{harm} \mid \text{action}) \leq \epsilon \text{ (alignment constraint)}',
        ],
    ],
];

$categories = [
    'all'         => 'All',
    'fundamentals'=> 'Fundamentals',
    'architecture'=> 'Architecture',
    'mathematics' => 'Mathematics',
    'philosophy'  => 'Philosophy',
    'systems'     => 'Systems',
];

$totalModules = count($modules);
$totalTopics  = count(array_unique(array_merge(...array_column($modules, 'tags'))));

ui_render_head(
    'Experiments | Post-Code Feed',
    'Unified feed for post-code concepts, mathematics foundations, philosophy of computation, and the nature of software as a general transformative power.'
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
            <p class="ui-section-text">High-signal notes on coding fundamentals, architecture, mathematics, and the philosophy of computation. Curated concepts that remain relevant regardless of which tools write the code—exploring software as a general power that can alter life in a technological civilization.</p>
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
                    <span class="ui-stat-value"><?= count($categories) - 1 ?></span>
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
