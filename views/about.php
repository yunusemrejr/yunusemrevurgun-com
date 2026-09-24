<?php
require_once dirname(__DIR__) . '/config/setPath.php';
require_once dirname(__DIR__) . '/models/Socials.php';
require_once __DIR__ . '/includes/ui.php';

$aboutSchema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'ProfilePage',
            '@id' => rtrim(FULL_BASE_PATH, '/') . '/about#profile',
            'url' => rtrim(FULL_BASE_PATH, '/') . '/about',
            'name' => 'About — Yunus Emre Vurgun',
            'inLanguage' => 'en-US',
            'mainEntity' => [
                '@type' => 'Person',
                'name' => 'Yunus Emre Vurgun',
                'givenName' => 'Yunus Emre',
                'familyName' => 'Vurgun',
                'additionalName' => 'Yemre',
                'alternateName' => ['Yemre', 'YEV', 'yunusemrejr', 'Yemrevu'],
                'url' => rtrim(FULL_BASE_PATH, '/') . '/',
                'image' => FULL_BASE_PATH . 'assets/images/yunus-emre-vurgun-portrait.jpg',
                'description' => 'Developer and IT specialist based in Istanbul, specializing in computational intelligence, AI/ML systems, and operational technology.',
                'jobTitle' => 'Software Developer & IT Specialist',
                'worksFor' => ['@type' => 'Organization', 'name' => 'ASP Otomasyon A.Ş.'],
                'sameAs' => array_values(array_filter(array_map(
                    fn($s) => !empty($s['url']) ? $s['url'] : null,
                    (new Socials())->getActiveLinks()
                ))),
            ],
        ],
    ],
];

ui_render_head(
    'About — Yunus Emre Vurgun | Software Developer & IT Specialist',
    'About Yunus Emre Vurgun (Yemre, YEV, yunusemrejr) — software developer and IT specialist in Istanbul, building AI/ML systems and operational technology solutions.',
    ['<script type="application/ld+json">' . json_encode($aboutSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>']
);
?>
<body>
<div class="ui-page">
    <?php ui_render_navbar('About'); ?>

    <main class="ui-main" id="main-content" tabindex="-1">
        <section class="ui-section">
            <p class="ui-eyebrow">Profile</p>
            <h1 class="ui-section-title">Yunus Emre Vurgun — Developer &amp; IT Specialist</h1>
            <p class="ui-section-text">Based in Istanbul, working at the intersection of computational intelligence and operational technology.</p>
        </section>

        <section class="ui-section">
            <div class="ui-record">
                    <section class="ui-record-section" aria-labelledby="about-summary">
                        <h2 class="ui-record-heading" id="about-summary">Summary</h2>
                        <div class="ui-record-body">
                            <p>Developer and IT specialist from Istanbul with a focus on AI and operational technology.</p>
                            <p>Building systems that are architecturally robust and computationally efficient. I prioritize time-resistant fundamentals and mathematical soundness over industry trends.</p>
                            <p>From <a href="<?= FULL_BASE_PATH ?>yunobot">CPU-optimized neural network proof-of-concepts</a> to <a href="<?= FULL_BASE_PATH ?>portfolio">industrial automation</a> — I build for the internal logic, not the surface-level implementation.</p>
                            <p>Currently working at ASP Otomasyon A.Ş., building and maintaining internal systems for industrial operations.</p>
                        </div>
                    </section>

                    <section class="ui-record-section" aria-labelledby="about-education">
                        <h2 class="ui-record-heading" id="about-education">Education</h2>
                        <div class="ui-record-body">
                            <ul class="ui-timeline">
                                <li class="ui-timeline-item"><strong>Information Technology — Bachelor's</strong><br>Illinois Institute of Technology | 2023 — present</li>
                                <li class="ui-timeline-item"><strong>Computer Programming — A.S.</strong><br>Beykoz University | 2020 — 2023</li>
                                <li class="ui-timeline-item"><strong>Management Information Systems — Bachelor's</strong><br>Anadolu University | 2024 — present</li>
                                <li class="ui-timeline-item"><strong>English Language Teaching — BA</strong><br>Bahcesehir University | 2018 — 2020</li>
                            </ul>
                        </div>
                    </section>

                    <section class="ui-record-section" aria-labelledby="about-work">
                        <h2 class="ui-record-heading" id="about-work">Work History</h2>
                        <div class="ui-record-body">
                            <ul class="ui-timeline">
                                <li class="ui-timeline-item"><strong>Software Developer & IT Specialist</strong><br>ASP Otomasyon A.Ş. | 2022 — present<br>Building and maintaining internal systems for industrial operations — from business tools to IT/OT infrastructure.</li>
                                <li class="ui-timeline-item"><strong>Financial Assistant</strong><br>ASP Otomasyon A.Ş. | 2019 — 2022<br>Started in finance and administration. The transition into technical work shaped my approach to building practical solutions.</li>
                            </ul>
                        </div>
                    </section>

                    <section class="ui-record-section" aria-labelledby="about-selected">
                        <h2 class="ui-record-heading" id="about-selected">Selected Work</h2>
                        <div class="ui-record-body">
                            <ul class="ui-card-list">
                                <li><a href="<?= FULL_BASE_PATH ?>portfolio"><strong>Project archive</strong></a><br>Systems across AI/ML, operational technology, industrial automation and web infrastructure, with the stack each one runs on.</li>
                                <li><a href="<?= FULL_BASE_PATH ?>blog"><strong>Journal</strong></a><br>Long-form writing on AI capability trends, model releases, and post-code engineering.</li>
                                <li><a href="<?= FULL_BASE_PATH ?>yunobot"><strong>YunoBot</strong></a><br>An assistant that answers questions about this site entirely in the browser — no server call, no API key.</li>
                                <li><a href="<?= FULL_BASE_PATH ?>post-code"><strong>Post-Code</strong></a><br>The mathematics and concepts reading list behind the above.</li>
                                <li><a href="<?= FULL_BASE_PATH ?>contact"><strong>Start a conversation</strong></a><br>Projects, questions, or just to say hello.</li>
                            </ul>
                        </div>
                    </section>

                    <section class="ui-record-section" aria-labelledby="about-certifications">
                        <h2 class="ui-record-heading" id="about-certifications">Certifications</h2>
                        <div class="ui-record-body">
                            <ul class="ui-card-list">
                                <li>IELTS Academic — Score: 7.5</li>
                                <li>Fortinet OT Security Architect Training</li>
                                <li>Introduction to Generative AI — Google Cloud</li>
                                <li>AI Essentials — Intel</li>
                                <li>Computational Vision — University of Colorado Boulder</li>
                                <li>Network Support and Security — Cisco</li>
                                <li>Technologies for AI — Politecnico di Milano</li>
                            </ul>
                        </div>
                    </section>

                    <section class="ui-record-section" aria-labelledby="about-connect">
                        <h2 class="ui-record-heading" id="about-connect">Connect</h2>
                        <div class="ui-record-body">
                            <ul class="ui-card-list">
                                <li><strong>YouTube</strong><br><a href="https://www.youtube.com/@yunusemrevurgun1" target="_blank" rel="noopener noreferrer">@yunusemrevurgun1</a></li>
                                <li><strong>GitHub</strong><br><a href="https://github.com/yunusemrejr" target="_blank" rel="noopener noreferrer">@yunusemrejr</a></li>
                                <li><strong>LinkedIn</strong><br><a href="https://linkedin.com/in/yunus-emre-vurgun-49ba9a177" target="_blank" rel="noopener noreferrer">Profile</a></li>
                                <li><strong>Mastodon</strong><br><a href="https://mastodon.social/@yunusemrevurgn" target="_blank" rel="noopener noreferrer">@yunusemrevurgn</a></li>
                                <li><strong>Bluesky</strong><br><a href="https://bsky.app/profile/yunusemrevurgun.bsky.social" target="_blank" rel="noopener noreferrer">@yunusemrevurgun</a></li>
                                <li><strong>Threads</strong><br><a href="https://www.threads.com/@yemrevu" target="_blank" rel="noopener noreferrer">@yemrevu</a></li>
                                <li><strong>Instagram</strong><br><a href="https://instagram.com/yemrevu" target="_blank" rel="noopener noreferrer">@yemrevu</a></li>
                            </ul>
                        </div>
                    </section>

                    <section class="ui-record-section" aria-labelledby="about-legal">
                        <h2 class="ui-record-heading" id="about-legal">Legal</h2>
                        <div class="ui-record-body">
                            <ul class="ui-card-list">
                                <li><a href="<?= FULL_BASE_PATH ?>privacy">Privacy Policy</a></li>
                                <li><a href="<?= FULL_BASE_PATH ?>terms">Terms of Use</a></li>
                                <li><a href="<?= FULL_BASE_PATH ?>cookies">Cookie Policy</a></li>
                            </ul>
                        </div>
                    </section>
                </div>
        </section>
    </main>

    <?php ui_render_footer(); ?>
</div>
<?php ui_render_tracker_codes(dirname(__DIR__)); ?>
</body>
</html>
