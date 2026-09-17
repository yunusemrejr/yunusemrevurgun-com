<?php
require_once dirname(__DIR__, 2) . '/config/setPath.php';
require_once dirname(__DIR__, 2) . '/views/includes/ui.php';
require_once dirname(__DIR__, 2) . '/views/includes/collection.php';

$modules = [
    [
        'title' => 'Alan Turing — The Universal Machine',
        'meta'  => 'Computer Science',
        'category' => 'computer-science',
        'tags'  => ['Computability', 'AI', 'Universal Machine'],
        'text'  => '"We can only see a short distance ahead, but we can see plenty there that needs to be done." Turing\'s 1936 paper "On Computable Numbers" introduced the concept of a universal machine that could simulate any other machine given the right description—the theoretical foundation of every computer in existence. He asked the question "Can machines think?" long before it was fashionable, proposing the Imitation Game (now the Turing Test) as a practical criterion. The halting problem—that no algorithm can determine whether an arbitrary program will halt—revealed an inherent limit to computation. This is not a bug in the theory; it is a fundamental property of the mathematical universe. Turing\'s work reminds us that the limits of what can be computed define the boundary of what can be known mechanically, and that all software operates within this boundary.',
        'hasMath' => true,
        'math'  => [
            '\nexists \text{ TM } H \text{ such that } H(\langle M \rangle, w) = \text{accept} \iff M \text{ halts on } w',
            'U(\langle M \rangle, w) = M(w)',
        ],
    ],
    [
        'title' => 'Albert Einstein — The Geometry of Reality',
        'meta'  => 'Physics',
        'category' => 'physics',
        'tags'  => ['Relativity', 'Spacetime', 'Cosmos'],
        'text'  => '"The most incomprehensible thing about the universe is that it is comprehensible." Einstein\'s special and general theories of relativity radically reshaped our understanding of space, time, and gravity. His famous equation E = mc² revealed the equivalence of mass and energy—that matter is frozen light. The general theory describes gravity not as a force but as the curvature of spacetime itself: mass tells spacetime how to curve, and curved spacetime tells mass how to move. Einstein\'s philosophical commitment to determinism—"God does not play dice with the universe"—put him at odds with quantum mechanics, but his contributions to the foundations of quantum theory (the photoelectric effect, for which he won the Nobel Prize) are equally profound. He teaches us that the deepest understanding comes not from accumulating facts but from refining the questions we ask about reality.',
        'hasMath' => true,
        'math'  => [
            'E = mc^2',
            'G_{\mu\nu} + \Lambda g_{\mu\nu} = \frac{8\pi G}{c^4} T_{\mu\nu}',
            'ds^2 = -c^2 dt^2 + dx^2 + dy^2 + dz^2',
        ],
    ],
    [
        'title' => 'Richard Feynman — The QED Magician',
        'meta'  => 'Physics',
        'category' => 'physics',
        'tags'  => ['QED', 'Quantum Mechanics', 'Path Integral'],
        'text'  => '"What I cannot create, I do not understand." Feynman revolutionized quantum electrodynamics (QED) with his intuitive Feynman diagrams, which transformed arcane calculations into visual stories of particle interactions. His path integral formulation of quantum mechanics—that a particle takes every possible path and we sum over them with a phase factor—is one of the most profound ideas in physics: \langle x_f | e^{-iHt/\hbar} | x_i \rangle = \int \mathcal{D}[x(t)] e^{iS[x]/\hbar}. Feynman\'s approach to science was relentlessly physical: never trust the mathematics alone; demand a picture, an intuition, a physical argument. "The first principle is that you must not fool yourself—and you are the easiest person to fool." His lectures at Caltech remain the gold standard for physics pedagogy, demonstrating that deep understanding can be made accessible without dilution.',
        'hasMath' => true,
        'math'  => [
            '\langle x_f | e^{-iHt/\hbar} | x_i \rangle = \int \mathcal{D}[x(t)] e^{iS[x]/\hbar}',
            'i\hbar \frac{\partial}{\partial t} \psi = \hat{H} \psi',
        ],
    ],
    [
        'title' => 'J. Robert Oppenheimer — The Weight of Knowledge',
        'meta'  => 'Physics',
        'category' => 'physics',
        'tags'  => ['Nuclear Physics', 'Ethics', 'Responsibility'],
        'text'  => '"Now I am become Death, the destroyer of worlds." Oppenheimer, quoting the Bhagavad Gita after witnessing the Trinity test, embodied the profound ethical weight of scientific discovery. As scientific director of the Manhattan Project, he led the development of the atomic bomb—a demonstration that theoretical physics, when technologically realized, can reshape civilization itself. Oppenheimer\'s later advocacy for international control of nuclear weapons and his opposition to the hydrogen bomb reflected a deep understanding that scientific progress without ethical constraint is catastrophe. His life is the most dramatic illustration of the principle that knowledge is power, and that power demands wisdom. For the builder of any system—whether nuclear or computational—the lesson is the same: the capability to create carries the responsibility to foresee the consequences.',
        'hasMath' => false,
    ],
    [
        'title' => 'Ada Lovelace — The First Programmer',
        'meta'  => 'Computer Science',
        'category' => 'computer-science',
        'tags'  => ['Programming', 'Vision', 'Imagination'],
        'text'  => '"That brain of mine is something more than merely mortal, as time will show." Ada Lovelace, writing in 1843, understood that Charles Babbage\'s Analytical Engine was not merely a calculating machine but something far more profound: a machine that could manipulate symbols according to rules, and thus could be programmed to express anything that could be formalized. She wrote the first algorithm designed for machine execution—a method for calculating Bernoulli numbers. More remarkably, she grasped that such a machine could compose music, produce graphics, and manipulate any content expressible in symbolic form. This was a century before the first electronic computer. "The Analytical Engine has no pretensions to originate anything," she wrote. "It can do whatever we know how to order it to perform." This is the foundational insight of all programming: the machine\'s power is bounded only by our ability to formalize our intentions.',
        'hasMath' => true,
        'math'  => [
            'B_n = \sum_{k=0}^{n} \frac{1}{k+1} \sum_{j=0}^{k} (-1)^j \binom{k}{j} j^n',
            '\text{\# Lovelace\'s Bernoulli algorithm was the first computer program}',
        ],
    ],
    [
        'title' => 'Claude Shannon — The Architect of Information',
        'meta'  => 'Mathematics',
        'category' => 'mathematics',
        'tags'  => ['Information Theory', 'Entropy', 'Communication'],
        'text'  => '"We know the past but cannot control it. We control the future but cannot know it." Shannon\'s 1948 paper "A Mathematical Theory of Communication" created the field of information theory from scratch. He defined entropy as a measure of uncertainty: H = -∑ p_i log₂ p_i. He proved that any communication channel has a maximum rate (the channel capacity) below which error-free transmission is possible—the noisy-channel coding theorem. Shannon also made foundational contributions to cryptography, circuit design, and artificial intelligence (his maze-solving Theseus mouse). He saw information as the fundamental currency of both communication and computation. Every bit transmitted, every byte stored, every compression algorithm owes its theoretical foundation to Shannon. His insight was that information is not about meaning—it is about the reduction of uncertainty. Meaning comes later, from the interaction between information and interpretation.',
        'hasMath' => true,
        'math'  => [
            'H(X) = -\sum_{i} p(x_i) \log_2 p(x_i)',
            'C = \max_{p(x)} I(X;Y)',
        ],
    ],
    [
        'title' => 'John von Neumann — The Universal Architect',
        'meta'  => 'Computer Science',
        'category' => 'computer-science',
        'tags'  => ['Architecture', 'Game Theory', 'Cellular Automata'],
        'text'  => '"The principle of the von Neumann architecture—stored-program computing—is so fundamental that we rarely question it: instructions and data share the same memory space. This simple insight made computers reprogrammable and thus universal. Von Neumann contributed to every domain he touched: quantum mechanics (the mathematical formulation), game theory (the minimax theorem, Nash equilibrium\'s precursor), economics (input-output models), cellular automata (self-reproducing machines), and computing (the EDVAC design). His work on self-reproducing automata asked what logical organization is necessary for a system to replicate itself—anticipating both DNA and computer viruses. The breadth of his genius exemplifies a crucial lesson for the post-code era: the most powerful insights come from connecting disparate domains. A programmer who understands game theory, quantum mechanics, and economics is not a dilettante—they are equipped to see patterns that specialists miss.',
        'hasMath' => true,
        'math'  => [
            '\min_{\sigma_1} \max_{\sigma_2} u_1(\sigma_1, \sigma_2) = \max_{\sigma_2} \min_{\sigma_1} u_1(\sigma_1, \sigma_2)',
            '\text{Universal Constructor: } U + D \rightarrow U + D + C',
        ],
    ],
    [
        'title' => 'Kurt Gödel — The Limits of Proof',
        'meta'  => 'Mathematics',
        'category' => 'mathematics',
        'tags'  => ['Incompleteness', 'Logic', 'Formal Systems'],
        'text'  => '"Either mathematics is too big for the human mind, or the human mind is more than a machine." Gödel\'s incompleteness theorems, published in 1931 when he was 25, are among the most profound results in intellectual history. The first theorem states that any consistent formal system capable of encoding arithmetic contains statements that are true but unprovable within the system. The second theorem states that such a system cannot prove its own consistency. These results shattered Hilbert\'s program to find a complete and consistent foundation for all of mathematics. But more than that, they reveal something about the nature of knowledge itself: there will always be truths that lie beyond our formal methods. For computer science, Gödel\'s work is the deep foundation for the Church-Turing thesis, the undecidability of the halting problem, and every limitation result in computation. The programmer who understands Gödel understands that some problems are not hard—they are impossible, and knowing which is which is the highest form of wisdom.',
        'hasMath' => true,
        'math'  => [
            '\forall \text{ consistent } T \supseteq \text{PA}, \exists \gamma \text{ s.t. } T \nvdash \gamma \text{ and } T \nvdash \lnot \gamma',
            'T \nvdash \text{Con}(T)',
        ],
    ],
    [
        'title' => 'Max Planck — The Quantum Pioneer',
        'meta'  => 'Physics',
        'category' => 'physics',
        'tags'  => ['Quantum Theory', 'Blackbody', 'Action'],
        'text'  => '"Science cannot solve the ultimate mystery of nature. And that is because, in the last analysis, we ourselves are part of the mystery that we are trying to solve." In 1900, Max Planck proposed that energy is emitted and absorbed in discrete packets—quanta—to solve the ultraviolet catastrophe of blackbody radiation. His constant h = 6.626 × 10⁻³⁴ J·s became the Planck constant, the fundamental granularity of action in the universe. The relation E = hν meant that energy comes in indivisible units, a concept so radical that Planck himself initially considered it a mathematical trick rather than a physical reality. Yet it was the birth of quantum mechanics. Planck\'s law E_ν = (8πhν³/c³) · 1/(e^{hν/kT} - 1) perfectly describes the spectrum of blackbody radiation. His reluctant revolution teaches us that sometimes the most productive path is to follow the mathematics where it leads, even when it contradicts our philosophical intuitions.',
        'hasMath' => true,
        'math'  => [
            'E = h\nu',
            'B_\nu(T) = \frac{2h\nu^3}{c^2} \frac{1}{e^{h\nu/kT} - 1}',
        ],
    ],
    [
        'title' => 'Erwin Schrödinger — The Cat and the Wave',
        'meta'  => 'Physics',
        'category' => 'physics',
        'tags'  => ['Wave Mechanics', 'Quantum', 'Paradox'],
        'text'  => '"The task is not to see what no one has yet seen, but to think what nobody has yet thought about that which everybody sees." Schrödinger\'s wave equation iℏ ∂ψ/∂t = Ĥψ is to quantum mechanics what Newton\'s F = ma is to classical mechanics—the fundamental dynamical law. His famous thought experiment with a cat that is neither alive nor dead until observed was meant to illustrate the absurdity of the Copenhagen interpretation, but instead became its most famous symbol. In "What Is Life?", Schrödinger anticipated the discovery of DNA by arguing that the hereditary molecule must be an aperiodic crystal—a stable structure capable of encoding an immense variety of genetic information. This insight directly inspired Watson and Crick. Schrödinger\'s career demonstrates that the deepest scientific contributions often come from following a question across disciplinary boundaries, refusing to accept that the categories we inherit are the categories that nature respects.',
        'hasMath' => true,
        'math'  => [
            'i\hbar \frac{\partial}{\partial t} \psi(\mathbf{r}, t) = \left(-\frac{\hbar^2}{2m} \nabla^2 + V(\mathbf{r}, t) \right) \psi(\mathbf{r}, t)',
            '|\psi\rangle = \frac{1}{\sqrt{2}}(|0\rangle + |1\rangle)',
        ],
    ],
    [
        'title' => 'Werner Heisenberg — Uncertainty and Reality',
        'meta'  => 'Physics',
        'category' => 'physics',
        'tags'  => ['Uncertainty', 'Matrix Mechanics', 'Measurement'],
        'text'  => '"What we observe is not nature itself, but nature exposed to our method of questioning." Heisenberg\'s uncertainty principle Δx·Δp ≥ ℏ/2 is not about the limitations of our measurement instruments—it is a fundamental property of quantum systems. A particle simply does not have a simultaneously well-defined position and momentum. This was not failure of measurement; it was the discovery that the classical notion of "simultaneous precise properties" is not a feature of reality. Heisenberg developed matrix mechanics, the first complete formulation of quantum theory, at the age of 23. His work forces us to confront a profound epistemological lesson: the concepts we use to describe reality are not neutral windows onto the world—they shape what we can discover. In software terms, our programming paradigms, our data models, our APIs are not just tools—they are epistemologies. They determine what questions we can ask and what answers we can receive.',
        'hasMath' => true,
        'math'  => [
            '\Delta x \cdot \Delta p \geq \frac{\hbar}{2}',
            '\Delta E \cdot \Delta t \geq \frac{\hbar}{2}',
        ],
    ],
    [
        'title' => 'Paul Dirac — The Poetry of Equations',
        'meta'  => 'Physics',
        'category' => 'physics',
        'tags'  => ['Relativity', 'Quantum', 'Antimatter'],
        'text'  => '"A physical law must possess mathematical beauty." Dirac\'s equation (iγ^μ ∂_μ - m)ψ = 0 unified quantum mechanics with special relativity and predicted the existence of antimatter—positively charged electrons—before they were experimentally observed. The equation\'s elegance was so compelling that Dirac trusted its mathematical beauty over empirical discomfort. He was right. His work exemplifies the profound principle that mathematical consistency is a guide to physical truth. For every developer, there is a lesson here: clean architecture, consistent abstractions, and elegant mathematical formulations are not cosmetic preferences—they are reliable guides to correctness. Dirac\'s bra-ket notation ⟨φ|ψ⟩ remains the standard language of quantum mechanics. He showed that the right notation is not just a convenience; it is a cognitive tool that shapes what we can think.',
        'hasMath' => true,
        'math'  => [
            '(i\gamma^\mu \partial_\mu - m)\psi = 0',
            '\langle \phi | \psi \rangle = \int \phi^*(\mathbf{r}) \psi(\mathbf{r}) \, d^3\mathbf{r}',
        ],
    ],
    [
        'title' => 'Stephen Hawking — The Edge of Spacetime',
        'meta'  => 'Physics',
        'category' => 'physics',
        'tags'  => ['Cosmology', 'Black Holes', 'Singularities'],
        'text'  => '"Look up at the stars and not down at your feet. Try to make sense of what you see, and wonder about what makes the universe exist." Hawking showed that black holes are not truly black—they emit radiation (Hawking radiation) due to quantum effects near the event horizon. This discovery connected general relativity, quantum mechanics, and thermodynamics in a way no one had anticipated: T = ℏc³/8πGMk_B. The entropy of a black hole, given by the Bekenstein-Hawking formula S = k_B A / 4ℓ²_P, revealed that the maximum entropy of a region of space is proportional to its surface area, not its volume—a deep clue about the nature of quantum gravity. Hawking\'s work, done while his body was progressively paralyzed by ALS, is a testament to the power of the mind to transcend its physical limitations. For every software engineer, Hawking represents the principle that constraints can be sources of creativity, not just obstacles.',
        'hasMath' => true,
        'math'  => [
            'T_{\text{Hawking}} = \frac{\hbar c^3}{8\pi G M k_B}',
            'S_{\text{BH}} = \frac{k_B A}{4\ell_P^2}',
        ],
    ],
    [
        'title' => 'Niels Bohr — Complementarity and Contradiction',
        'meta'  => 'Physics',
        'category' => 'physics',
        'tags'  => ['Complementarity', 'Atomic Theory', 'Philosophy'],
        'text'  => '"The opposite of a correct statement is a false statement. The opposite of a profound truth may well be another profound truth." Bohr\'s principle of complementarity holds that quantum systems possess mutually exclusive properties—wave and particle—that cannot be observed simultaneously, yet both are necessary for a complete description. Light is both a wave and a particle, not in an either/or sense but in a both/and sense that defies classical logic. Bohr\'s atomic model (the Bohr model) introduced quantized electron orbits, explaining the hydrogen spectrum. The Copenhagen interpretation of quantum mechanics, which Bohr championed, holds that physical systems have no definite properties before measurement—reality is created, in some sense, by the act of asking. This philosophical position has profound resonance for computation: the observer and the observed are not separable. Every program is a measurement apparatus; every user interface is an act of reality-construction.',
        'hasMath' => true,
        'math'  => [
            'E_n = -\frac{13.6\,\text{eV}}{n^2}',
            'r_n = n^2 a_0, \quad a_0 = \frac{4\pi\epsilon_0 \hbar^2}{m_e e^2}',
        ],
    ],
    [
        'title' => 'Grace Hopper — The Human Side of Computing',
        'meta'  => 'Computer Science',
        'category' => 'computer-science',
        'tags'  => ['Compilers', 'COBOL', 'Debugging'],
        'text'  => '"The most damaging phrase in the language is: \'It\'s always been done that way.\'" Grace Hopper pioneered the first compiler (the A-0 system), transforming programming from machine-code entry into high-level language compilation. She believed that programming languages should be closer to human language than to machine language, a radical idea at a time when programmers prided themselves on raw efficiency. Her work led to COBOL, one of the first cross-platform programming languages. The term "debugging" was popularized when a moth was found in a relay of the Harvard Mark II—Hopper\'s team literally removed a bug. But her real legacy is the idea that software should serve human understanding, not machine convenience. She understood that the purpose of abstraction is not to hide complexity from the machine but to manage it for the human. Every modern programming language—every compiler, every IDE—stands on the foundation Hopper built.',
        'hasMath' => false,
    ],
    [
        'title' => 'Carl Friedrich Gauss — The Prince of Mathematicians',
        'meta'  => 'Mathematics',
        'category' => 'mathematics',
        'tags'  => ['Number Theory', 'Statistics', 'Geometry'],
        'text'  => '"Mathematics is the queen of the sciences, and number theory is the queen of mathematics." Gauss made foundational contributions to number theory (disquisitiones arithmeticae), statistics (the Gaussian distribution), differential geometry (theorema egregium), and many other fields. His method of least squares—minimizing the sum of squared residuals—is the foundation of modern regression analysis and machine learning. The normal distribution, or Gaussian, arises whenever many independent random variables are summed (the central limit theorem). Gauss\'s insight that curvature is an intrinsic property of a surface, independent of how it is embedded in space, presaged Einstein\'s general relativity. He showed that the mathematics we discover is not arbitrary—it is the structure of reality itself, waiting to be found. For every data scientist and ML engineer, the Gaussian distribution and the method of least squares are daily tools that trace directly back to Gauss\'s genius.',
        'hasMath' => true,
        'math'  => [
            'f(x \mid \mu, \sigma^2) = \frac{1}{\sqrt{2\pi\sigma^2}} e^{-\frac{(x-\mu)^2}{2\sigma^2}}',
            '\text{minimize } \sum_{i=1}^{n} (y_i - \beta_0 - \beta_1 x_i)^2',
        ],
    ],
    [
        'title' => 'Évariste Galois — Symmetry and Solvability',
        'meta'  => 'Mathematics',
        'category' => 'mathematics',
        'tags'  => ['Group Theory', 'Symmetry', 'Polynomials'],
        'text'  => '"A mathematical truth is neither simple nor complicated in itself, it is." Galois, who died at 20 in a duel, created group theory in a single night before his death. He solved the long-standing problem of determining when a polynomial equation is solvable by radicals—Galois theory. The central insight is that the symmetries of a polynomial\'s roots (its Galois group) determine whether a closed-form solution exists. This connection between symmetry and solvability is one of the most beautiful in mathematics. For computer science, group theory underlies everything from cryptography to error-correcting codes to the symmetries of deep learning architectures. Galois\'s tragic death at the threshold of his genius is a reminder that creative mathematics is not just calculation—it is vision. The post-code developer who understands symmetry understands why certain problems are tractable and others are not.',
        'hasMath' => true,
        'math'  => [
            '\text{Gal}(f) \text{ is solvable } \iff f \text{ is solvable by radicals}',
            'S_n \text{ for } n \geq 5 \text{ is not solvable}',
        ],
    ],
    [
        'title' => 'Ludwig Boltzmann — Entropy and Probability',
        'meta'  => 'Physics',
        'category' => 'physics',
        'tags'  => ['Thermodynamics', 'Entropy', 'Statistical Mechanics'],
        'text'  => '"The general struggle for existence of animate beings is not a struggle for raw materials—these, for organisms, are air, water and soil, all abundantly available—nor for energy, which exists in plenty in any body in the form of heat, but rather a struggle for entropy." Boltzmann\'s statistical interpretation of entropy—S = k_B ln W—connects the macroscopic world of thermodynamics to the microscopic world of atomic configurations. Entropy is not a mysterious fluid; it is the logarithm of the number of microscopic arrangements that yield the same macroscopic state. The second law of thermodynamics (entropy tends to increase) becomes a probabilistic statement: systems evolve toward more probable configurations. Boltzmann\'s insight is foundational for machine learning, where cross-entropy loss, KL divergence, and maximum entropy methods are direct descendants. The tendency of systems to maximize entropy is mirrored in the tendency of neural networks to find the most generalizable solution—the principle of maximum entropy is a computational principle as much as a physical one.',
        'hasMath' => true,
        'math'  => [
            'S = k_B \ln W',
            'W = \frac{N!}{\prod_i n_i!}',
        ],
    ],
    [
        'title' => 'Leonhard Euler — The Master of Us All',
        'meta'  => 'Mathematics',
        'category' => 'mathematics',
        'tags'  => ['Analysis', 'Graph Theory', 'Identity'],
        'text'  => '"Read Euler, read Euler. He is the master of us all." Euler, the most prolific mathematician in history, contributed to every branch of mathematics. His identity e^{iπ} + 1 = 0 is often called the most beautiful equation in mathematics, connecting the five fundamental constants: e, i, π, 1, and 0. He founded graph theory with his solution to the Königsberg bridge problem, showing that the impossibility of traversing each bridge exactly once was a topological property of the graph—the first proof in graph theory. Euler\'s notation f(x) for functions, e for the base of natural logarithms, π for pi, Σ for summation, and i for √-1 are still standard. He did much of his best work while blind, dictating to assistants. Euler demonstrates that mathematical creativity is not diminished by constraint—that the deepest truths are not discovered by the senses but by the disciplined imagination.',
        'hasMath' => true,
        'math'  => [
            'e^{i\pi} + 1 = 0',
            'V - E + F = 2 \text{ (for convex polyhedra)}',
        ],
    ],
];

$categories = [
    'all'             => 'All',
    'physics'         => 'Physics',
    'mathematics'     => 'Mathematics',
    'computer-science'=> 'Computer Science',
];

$totalModules = count($modules);
$totalTopics  = count(array_unique(array_merge(...array_column($modules, 'tags'))));

ui_render_head(
    'Science Corner | Physics, Mathematics & Computer Science',
    'Explore figures, equations and ideas from physics, mathematics and computer science in Yunus Emre Vurgun’s science collection.',
    [ui_collection_schema('Science Corner', 'science-corner', array_column($modules, 'title'))]
);
?>
<link rel="stylesheet" href="<?= FULL_BASE_PATH ?>assets/css/post-code.css?v=<?= filemtime(__DIR__ . '/../../assets/css/post-code.css') ?>">
<body class="ui-collection">
<div class="ui-page">
    <?php ui_render_navbar('Experiments'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Archive</p>
            <h1 class="ui-section-title">Science Corner</h1>
            <p class="ui-section-text">Figures and ideas I return to in physics, mathematics, and computer science. Browse by field, search a topic, or open the index.</p>
        </section>

        <section class="ui-section">
            <div class="ui-postcode-stat-bar">
                <div class="ui-stat-card">
                    <span class="ui-stat-value"><?= $totalModules ?></span>
                    <span class="ui-stat-label">Figures</span>
                </div>
                <div class="ui-stat-card">
                    <span class="ui-stat-value"><?= $totalTopics ?></span>
                    <span class="ui-stat-label">Topics</span>
                </div>
                <div class="ui-stat-card">
                    <span class="ui-stat-value"><?= count($categories) - 1 ?></span>
                    <span class="ui-stat-label">Fields</span>
                </div>
            </div>
        </section>

        <section class="ui-section" data-collection>
            <?php ui_collection_tools($modules); ?>
            <div class="ui-archive-filters" role="group" aria-label="Science Corner category filters">
                <?php foreach ($categories as $key => $label): ?>
                    <button class="ui-filter-pill<?= $key === 'all' ? ' is-active' : '' ?>" type="button" data-filter="<?= htmlspecialchars($key) ?>" aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>"><?= htmlspecialchars($label) ?></button>
                <?php endforeach; ?>
                <?php ui_render_hampton('filters'); ?>
            </div>

            <div class="ui-feed-list" id="scienceFeed">
                <?php foreach ($modules as $module): ?>
                    <article class="ui-feed-card" id="<?= ui_entry_id($module['title']) ?>" data-collection-item data-category="<?= htmlspecialchars($module['category']) ?>">
                        <div class="ui-feed-card-header">
                            <div>
                                <h2 class="ui-feed-title"><a href="#<?= ui_entry_id($module['title']) ?>"><?= htmlspecialchars($module['title']) ?></a></h2>
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

            <div class="ui-collection-empty" data-collection-empty hidden><p>No entries match. Try a shorter phrase or clear the filters.</p><button type="button" class="ui-btn ui-btn-secondary" data-collection-reset>Clear filters</button></div>
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

</body>
</html>
